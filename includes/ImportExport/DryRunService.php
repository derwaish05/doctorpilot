<?php
/**
 * Import dry run and preview.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\ImportExport;

use BrainStudioz\DoctorPilot\Migration\MappingConfig;
use BrainStudioz\DoctorPilot\Migration\ReviewFlags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates an import without writing anything.
 *
 * Confirms the mapping, samples rows, runs the review-flag heuristics, and
 * reports counts and issues so an editor can approve before the real, draft-only
 * import runs. This service is guaranteed to make no canonical changes.
 */
final class DryRunService {

	/**
	 * Maximum rows sampled for preview detail.
	 *
	 * @var int
	 */
	private const SAMPLE = 25;

	/**
	 * Run a dry run over a staged CSV.
	 *
	 * @param CsvReader             $reader  CSV reader.
	 * @param array<string, string> $mapping Column => target mapping.
	 * @return array<string, mixed>|\WP_Error Summary, or error for an invalid mapping.
	 */
	public function run( CsvReader $reader, array $mapping ) {
		$mapping_errors = MappingConfig::validate_mapping( $mapping );
		if ( array() !== $mapping_errors ) {
			return new \WP_Error( 'doctorpilot_invalid_filter', implode( ' ', $mapping_errors ) );
		}

		$header = $reader->header();
		if ( is_wp_error( $header ) ) {
			return $header;
		}

		$site_host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$total_rows = $reader->count_rows();
		$rows       = $reader->rows( 0, self::SAMPLE );
		$mapper     = new RowMapper( $mapping );

		$valid    = 0;
		$flagged  = 0;
		$missing  = 0;
		$samples  = array();
		$keys     = array();
		$dup_keys = 0;

		foreach ( $rows as $raw ) {
			$mapped = $mapper->map( $raw );
			$flags  = ReviewFlags::inspect( $mapped['values'], $site_host );

			if ( '' === trim( (string) ( $mapped['values']['name'] ?? '' ) ) ) {
				++$missing;
			} else {
				++$valid;
			}

			if ( array() !== $flags ) {
				++$flagged;
			}

			if ( '' !== $mapped['source_key'] ) {
				if ( isset( $keys[ $mapped['source_key'] ] ) ) {
					++$dup_keys;
				}
				$keys[ $mapped['source_key'] ] = true;
			}

			if ( count( $samples ) < self::SAMPLE ) {
				$samples[] = array(
					'row'    => $mapped['row'],
					'name'   => (string) ( $mapped['values']['name'] ?? '' ),
					'flags'  => $flags,
				);
			}
		}

		return array(
			'total_rows'     => $total_rows,
			'sampled'        => count( $rows ),
			'valid_sampled'  => $valid,
			'missing_name'   => $missing,
			'flagged'        => $flagged,
			'duplicate_keys' => $dup_keys,
			'samples'        => $samples,
			'writes'         => 0,
		);
	}
}
