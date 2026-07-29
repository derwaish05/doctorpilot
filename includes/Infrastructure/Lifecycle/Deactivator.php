<?php
/**
 * Plugin deactivation lifecycle.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Infrastructure\Lifecycle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles deactivation.
 *
 * Deactivation stops scheduled work and clears rewrite rules but never deletes
 * doctors, taxonomies, settings, or capabilities. Data removal is reserved for
 * the explicit, opt-in uninstall path.
 */
final class Deactivator {

	/**
	 * Run deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Unschedule bounded maintenance so no work runs while inactive.
		$timestamp = wp_next_scheduled( Activator::MAINTENANCE_HOOK );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, Activator::MAINTENANCE_HOOK );
		}
		wp_clear_scheduled_hook( Activator::MAINTENANCE_HOOK );

		// Clear any pending flush request and rebuild rewrite rules without ours.
		delete_option( Activator::FLUSH_FLAG );
		flush_rewrite_rules();
	}
}
