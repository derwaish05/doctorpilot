<?php
/**
 * Aggregate analytics storage.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Analytics;

use BrainStudioz\DoctorPilot\Support\Tables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores only non-identifying daily aggregates.
 *
 * Each event increments a per-day, per-type, per-dimension counter via an atomic
 * upsert. The table has no IP, user, session, cookie, or raw user-agent column,
 * and raw event bodies are never retained (spec §14.4, §17).
 */
final class AnalyticsRepository {

	/**
	 * Increment a daily aggregate counter.
	 *
	 * @param string $event_type      search|doctor_view|symptom|no_result.
	 * @param string $dimension_label Non-identifying normalized label.
	 * @return void
	 */
	public function increment( string $event_type, string $dimension_label ): void {
		global $wpdb;

		$event_type = substr( sanitize_key( $event_type ), 0, 32 );
		$label      = substr( sanitize_text_field( $dimension_label ), 0, 190 );
		$hash       = hash( 'sha256', $event_type . '|' . $label );
		$date       = current_time( 'Y-m-d' );
		$table      = Tables::analytics();

		// Atomic upsert: insert with count 1, or bump the existing daily row.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"INSERT INTO {$table} (event_date, event_type, dimension_hash, dimension_label, count)
				 VALUES (%s, %s, %s, %s, 1)
				 ON DUPLICATE KEY UPDATE count = count + 1",
				$date,
				$event_type,
				$hash,
				$label
			)
		);
	}

	/**
	 * Top dimensions for an event type over a recent window.
	 *
	 * @param string $event_type Event type.
	 * @param int    $days       Lookback window in days.
	 * @param int    $limit      Maximum rows.
	 * @return array<int, array{label:string, total:int}>
	 */
	public function top( string $event_type, int $days = 30, int $limit = 10 ): array {
		global $wpdb;

		$table = Tables::analytics();
		$since = gmdate( 'Y-m-d', time() - ( max( 1, $days ) * DAY_IN_SECONDS ) );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT dimension_label AS label, SUM(count) AS total
				 FROM {$table}
				 WHERE event_type = %s AND event_date >= %s AND dimension_label <> ''
				 GROUP BY dimension_label
				 ORDER BY total DESC
				 LIMIT %d",
				sanitize_key( $event_type ),
				$since,
				max( 1, min( 100, $limit ) )
			),
			ARRAY_A
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'label' => (string) $row['label'],
				'total' => (int) $row['total'],
			);
		}

		return $out;
	}

	/**
	 * Delete aggregates older than a retention window.
	 *
	 * @param int $retention_days Days to keep.
	 * @return void
	 */
	public function prune( int $retention_days ): void {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d', time() - ( max( 1, $retention_days ) * DAY_IN_SECONDS ) );
		$table  = Tables::analytics();

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "DELETE FROM {$table} WHERE event_date < %s", $cutoff )
		);
	}
}
