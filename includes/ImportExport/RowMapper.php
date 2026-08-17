<?php
/**
 * Maps a raw CSV row onto import targets.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\ImportExport;

use BrainStudioz\DoctorPilot\Migration\MappingConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies a column→target mapping to a raw CSV row.
 *
 * Produces the mapped values plus a stable source key and content checksum used
 * for idempotent re-import (spec §15.1).
 */
final class RowMapper {

	/**
	 * Column header => target key mapping.
	 *
	 * @var array<string, string>
	 */
	private array $mapping;

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $mapping Column => target mapping.
	 */
	public function __construct( array $mapping ) {
		$this->mapping = $mapping;
	}

	/**
	 * Map a raw row into target values.
	 *
	 * @param array<string, string> $row Raw header-keyed row.
	 * @return array{values:array<string,string>, source_key:string, checksum:string, row:int}
	 */
	public function map( array $row ): array {
		$values = array();

		foreach ( $this->mapping as $column => $target ) {
			if ( '' === $target || ! MappingConfig::has_target( $target ) ) {
				continue;
			}
			$values[ $target ] = isset( $row[ $column ] ) ? (string) $row[ $column ] : '';
		}

		$source_key = isset( $values['source_key'] ) && '' !== $values['source_key']
			? sanitize_text_field( $values['source_key'] )
			: '';

		// Fall back to a name-derived key only when no explicit key is present.
		if ( '' === $source_key && isset( $values['name'] ) ) {
			$source_key = sanitize_title( $values['name'] );
		}

		$checksum = hash( 'sha256', (string) wp_json_encode( $values ) );

		return array(
			'values'     => $values,
			'source_key' => $source_key,
			'checksum'   => $checksum,
			'row'        => isset( $row['__row'] ) ? (int) $row['__row'] : 0,
		);
	}
}
