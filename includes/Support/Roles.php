<?php
/**
 * Scoped role presets and capability grants.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the optional scoped role presets and ensures their capabilities
 * exist.
 *
 * Role presets are a convenience only — authorization is always enforced by the
 * capability plus object/department scope (see {@see ScopeGuard}), never by role
 * name. Deactivating the plugin leaves roles and data intact; removal is
 * explicit, and happens only through the opt-in uninstall path.
 */
final class Roles {

	public const DEPARTMENT_MANAGER = 'doctorpilot_department_manager';
	public const DOCTOR             = 'doctorpilot_doctor';

	/**
	 * Base doctor object/collection capabilities shared by presets.
	 *
	 * @return array<int, string>
	 */
	private static function doctor_caps(): array {
		return array(
			'read'                            => true,
			'read_doctorpilot_doctor'         => true,
			'edit_doctorpilot_doctor'         => true,
			'edit_doctorpilot_doctors'        => true,
			'edit_published_doctorpilot_doctors' => true,
			'upload_files'                    => true,
		);
	}

	/**
	 * Install role presets and grant the migration and analytics capabilities
	 * to administrators.
	 *
	 * @return void
	 */
	public static function install(): void {
		// Ensure administrators hold these capabilities even on a site whose
		// roles were written before the capabilities existed.
		$admin = get_role( 'administrator' );
		if ( null !== $admin ) {
			foreach ( array( 'manage_doctorpilot_migrations', 'view_doctorpilot_analytics' ) as $cap ) {
				if ( ! $admin->has_cap( $cap ) ) {
					$admin->add_cap( $cap );
				}
			}
		}

		// Department Manager: edits doctors within assigned departments (scope enforced separately).
		// Role display names are passed untranslated: this runs during activation
		// (before `init`), and translating here would trigger a WordPress 6.7
		// "translation loaded too early" notice output during activation. WordPress
		// handles role-name display itself.
		$dept_caps = self::doctor_caps();
		$dept_caps['edit_others_doctorpilot_doctors'] = true;
		$dept_caps['publish_doctorpilot_doctors']     = true;
		self::upsert_role( self::DEPARTMENT_MANAGER, 'Doctor Department Manager', $dept_caps );

		// Doctor: may edit only their own linked profile (scope enforced separately).
		self::upsert_role( self::DOCTOR, 'Doctor (self-edit)', self::doctor_caps() );
	}

	/**
	 * Remove the role presets. Reserved for explicit uninstall.
	 *
	 * @return void
	 */
	public static function remove(): void {
		remove_role( self::DEPARTMENT_MANAGER );
		remove_role( self::DOCTOR );
	}

	/**
	 * Create or refresh a role with a capability map.
	 *
	 * @param string              $role  Role slug.
	 * @param string              $label Display name.
	 * @param array<string, bool> $caps  Capability map.
	 * @return void
	 */
	private static function upsert_role( string $role, string $label, array $caps ): void {
		if ( null !== get_role( $role ) ) {
			$existing = get_role( $role );
			foreach ( $caps as $cap => $grant ) {
				$existing->add_cap( $cap, (bool) $grant );
			}
			return;
		}

		add_role( $role, $label, $caps );
	}
}
