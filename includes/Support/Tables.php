<?php
/**
 * Pro database table names and schema.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central definition of the plugin-owned database tables (spec §14).
 */
final class Tables {

	/**
	 * Full table name for redirects.
	 *
	 * @return string
	 */
	public static function redirects(): string {
		global $wpdb;

		return $wpdb->prefix . 'bstdz_doctorpilot_redirects';
	}

	/**
	 * Full table name for import jobs.
	 *
	 * @return string
	 */
	public static function import_jobs(): string {
		global $wpdb;

		return $wpdb->prefix . 'bstdz_doctorpilot_import_jobs';
	}

	/**
	 * Full table name for import items.
	 *
	 * @return string
	 */
	public static function import_items(): string {
		global $wpdb;

		return $wpdb->prefix . 'bstdz_doctorpilot_import_items';
	}

	/**
	 * Full table name for daily analytics aggregates.
	 *
	 * @return string
	 */
	public static function analytics(): string {
		global $wpdb;

		return $wpdb->prefix . 'bstdz_doctorpilot_analytics_daily';
	}

	/**
	 * All Pro table names.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array( self::redirects(), self::import_jobs(), self::import_items(), self::analytics() );
	}

	/**
	 * Create or update the plugin tables via dbDelta.
	 *
	 * @return void
	 */
	public static function create(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$redirects       = self::redirects();
		$jobs            = self::import_jobs();
		$items           = self::import_items();

		dbDelta(
			"CREATE TABLE {$redirects} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				source_path varchar(255) NOT NULL,
				doctor_id bigint(20) unsigned NOT NULL DEFAULT 0,
				destination_path varchar(255) NOT NULL DEFAULT '',
				status_code smallint(6) NOT NULL DEFAULT 301,
				is_active tinyint(1) NOT NULL DEFAULT 1,
				created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
				updated_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY source_path (source_path),
				KEY doctor_id (doctor_id),
				KEY active_source (is_active, source_path)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$jobs} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				job_uuid char(36) NOT NULL,
				status varchar(24) NOT NULL DEFAULT 'uploaded',
				source_name varchar(190) NOT NULL DEFAULT '',
				mapping_json longtext NULL,
				import_mode varchar(20) NOT NULL DEFAULT 'create',
				cursor_pos bigint(20) unsigned NOT NULL DEFAULT 0,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				total_rows bigint(20) unsigned NOT NULL DEFAULT 0,
				created_rows bigint(20) unsigned NOT NULL DEFAULT 0,
				updated_rows bigint(20) unsigned NOT NULL DEFAULT 0,
				skipped_rows bigint(20) unsigned NOT NULL DEFAULT 0,
				failed_rows bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
				updated_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
				expires_at datetime NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY job_uuid (job_uuid),
				KEY status (status)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$items} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				job_id bigint(20) unsigned NOT NULL,
				row_number bigint(20) unsigned NOT NULL DEFAULT 0,
				source_key varchar(190) NOT NULL DEFAULT '',
				row_checksum char(64) NOT NULL DEFAULT '',
				doctor_id bigint(20) unsigned NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				error_code varchar(100) NULL,
				error_message text NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY job_row (job_id, row_number),
				KEY job_status (job_id, status),
				KEY source_key (source_key)
			) {$charset_collate};"
		);

		$analytics = self::analytics();

		// Aggregate-only: no IP, user, session, cookie, or raw user-agent columns.
		dbDelta(
			"CREATE TABLE {$analytics} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_date date NOT NULL,
				event_type varchar(32) NOT NULL DEFAULT '',
				dimension_hash char(64) NOT NULL DEFAULT '',
				dimension_label varchar(190) NOT NULL DEFAULT '',
				count bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY event_dimension (event_date, event_type, dimension_hash),
				KEY event_date (event_date)
			) {$charset_collate};"
		);
	}

	/**
	 * Drop the Pro tables. Reserved for the explicit uninstall path.
	 *
	 * @return void
	 */
	public static function drop(): void {
		global $wpdb;

		foreach ( self::all() as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
	}
}
