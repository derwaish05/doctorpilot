<?php
/**
 * Resumable, chunked import runner.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\ImportExport;

use BrainStudioz\DoctorPilot\Infrastructure\Lifecycle\Activator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes an import in bounded, resumable chunks.
 *
 * Each chunk advances the job cursor and persists progress before scheduling
 * the next, so a killed process resumes without duplicating work. Idempotency is
 * enforced by {@see DoctorWriter} via the external source key. All imported
 * doctors are drafts pending review — never auto-published.
 */
final class ImportRunner {

	/**
	 * Rows processed per chunk.
	 *
	 * @var int
	 */
	private const CHUNK = 25;

	/**
	 * Register the cron continuation hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( Activator::IMPORT_HOOK, array( $this, 'run_chunk' ), 10, 2 );
	}

	/**
	 * Begin processing a validated job.
	 *
	 * @param int    $job_id Job ID.
	 * @param string $path   Staged CSV path.
	 * @return void
	 */
	public function start( int $job_id, string $path ): void {
		( new JobRepository() )->update( $job_id, array( 'status' => 'importing' ) );
		$this->schedule_next( $job_id, $path );
	}

	/**
	 * Process one chunk of an import job.
	 *
	 * @param int    $job_id Job ID.
	 * @param string $path   Staged CSV path.
	 * @return void
	 */
	public function run_chunk( int $job_id, string $path ): void {
		$jobs = new JobRepository();
		$job  = $jobs->get( $job_id );

		if ( null === $job || 'importing' !== $job['status'] ) {
			return;
		}

		if ( ! is_readable( $path ) ) {
			$jobs->update( $job_id, array( 'status' => 'failed' ) );
			return;
		}

		$mapping_data = json_decode( (string) $job['mapping_json'], true );
		$mapping      = is_array( $mapping_data['mapping'] ?? null ) ? $mapping_data['mapping'] : array();
		$create_terms = ! empty( $mapping_data['create_terms'] );
		$mode         = (string) $job['import_mode'];

		$reader = new CsvReader( $path );
		$offset = (int) $job['cursor_pos'];
		$rows   = $reader->rows( $offset, self::CHUNK );

		$items  = new ItemRepository();
		$writer = new DoctorWriter();
		$mapper = new RowMapper( $mapping );

		$deltas = array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0 );

		foreach ( $rows as $raw ) {
			$mapped = $mapper->map( $raw );
			$result = $writer->write( $mapped, $mode, $create_terms );

			$status = $result['status'];
			$deltas[ $status ] = ( $deltas[ $status ] ?? 0 ) + 1;

			$items->record(
				$job_id,
				$mapped['row'],
				$mapped['source_key'],
				$mapped['checksum'],
				$result['doctor_id'] > 0 ? $result['doctor_id'] : null,
				$status,
				$result['error'],
				null
			);
		}

		$new_cursor = $offset + count( $rows );
		$done       = array() === $rows || $new_cursor >= (int) $job['total_rows'];
		$status     = $done ? 'completed' : 'importing';

		$jobs->progress( $job_id, $deltas, $new_cursor, $status );

		if ( $done ) {
			// Remove the staged private file once the import finishes.
			if ( is_writable( $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
				wp_delete_file( $path );
			}
			return;
		}

		$this->schedule_next( $job_id, $path );
	}

	/**
	 * Schedule the next chunk.
	 *
	 * @param int    $job_id Job ID.
	 * @param string $path   Staged CSV path.
	 * @return void
	 */
	private function schedule_next( int $job_id, string $path ): void {
		if ( ! wp_next_scheduled( Activator::IMPORT_HOOK, array( $job_id, $path ) ) ) {
			wp_schedule_single_event( time() + 5, Activator::IMPORT_HOOK, array( $job_id, $path ) );
		}
	}
}
