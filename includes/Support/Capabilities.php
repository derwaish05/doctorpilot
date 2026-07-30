<?php
/**
 * Capability definitions and role grants.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single authority for DoctorPilot capability primitives.
 *
 * Authorization is always enforced server-side against these capabilities plus
 * object/department scope where applicable. Role names never grant access on
 * their own, and a client-supplied department never bypasses a scope check.
 */
final class Capabilities {

	/**
	 * The primitive capability required to manage plugin settings and screens.
	 *
	 * @var string
	 */
	public const MANAGE = 'manage_doctorpilot';

	/**
	 * Capability required to manage DoctorPilot taxonomy terms.
	 *
	 * @var string
	 */
	public const MANAGE_TERMS = 'manage_doctorpilot_terms';

	/**
	 * Capability required to manage the global content library records.
	 *
	 * @var string
	 */
	public const MANAGE_LIBRARY = 'manage_doctorpilot_library';

	/**
	 * All capability primitives owned by the plugin.
	 *
	 * Pro-only primitives are included so a single grant model covers both
	 * packages; Free simply never checks the Pro ones.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array(
			// Object and collection capabilities for the doctor post type.
			'read_doctorpilot_doctor',
			'read_private_doctorpilot_doctors',
			'edit_doctorpilot_doctor',
			'edit_doctorpilot_doctors',
			'edit_others_doctorpilot_doctors',
			'edit_published_doctorpilot_doctors',
			'publish_doctorpilot_doctors',
			'delete_doctorpilot_doctor',
			'delete_doctorpilot_doctors',
			'delete_others_doctorpilot_doctors',
			'delete_published_doctorpilot_doctors',
			// Plugin-level control-plane capabilities.
			self::MANAGE,
			self::MANAGE_TERMS,
			self::MANAGE_LIBRARY,
			// Pro capabilities (granted to administrators, checked only by Pro).
			'manage_doctorpilot_migrations',
			'view_doctorpilot_analytics',
		);
	}

	/**
	 * Idempotently grant every DoctorPilot capability to the administrator role.
	 *
	 * @return void
	 */
	public static function grant_default_roles(): void {
		$admin = get_role( 'administrator' );
		if ( null === $admin ) {
			return;
		}

		foreach ( self::all() as $capability ) {
			if ( ! $admin->has_cap( $capability ) ) {
				$admin->add_cap( $capability );
			}
		}
	}

	/**
	 * Remove every DoctorPilot capability from all roles.
	 *
	 * Reserved for the explicit uninstall path; not used on deactivation.
	 *
	 * @return void
	 */
	public static function remove_from_all_roles(): void {
		$roles = wp_roles();
		if ( ! ( $roles instanceof \WP_Roles ) ) {
			return;
		}

		foreach ( array_keys( $roles->roles ) as $role_name ) {
			$role = get_role( $role_name );
			if ( null === $role ) {
				continue;
			}

			foreach ( self::all() as $capability ) {
				if ( $role->has_cap( $capability ) ) {
					$role->remove_cap( $capability );
				}
			}
		}
	}
}
