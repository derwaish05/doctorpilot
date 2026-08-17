<?php
/**
 * Retention cleanup for migration data.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Operations;

use BrainStudioz\DoctorPilot\Infrastructure\Lifecycle\Activator;
use BrainStudioz\DoctorPilot\Support\Tables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prunes expired import jobs and their items in bounded batches.
 *
 * Runs on a daily cron. Deletes only expired job logs; it never touches doctor
 * records, redirects, or any published content.
 */
final class RetentionJob {

	/**
	 * Register the cron handler.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( Activator::RETENTION_HOOK, array( $this, 'run' ) );
	}

	/**
	 * Delete expired jobs and orphaned items.
	 *
	 * @return void
	 */
	public function run(): void {
		global $wpdb;

		$now  = current_time( 'mysql', true );
		$jobs = Tables::import_jobs();
		$items = Tables::import_items();

		// Find a bounded set of expired jobs.
		$expired = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT id FROM {$jobs} WHERE expires_at IS NOT NULL AND expires_at < %s LIMIT 50", $now )
		);

		foreach ( array_map( 'intval', (array) $expired ) as $job_id ) {
			$wpdb->delete( $items, array( 'job_id' => $job_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $jobs, array( 'id' => $job_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		// Prune analytics aggregates beyond the configured retention window.
		$settings  = get_option( 'bstdz_doctorpilot_settings', array() );
		$retention = is_array( $settings ) && isset( $settings['privacy']['analytics_retention_days'] )
			? (int) $settings['privacy']['analytics_retention_days']
			: 90;

		( new \BrainStudioz\DoctorPilot\Analytics\AnalyticsRepository() )->prune( $retention );
	}
}
