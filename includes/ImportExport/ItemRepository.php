<?php
/**
 * Import item repository.
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
 * Persists per-row import outcomes for idempotency and reporting.
 *
 * Stores only a source key, a content checksum, the resulting doctor ID, and a
 * safe status/error code — never the full raw row or any private field values.
 */
final class ItemRepository {

	/**
	 * Record (or update) an item outcome, keyed uniquely by job + row number.
	 *
	 * @param int         $job_id     Job ID.
	 * @param int         $row_number Row index.
	 * @param string      $source_key Source key.
	 * @param string      $checksum   Row checksum.
	 * @param int|null    $doctor_id  Resulting doctor ID.
	 * @param string      $status     created|updated|skipped|failed.
	 * @param string|null $error_code Stable error code.
	 * @param string|null $error_msg  Safe error message (no private data).
	 * @return void
	 */
	public function record( int $job_id, int $row_number, string $source_key, string $checksum, ?int $doctor_id, string $status, ?string $error_code = null, ?string $error_msg = null ): void {
		global $wpdb;

		$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( 'SELECT id FROM ' . Tables::import_items() . ' WHERE job_id = %d AND row_number = %d', $job_id, $row_number )
		);

		$data = array(
			'job_id'        => $job_id,
			'row_number'    => $row_number,
			'source_key'    => substr( sanitize_text_field( $source_key ), 0, 190 ),
			'row_checksum'  => $checksum,
			'doctor_id'     => $doctor_id,
			'status'        => $status,
			'error_code'    => $error_code,
			'error_message' => null === $error_msg ? null : sanitize_text_field( $error_msg ),
		);

		if ( null !== $existing ) {
			$wpdb->update( Tables::import_items(), $data, array( 'id' => (int) $existing ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$wpdb->insert( Tables::import_items(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Get the doctor IDs created by a job (for rollback).
	 *
	 * @param int $job_id Job ID.
	 * @return array<int, int>
	 */
	public function created_doctor_ids( int $job_id ): array {
		global $wpdb;

		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				'SELECT doctor_id FROM ' . Tables::import_items() . " WHERE job_id = %d AND status = 'created' AND doctor_id IS NOT NULL",
				$job_id
			)
		);

		return array_map( 'intval', (array) $ids );
	}
}
