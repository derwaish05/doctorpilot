<?php
/**
 * Doctor profile completeness calculation.
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
 * Computes a completeness score and the list of missing recommended fields.
 *
 * The score is always derived from stored data and is never accepted from a
 * client. It powers the editor's completeness indicator and the admin health
 * view without gating publication by itself.
 */
final class Completeness {

	/**
	 * Weighted checks: label => weight. Weights are relative.
	 *
	 * @var array<string, int>
	 */
	private const WEIGHTS = array(
		'name'         => 3,
		'photo'        => 2,
		'intro'        => 2,
		'biography'    => 2,
		'credentials'  => 1,
		'department'   => 3,
		'specialty'    => 3,
		'experience'   => 1,
		'languages'    => 1,
		'location'     => 2,
		'booking'      => 2,
	);

	/**
	 * Compute completeness for a doctor post.
	 *
	 * @param int $post_id Doctor post ID.
	 * @return array{score:int, missing:array<int,string>} Score 0-100 and missing labels.
	 */
	public static function for_post( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! ( $post instanceof \WP_Post ) ) {
			return array(
				'score'   => 0,
				'missing' => array(),
			);
		}

		$checks = array(
			'name'        => '' !== trim( (string) $post->post_title ),
			'photo'       => has_post_thumbnail( $post_id ),
			'intro'       => '' !== trim( (string) $post->post_excerpt ),
			'biography'   => '' !== trim( wp_strip_all_tags( (string) $post->post_content ) ),
			'credentials' => '' !== (string) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'credentials', true ),
			'department'  => self::has_terms( $post_id, Taxonomies::DEPARTMENT ),
			'specialty'   => self::has_terms( $post_id, Taxonomies::SPECIALTY ),
			'experience'  => (int) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'years_experience', true ) > 0,
			'languages'   => self::has_terms( $post_id, Taxonomies::LANGUAGE ),
			'location'    => self::has_location( $post_id ),
			'booking'     => self::has_booking( $post_id ),
		);

		$earned = 0;
		$total  = 0;
		$missing = array();

		foreach ( self::WEIGHTS as $key => $weight ) {
			$total += $weight;
			if ( ! empty( $checks[ $key ] ) ) {
				$earned += $weight;
			} else {
				$missing[] = self::label( $key );
			}
		}

		$score = $total > 0 ? (int) round( ( $earned / $total ) * 100 ) : 0;

		return array(
			'score'   => $score,
			'missing' => $missing,
		);
	}

	/**
	 * Whether a doctor has any terms in a taxonomy.
	 *
	 * @param int    $post_id  Doctor post ID.
	 * @param string $taxonomy Taxonomy key.
	 * @return bool
	 */
	private static function has_terms( int $post_id, string $taxonomy ): bool {
		$terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );

		return ! is_wp_error( $terms ) && array() !== $terms;
	}

	/**
	 * Whether at least one location record with a location term exists.
	 *
	 * @param int $post_id Doctor post ID.
	 * @return bool
	 */
	private static function has_location( int $post_id ): bool {
		$locations = get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'locations', true );
		if ( ! is_array( $locations ) ) {
			return false;
		}

		foreach ( $locations as $location ) {
			if ( is_array( $location ) && ! empty( $location['location_term_id'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a usable booking destination is configured.
	 *
	 * @param int $post_id Doctor post ID.
	 * @return bool
	 */
	private static function has_booking( int $post_id ): bool {
		$method = (string) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'booking_method', true );

		if ( 'phone' === $method ) {
			return '' !== (string) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'booking_phone', true );
		}

		if ( 'external_url' === $method ) {
			return '' !== (string) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'booking_external_url', true );
		}

		// Enquiry needs no destination of its own.
		return 'enquiry' === $method;
	}

	/**
	 * Human-readable label for a completeness check key.
	 *
	 * @param string $key Check key.
	 * @return string
	 */
	private static function label( string $key ): string {
		$labels = array(
			'name'        => __( 'Full name', 'doctorpilot' ),
			'photo'       => __( 'Photograph', 'doctorpilot' ),
			'intro'       => __( 'Short introduction', 'doctorpilot' ),
			'biography'   => __( 'Biography', 'doctorpilot' ),
			'credentials' => __( 'Credentials', 'doctorpilot' ),
			'department'  => __( 'Department', 'doctorpilot' ),
			'specialty'   => __( 'Specialty', 'doctorpilot' ),
			'experience'  => __( 'Years of experience', 'doctorpilot' ),
			'languages'   => __( 'Languages', 'doctorpilot' ),
			'location'    => __( 'Location', 'doctorpilot' ),
			'booking'     => __( 'Booking destination', 'doctorpilot' ),
		);

		return $labels[ $key ] ?? $key;
	}
}
