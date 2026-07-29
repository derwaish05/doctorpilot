<?php
/**
 * Field sanitizers.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps sanitizer tokens to concrete, recursive sanitization behaviour.
 *
 * Every value stored for a doctor passes through here. Repeater rows are
 * sanitized recursively against their item schema, and unknown keys are
 * discarded so no unexpected data is ever persisted.
 */
final class Sanitizer {

	/**
	 * Sanitize a raw value for a given field definition.
	 *
	 * @param array<string, mixed> $field Field definition from the registry.
	 * @param mixed                $value Raw value.
	 * @return mixed Sanitized value ready for storage.
	 */
	public static function apply( array $field, $value ) {
		$token = isset( $field['sanitize'] ) ? (string) $field['sanitize'] : 'text';

		switch ( $token ) {
			case 'repeater':
				return self::repeater( $field, $value );
			case 'term_ids':
				return self::term_ids( $value );
			case 'lines':
				return self::lines( $value );
			default:
				return self::scalar( $token, $value );
		}
	}

	/**
	 * Sanitize a scalar value by token.
	 *
	 * @param string $token Sanitizer token.
	 * @param mixed  $value Raw value.
	 * @return mixed
	 */
	public static function scalar( string $token, $value ) {
		switch ( $token ) {
			case 'html':
				return wp_kses_post( is_string( $value ) ? $value : '' );
			case 'textarea':
				return sanitize_textarea_field( is_scalar( $value ) ? (string) $value : '' );
			case 'key':
				return sanitize_key( is_scalar( $value ) ? (string) $value : '' );
			case 'int':
				return is_numeric( $value ) ? (int) $value : 0;
			case 'nonneg_int':
				return is_numeric( $value ) ? max( 0, (int) $value ) : 0;
			case 'year':
				$year = is_numeric( $value ) ? (int) $value : 0;
				return ( $year >= 1900 && $year <= 2100 ) ? $year : 0;
			case 'bool':
				return ! empty( $value ) ? 1 : 0;
			case 'url':
				return esc_url_raw( is_scalar( $value ) ? (string) $value : '', array( 'http', 'https' ) );
			case 'email':
				$email = sanitize_email( is_scalar( $value ) ? (string) $value : '' );
				return is_email( $email ) ? $email : '';
			case 'phone':
				return self::phone( $value );
			case 'review_status':
				return self::review_status( $value );
			case 'text':
			default:
				return sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
		}
	}

	/**
	 * Sanitize a repeater value against its item schema.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Raw rows.
	 * @return array<int, array<string, mixed>>
	 */
	private static function repeater( array $field, $value ): array {
		$schema = isset( $field['repeater'] ) && is_array( $field['repeater'] ) ? $field['repeater'] : array();
		$rows   = is_array( $value ) ? $value : array();
		$clean  = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$clean_row = array();
			foreach ( $schema as $sub_key => $sub_def ) {
				$sub_token           = isset( $sub_def['sanitize'] ) ? (string) $sub_def['sanitize'] : 'text';
				$raw                 = $row[ $sub_key ] ?? '';
				$clean_row[ $sub_key ] = self::scalar( $sub_token, $raw );
			}

			// Skip fully empty rows so blank repeater entries are not stored.
			if ( self::row_has_content( $clean_row ) ) {
				$clean[] = $clean_row;
			}
		}

		return $clean;
	}

	/**
	 * Whether a repeater row contains any non-empty value.
	 *
	 * @param array<string, mixed> $row Sanitized row.
	 * @return bool
	 */
	private static function row_has_content( array $row ): bool {
		foreach ( $row as $value ) {
			if ( '' !== $value && 0 !== $value && '0' !== $value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sanitize a list of term IDs.
	 *
	 * @param mixed $value Raw list.
	 * @return array<int, int>
	 */
	private static function term_ids( $value ): array {
		$value = is_array( $value ) ? $value : array();
		$ids   = array();

		foreach ( $value as $id ) {
			$id = (int) $id;
			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Sanitize a newline-delimited text list into an array of strings.
	 *
	 * @param mixed $value Raw value (string or array).
	 * @return array<int, string>
	 */
	private static function lines( $value ): array {
		if ( is_array( $value ) ) {
			$parts = $value;
		} else {
			$parts = preg_split( '/\r\n|\r|\n/', is_scalar( $value ) ? (string) $value : '' );
		}

		$clean = array();
		foreach ( (array) $parts as $part ) {
			$part = sanitize_text_field( (string) $part );
			if ( '' !== $part ) {
				$clean[] = $part;
			}
		}

		return $clean;
	}

	/**
	 * Sanitize a phone string to safe dialing characters.
	 *
	 * @param mixed $value Raw phone.
	 * @return string
	 */
	private static function phone( $value ): string {
		$value = is_scalar( $value ) ? (string) $value : '';
		$value = preg_replace( '/[^0-9+()\-\s]/', '', $value );

		return trim( (string) $value );
	}

	/**
	 * Sanitize the separate review status value.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function review_status( $value ): string {
		$value   = sanitize_key( is_scalar( $value ) ? (string) $value : '' );
		$allowed = array( 'draft', 'needs_review', 'approved' );

		return in_array( $value, $allowed, true ) ? $value : 'draft';
	}
}
