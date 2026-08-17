<?php
/**
 * Redirect storage.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Redirects;

use BrainStudioz\DoctorPilot\Support\Tables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes redirect rows.
 *
 * Lookups are by exact normalized source path against a unique index — never a
 * scan — so resolution stays fast even with many redirects.
 */
final class RedirectRepository {

	/**
	 * Upsert a redirect by source path.
	 *
	 * @param string $source_path      Normalized source path.
	 * @param int    $doctor_id        Destination doctor ID.
	 * @param string $destination_path Reviewed destination path.
	 * @param bool   $active           Whether the redirect is active.
	 * @return bool
	 */
	public function save( string $source_path, int $doctor_id, string $destination_path, bool $active ): bool {
		global $wpdb;

		$now      = current_time( 'mysql', true );
		$existing = $this->find( $source_path );

		$data = array(
			'source_path'      => $source_path,
			'doctor_id'        => $doctor_id,
			'destination_path' => $destination_path,
			'status_code'      => 301,
			'is_active'        => $active ? 1 : 0,
			'updated_at'       => $now,
		);

		if ( null !== $existing ) {
			return false !== $wpdb->update( Tables::redirects(), $data, array( 'id' => (int) $existing['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		$data['created_at'] = $now;

		return false !== $wpdb->insert( Tables::redirects(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Find a redirect by exact source path.
	 *
	 * @param string $source_path Normalized source path.
	 * @return array<string, mixed>|null
	 */
	public function find( string $source_path ): ?array {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( 'SELECT * FROM ' . Tables::redirects() . ' WHERE source_path = %s', $source_path ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Find an active redirect for a source path.
	 *
	 * @param string $source_path Normalized source path.
	 * @return array<string, mixed>|null
	 */
	public function find_active( string $source_path ): ?array {
		$row = $this->find( $source_path );

		return ( null !== $row && (int) $row['is_active'] === 1 ) ? $row : null;
	}

	/**
	 * List redirects (bounded).
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function all( int $limit = 200 ): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( 'SELECT * FROM ' . Tables::redirects() . ' ORDER BY updated_at DESC LIMIT %d', max( 1, min( 1000, $limit ) ) ),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete redirects that point at a given doctor.
	 *
	 * @param int $doctor_id Doctor ID.
	 * @return void
	 */
	public function delete_for_doctor( int $doctor_id ): void {
		global $wpdb;

		$wpdb->delete( Tables::redirects(), array( 'doctor_id' => $doctor_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
