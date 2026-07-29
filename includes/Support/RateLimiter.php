<?php
/**
 * Lightweight abuse rate limiter.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A short-window request counter for public endpoints.
 *
 * This is an abuse control, not authentication. Windows are short so a shared
 * hospital or campus IP is never permanently blocked; a burst simply waits for
 * the next window. No IP address is stored in clear — only a salted hash.
 */
final class RateLimiter {

	/**
	 * Whether a bucket is currently within its allowed request budget.
	 *
	 * Increments the counter as a side effect.
	 *
	 * @param string $bucket    Logical bucket name (e.g. search, suggest).
	 * @param int    $max        Maximum requests per window.
	 * @param int    $window_sec Window length in seconds.
	 * @return bool True when allowed, false when the budget is exceeded.
	 */
	public static function allow( string $bucket, int $max = 60, int $window_sec = 60 ): bool {
		$key   = self::key( $bucket );
		$count = (int) get_transient( $key );

		if ( $count >= max( 1, $max ) ) {
			return false;
		}

		set_transient( $key, $count + 1, max( 1, $window_sec ) );

		return true;
	}

	/**
	 * Build a salted, non-identifying cache key for the current client.
	 *
	 * @param string $bucket Bucket name.
	 * @return string
	 */
	private static function key( string $bucket ): string {
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$hash = hash( 'sha256', $bucket . '|' . $ip . '|' . wp_salt() );

		return 'dp_rl_' . substr( $hash, 0, 24 );
	}
}
