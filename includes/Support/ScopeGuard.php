<?php
/**
 * Object/department scope enforcement.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enforces object-level scope for scoped roles via map_meta_cap.
 *
 * A Department Manager may only edit/publish doctors in a department they
 * manage; a self-edit Doctor may only edit their own linked profile. Scope is
 * checked server-side against stored assignments — never a role name and never a
 * client-supplied department (ADR-0014).
 */
final class ScopeGuard {

	private const POST_TYPE          = 'doctorpilot_doctor';
	private const MANAGED_DEPTS_META = 'doctorpilot_managed_department_ids';
	private const LINKED_USER_META   = '_doctorpilot_linked_user_id';

	/**
	 * Register the meta-cap filter.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'map_meta_cap', array( $this, 'map_meta_cap' ), 10, 4 );
	}

	/**
	 * Restrict scoped capabilities to owned/department doctors.
	 *
	 * @param array<int, string> $caps    Required primitive caps.
	 * @param string             $cap     Capability being checked.
	 * @param int                $user_id User ID.
	 * @param array<int, mixed>  $args    Context args (args[0] = post ID).
	 * @return array<int, string>
	 */
	public function map_meta_cap( array $caps, string $cap, int $user_id, array $args ): array {
		$scoped = array(
			'edit_doctorpilot_doctor'   => true,
			'delete_doctorpilot_doctor' => true,
			'publish_doctorpilot_doctor' => true,
		);

		if ( ! isset( $scoped[ $cap ] ) || ! isset( $args[0] ) ) {
			return $caps;
		}

		$post_id = (int) $args[0];
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return $caps;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! ( $user instanceof \WP_User ) ) {
			return $caps;
		}

		// Administrators and users who can edit others are unaffected.
		if ( user_can( $user, 'edit_others_doctorpilot_doctors' ) && ! in_array( Roles::DEPARTMENT_MANAGER, (array) $user->roles, true ) ) {
			return $caps;
		}

		// Self-edit Doctor: only the linked profile.
		if ( in_array( Roles::DOCTOR, (array) $user->roles, true ) ) {
			$linked = (int) get_post_meta( $post_id, self::LINKED_USER_META, true );
			if ( $linked !== $user_id ) {
				return array( 'do_not_allow' );
			}
			return $caps;
		}

		// Department Manager: only doctors in a managed department.
		if ( in_array( Roles::DEPARTMENT_MANAGER, (array) $user->roles, true ) ) {
			if ( ! $this->manages_department_of( $user_id, $post_id ) ) {
				return array( 'do_not_allow' );
			}
		}

		return $caps;
	}

	/**
	 * Whether a manager's departments intersect a doctor's departments.
	 *
	 * @param int $user_id   Manager user ID.
	 * @param int $doctor_id Doctor post ID.
	 * @return bool
	 */
	private function manages_department_of( int $user_id, int $doctor_id ): bool {
		$managed = get_user_meta( $user_id, self::MANAGED_DEPTS_META, true );
		$managed = is_array( $managed ) ? array_map( 'intval', $managed ) : array();
		if ( array() === $managed ) {
			return false;
		}

		$doctor_depts = wp_get_object_terms( $doctor_id, 'doctorpilot_department', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $doctor_depts ) ) {
			return false;
		}

		return array() !== array_intersect( $managed, array_map( 'intval', $doctor_depts ) );
	}
}
