<?php
/**
 * Facet counts for the enabled filters.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Application\Search;

use BrainStudioz\DoctorPilot\Admin\Settings;
use BrainStudioz\DoctorPilot\Domain\Search\SearchRequestFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides available filter options with counts for enabled Free filters.
 *
 * Counts reflect published doctors only (WordPress term counts for the doctor
 * post type). Only enabled, allowlisted filters are exposed.
 */
final class FacetService {

	/**
	 * Build facets for the enabled taxonomy filters.
	 *
	 * @param int $limit Maximum options per facet.
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function facets( int $limit = 50 ): array {
		$settings = Settings::get();
		$enabled  = isset( $settings['search']['enabled_filters'] ) && is_array( $settings['search']['enabled_filters'] )
			? $settings['search']['enabled_filters']
			: array();

		$facets      = array();
		$filter_taxes = SearchRequestFactory::filter_taxonomies();

		foreach ( $filter_taxes as $param => $taxonomy ) {
			if ( ! in_array( $param, $enabled, true ) ) {
				continue;
			}

			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
					'number'     => max( 1, min( 200, $limit ) ),
					'orderby'    => 'count',
					'order'      => 'DESC',
				)
			);
			if ( is_wp_error( $terms ) ) {
				continue;
			}

			$options = array();
			foreach ( $terms as $term ) {
				$options[] = array(
					'slug'  => $term->slug,
					'label' => $term->name,
					'count' => (int) $term->count,
				);
			}

			if ( array() !== $options ) {
				$facets[ $param ] = $options;
			}
		}

		return $facets;
	}
}
