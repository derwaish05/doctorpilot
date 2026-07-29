<?php
/**
 * Plugin activation lifecycle.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Infrastructure\Lifecycle;

use BrainStudioz\DoctorPilot\Support\Capabilities;
use BrainStudioz\DoctorPilot\Support\RuntimeGuard;
use BrainStudioz\DoctorPilot\Admin\Settings;
use BrainStudioz\DoctorPilot\Infrastructure\Migrations\Migrator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles activation in an ordered, idempotent, data-safe way.
 *
 * Order: verify runtime, seed options, run migrations, grant capabilities,
 * schedule maintenance, and request a one-time rewrite flush. Activation never
 * deletes data and never assumes a first install.
 */
final class Activator {

	/**
	 * Option key that signals a pending one-time rewrite flush.
	 *
	 * @var string
	 */
	public const FLUSH_FLAG = 'bstdz_doctorpilot_needs_rewrite_flush';

	/**
	 * Cron hook used for bounded maintenance work.
	 *
	 * @var string
	 */
	public const MAINTENANCE_HOOK = 'bstdz_doctorpilot_daily_maintenance';

	/**
	 * Run activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Refuse to activate on an unsupported runtime with an actionable message.
		if ( ! RuntimeGuard::is_supported() ) {
			$message  = '<h1>' . esc_html__( 'DoctorPilot cannot be activated', 'doctorpilot' ) . '</h1>';
			$message .= '<ul>';
			foreach ( RuntimeGuard::unmet_requirements() as $requirement ) {
				$message .= '<li>' . esc_html( $requirement ) . '</li>';
			}
			$message .= '</ul>';

			wp_die(
				wp_kses_post( $message ),
				esc_html__( 'Plugin activation error', 'doctorpilot' ),
				array( 'back_link' => true )
			);
		}

		self::seed_versions_and_settings();

		// Run ordered, idempotent schema migrations for this and any future upgrade.
		( new Migrator() )->run();

		// Idempotently grant administrator capabilities.
		Capabilities::grant_default_roles();

		// Ensure bounded maintenance is scheduled exactly once.
		if ( ! wp_next_scheduled( self::MAINTENANCE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::MAINTENANCE_HOOK );
		}

		// Defer the rewrite flush to a normal `init` request so activation stays cheap.
		update_option( self::FLUSH_FLAG, '1', false );
	}

	/**
	 * Seed version options and default settings without overwriting existing data.
	 *
	 * @return void
	 */
	private static function seed_versions_and_settings(): void {
		update_option( 'bstdz_doctorpilot_plugin_version', DOCTORPILOT_VERSION, false );

		// Only initialise the cache generation counter if it is missing.
		if ( false === get_option( 'bstdz_doctorpilot_cache_generation', false ) ) {
			update_option( 'bstdz_doctorpilot_cache_generation', 1, true );
		}

		// Establish default settings only when none exist; never clobber saved config.
		if ( false === get_option( Settings::OPTION_KEY, false ) ) {
			update_option( Settings::OPTION_KEY, Settings::defaults(), true );
		}
	}
}
