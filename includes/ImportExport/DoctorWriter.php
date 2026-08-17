<?php
/**
 * Writes a mapped row into a draft doctor record.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\ImportExport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists a mapped import row as a draft doctor, idempotently.
 *
 * Every imported doctor is created as a draft with review status "needs review"
 * — nothing is auto-published (ADR-0007). Idempotency is keyed on an external
 * source key stored in `_doctorpilot_external_source_id`, so re-running an import
 * updates or skips rather than duplicating.
 */
final class DoctorWriter {

	private const POST_TYPE   = 'doctorpilot_doctor';
	private const META_PREFIX = '_doctorpilot_';

	/**
	 * Taxonomy target key => taxonomy name.
	 *
	 * @var array<string, string>
	 */
	private const TAXONOMIES = array(
		'department' => 'doctorpilot_department',
		'specialty'  => 'doctorpilot_specialty',
		'symptom'    => 'doctorpilot_symptom',
		'treatment'  => 'doctorpilot_treatment',
		'language'   => 'doctorpilot_language',
	);

	/**
	 * Write a mapped row.
	 *
	 * @param array{values:array<string,string>, source_key:string, checksum:string} $mapped     Mapped row.
	 * @param string                                                                  $mode        create|update|upsert.
	 * @param bool                                                                    $create_terms Whether to create missing terms.
	 * @return array{doctor_id:int, status:string, error:?string}
	 */
	public function write( array $mapped, string $mode, bool $create_terms ): array {
		$values     = $mapped['values'];
		$source_key = (string) $mapped['source_key'];
		$name       = trim( (string) ( $values['name'] ?? '' ) );

		if ( '' === $name ) {
			return array( 'doctor_id' => 0, 'status' => 'failed', 'error' => 'missing_name' );
		}
		if ( '' === $source_key ) {
			return array( 'doctor_id' => 0, 'status' => 'failed', 'error' => 'missing_source_key' );
		}

		$existing = $this->find_by_source_key( $source_key );

		if ( $existing > 0 && 'create' === $mode ) {
			return array( 'doctor_id' => $existing, 'status' => 'skipped', 'error' => null );
		}
		if ( 0 === $existing && 'update' === $mode ) {
			return array( 'doctor_id' => 0, 'status' => 'skipped', 'error' => null );
		}

		$postarr = array(
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'draft',
			'post_title'   => sanitize_text_field( $name ),
			'post_excerpt' => sanitize_textarea_field( (string) ( $values['intro'] ?? '' ) ),
			'post_content' => wp_kses_post( (string) ( $values['biography'] ?? '' ) ),
		);

		if ( $existing > 0 ) {
			$postarr['ID'] = $existing;
			$doctor_id     = wp_update_post( $postarr, true );
			$status        = 'updated';
		} else {
			$doctor_id = wp_insert_post( $postarr, true );
			$status    = 'created';
		}

		if ( is_wp_error( $doctor_id ) || 0 === (int) $doctor_id ) {
			return array( 'doctor_id' => 0, 'status' => 'failed', 'error' => 'write_failed' );
		}

		$doctor_id = (int) $doctor_id;

		update_post_meta( $doctor_id, self::META_PREFIX . 'external_source_id', $source_key );
		update_post_meta( $doctor_id, self::META_PREFIX . 'review_status', 'needs_review' );

		$this->write_scalar_meta( $doctor_id, $values );
		$this->write_terms( $doctor_id, $values, $create_terms );

		// Rebuild the derived search index if the builder is available.
		if ( class_exists( '\\BrainStudioz\\DoctorPilot\\Application\\Search\\SearchIndexBuilder' ) ) {
			\BrainStudioz\DoctorPilot\Application\Search\SearchIndexBuilder::rebuild( $doctor_id );
		}

		return array( 'doctor_id' => $doctor_id, 'status' => $status, 'error' => null );
	}

	/**
	 * Find an existing doctor by external source key.
	 *
	 * @param string $source_key Source key.
	 * @return int Doctor ID or 0.
	 */
	private function find_by_source_key( string $source_key ): int {
		$ids = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'meta_key'         => self::META_PREFIX . 'external_source_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $source_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return array() === $ids ? 0 : (int) $ids[0];
	}

	/**
	 * Write scalar meta fields from mapped values.
	 *
	 * @param int                   $doctor_id Doctor ID.
	 * @param array<string, string> $values    Mapped values.
	 * @return void
	 */
	private function write_scalar_meta( int $doctor_id, array $values ): void {
		if ( isset( $values['credentials'] ) ) {
			update_post_meta( $doctor_id, self::META_PREFIX . 'credentials', sanitize_text_field( $values['credentials'] ) );
		}
		if ( isset( $values['gender'] ) ) {
			update_post_meta( $doctor_id, self::META_PREFIX . 'gender', sanitize_key( $values['gender'] ) );
		}
		if ( isset( $values['experience'] ) ) {
			update_post_meta( $doctor_id, self::META_PREFIX . 'years_experience', max( 0, (int) $values['experience'] ) );
		}
		if ( isset( $values['legacy_url'] ) ) {
			update_post_meta( $doctor_id, self::META_PREFIX . 'legacy_url', esc_url_raw( $values['legacy_url'], array( 'http', 'https' ) ) );
		}

		$booking_url = isset( $values['booking_url'] ) ? esc_url_raw( $values['booking_url'], array( 'http', 'https' ) ) : '';
		$phone       = isset( $values['phone'] ) ? preg_replace( '/[^0-9+()\-\s]/', '', (string) $values['phone'] ) : '';

		if ( '' !== $booking_url ) {
			update_post_meta( $doctor_id, self::META_PREFIX . 'booking_external_url', $booking_url );
			update_post_meta( $doctor_id, self::META_PREFIX . 'booking_method', 'external_url' );
		} elseif ( '' !== (string) $phone ) {
			update_post_meta( $doctor_id, self::META_PREFIX . 'booking_phone', trim( (string) $phone ) );
			update_post_meta( $doctor_id, self::META_PREFIX . 'booking_method', 'phone' );
		}
	}

	/**
	 * Assign taxonomy terms (by name), optionally creating missing terms.
	 *
	 * @param int                   $doctor_id    Doctor ID.
	 * @param array<string, string> $values       Mapped values.
	 * @param bool                  $create_terms Whether to create missing terms.
	 * @return void
	 */
	private function write_terms( int $doctor_id, array $values, bool $create_terms ): void {
		foreach ( self::TAXONOMIES as $target => $taxonomy ) {
			if ( ! isset( $values[ $target ] ) || '' === trim( $values[ $target ] ) ) {
				continue;
			}

			$names    = preg_split( '/[|,]/', $values[ $target ] );
			$term_ids = array();

			foreach ( (array) $names as $raw_name ) {
				$term_name = sanitize_text_field( trim( (string) $raw_name ) );
				if ( '' === $term_name ) {
					continue;
				}

				$term = get_term_by( 'name', $term_name, $taxonomy );
				if ( $term instanceof \WP_Term ) {
					$term_ids[] = (int) $term->term_id;
				} elseif ( $create_terms ) {
					$created = wp_insert_term( $term_name, $taxonomy );
					if ( ! is_wp_error( $created ) ) {
						$term_ids[] = (int) $created['term_id'];
					}
				}
			}

			if ( array() !== $term_ids ) {
				wp_set_object_terms( $doctor_id, $term_ids, $taxonomy, false );
			}
		}
	}
}
