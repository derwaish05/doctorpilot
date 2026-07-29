<?php
/**
 * Versioned extension registry for DoctorPilot Pro and third-party add-ons.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Guarded registration point for add-ons.
 *
 * Add-ons declare the base API version they were built against. Incompatible
 * add-ons are refused with a safe admin notice rather than a fatal error, and
 * Free continues to function on its own. Extensions can add behaviour through
 * documented hooks but can never expose private fields, bypass validation, or
 * change publication authority — those remain Free-owned invariants.
 */
final class ExtensionRegistry {

	/**
	 * Registered, compatible add-ons keyed by identifier.
	 *
	 * @var array<string, array{name: string, requires_api: int, version: string}>
	 */
	private array $extensions = array();

	/**
	 * Add-ons refused for incompatibility, retained for admin messaging.
	 *
	 * @var array<int, array{name: string, requires_api: int}>
	 */
	private array $incompatible = array();

	/**
	 * Singleton instance.
	 *
	 * @var ExtensionRegistry|null
	 */
	private static ?ExtensionRegistry $instance = null;

	/**
	 * Retrieve the shared registry instance.
	 *
	 * @return ExtensionRegistry
	 */
	public static function instance(): ExtensionRegistry {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register an add-on if it is compatible with the current base API.
	 *
	 * @param string $id           Unique add-on identifier.
	 * @param string $name         Human-readable add-on name.
	 * @param int    $requires_api Minimum base API version the add-on needs.
	 * @param string $version      Add-on version string.
	 * @return bool True when registered, false when refused as incompatible.
	 */
	public function register( string $id, string $name, int $requires_api, string $version ): bool {
		if ( $requires_api > DOCTORPILOT_BASE_API_VERSION ) {
			$this->incompatible[] = array(
				'name'         => $name,
				'requires_api' => $requires_api,
			);

			return false;
		}

		$this->extensions[ $id ] = array(
			'name'         => $name,
			'requires_api' => $requires_api,
			'version'      => $version,
		);

		return true;
	}

	/**
	 * Whether a given add-on has registered.
	 *
	 * @param string $id Add-on identifier.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->extensions[ $id ] );
	}

	/**
	 * All compatible registered add-ons.
	 *
	 * @return array<string, array{name: string, requires_api: int, version: string}>
	 */
	public function all(): array {
		return $this->extensions;
	}

	/**
	 * Add-ons refused for incompatibility.
	 *
	 * @return array<int, array{name: string, requires_api: int}>
	 */
	public function incompatible(): array {
		return $this->incompatible;
	}
}
