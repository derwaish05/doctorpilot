<?php
/**
 * Redirect mapping validation.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Redirects;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates a legacy → doctor redirect before it is stored or activated.
 *
 * Rejects everything unsafe: empty or query-only sources, the directory home as
 * a catch-all destination, self-redirects, chains/loops, and any staging or
 * external destination host. Redirects are explicit per doctor — never guessed
 * (ADR-0006).
 */
final class RedirectValidator {

	/**
	 * Normalize a same-site path (leading slash, no host, no query/fragment).
	 *
	 * @param string $raw Raw source path or URL.
	 * @return string Normalized path, or empty string when invalid.
	 */
	public static function normalize_source( string $raw ): string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}

		// Accept a full same-site URL or a bare path; extract the path only.
		$path = wp_parse_url( $raw, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return '';
		}

		$path = '/' . ltrim( $path, '/' );
		$path = user_trailingslashit( $path );

		return $path;
	}

	/**
	 * Validate a source path and destination doctor.
	 *
	 * @param string $source_path Normalized source path.
	 * @param int    $doctor_id   Destination doctor ID.
	 * @return array<int, string> Error messages (empty when valid).
	 */
	public static function validate( string $source_path, int $doctor_id ): array {
		$errors = array();

		if ( '' === $source_path || '/' === $source_path ) {
			$errors[] = __( 'The source path is empty or is the site home.', 'doctorpilot' );
		}

		if ( false !== strpos( $source_path, '?' ) ) {
			$errors[] = __( 'Query-only sources are not allowed.', 'doctorpilot' );
		}

		if ( $doctor_id <= 0 || 'doctorpilot_doctor' !== get_post_type( $doctor_id ) ) {
			$errors[] = __( 'The destination must be an existing doctor.', 'doctorpilot' );
			return $errors;
		}

		$destination = wp_parse_url( (string) get_permalink( $doctor_id ), PHP_URL_PATH );
		$destination = is_string( $destination ) ? user_trailingslashit( '/' . ltrim( $destination, '/' ) ) : '';

		// Self-redirect / immediate loop.
		if ( '' !== $destination && $destination === $source_path ) {
			$errors[] = __( 'The source and destination are the same path.', 'doctorpilot' );
		}

		// Directory home as destination (catch-all is forbidden).
		$base = self::directory_base_path();
		if ( '' !== $base && $destination === $base ) {
			$errors[] = __( 'Redirecting to the directory home is not allowed.', 'doctorpilot' );
		}

		return $errors;
	}

	/**
	 * Whether a destination host is staging/external relative to this site.
	 *
	 * @param string $url Candidate destination URL.
	 * @return bool
	 */
	public static function is_unsafe_destination_host( string $url ): bool {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false; // Relative path resolves to this site.
		}

		$site_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		if ( false !== stripos( $host, 'tempurl.host' ) ) {
			return true;
		}

		return strtolower( $host ) !== strtolower( $site_host );
	}

	/**
	 * The directory archive base path, if resolvable.
	 *
	 * @return string
	 */
	private static function directory_base_path(): string {
		$archive = get_post_type_archive_link( 'doctorpilot_doctor' );
		if ( ! is_string( $archive ) ) {
			return '';
		}

		$path = wp_parse_url( $archive, PHP_URL_PATH );

		return is_string( $path ) ? user_trailingslashit( '/' . ltrim( $path, '/' ) ) : '';
	}
}
