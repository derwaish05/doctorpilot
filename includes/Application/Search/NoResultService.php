<?php
/**
 * No-result suggestions.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Application\Search;

use BrainStudioz\DoctorPilot\Application\Doctors\DoctorView;
use BrainStudioz\DoctorPilot\Fields\FieldRegistry;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;
use BrainStudioz\DoctorPilot\Taxonomies\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds safe, non-diagnostic content when a search returns nothing.
 *
 * Offers curated related departments/specialties and featured doctors, plus a
 * discovery-not-diagnosis disclaimer. It never infers a diagnosis, urgency, or
 * treatment from the query.
 */
final class NoResultService {

	/**
	 * Build no-result content.
	 *
	 * @param string $query Original query.
	 * @return array<string, mixed>
	 */
	public function build( string $query ): array {
		return array(
			'message'          => __( 'No doctors matched your search.', 'doctorpilot' ),
			'disclaimer'       => __( 'Search helps you find a doctor and does not provide a diagnosis. For emergencies, contact your local emergency service.', 'doctorpilot' ),
			'departments'      => $this->popular_terms( Taxonomies::DEPARTMENT ),
			'specialties'      => $this->popular_terms( Taxonomies::SPECIALTY ),
			'featured_doctors' => $this->featured_doctors(),
		);
	}

	/**
	 * Popular terms for a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy key.
	 * @return array<int, array<string, mixed>>
	 */
	private function popular_terms( string $taxonomy ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => 6,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$out = array();
		foreach ( $terms as $term ) {
			$out[] = array(
				'slug'  => $term->slug,
				'label' => $term->name,
			);
		}

		return $out;
	}

	/**
	 * A small set of featured, published doctors as cards.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function featured_doctors(): array {
		$ids = get_posts(
			array(
				'post_type'      => DoctorPostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 4,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => FieldRegistry::META_PREFIX . 'is_featured', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		$view  = new DoctorView();
		$cards = array();
		foreach ( (array) $ids as $id ) {
			$card = $view->card( (int) $id );
			if ( null !== $card ) {
				$cards[] = $card;
			}
		}

		return $cards;
	}
}
