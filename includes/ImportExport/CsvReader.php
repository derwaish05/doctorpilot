<?php
/**
 * Bounded CSV reader.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\ImportExport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads a staged CSV file with bounded memory.
 *
 * Normalizes the header, rejects ambiguous duplicate headers, and yields rows
 * as associative arrays keyed by header. Designed to be called in bounded
 * slices (offset/limit) so large files never load entirely into memory.
 */
final class CsvReader {

	/**
	 * Absolute path to the staged CSV.
	 *
	 * @var string
	 */
	private string $path;

	/**
	 * Constructor.
	 *
	 * @param string $path Absolute file path.
	 */
	public function __construct( string $path ) {
		$this->path = $path;
	}

	/**
	 * Read and normalize the header row.
	 *
	 * @return array<int, string>|\WP_Error
	 */
	public function header() {
		$handle = $this->open();
		if ( is_wp_error( $handle ) ) {
			return $handle;
		}

		$header = fgetcsv( $handle );
		fclose( $handle );

		if ( false === $header || array() === $header ) {
			return new \WP_Error( 'doctorpilot_invalid_parameter', __( 'The CSV has no header row.', 'doctorpilot' ) );
		}

		$clean = array();
		foreach ( $header as $col ) {
			$name = trim( $this->to_utf8( (string) $col ) );
			$name = ltrim( $name, "\xEF\xBB\xBF" ); // Strip UTF-8 BOM.
			if ( in_array( $name, $clean, true ) ) {
				return new \WP_Error( 'doctorpilot_invalid_parameter', sprintf( /* translators: %s: column name. */ __( 'Duplicate column "%s" in the CSV header.', 'doctorpilot' ), $name ) );
			}
			$clean[] = $name;
		}

		return $clean;
	}

	/**
	 * Count the data rows (excluding the header).
	 *
	 * @return int
	 */
	public function count_rows(): int {
		$handle = $this->open();
		if ( is_wp_error( $handle ) ) {
			return 0;
		}

		$count = 0;
		$first = true;
		while ( false !== ( $line = fgetcsv( $handle ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
			if ( $first ) {
				$first = false;
				continue;
			}
			if ( array( null ) === $line ) {
				continue;
			}
			++$count;
		}
		fclose( $handle );

		return $count;
	}

	/**
	 * Read a bounded slice of rows as header-keyed associative arrays.
	 *
	 * @param int $offset Zero-based data-row offset.
	 * @param int $limit  Maximum rows to read.
	 * @return array<int, array<string, string>>
	 */
	public function rows( int $offset, int $limit ): array {
		$header = $this->header();
		if ( is_wp_error( $header ) ) {
			return array();
		}

		$handle = $this->open();
		if ( is_wp_error( $handle ) ) {
			return array();
		}

		fgetcsv( $handle ); // Skip header.

		$rows  = array();
		$index = 0;
		while ( false !== ( $line = fgetcsv( $handle ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
			if ( array( null ) === $line ) {
				continue;
			}
			if ( $index >= $offset && count( $rows ) < $limit ) {
				$assoc = array();
				foreach ( $header as $i => $col ) {
					$assoc[ $col ] = isset( $line[ $i ] ) ? trim( $this->to_utf8( (string) $line[ $i ] ) ) : '';
				}
				$assoc['__row'] = $index;
				$rows[]         = $assoc;
			}
			++$index;
			if ( count( $rows ) >= $limit ) {
				break;
			}
		}
		fclose( $handle );

		return $rows;
	}

	/**
	 * Open the CSV file for reading.
	 *
	 * @return resource|\WP_Error
	 */
	private function open() {
		if ( ! is_readable( $this->path ) ) {
			return new \WP_Error( 'doctorpilot_not_found', __( 'The import file could not be read.', 'doctorpilot' ) );
		}

		$handle = fopen( $this->path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			return new \WP_Error( 'doctorpilot_internal_error', __( 'The import file could not be opened.', 'doctorpilot' ) );
		}

		return $handle;
	}

	/**
	 * Convert a value to UTF-8 when the source encoding differs.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function to_utf8( string $value ): string {
		if ( function_exists( 'mb_detect_encoding' ) ) {
			$encoding = mb_detect_encoding( $value, array( 'UTF-8', 'ISO-8859-1', 'Windows-1252' ), true );
			if ( $encoding && 'UTF-8' !== $encoding ) {
				return (string) mb_convert_encoding( $value, 'UTF-8', $encoding );
			}
		}

		return $value;
	}
}
