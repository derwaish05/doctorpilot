<?php
/**
 * Reverse-reference index for library records.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Library;

use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks which doctors reference each global library record.
 *
 * Each global record stores a rebuildable list of assigned doctor IDs in
 * `_doctorpilot_lib_doctors`, maintained transactionally when a doctor's
 * assignments change. When a global record is permanently deleted, this index
 * lets the plugin remove the reference from every doctor without an unreliable
 * serialized-meta `LIKE` scan.
 */
final class ReverseIndex {

	/**
	 * Register the delete-cleanup hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'before_delete_post', array( $this, 'on_delete' ) );
	}

	/**
	 * Apply the diff of a doctor's assignments to the affected globals' index.
	 *
	 * @param int             $doctor_id Doctor post ID.
	 * @param array<int, int> $added     Newly assigned global IDs.
	 * @param array<int, int> $removed   Unassigned global IDs.
	 * @return void
	 */
	public static function apply_diff( int $doctor_id, array $added, array $removed ): void {
		foreach ( $added as $global_id ) {
			self::add_doctor( (int) $global_id, $doctor_id );
		}
		foreach ( $removed as $global_id ) {
			self::remove_doctor( (int) $global_id, $doctor_id );
		}
	}

	/**
	 * When a global record is deleted, drop its reference from all doctors.
	 *
	 * @param int $post_id Deleted post ID.
	 * @return void
	 */
	public function on_delete( int $post_id ): void {
		if ( ! in_array( get_post_type( $post_id ), LibraryPostTypes::keys(), true ) ) {
			return;
		}

		$doctors = get_post_meta( $post_id, LibraryPostTypes::META_DOCTORS, true );
		$doctors = is_array( $doctors ) ? array_map( 'intval', $doctors ) : array();

		foreach ( $doctors as $doctor_id ) {
			if ( DoctorPostType::POST_TYPE !== get_post_type( $doctor_id ) ) {
				continue;
			}
			foreach ( DoctorContentResolver::types() as $type ) {
				$key = DoctorContentResolver::ids_key( $type );
				$ids = get_post_meta( $doctor_id, $key, true );
				$ids = is_array( $ids ) ? array_map( 'intval', $ids ) : array();
				if ( in_array( $post_id, $ids, true ) ) {
					$ids = array_values( array_diff( $ids, array( $post_id ) ) );
					update_post_meta( $doctor_id, $key, $ids );
				}
			}
		}
	}

	/**
	 * Add a doctor to a global record's index.
	 *
	 * @param int $global_id Global record ID.
	 * @param int $doctor_id Doctor ID.
	 * @return void
	 */
	private static function add_doctor( int $global_id, int $doctor_id ): void {
		if ( ! in_array( get_post_type( $global_id ), LibraryPostTypes::keys(), true ) ) {
			return;
		}
		$doctors = get_post_meta( $global_id, LibraryPostTypes::META_DOCTORS, true );
		$doctors = is_array( $doctors ) ? array_map( 'intval', $doctors ) : array();
		if ( ! in_array( $doctor_id, $doctors, true ) ) {
			$doctors[] = $doctor_id;
			update_post_meta( $global_id, LibraryPostTypes::META_DOCTORS, $doctors );
		}
	}

	/**
	 * Remove a doctor from a global record's index.
	 *
	 * @param int $global_id Global record ID.
	 * @param int $doctor_id Doctor ID.
	 * @return void
	 */
	private static function remove_doctor( int $global_id, int $doctor_id ): void {
		$doctors = get_post_meta( $global_id, LibraryPostTypes::META_DOCTORS, true );
		$doctors = is_array( $doctors ) ? array_map( 'intval', $doctors ) : array();
		if ( in_array( $doctor_id, $doctors, true ) ) {
			$doctors = array_values( array_diff( $doctors, array( $doctor_id ) ) );
			update_post_meta( $global_id, LibraryPostTypes::META_DOCTORS, $doctors );
		}
	}
}
