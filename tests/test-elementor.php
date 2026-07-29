<?php
/**
 * Elementor integration compatibility tests.
 *
 * These tests run without Elementor installed and assert that the integration
 * degrades safely (no fatal, no widget registration, native output intact).
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

use BrainStudioz\DoctorPilot\Elementor\Compatibility;
use BrainStudioz\DoctorPilot\Elementor\Integration;

/**
 * @covers \BrainStudioz\DoctorPilot\Elementor\Compatibility
 * @covers \BrainStudioz\DoctorPilot\Elementor\Integration
 */
final class Test_DoctorPilot_Elementor extends WP_UnitTestCase {

	public function test_compatibility_reports_elementor_absent(): void {
		// The test suite runs without Elementor loaded.
		$this->assertFalse( Compatibility::is_supported() );
		$this->assertFalse( Compatibility::is_pro_supported() );
	}

	public function test_integration_register_is_safe_without_elementor(): void {
		$integration = new Integration();

		// Must not fatal or register Elementor hooks when Elementor is absent.
		$integration->register();

		$this->assertFalse( has_action( 'elementor/widgets/register' ) );
		$this->assertTrue( true );
	}

	public function test_widget_files_exist_for_all_six_free_widgets(): void {
		$dir     = dirname( __DIR__ ) . '/includes/Elementor/Widgets/';
		$widgets = array(
			'DoctorSearch.php',
			'DoctorGridList.php',
			'DepartmentGrid.php',
			'SymptomDirectory.php',
			'DoctorProfile.php',
			'BookingButton.php',
		);

		foreach ( $widgets as $file ) {
			$this->assertFileExists( $dir . $file, "Missing widget file {$file}" );
		}
	}
}
