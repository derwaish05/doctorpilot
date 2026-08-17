<?php
/**
 * Import job repository.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\ImportExport;

use BrainStudioz\DoctorPilot\Support\Tables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists and updates import job rows.
 *
 * A job records the mapping, mode, progress cursor, and running counts. Source
 * file paths are never stored — only a sanitized display name — so private paths
 * do not leak into the database or logs.
 */
final class JobRepository {

	/**
	 * Create a new import job.
	 *
	 * @param string                $source_name Sanitized display name.
	 * @param array<string, mixed>  $mapping     Mapping + options.
	 * @param string                $mode        create|update|upsert.
	 * @param int                   $total_rows  Total data rows.
	 * @return int Job ID (0 on failure).
	 */
	public function create( string $source_name, array $mapping, string $mode, int $total_rows ): int {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Tables::import_jobs(),
			array(
				'job_uuid'     => wp_generate_uuid4(),
				'status'       => 'validated',
				'source_name'  => sanitize_text_field( $source_name ),
				'mapping_json' => (string) wp_json_encode( $mapping ),
				'import_mode'  => in_array( $mode, array( 'create', 'update', 'upsert' ), true ) ? $mode : 'create',
				'cursor_pos'   => 0,
				'created_by'   => get_current_user_id(),
				'total_rows'   => max( 0, $total_rows ),
				'created_at'   => $now,
				'updated_at'   => $now,
				'expires_at'   => gmdate( 'Y-m-d H:i:s', time() + ( 30 * DAY_IN_SECONDS ) ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get a job row by ID.
	 *
	 * @param int $job_id Job ID.
	 * @return array<string, mixed>|null
	 */
	public function get( int $job_id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( 'SELECT * FROM ' . Tables::import_jobs() . ' WHERE id = %d', $job_id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Update job fields.
	 *
	 * @param int                  $job_id Job ID.
	 * @param array<string, mixed> $fields Column => value.
	 * @return void
	 */
	public function update( int $job_id, array $fields ): void {
		global $wpdb;

		$fields['updated_at'] = current_time( 'mysql', true );

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Tables::import_jobs(),
			$fields,
			array( 'id' => $job_id )
		);
	}

	/**
	 * Increment running counters and advance the cursor.
	 *
	 * @param int                $job_id   Job ID.
	 * @param array<string, int> $deltas   Counter deltas (created_rows, etc.).
	 * @param int                $cursor   New cursor position.
	 * @param string             $status   New status.
	 * @return void
	 */
	public function progress( int $job_id, array $deltas, int $cursor, string $status ): void {
		$job = $this->get( $job_id );
		if ( null === $job ) {
			return;
		}

		$this->update(
			$job_id,
			array(
				'created_rows' => (int) $job['created_rows'] + (int) ( $deltas['created'] ?? 0 ),
				'updated_rows' => (int) $job['updated_rows'] + (int) ( $deltas['updated'] ?? 0 ),
				'skipped_rows' => (int) $job['skipped_rows'] + (int) ( $deltas['skipped'] ?? 0 ),
				'failed_rows'  => (int) $job['failed_rows'] + (int) ( $deltas['failed'] ?? 0 ),
				'cursor_pos'   => $cursor,
				'status'       => $status,
			)
		);
	}
}
