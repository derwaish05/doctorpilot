<?php
/**
 * Import rollback.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\ImportExport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reverses an import job by removing only what it created and left unpublished.
 *
 * A doctor that has since been reviewed and published is never deleted — the
 * rollback skips it and reports it, so a human decision is required for anything
 * that is already live.
 */
final class Rollback {

	/**
	 * Roll back a job.
	 *
	 * @param int $job_id Job ID.
	 * @return array{deleted:int, kept_published:int}
	 */
	public function rollback( int $job_id ): array {
		$items   = new ItemRepository();
		$ids     = $items->created_doctor_ids( $job_id );
		$deleted = 0;
		$kept    = 0;

		foreach ( $ids as $doctor_id ) {
			if ( $doctor_id <= 0 ) {
				continue;
			}

			if ( 'publish' === get_post_status( $doctor_id ) ) {
				++$kept;
				continue;
			}

			// Permanently delete the unpublished, job-created draft.
			wp_delete_post( $doctor_id, true );
			++$deleted;
		}

		( new JobRepository() )->update( $job_id, array( 'status' => 'rolled_back' ) );

		return array(
			'deleted'        => $deleted,
			'kept_published' => $kept,
		);
	}
}
