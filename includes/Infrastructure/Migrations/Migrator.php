<?php
/**
 * Ordered, idempotent schema migrator.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Infrastructure\Migrations;

use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;
use BrainStudioz\DoctorPilot\Support\Capabilities;

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
		);
	}
}
