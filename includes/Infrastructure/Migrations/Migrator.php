<?php
/**
 * Ordered, idempotent schema migrator.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Infrastructure\Migrations;

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
		);
	}
}
