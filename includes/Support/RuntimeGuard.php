<?php
/**
 * Runtime compatibility guard.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verifies the WordPress and PHP runtime meet DoctorPilot's minimum baseline.
 *
 * The guard never triggers a fatal error and never touches stored data; on an
 * unsupported runtime it simply reports an actionable admin notice so the site
 * owner can upgrade.
 */
final class RuntimeGuard {

	/**
	 * Whether the current runtime satisfies the minimum WordPress and PHP versions.
	 *
	 * @return bool
	 */
	public static function is_supported(): bool {
		return array() === self::unmet_requirements();
	}

	/**
	 * List the unmet runtime requirements, if any.
	 *
	 * @return array<int, string> Human-readable requirement descriptions.
	 */
	public static function unmet_requirements(): array {
		$unmet = array();

		if ( version_compare( PHP_VERSION, DOCTORPILOT_MIN_PHP, '<' ) ) {
			$unmet[] = sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'PHP %1$s or higher is required. This site is running PHP %2$s.', 'doctorpilot' ),
				DOCTORPILOT_MIN_PHP,
				PHP_VERSION
			);
		}

		$wp_version = get_bloginfo( 'version' );
		if ( version_compare( $wp_version, DOCTORPILOT_MIN_WP, '<' ) ) {
			$unmet[] = sprintf(
				/* translators: 1: required WordPress version, 2: current WordPress version. */
				__( 'WordPress %1$s or higher is required. This site is running WordPress %2$s.', 'doctorpilot' ),
				DOCTORPILOT_MIN_WP,
				$wp_version
			);
		}

		return $unmet;
	}

	/**
	 * Render the admin notice describing why DoctorPilot did not load.
	 *
	 * @return void
	 */
	public static function render_admin_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$unmet = self::unmet_requirements();
		if ( array() === $unmet ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>';
		echo esc_html__( 'DoctorPilot could not start.', 'doctorpilot' );
		echo '</strong></p><ul style="list-style:disc;margin-left:20px;">';
		foreach ( $unmet as $requirement ) {
			echo '<li>' . esc_html( $requirement ) . '</li>';
		}
		echo '</ul></div>';
	}
}
