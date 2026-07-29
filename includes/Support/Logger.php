<?php
/**
 * Redacting logger implementation.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default logger that redacts sensitive context and only writes when debug
 * logging is enabled. It never persists secrets, private field values, uploaded
 * CSV rows, full request bodies, or any IP/user/session identity.
 */
final class Logger implements LoggerInterface {

	/**
	 * Context keys whose values are always redacted.
	 *
	 * @var array<int, string>
	 */
	private const REDACTED_KEYS = array(
		'password',
		'secret',
		'token',
		'nonce',
		'authorization',
		'cookie',
		'email',
		'phone',
		'ip',
		'ip_address',
		'user_agent',
		'user_id',
		'session',
		'license_number',
		'api_key',
		'csv_row',
		'request_body',
	);

	/**
	 * {@inheritDoc}
	 */
	public function error( string $code, string $message, array $context = array() ): void {
		$this->write( 'error', $code, $message, $context );
	}

	/**
	 * {@inheritDoc}
	 */
	public function warning( string $code, string $message, array $context = array() ): void {
		$this->write( 'warning', $code, $message, $context );
	}

	/**
	 * {@inheritDoc}
	 */
	public function info( string $code, string $message, array $context = array() ): void {
		$this->write( 'info', $code, $message, $context );
	}

	/**
	 * Write a redacted log line when WP_DEBUG_LOG is active.
	 *
	 * @param string               $level   Severity level.
	 * @param string               $code    Stable event code.
	 * @param string               $message Human-readable message.
	 * @param array<string, mixed> $context Structured context.
	 * @return void
	 */
	private function write( string $level, string $code, string $message, array $context ): void {
		if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return;
		}

		$safe = $this->redact( $context );
		$line = sprintf(
			'[DoctorPilot][%s][%s] %s %s',
			strtoupper( $level ),
			sanitize_key( $code ),
			$message,
			array() === $safe ? '' : (string) wp_json_encode( $safe )
		);

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Guarded by WP_DEBUG.
		error_log( $line );
	}

	/**
	 * Recursively redact sensitive keys from a context array.
	 *
	 * @param array<string, mixed> $context Context to redact.
	 * @return array<string, mixed>
	 */
	private function redact( array $context ): array {
		$clean = array();

		foreach ( $context as $key => $value ) {
			$normalized = strtolower( (string) $key );

			if ( in_array( $normalized, self::REDACTED_KEYS, true ) ) {
				$clean[ $key ] = '[redacted]';
				continue;
			}

			if ( is_array( $value ) ) {
				$clean[ $key ] = $this->redact( $value );
				continue;
			}

			// Only scalar, non-identifying values are retained.
			$clean[ $key ] = is_scalar( $value ) ? $value : '[object]';
		}

		return $clean;
	}
}
