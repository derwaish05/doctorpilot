<?php
/**
 * Deferred, bounded reindex of the doctors that migration 5 stripped.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Infrastructure\Migrations;

use BrainStudioz\DoctorPilot\Application\Search\SearchIndexBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rebuilds the search index for doctors whose Credentials meta was deleted.
 *
 * Migration 5 deletes `_doctorpilot_credentials`, but the text had already been
 * copied into `_doctorpilot_search_document` and into the `keywords` bucket of
 * `_doctorpilot_search_index`, both of which `CandidateRepository` reads
 * directly. Deleting the source without recomputing the copies would leave a
 * removed qualification matching searches indefinitely.
 *
 * The rebuild cannot happen inside the migration, for two independent reasons:
 *
 * 1. **Taxonomies are not registered yet.** `Plugin::maybe_upgrade()` runs on
 *    `init` priority 1; `DoctorPostType` registers at 5 and `Taxonomies` at 6.
 *    `SearchIndexBuilder::rebuild()` resolves six taxonomies through
 *    `wp_get_object_terms()`, which returns `WP_Error( 'invalid_taxonomy' )`
 *    for an unregistered taxonomy — and `term_names()` maps that error to an
 *    empty array. Rebuilding at that point would therefore overwrite every
 *    affected doctor's index with name and excerpt only, silently erasing their
 *    department, specialty, symptom, condition, treatment and language terms
 *    from search, permanently: the schema version is bumped either way, so
 *    nothing would ever recompute them.
 * 2. **Unbounded work in one request.** A rebuild is six term queries and three
 *    meta writes per doctor. A hospital with a few thousand doctors would spend
 *    tens of thousands of queries inside the single request that happens to be
 *    first through the door after an upgrade.
 *
 * So the migration records the IDs and this queue drains them in batches, on
 * `shutdown` at priority 20 — after taxonomies are registered, and after
 * `SaveDoctor::flush_pending()` (priority 5) has rebuilt anything the request
 * itself changed, so this pass can only ever be redundant, never stale.
 */
final class CredentialsReindexQueue {

	/**
	 * Option holding the doctor IDs still to reindex.
	 *
	 * Autoloaded, deliberately. It exists for a handful of requests after one
	 * upgrade and is deleted the moment the queue empties, so while it matters it
	 * rides along with `alloptions` and `register()` costs nothing; once it is
	 * gone there is nothing left to load. The alternative — autoload off — would
	 * make the same check a separate query on every request, forever, on any site
	 * without a persistent object cache.
	 *
	 * @var string
	 */
	public const OPTION = 'bstdz_doctorpilot_credentials_reindex';

	/**
	 * Doctors reindexed per request.
	 *
	 * Bounded so the work is spread over requests rather than concentrated in
	 * the one that trips the upgrade.
	 *
	 * @var int
	 */
	private const BATCH = 100;

	/**
	 * Record the doctors to reindex and drain the first batch this request.
	 *
	 * @param array<int, int> $ids Doctor post IDs.
	 * @return void
	 */
	public static function seed( array $ids ): void {
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );

		if ( array() === $ids ) {
			return;
		}

		update_option( self::OPTION, $ids, true );

		// `boot()` has already run for this request, so nothing would have
		// hooked the drain; hook it here so the first batch goes out now
		// rather than waiting for the next page view.
		( new self() )->register();
	}

	/**
	 * Hook the drain, but only while there is something queued.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( false === get_option( self::OPTION, false ) ) {
			return;
		}

		if ( ! has_action( 'shutdown', array( $this, 'drain' ) ) ) {
			add_action( 'shutdown', array( $this, 'drain' ), 20 );
		}
	}

	/**
	 * Reindex up to one batch, then put the remainder back.
	 *
	 * @return void
	 */
	public function drain(): void {
		$ids = get_option( self::OPTION, false );

		if ( ! is_array( $ids ) || array() === $ids ) {
			delete_option( self::OPTION );

			return;
		}

		$batch = array_slice( $ids, 0, self::BATCH );
		$rest  = array_slice( $ids, self::BATCH );

		// The remainder is written back *before* the work, so a request that
		// dies mid-batch loses at most one batch rather than repeating the
		// whole queue on every subsequent request.
		if ( array() === $rest ) {
			delete_option( self::OPTION );
		} else {
			update_option( self::OPTION, $rest, true );
		}

		foreach ( $batch as $doctor_id ) {
			SearchIndexBuilder::rebuild( (int) $doctor_id );
		}
	}
}
