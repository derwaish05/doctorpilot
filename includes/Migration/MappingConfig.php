<?php
/**
 * Reusable CSV column → field mapping configuration.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Migration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Declares the target fields a CSV import can map onto, with no hospital-specific
 * constants baked in.
 *
 * Each target describes how an incoming column is stored: a native post field,
 * a prefixed doctor meta key, or a taxonomy relationship (by name). The Aman
 * Hospital specifics live in reviewed fixtures/config, never in this reusable
 * code.
 */
final class MappingConfig {

	/**
	 * Meta prefix shared with the canonical field registry.
	 *
	 * @var string
	 */
	public const META_PREFIX = '_doctorpilot_';

	/**
	 * The mappable import targets.
	 *
	 * @return array<string, array<string, string>> target key => {label, kind, ref}
	 */
	public static function targets(): array {
		return array(
			'source_key'   => array( 'label' => 'Source key (idempotency)', 'kind' => 'control', 'ref' => 'source_key' ),
			'name'         => array( 'label' => 'Full name', 'kind' => 'post', 'ref' => 'post_title' ),
			'intro'        => array( 'label' => 'Short introduction', 'kind' => 'post', 'ref' => 'post_excerpt' ),
			'biography'    => array( 'label' => 'Biography', 'kind' => 'post', 'ref' => 'post_content' ),
			'gender'       => array( 'label' => 'Gender', 'kind' => 'meta', 'ref' => self::META_PREFIX . 'gender' ),
			'experience'   => array( 'label' => 'Years of experience', 'kind' => 'meta', 'ref' => self::META_PREFIX . 'years_experience' ),
			'phone'        => array( 'label' => 'Phone', 'kind' => 'meta', 'ref' => self::META_PREFIX . 'booking_phone' ),
			'booking_url'  => array( 'label' => 'Booking URL', 'kind' => 'meta', 'ref' => self::META_PREFIX . 'booking_external_url' ),
			'department'   => array( 'label' => 'Department', 'kind' => 'taxonomy', 'ref' => 'doctorpilot_department' ),
			'specialty'    => array( 'label' => 'Specialty', 'kind' => 'taxonomy', 'ref' => 'doctorpilot_specialty' ),
			'symptom'      => array( 'label' => 'Symptoms', 'kind' => 'taxonomy', 'ref' => 'doctorpilot_symptom' ),
			'treatment'    => array( 'label' => 'Treatments', 'kind' => 'taxonomy', 'ref' => 'doctorpilot_treatment' ),
			'language'     => array( 'label' => 'Languages', 'kind' => 'taxonomy', 'ref' => 'doctorpilot_language' ),
			'legacy_url'   => array( 'label' => 'Legacy URL (for redirect)', 'kind' => 'meta', 'ref' => self::META_PREFIX . 'legacy_url' ),
		);
	}

	/**
	 * Whether a target key exists.
	 *
	 * @param string $target Target key.
	 * @return bool
	 */
	public static function has_target( string $target ): bool {
		return array_key_exists( $target, self::targets() );
	}

	/**
	 * Get a single target definition.
	 *
	 * @param string $target Target key.
	 * @return array<string, string>|null
	 */
	public static function target( string $target ): ?array {
		$targets = self::targets();

		return $targets[ $target ] ?? null;
	}

	/**
	 * Auto-map CSV headers to targets by name/synonym.
	 *
	 * @param array<int, string> $headers CSV header names.
	 * @return array<string, string> column => target key.
	 */
	public static function auto_map( array $headers ): array {
		$synonyms = array(
			'source_key'  => array( 'id', 'source_key', 'external_id', 'ref' ),
			'name'        => array( 'name', 'full_name', 'doctor', 'doctor_name', 'title' ),
			'intro'       => array( 'intro', 'introduction', 'summary', 'excerpt' ),
			'biography'   => array( 'biography', 'bio', 'about', 'description' ),
			'gender'      => array( 'gender', 'sex' ),
			'experience'  => array( 'experience', 'years_experience', 'years' ),
			'phone'       => array( 'phone', 'telephone', 'contact', 'mobile' ),
			'booking_url' => array( 'booking_url', 'booking', 'appointment_url', 'book' ),
			'department'  => array( 'department', 'dept' ),
			'specialty'   => array( 'specialty', 'speciality', 'specialties' ),
			'symptom'     => array( 'symptom', 'symptoms' ),
			'treatment'   => array( 'treatment', 'treatments', 'procedures' ),
			'language'    => array( 'language', 'languages', 'spoken' ),
			'legacy_url'  => array( 'legacy_url', 'old_url', 'previous_url' ),
		);

		$mapping = array();
		foreach ( $headers as $header ) {
			$key = sanitize_key( str_replace( array( ' ', '-' ), '_', (string) $header ) );
			foreach ( $synonyms as $target => $aliases ) {
				if ( in_array( $key, $aliases, true ) ) {
					$mapping[ (string) $header ] = $target;
					break;
				}
			}
		}

		return $mapping;
	}

	/**
	 * Validate a proposed column→target mapping.
	 *
	 * Rejects unknown targets and duplicate target assignments (two columns
	 * writing the same field).
	 *
	 * @param array<string, string> $mapping column header => target key.
	 * @return array<int, string> List of validation error messages (empty when valid).
	 */
	public static function validate_mapping( array $mapping ): array {
		$errors = array();
		$seen   = array();

		foreach ( $mapping as $column => $target ) {
			if ( '' === $target ) {
				continue;
			}
			if ( ! self::has_target( $target ) ) {
				$errors[] = sprintf( 'Unknown mapping target "%s".', $target );
				continue;
			}
			if ( isset( $seen[ $target ] ) ) {
				$errors[] = sprintf( 'Target "%s" is mapped by more than one column.', $target );
			}
			$seen[ $target ] = true;
		}

		if ( ! isset( $seen['name'] ) ) {
			$errors[] = 'A column must be mapped to "Full name".';
		}
		if ( ! isset( $seen['source_key'] ) ) {
			$errors[] = 'A column must be mapped to "Source key" for safe re-import.';
		}

		return $errors;
	}
}
