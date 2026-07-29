<?php
/**
 * Elementor compatibility checks.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects whether a supported Elementor (and optionally Elementor Pro) is
 * active before any Elementor class is referenced.
 *
 * DoctorPilot never hard-depends on Elementor: when it is absent or too old the
 * plugin keeps rendering native templates and shortcodes, and the integration
 * simply does not load.
 */
final class Compatibility {

	/**
	 * Minimum supported Elementor version.
	 *
	 * @var string
	 */
	public const MIN_ELEMENTOR = '3.5.0';

	/**
	 * Minimum supported Elementor Pro version (for Pro-only features).
	 *
	 * @var string
	 */
	public const MIN_ELEMENTOR_PRO = '3.5.0';

	/**
	 * Whether Elementor is active at all.
	 *
	 * @return bool
	 */
	public static function is_elementor_active(): bool {
		return did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' );
	}

	/**
	 * Whether an Elementor version DoctorPilot supports is active.
	 *
	 * @return bool
	 */
	public static function is_supported(): bool {
		if ( ! self::is_elementor_active() || ! defined( 'ELEMENTOR_VERSION' ) ) {
			return false;
		}

		return version_compare( ELEMENTOR_VERSION, self::MIN_ELEMENTOR, '>=' );
	}

	/**
	 * Whether a supported Elementor Pro is active (Pro features only).
	 *
	 * @return bool
	 */
	public static function is_pro_supported(): bool {
		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			return false;
		}

		return version_compare( ELEMENTOR_PRO_VERSION, self::MIN_ELEMENTOR_PRO, '>=' );
	}

	/**
	 * Whether Elementor is active but below the supported version.
	 *
	 * @return bool
	 */
	public static function is_outdated(): bool {
		return self::is_elementor_active() && ! self::is_supported();
	}
}
