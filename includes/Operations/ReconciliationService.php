<?php
/**
 * Migration reconciliation counts.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Operations;

use BrainStudioz\DoctorPilot\Support\Tables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reconciles source, imported, published, and redirect counts.
 *
 * Gives operators a single view to confirm a migration is complete before
 * launch: how many doctors were imported, how many are published, and how many
 * active redirects exist.
 */
final class ReconciliationService {

	/**
	 * Build the reconciliation summary.
	 *
	 * @return array<string, int>
	 */
	public function summary(): array {
		global $wpdb;

		$imported = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				'_doctorpilot_external_source_id'
			)
		);

		$counts = wp_count_posts( 'doctorpilot_doctor' );

		$active_redirects = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'SELECT COUNT(*) FROM ' . Tables::redirects() . ' WHERE is_active = 1'
		);

		$source_rows = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'SELECT COALESCE(SUM(total_rows),0) FROM ' . Tables::import_jobs()
		);

		return array(
			'source_rows'      => $source_rows,
			'imported'         => $imported,
			'published'        => isset( $counts->publish ) ? (int) $counts->publish : 0,
			'drafts'           => isset( $counts->draft ) ? (int) $counts->draft : 0,
			'pending'          => isset( $counts->pending ) ? (int) $counts->pending : 0,
			'active_redirects' => $active_redirects,
		);
	}
}
