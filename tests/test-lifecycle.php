<?php
/**
 * Lifecycle and capability tests.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

use BrainStudioz\DoctorPilot\Support\Capabilities;
use BrainStudioz\DoctorPilot\Infrastructure\Lifecycle\Activator;

/**
 * @covers \BrainStudioz\DoctorPilot\Infrastructure\Lifecycle\Activator
 * @covers \BrainStudioz\DoctorPilot\Support\Capabilities
 */
final class Test_DoctorPilot_Lifecycle extends WP_UnitTestCase {

	public function test_activation_seeds_versions(): void {
		Activator::activate();

		$this->assertSame( DOCTORPILOT_VERSION, get_option( 'bstdz_doctorpilot_plugin_version' ) );
		$this->assertSame( (int) DOCTORPILOT_SCHEMA_VERSION, (int) get_option( 'bstdz_doctorpilot_schema_version' ) );
		$this->assertNotFalse( get_option( 'bstdz_doctorpilot_settings' ) );
	}

	public function test_activation_is_idempotent_for_capabilities(): void {
		Activator::activate();
		Activator::activate();

		$admin = get_role( 'administrator' );
		$this->assertTrue( $admin->has_cap( Capabilities::MANAGE ) );
		$this->assertTrue( $admin->has_cap( 'publish_doctorpilot_doctors' ) );
	}

	public function test_capabilities_can_be_removed(): void {
		Capabilities::grant_default_roles();
		Capabilities::remove_from_all_roles();

		$admin = get_role( 'administrator' );
		$this->assertFalse( $admin->has_cap( Capabilities::MANAGE ) );
	}

	public function test_activation_requests_single_rewrite_flush(): void {
		delete_option( Activator::FLUSH_FLAG );
		Activator::activate();

		$this->assertSame( '1', get_option( Activator::FLUSH_FLAG ) );
	}
}
