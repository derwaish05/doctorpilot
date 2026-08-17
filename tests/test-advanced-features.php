<?php
/**
 * DoctorPilot Pro feature tests (schema, filters, booking, analytics).
 *
 * Requires the WordPress PHPUnit harness with DoctorPilot and DoctorPilot Pro
 * loaded and activated.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

use BrainStudioz\DoctorPilot\Analytics\AnalyticsRepository;
use BrainStudioz\DoctorPilot\Directory\AdvancedFilters;
use BrainStudioz\DoctorPilot\Directory\InsuranceTaxonomy;

/**
 * @covers \BrainStudioz\DoctorPilot\Directory\AdvancedFilters
 * @covers \BrainStudioz\DoctorPilot\Analytics\AnalyticsRepository
 */
final class Test_DoctorPilot_Advanced_Features extends WP_UnitTestCase {

	public function test_advanced_filters_extend_the_base_filter_map(): void {
		( new AdvancedFilters() )->register();

		$map = (array) apply_filters(
			'doctorpilot/search/filter_taxonomies',
			array( 'department' => 'doctorpilot_department' )
		);

		$this->assertArrayHasKey( 'insurance', $map );
		$this->assertArrayHasKey( 'condition', $map );
		$this->assertSame( InsuranceTaxonomy::TAXONOMY, $map['insurance'] );
	}

	public function test_locations_max_is_raised_for_pro(): void {
		( new AdvancedFilters() )->register();

		$this->assertGreaterThan( 1, (int) apply_filters( 'doctorpilot/locations/max', 1 ) );
	}

	public function test_analytics_disabled_by_default(): void {
		$this->assertFalse( \BrainStudioz\DoctorPilot\Analytics\AnalyticsService::enabled() );
	}

	public function test_analytics_aggregate_increment_and_top(): void {
		\BrainStudioz\DoctorPilot\Support\Tables::create();

		$repo = new AnalyticsRepository();
		$repo->increment( 'symptom', 'chest-pain' );
		$repo->increment( 'symptom', 'chest-pain' );
		$repo->increment( 'symptom', 'headache' );

		$top = $repo->top( 'symptom', 30, 5 );

		$this->assertNotEmpty( $top );
		$this->assertSame( 'chest-pain', $top[0]['label'] );
		$this->assertSame( 2, $top[0]['total'] );
	}
}
