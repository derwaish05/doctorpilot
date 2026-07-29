<?php
/**
 * Search request, ranking, and service tests.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

use BrainStudioz\DoctorPilot\Application\Search\SearchIndexBuilder;
use BrainStudioz\DoctorPilot\Application\Search\SearchService;
use BrainStudioz\DoctorPilot\Domain\Search\Normalizer;
use BrainStudioz\DoctorPilot\Domain\Search\SearchRequest;
use BrainStudioz\DoctorPilot\Domain\Search\SearchRequestFactory;
use BrainStudioz\DoctorPilot\Fields\FieldRegistry;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;
use BrainStudioz\DoctorPilot\Taxonomies\Taxonomies;

/**
 * @covers \BrainStudioz\DoctorPilot\Domain\Search\SearchRequestFactory
 * @covers \BrainStudioz\DoctorPilot\Application\Search\SearchService
 */
final class Test_DoctorPilot_Search extends WP_UnitTestCase {

	/**
	 * Create a published doctor with an assigned specialty and rebuilt index.
	 *
	 * @param string $name      Doctor name.
	 * @param string $specialty Specialty term name.
	 * @param bool   $featured  Featured flag.
	 * @return int
	 */
	private function make_doctor( string $name, string $specialty = '', bool $featured = false ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => DoctorPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $name,
			)
		);

		if ( '' !== $specialty ) {
			$term = self::factory()->term->create(
				array(
					'taxonomy' => Taxonomies::SPECIALTY,
					'name'     => $specialty,
				)
			);
			wp_set_object_terms( $id, array( (int) $term ), Taxonomies::SPECIALTY );
		}

		if ( $featured ) {
			update_post_meta( $id, FieldRegistry::META_PREFIX . 'is_featured', 1 );
		}

		SearchIndexBuilder::rebuild( $id );

		return $id;
	}

	public function test_factory_bounds_per_page_and_query_length(): void {
		$request = SearchRequestFactory::from_array(
			array(
				'per_page' => 500,
				'q'        => str_repeat( 'a', 500 ),
				'sort'     => 'not-a-sort',
			),
			12
		);

		$this->assertLessThanOrEqual( SearchRequestFactory::MAX_PER_PAGE, $request->per_page() );
		$this->assertLessThanOrEqual( 100, strlen( $request->query() ) );
		$this->assertSame( 'relevance', $request->sort() );
	}

	public function test_factory_parses_and_sorts_filter_slugs(): void {
		$request = SearchRequestFactory::from_array( array( 'specialty' => 'cardiology,neurology,cardiology' ), 12 );
		$filters = $request->taxonomy_filters();

		$this->assertArrayHasKey( Taxonomies::SPECIALTY, $filters );
		$this->assertSame( array( 'cardiology', 'neurology' ), $filters[ Taxonomies::SPECIALTY ] );
	}

	public function test_normalizer_strips_prefix_and_accents(): void {
		$this->assertSame( 'ali haydar', Normalizer::normalize_name( 'Dr. Ali Häydar' ) );
	}

	public function test_only_published_doctors_are_returned(): void {
		$this->make_doctor( 'Dr. Published' );
		self::factory()->post->create(
			array(
				'post_type'   => DoctorPostType::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => 'Dr. Draft',
			)
		);

		$request = SearchRequestFactory::from_array( array(), 12 );
		$result  = ( new SearchService() )->search( $request );

		$this->assertSame( 1, $result['total'] );
	}

	public function test_exact_name_outranks_specialty_match(): void {
		$name_match      = $this->make_doctor( 'Cardiology', '' );
		$specialty_match = $this->make_doctor( 'Dr. Jane Smith', 'Cardiology' );

		$request = SearchRequestFactory::from_array( array( 'q' => 'cardiology', 'sort' => 'relevance' ), 12 );
		$result  = ( new SearchService() )->search( $request );

		$this->assertNotEmpty( $result['ids'] );
		$this->assertSame( $name_match, $result['ids'][0], 'Exact name should rank first' );
		$this->assertContains( $specialty_match, $result['ids'] );
	}

	public function test_query_filters_out_non_matches(): void {
		$this->make_doctor( 'Dr. Alpha', 'Cardiology' );
		$this->make_doctor( 'Dr. Beta', 'Dermatology' );

		$request = SearchRequestFactory::from_array( array( 'q' => 'cardiology' ), 12 );
		$result  = ( new SearchService() )->search( $request );

		$this->assertSame( 1, $result['total'] );
	}

	public function test_pagination_metadata(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->make_doctor( 'Dr. Number ' . $i );
		}

		$request = SearchRequestFactory::from_array( array( 'per_page' => 2, 'page' => 2 ), 2 );
		$result  = ( new SearchService() )->search( $request );

		$this->assertSame( 5, $result['total'] );
		$this->assertSame( 3, $result['total_pages'] );
		$this->assertSame( 2, $result['page'] );
		$this->assertCount( 2, $result['ids'] );
	}
}
