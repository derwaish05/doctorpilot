<?php
/**
 * PSR-4 style autoloader for the DoctorPilot namespace.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal, dependency-free class autoloader.
 *
 * Maps the `BrainStudioz\DoctorPilot\` namespace prefix to the plugin's
 * `includes/` directory. Only DoctorPilot classes are handled; other
 * namespaces are ignored so we never interfere with core or third parties.
 */
final class Autoloader {

	/**
	 * Register the autoloader for a namespace prefix and base directory.
	 *
	 * @param string $prefix   Fully qualified namespace prefix, trailing separator included.
	 * @param string $base_dir Absolute path to the directory that maps to the prefix.
	 * @return void
	 */
	public static function register( string $prefix, string $base_dir ): void {
		$prefix   = ltrim( $prefix, '\\' );
		$base_dir = rtrim( $base_dir, '/\\' ) . '/';

		spl_autoload_register(
			static function ( string $class ) use ( $prefix, $base_dir ): void {
				$class = ltrim( $class, '\\' );

				// Bail early for classes outside our namespace prefix.
				if ( 0 !== strncmp( $prefix, $class, strlen( $prefix ) ) ) {
					return;
				}

				$relative = substr( $class, strlen( $prefix ) );
				$relative = str_replace( '\\', '/', $relative );
				$path     = $base_dir . $relative . '.php';

				if ( is_readable( $path ) ) {
					require_once $path;
				}
			}
		);
	}
}
