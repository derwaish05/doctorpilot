<?php
/**
 * Validates primary department/specialty invariants.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Domain\Doctor;

use BrainStudioz\DoctorPilot\Fields\FieldRegistry;
use BrainStudioz\DoctorPilot\Taxonomies\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ensures a doctor's primary department and specialty are always among the
 * terms actually assigned to that doctor.
 *
 * If a primary term is removed from the assigned set, the primary reference is
 * cleared rather than left dangling. This keeps the taxonomy relationships
 * canonical (ADR-0012).
 */
final class PrimaryTermValidator {

	/**
	 * Reconcile primary term references against the assigned terms.
	 *
	 * @param int $post_id Doctor post ID.
	 * @return void
	 */
	public static function reconcile( int $post_id ): void {
		self::reconcile_one(
			$post_id,
			FieldRegistry::META_PREFIX . 'primary_department_id',
			Taxonomies::DEPARTMENT
		);
		self::reconcile_one(
			$post_id,
			FieldRegistry::META_PREFIX . 'primary_specialty_id',
			Taxonomies::SPECIALTY
		);
	}

	/**
	 * Whether the given primary term is valid for the doctor.
	 *
	 * @param int    $post_id  Doctor post ID.
	 * @param int    $term_id  Candidate primary term ID.
	 * @param string $taxonomy Taxonomy key.
	 * @return bool
	 */
	public static function is_valid_primary( int $post_id, int $term_id, string $taxonomy ): bool {
		if ( $term_id <= 0 ) {
			return true; // No primary set is a valid state.
		}

		$assigned = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $assigned ) ) {
			return false;
		}

		return in_array( $term_id, array_map( 'intval', $assigned ), true );
	}

	/**
	 * Clear an invalid primary reference for a single taxonomy.
	 *
	 * @param int    $post_id  Doctor post ID.
	 * @param string $meta_key Meta key holding the primary term ID.
	 * @param string $taxonomy Taxonomy key.
	 * @return void
	 */
	private static function reconcile_one( int $post_id, string $meta_key, string $taxonomy ): void {
		$primary = (int) get_post_meta( $post_id, $meta_key, true );

		if ( 0 === $primary ) {
			return;
		}

		if ( ! self::is_valid_primary( $post_id, $primary, $taxonomy ) ) {
			delete_post_meta( $post_id, $meta_key );
		}
	}
}
