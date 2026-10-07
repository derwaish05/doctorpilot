<?php
/**
 * Ordered, idempotent schema migrator.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Infrastructure\Migrations;

use BrainStudioz\DoctorPilot\Admin\Settings;
use BrainStudioz\DoctorPilot\Infrastructure\Cache\CacheGeneration;
use BrainStudioz\DoctorPilot\Infrastructure\Lifecycle\Activator;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;
use BrainStudioz\DoctorPilot\Support\Capabilities;
use BrainStudioz\DoctorPilot\Support\Roles;
use BrainStudioz\DoctorPilot\Support\Tables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs ordered, idempotent schema migrations up to DOCTORPILOT_SCHEMA_VERSION.
 *
 * Each migration is keyed by the target schema version and applied only once.
 * Migrations must be safe to re-run and safe for existing data. Foundation
 * milestone ships version 1 as a baseline; feature milestones append entries.
 */
final class Migrator {

	/**
	 * Option storing the applied schema version.
	 *
	 * @var string
	 */
	private const VERSION_OPTION = 'bstdz_doctorpilot_schema_version';

	/**
	 * Run all pending migrations in order.
	 *
	 * @return void
	 */
	public function run(): void {
		$current = (int) get_option( self::VERSION_OPTION, 0 );
		$target  = (int) DOCTORPILOT_SCHEMA_VERSION;

		if ( $current >= $target ) {
			return;
		}

		foreach ( $this->migrations() as $version => $migration ) {
			if ( $version <= $current ) {
				continue;
			}

			// Each migration is a no-throw callable that leaves data consistent.
			$migration();

			update_option( self::VERSION_OPTION, $version, true );
			$current = $version;
		}
	}

	/**
	 * The ordered map of schema-version => migration callable.
	 *
	 * @return array<int, callable>
	 */
	private function migrations(): array {
		return array(
			// Version 1: establish the baseline. No structural change beyond the
			// options seeded at activation; present so upgrades have a floor.
			1 => static function (): void {
				if ( false === get_option( 'bstdz_doctorpilot_cache_generation', false ) ) {
					update_option( 'bstdz_doctorpilot_cache_generation', 1, true );
				}
			},

			// Version 2: global content library for Experience & Education
			// (ADR-0017). Grant the new library capability and split any legacy
			// combined `_doctorpilot_education_experience` rows into the two
			// doctor-specific local repeaters. Safe to re-run: the legacy key is
			// removed once copied, so a second pass finds nothing to migrate.
			2 => static function (): void {
				Capabilities::grant_default_roles();

				$doctors = get_posts(
					array(
						'post_type'      => DoctorPostType::POST_TYPE,
						'post_status'    => 'any',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'no_found_rows'  => true,
						// Only doctors that still hold the legacy key.
						'meta_key'       => '_doctorpilot_education_experience', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					)
				);

				$edu_types = array( 'education', 'fellowship', 'residency', 'training', 'degree', 'qualification' );

				foreach ( $doctors as $doctor_id ) {
					$rows = get_post_meta( $doctor_id, '_doctorpilot_education_experience', true );
					if ( ! is_array( $rows ) ) {
						delete_post_meta( $doctor_id, '_doctorpilot_education_experience' );
						continue;
					}

					$exp = get_post_meta( $doctor_id, '_doctorpilot_exp_local', true );
					$exp = is_array( $exp ) ? $exp : array();
					$edu = get_post_meta( $doctor_id, '_doctorpilot_edu_local', true );
					$edu = is_array( $edu ) ? $edu : array();

					foreach ( $rows as $row ) {
						if ( ! is_array( $row ) ) {
							continue;
						}

						$entry = array(
							'primary'     => (string) ( $row['qualification_or_position'] ?? '' ),
							'secondary'   => (string) ( $row['institution'] ?? '' ),
							'start_year'  => (string) ( $row['start_year'] ?? '' ),
							'end_year'    => (string) ( $row['end_year'] ?? '' ),
							'country'     => (string) ( $row['country'] ?? '' ),
							'description' => (string) ( $row['description'] ?? '' ),
						);

						$type = strtolower( (string) ( $row['type'] ?? '' ) );
						if ( in_array( $type, $edu_types, true ) ) {
							$edu[] = $entry;
						} else {
							$exp[] = $entry;
						}
					}

					update_post_meta( $doctor_id, '_doctorpilot_exp_local', $exp );
					update_post_meta( $doctor_id, '_doctorpilot_edu_local', $edu );
					delete_post_meta( $doctor_id, '_doctorpilot_education_experience' );
				}
			},

			// Version 3: adopt everything the Pro add-on used to own, now that
			// it is part of this plugin.
			//
			// A site upgrading from the two-plugin layout already has these
			// tables, roles and schedules — the add-on created them on its own
			// activation, and merging the code does not re-run any activation
			// hook. Every step below is therefore written to be a no-op on that
			// site and a first install on a site that never had the add-on:
			// `dbDelta` adds only what is missing, `Roles::install()` and
			// `Capabilities::grant_default_roles()` are both idempotent grants,
			// and the schedules are only created when nothing is scheduled.
			//
			// The table, option, role and cron-hook names are deliberately
			// unchanged from the add-on. Renaming any of them here would strand
			// live rows and stop in-flight imports for exactly the sites this
			// migration exists to carry across.
			3 => static function (): void {
				Tables::create();
				Roles::install();
				Capabilities::grant_default_roles();

				if ( false === get_option( 'bstdz_doctorpilot_pro_db_version', false ) ) {
					update_option( 'bstdz_doctorpilot_pro_db_version', 2, false );
				}

				if ( ! wp_next_scheduled( Activator::RETENTION_HOOK ) ) {
					wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', Activator::RETENTION_HOOK );
				}
			},

			// Version 4: the BookPilot provider is gone, and the site now
			// carries booking defaults that every doctor can inherit.
			//
			// Two corrections, both there to stop a booking button changing
			// behaviour behind the client's back:
			//
			// 1. Doctors still set to `bookpilot` move to the site default
			//    method. Their stored method names a provider that is no longer
			//    registered; `BookingResolver` already falls back to the site
			//    default at render time, so this changes no output — it makes
			//    the stored value honest, so the Booking tab shows the method
			//    actually in use.
			//
			// 2. `booking.default_target` is corrected to `_blank`. The key has
			//    sat in the settings schema since v1 defaulting to `_self`, but
			//    nothing read it and no screen showed it, while
			//    `ExternalUrlProvider` hard-coded `_blank`. Now that the setting
			//    is honoured, leaving `_self` stored would quietly move every
			//    external booking link out of a new tab.
			//
			// Re-running is safe: afterwards no doctor holds `bookpilot`, and the
			// settings half is gated on a key this release introduces, so it
			// cannot fire a second time — not even if the schema version is
			// rolled back by a database restore.
			4 => static function (): void {
				$settings = get_option( Settings::OPTION_KEY, array() );
				$settings = is_array( $settings ) ? $settings : array();
				$booking  = is_array( $settings['booking'] ?? null ) ? $settings['booking'] : array();

				// `default_url` is introduced by this very release, so its absence
				// is proof the group was last written by the old schema and no
				// admin has ever seen these controls. Testing the old values
				// instead would also match a site that deliberately picks
				// "same tab" later and re-fire on any schema-version rollback.
				$never_seen = ! array_key_exists( 'default_url', $booking );

				if ( $never_seen ) {
					if ( '_self' === ( $booking['default_target'] ?? '' ) ) {
						$settings['booking']['default_target'] = '_blank';
					}

					// Same reasoning as the target: the old default was the literal
					// "Book Appointment", which under the new label rules would
					// override "Call to book" and "Request an appointment".
					if ( 'Book Appointment' === ( $booking['default_label'] ?? '' ) ) {
						$settings['booking']['default_label'] = '';
					}

					// The shipped site default is now the enquiry form. Only the
					// old unexposed value is moved; anything else was chosen.
					if ( 'external_url' === ( $booking['default_type'] ?? '' ) ) {
						$settings['booking']['default_type'] = 'enquiry';
					}

					// Marks the group as migrated, so this never runs twice.
					$settings['booking']['default_url']   = (string) ( $booking['default_url'] ?? '' );
					$settings['booking']['default_phone'] = (string) ( $booking['default_phone'] ?? '' );

					// NOTE: `Settings::register()` runs at boot, not on a hook, so
					// this write passes through `Settings::sanitize()`. That is
					// lossless for a well-formed option today; keep it that way.
					update_option( Settings::OPTION_KEY, $settings, true );
				}

				// Read the method AFTER the correction above, so a doctor moved off
				// `bookpilot` lands on the value the site actually ends up with
				// rather than the one it held a few lines earlier.
				$default_method = (string) ( $settings['booking']['default_type'] ?? 'enquiry' );
				if ( ! in_array( $default_method, array( 'external_url', 'phone', 'enquiry' ), true ) ) {
					$default_method = 'enquiry';
				}

				$doctors = get_posts(
					array(
						'post_type'      => DoctorPostType::POST_TYPE,
						'post_status'    => 'any',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'no_found_rows'  => true,
						'meta_key'       => '_doctorpilot_booking_method', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_value'     => 'bookpilot', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					)
				);

				foreach ( $doctors as $doctor_id ) {
					update_post_meta( $doctor_id, '_doctorpilot_booking_method', $default_method );
				}
			},

			// Version 5: the Credentials field is removed (ADR-0024). The editor
			// row, the card line, the profile line, the dynamic tag, the
			// completeness check and the import/export column are all gone, so the
			// stored values are unreachable — and the removal was asked for as a
			// removal, not as hiding.
			//
			// This migration is deliberately destructive: `_doctorpilot_credentials`
			// is deleted, and the strings are not recoverable afterwards.
			//
			// Deleting the meta is not enough on its own. The credentials text was
			// copied into `_doctorpilot_search_document` and into the `keywords`
			// bucket of `_doctorpilot_search_index` when the doctor was last saved,
			// and both are read directly by `CandidateRepository`. Left alone they
			// would keep matching searches for a qualification the site no longer
			// stores. The recompute is handed to `CredentialsReindexQueue` rather
			// than done here — see that class for why doing it inline would erase
			// every affected doctor's taxonomy terms from the index.
			//
			// The rows are found and deleted through `$wpdb` rather than
			// `get_posts()` on purpose. `_doctorpilot_credentials` was declared
			// `action="translate"` in `wpml-config.xml`, so on a WPML site every
			// translation carries its own copy — and WPML scopes queries through
			// `pre_get_posts`, which `suppress_filters` does not disable. A scoped
			// query would delete the active language's rows, bump the schema
			// version, and strand the other languages' copies for good. A direct
			// delete sees every row, in every language, and costs two queries
			// instead of one `WP_Query` plus a meta write per doctor.
			//
			// Re-running is safe: the second pass finds no rows, so it deletes
			// nothing and queues nothing.
			5 => static function (): void {
				global $wpdb;

				$doctors = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
						'_doctorpilot_credentials'
					)
				);

				$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_doctorpilot_credentials' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery.slow_db_query_meta_key

				foreach ( (array) $doctors as $doctor_id ) {
					clean_post_cache( (int) $doctor_id );
				}

				CredentialsReindexQueue::seed( array_map( 'intval', (array) $doctors ) );

				// Bumped unconditionally, and outside the "did we delete anything"
				// question. A pass that deletes the rows but dies before the version
				// option is written re-enters this migration on the next request and
				// finds nothing left to delete; gating the bump on a non-empty result
				// would mean that run — the one that finally records version 5 —
				// leaves every cached response built from the old search documents
				// live. One option write on a site that never had the field is a far
				// cheaper mistake than a cache that never retires.
				( new CacheGeneration() )->bump();
			},
		);
	}
}
