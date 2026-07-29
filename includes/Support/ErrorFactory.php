<?php
/**
 * Stable error factory.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Produces WP_Error objects with stable codes and a request correlation ID.
 *
 * Error data never exposes SQL, filesystem paths, secrets, or stack traces. The
 * request ID lets support correlate a user-facing error with a redacted log
 * entry without leaking anything sensitive.
 */
final class ErrorFactory {

	/**
	 * Stable, machine-readable error codes (aligned with spec §7.3).
	 */
	public const INVALID_PARAMETER   = 'doctorpilot_invalid_parameter';
	public const INVALID_FILTER      = 'doctorpilot_invalid_filter';
	public const NOT_FOUND           = 'doctorpilot_not_found';
	public const FORBIDDEN           = 'doctorpilot_forbidden';
	public const RATE_LIMITED        = 'doctorpilot_rate_limited';
	public const PROVIDER_UNAVAILABLE = 'doctorpilot_provider_unavailable';
	public const INTERNAL            = 'doctorpilot_internal_error';

	/**
	 * Build a WP_Error with a stable code, HTTP status, and request ID.
	 *
	 * @param string               $code    One of the stable code constants.
	 * @param string               $message Safe, human-readable message.
	 * @param int                  $status  HTTP status code.
	 * @param array<string, mixed> $details Non-sensitive, structured details.
	 * @return \WP_Error
	 */
	public static function create( string $code, string $message, int $status = 400, array $details = array() ): \WP_Error {
		return new \WP_Error(
			$code,
			$message,
			array(
				'status'     => $status,
				'details'    => $details,
				'request_id' => self::request_id(),
			)
		);
	}

	/**
	 * Generate a short, non-identifying request correlation ID.
	 *
	 * @return string
	 */
	public static function request_id(): string {
		return 'dp_' . substr( md5( uniqid( '', true ) ), 0, 16 );
	}
}
