<?php
/**
 * Logger contract.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal PSR-3-inspired logger contract used across DoctorPilot.
 *
 * Implementations must redact secrets, nonces, private field values, uploaded
 * rows, full request bodies, and any IP/user/session identity before writing.
 */
interface LoggerInterface {

	/**
	 * Log an error-level event.
	 *
	 * @param string               $code    Stable, machine-readable event code.
	 * @param string               $message Human-readable message (no private data).
	 * @param array<string, mixed> $context Structured, redactable context.
	 * @return void
	 */
	public function error( string $code, string $message, array $context = array() ): void;

	/**
	 * Log a warning-level event.
	 *
	 * @param string               $code    Stable, machine-readable event code.
	 * @param string               $message Human-readable message (no private data).
	 * @param array<string, mixed> $context Structured, redactable context.
	 * @return void
	 */
	public function warning( string $code, string $message, array $context = array() ): void;

	/**
	 * Log an info-level event.
	 *
	 * @param string               $code    Stable, machine-readable event code.
	 * @param string               $message Human-readable message (no private data).
	 * @param array<string, mixed> $context Structured, redactable context.
	 * @return void
	 */
	public function info( string $code, string $message, array $context = array() ): void;
}
