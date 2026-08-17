<?php
/**
 * Advanced Pro search filters and multi-location.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Directory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extends the Free search contract with additional taxonomy filters and raises
 * the multi-location limit.
 *
 * Uses only the published extension hooks, so candidate querying, active-filter
 * chips, and facets all continue to work through the Free services — no parallel
 * query engine. When Pro is disabled these hooks disappear and the directory
 * cleanly reverts to the Free filter set.
 */
final class AdvancedFilters {

	/**
	 * Maximum locations per doctor under Pro.
	 *
	 * @var int
	 */
	private const MAX_LOCATIONS = 10;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'doctorpilot/search/filter_taxonomies', array( $this, 'add_filter_taxonomies' ) );
		add_filter( 'doctorpilot/locations/max', array( $this, 'locations_max' ) );
	}

	/**
	 * Add condition, treatment, location, and insurance to the filter map.
	 *
	 * @param array<string, string> $map Param => taxonomy map.
	 * @return array<string, string>
	 */
	public function add_filter_taxonomies( array $map ): array {
		$map['condition'] = 'doctorpilot_condition';
		$map['treatment'] = 'doctorpilot_treatment';
		$map['location']  = 'doctorpilot_location';
		$map['insurance'] = InsuranceTaxonomy::TAXONOMY;

		return $map;
	}

	/**
	 * Raise the stored-location limit for Pro.
	 *
	 * @param int $max Current maximum.
	 * @return int
	 */
	public function locations_max( int $max ): int {
		return max( $max, self::MAX_LOCATIONS );
	}
}
