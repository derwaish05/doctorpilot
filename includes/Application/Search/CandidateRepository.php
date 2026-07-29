<?php
/**
 * Retrieves published doctor candidates for a search request.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Application\Search;

use BrainStudioz\DoctorPilot\Domain\Search\SearchRequest;
use BrainStudioz\DoctorPilot\Fields\FieldRegistry;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies visibility and filters, then returns lightweight candidate rows.
 *
 * Only published doctors are ever returned. Taxonomy and meta filters are
 * applied at the query level (before scoring), IDs are retrieved first, and the
 * meta cache is primed once so scoring does not trigger per-row queries.
 */
final class CandidateRepository {

	/**
	 * Hard cap on candidates considered before scoring/pagination.
	 *
	 * @var int
	 */
	private const MAX_CANDIDATES = 2000;

	/**
	 * Fetch candidate rows matching the request's filters.
	 *
	 * @param SearchRequest $request Search request.
	 * @return array<int, array<string, mixed>> Rows with id, name, index, featured, order, experience, created.
	 */
	public function candidates( SearchRequest $request ): array {
		$args = array(
			'post_type'              => DoctorPostType::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => self::MAX_CANDIDATES,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
		);

		$tax_query = $this->tax_query( $request );
		if ( array() !== $tax_query ) {
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Bounded, indexed.
		}

		$meta_query = $this->meta_query( $request );
		if ( array() !== $meta_query ) {
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded filters.
		}

		$query = new \WP_Query( $args );
		$ids   = array_map( 'intval', $query->posts );

		if ( array() === $ids ) {
			return array();
		}

		// Prime the meta cache once to avoid per-candidate meta queries.
		update_meta_cache( 'post', $ids );

		$rows = array();
		foreach ( $ids as $id ) {
			$index = get_post_meta( $id, SearchIndexBuilder::INDEX, true );
			$rows[] = array(
				'id'         => $id,
				'name'       => (string) get_post_meta( $id, SearchIndexBuilder::SORT_NAME, true ),
				'index'      => is_array( $index ) ? $index : array(),
				'document'   => (string) get_post_meta( $id, SearchIndexBuilder::DOCUMENT, true ),
				'featured'   => (bool) get_post_meta( $id, FieldRegistry::META_PREFIX . 'is_featured', true ),
				'order'      => (int) get_post_meta( $id, FieldRegistry::META_PREFIX . 'manual_order', true ),
				'experience' => (int) get_post_meta( $id, FieldRegistry::META_PREFIX . 'years_experience', true ),
				'created'    => (int) get_post_time( 'U', true, $id ),
			);
		}

		return $rows;
	}

	/**
	 * Build the tax_query from taxonomy filters.
	 *
	 * @param SearchRequest $request Search request.
	 * @return array<int|string, mixed>
	 */
	private function tax_query( SearchRequest $request ): array {
		$filters = $request->taxonomy_filters();
		if ( array() === $filters ) {
			return array();
		}

		$tax_query = array( 'relation' => 'AND' );
		foreach ( $filters as $taxonomy => $slugs ) {
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $slugs,
				'operator' => 'IN',
			);
		}

		return $tax_query;
	}

	/**
	 * Build the meta_query from gender/accepting/online filters.
	 *
	 * @param SearchRequest $request Search request.
	 * @return array<int|string, mixed>
	 */
	private function meta_query( SearchRequest $request ): array {
		$clauses = array();

		if ( '' !== $request->gender() ) {
			$clauses[] = array(
				'key'   => FieldRegistry::META_PREFIX . 'gender',
				'value' => $request->gender(),
			);
		}

		if ( true === $request->accepting_new() ) {
			$clauses[] = array(
				'key'   => FieldRegistry::META_PREFIX . 'accepting_new_patients',
				'value' => '1',
			);
		}

		if ( true === $request->online() ) {
			$clauses[] = array(
				'key'   => FieldRegistry::META_PREFIX . 'online_consultation',
				'value' => '1',
			);
		}

		if ( array() === $clauses ) {
			return array();
		}

		$clauses['relation'] = 'AND';

		return $clauses;
	}
}
