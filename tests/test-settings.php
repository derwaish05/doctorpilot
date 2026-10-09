<?php
/**
 * Settings validation tests.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

use BrainStudioz\DoctorPilot\Admin\Settings;
use BrainStudioz\DoctorPilot\Fields\FieldRegistry;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;

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

	/**
	 * Store the two single-doctor layout IDs.
	 *
	 * @param int $standard      Standard layout ID.
	 * @param int $international International layout ID.
	 */
	private function set_layouts( int $standard, int $international ): void {
		update_option(
			Settings::OPTION_KEY,
			( new Settings() )->sanitize(
				array(
					'general' => array(
						'single_doctor_template'               => $standard,
						'single_doctor_template_international' => $international,
					),
				)
			)
		);
	}

	private function doctor( bool $international ): int {
		$id = self::factory()->post->create( array( 'post_type' => DoctorPostType::POST_TYPE ) );
		update_post_meta( $id, FieldRegistry::META_PREFIX . 'is_international', $international ? 1 : 0 );

		return $id;
	}

	public function test_international_layout_defaults_to_unset(): void {
		$this->assertSame( 0, Settings::defaults()['general']['single_doctor_template_international'] );
	}

	public function test_flagged_doctor_gets_the_international_layout(): void {
		$standard      = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$international = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->set_layouts( $standard, $international );

		$this->assertSame( $international, Settings::single_doctor_template_for( $this->doctor( true ) ) );
		$this->assertSame( $standard, Settings::single_doctor_template_for( $this->doctor( false ) ) );
	}

	public function test_unset_international_layout_leaves_every_doctor_on_the_standard_one(): void {
		$standard = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->set_layouts( $standard, 0 );

		$this->assertSame( $standard, Settings::single_doctor_template_for( $this->doctor( true ) ) );
	}

	public function test_deleted_or_unpublished_international_layout_falls_back(): void {
		$standard = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$draft    = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'draft' ) );
		$flagged  = $this->doctor( true );

		$this->set_layouts( $standard, $draft );
		$this->assertSame( $standard, Settings::single_doctor_template_for( $flagged ) );

		$this->set_layouts( $standard, 99999999 );
		$this->assertSame( $standard, Settings::single_doctor_template_for( $flagged ) );
	}
}
