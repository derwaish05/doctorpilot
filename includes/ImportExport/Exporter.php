<?php
/**
 * Formula-safe doctor CSV export.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\ImportExport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exports doctors to CSV with spreadsheet formula-injection protection.
 *
 * Only fields the current user can access are exported, and any cell beginning
 * with a formula trigger is neutralized so opening the file cannot execute code.
 */
final class Exporter {

	private const META_PREFIX = '_doctorpilot_';

	/**
	 * Export doctors to a CSV string.
	 *
	 * @param int $limit Maximum doctors to export.
	 * @return string CSV content.
	 */
	public function to_string( int $limit = 5000 ): string {
		if ( ! current_user_can( 'edit_doctorpilot_doctors' ) ) {
			return '';
		}

		$ids = get_posts(
			array(
				'post_type'      => 'doctorpilot_doctor',
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'posts_per_page' => max( 1, min( 10000, $limit ) ),
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$columns = array( 'source_key', 'name', 'credentials', 'department', 'specialty', 'language', 'legacy_url' );

		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $handle, $columns );

		foreach ( (array) $ids as $id ) {
			$id  = (int) $id;
			$row = array(
				$this->safe( (string) get_post_meta( $id, self::META_PREFIX . 'external_source_id', true ) ?: get_post_field( 'post_name', $id ) ),
				$this->safe( (string) get_the_title( $id ) ),
				$this->safe( (string) get_post_meta( $id, self::META_PREFIX . 'credentials', true ) ),
				$this->safe( $this->terms( $id, 'doctorpilot_department' ) ),
				$this->safe( $this->terms( $id, 'doctorpilot_specialty' ) ),
				$this->safe( $this->terms( $id, 'doctorpilot_language' ) ),
				$this->safe( (string) get_post_meta( $id, self::META_PREFIX . 'legacy_url', true ) ),
			);
			fputcsv( $handle, $row );
		}

		rewind( $handle );
		$csv = (string) stream_get_contents( $handle );
		fclose( $handle );

		return $csv;
	}

	/**
	 * Comma-joined term names for a taxonomy.
	 *
	 * @param int    $id       Doctor ID.
	 * @param string $taxonomy Taxonomy.
	 * @return string
	 */
	private function terms( int $id, string $taxonomy ): string {
		$names = wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'names' ) );

		return is_wp_error( $names ) ? '' : implode( '|', array_map( 'strval', $names ) );
	}

	/**
	 * Neutralize a value that could be interpreted as a spreadsheet formula.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	private function safe( string $value ): string {
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true ) ) {
			return "'" . $value;
		}

		return $value;
	}
}
