<?php
/**
 * Settings validation tests.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

use BrainStudioz\DoctorPilot\Admin\Settings;

/**
 * @covers \BrainStudioz\DoctorPilot\Admin\Settings
 */
final class Test_DoctorPilot_Settings extends WP_UnitTestCase {

	/**
	 * Act as an administrator so the manage capability check passes.
	 */
	public function set_up(): void {
		parent::set_up();
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
	}

	public function test_defaults_are_complete(): void {
		$defaults = Settings::defaults();

		$this->assertSame( 1, $defaults['version'] );
		$this->assertSame( 'find-a-doctor', $defaults['general']['directory_base'] );
		$this->assertFalse( $defaults['data']['delete_on_uninstall'] );
		$this->assertFalse( $defaults['privacy']['analytics_enabled'] );
	}

	public function test_per_page_is_clamped_to_bounds(): void {
		$settings = new Settings();

		$clean = $settings->sanitize( array( 'general' => array( 'per_page' => 9999 ) ) );
		$this->assertSame( 48, $clean['general']['per_page'] );

		$clean = $settings->sanitize( array( 'general' => array( 'per_page' => -5 ) ) );
		$this->assertSame( 1, $clean['general']['per_page'] );
	}

	public function test_unknown_sort_falls_back_to_relevance(): void {
		$settings = new Settings();
		$clean    = $settings->sanitize( array( 'general' => array( 'default_sort' => 'not-a-sort' ) ) );

		$this->assertSame( 'relevance', $clean['general']['default_sort'] );
	}

	public function test_reserved_directory_base_is_rejected(): void {
		$settings = new Settings();
		$clean    = $settings->sanitize( array( 'general' => array( 'directory_base' => 'wp-admin' ) ) );

		$this->assertSame( 'find-a-doctor', $clean['general']['directory_base'] );
	}

	public function test_unknown_keys_are_discarded(): void {
		$settings = new Settings();
		$clean    = $settings->sanitize( array( 'evil' => array( 'x' => 1 ) ) );

		$this->assertArrayNotHasKey( 'evil', $clean );
	}
}
