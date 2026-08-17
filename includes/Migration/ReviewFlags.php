<?php
/**
 * Editorial review flags for migrated content.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Migration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects content that needs human review before publishing.
 *
 * Flags are heuristics that quarantine suspicious rows; they never rewrite,
 * infer, or auto-correct content. Everything flagged stays as draft / needs
 * review until a person approves it (ADR-0007).
 */
final class ReviewFlags {

	/**
	 * Inspect a mapped row and return review flags.
	 *
	 * @param array<string, string> $row       Mapped values (target => value).
	 * @param string                $site_host The production site host, for staging detection.
	 * @return array<int, string> Flag messages (empty when nothing suspicious).
	 */
	public static function inspect( array $row, string $site_host = '' ): array {
		$flags = array();

		$name = isset( $row['name'] ) ? (string) $row['name'] : '';
		$blob = strtolower( implode( ' ', array_map( 'strval', $row ) ) );

		// Wrong-doctor content: a different doctor's name appears in the body.
		if ( '' !== $name ) {
			$name_lc = strtolower( $name );
			if ( preg_match_all( '/\bdr\.?\s+([a-z]+)/i', $blob, $matches ) ) {
				foreach ( $matches[1] as $surname ) {
					if ( '' !== $surname && false === strpos( $name_lc, strtolower( $surname ) ) ) {
						$flags[] = sprintf( 'Possible wrong-doctor reference: "Dr. %s" appears but does not match the profile name.', $surname );
						break;
					}
				}
			}
		}

		// Placeholder counters such as "1+".
		if ( preg_match( '/\b\d+\+/', $blob ) ) {
			$flags[] = 'Placeholder-style counter (e.g. "1+") detected; verify or remove.';
		}

		// Testimonials/quotes that may belong to another profile.
		if ( false !== strpos( $blob, 'testimonial' ) || preg_match( '/[“"][^”"]{20,}[”"]/u', $blob ) ) {
			$flags[] = 'Testimonial/quoted content detected; confirm it belongs to this doctor.';
		}

		// Staging host leaked into a URL.
		if ( preg_match( '#https?://[^\s]*tempurl\.host#i', $blob ) ) {
			$flags[] = 'Staging-domain URL detected; replace with the production domain before launch.';
		}
		if ( '' !== $site_host && false !== strpos( $blob, 'tempurl' ) ) {
			$flags[] = 'A temporary/staging reference was found; verify against the production host ' . $site_host . '.';
		}

		// Missing name entirely.
		if ( '' === trim( $name ) ) {
			$flags[] = 'Missing full name; row cannot be published without one.';
		}

		return $flags;
	}
}
