<?php
/**
 * Resolves combined global + local library content for a doctor.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Library;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Merges a doctor's assigned global library records with their doctor-specific
 * local entries into one ordered list per type (ADR-0017).
 *
 * In a public context only published globals are included; the resolver always
 * ignores missing or non-published records defensively, so a stale reference
 * never causes a fatal or a leak. Assigned globals appear first (in the assigned
 * order), followed by the doctor's local entries.
 */
final class DoctorContentResolver {

	/**
	 * Assignment (global ID list) meta key per type.
	 */
	private const IDS = array(
		'exp'  => '_doctorpilot_exp_ids',
		'edu'  => '_doctorpilot_edu_ids',
		'proc' => '_doctorpilot_proc_ids',
		'pub'  => '_doctorpilot_pub_ids',
		'faq'  => '_doctorpilot_faq_ids',
		'loc'  => '_doctorpilot_loc_ids',
	);

	/**
	 * Local entries (repeater) meta key per type.
	 *
	 * Experience/Education use dedicated split repeaters; Procedures,
	 * Publications, and FAQs reuse the pre-existing doctor repeater keys so no
	 * data migration is required — those repeaters simply become the "local"
	 * items in the hybrid model.
	 */
	private const LOCAL = array(
		'exp'  => '_doctorpilot_exp_local',
		'edu'  => '_doctorpilot_edu_local',
		'proc' => '_doctorpilot_procedures',
		'pub'  => '_doctorpilot_publications',
		'faq'  => '_doctorpilot_faqs',
		// Locations keep their own per-doctor repeater (`_doctorpilot_locations`),
		// merged separately in DoctorView; this placeholder key is never populated
		// so resolve('loc') returns only the assigned shared locations.
		'loc'  => '_doctorpilot_loc_local',
	);

	/**
	 * The CPT key per type.
	 */
	private const CPT = array(
		'exp'  => LibraryPostTypes::EXPERIENCE,
		'edu'  => LibraryPostTypes::EDUCATION,
		'proc' => LibraryPostTypes::PROCEDURE,
		'pub'  => LibraryPostTypes::PUBLICATION,
		'faq'  => LibraryPostTypes::FAQ,
		'loc'  => LibraryPostTypes::LOCATION,
	);

	/**
	 * Valid type keys.
	 *
	 * @return array<int, string>
	 */
	public static function types(): array {
		return array( 'exp', 'edu', 'proc', 'pub', 'faq', 'loc' );
	}

	/**
	 * The assignment meta key for a type.
	 *
	 * @param string $type Type key.
	 * @return string
	 */
	public static function ids_key( string $type ): string {
		return self::IDS[ $type ] ?? '';
	}

	/**
	 * The local-entries meta key for a type.
	 *
	 * @param string $type Type key.
	 * @return string
	 */
	public static function local_key( string $type ): string {
		return self::LOCAL[ $type ] ?? '';
	}

	/**
	 * The CPT key for a type.
	 *
	 * @param string $type Type key.
	 * @return string
	 */
	public static function cpt( string $type ): string {
		return self::CPT[ $type ] ?? '';
	}

	/**
	 * Resolve the combined ordered list for a doctor and type.
	 *
	 * @param int    $doctor_id Doctor post ID.
	 * @param string $type      'exp' or 'edu'.
	 * @param string $context   'public' or 'admin'.
	 * @return array<int, array<string, mixed>>
	 */
	public function resolve( int $doctor_id, string $type, string $context = 'public' ): array {
		if ( ! isset( self::IDS[ $type ] ) ) {
			return array();
		}

		$items = array();

		// Assigned global records, in the stored order.
		$ids = get_post_meta( $doctor_id, self::IDS[ $type ], true );
		$ids = is_array( $ids ) ? array_map( 'intval', $ids ) : array();
		$cpt = self::CPT[ $type ];

		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! ( $post instanceof \WP_Post ) || $cpt !== $post->post_type ) {
				continue;
			}
			if ( 'public' === $context && 'publish' !== $post->post_status ) {
				continue;
			}

			$items[] = $this->global_item( $post, $context );
		}

		// Doctor-specific local entries.
		$local = get_post_meta( $doctor_id, self::LOCAL[ $type ], true );
		if ( is_array( $local ) ) {
			foreach ( $local as $row ) {
				if ( is_array( $row ) ) {
					$items[] = $this->local_item( $cpt, $row, $context );
				}
			}
		}

		return $items;
	}

	/**
	 * Build an item from a global record using the type's field schema.
	 *
	 * Each schema field becomes a flat key on the item: meta-stored fields come
	 * from `_doctorpilot_lib_{key}`, and the single content field comes from
	 * `post_content`. The resulting shape matches the doctor's local repeater
	 * rows for the same type, so consumers render globals and locals uniformly.
	 *
	 * @param \WP_Post $post    Library post.
	 * @param string   $context Context.
	 * @return array<string, mixed>
	 */
	private function global_item( \WP_Post $post, string $context ): array {
		$item   = array( 'title' => (string) $post->post_title );
		$fields = (array) ( LibraryPostTypes::config( $post->post_type )['fields'] ?? array() );

		foreach ( $fields as $key => $field ) {
			switch ( $field['store'] ?? 'meta' ) {
				case 'content':
					$item[ $key ] = (string) $post->post_content;
					break;
				case 'title':
					$item[ $key ] = (string) $post->post_title;
					break;
				default:
					$item[ $key ] = (string) get_post_meta( $post->ID, LibraryPostTypes::META_PREFIX . $key, true );
					break;
			}
		}

		if ( 'admin' === $context ) {
			$item['origin'] = 'global';
			$item['id']     = (int) $post->ID;
			$item['status'] = (string) $post->post_status;
		}

		return $item;
	}

	/**
	 * Build an item from a local repeater row using the type's field schema.
	 *
	 * @param string               $cpt     CPT key for the type.
	 * @param array<string, mixed>  $row     Local row.
	 * @param string               $context Context.
	 * @return array<string, mixed>
	 */
	private function local_item( string $cpt, array $row, string $context ): array {
		$fields = (array) ( LibraryPostTypes::config( $cpt )['fields'] ?? array() );

		$item   = array();
		$values = array();
		foreach ( $fields as $key => $field ) {
			$item[ $key ]   = (string) ( $row[ $key ] ?? '' );
			$values[ $key ] = $item[ $key ];
		}

		$item['title'] = LibraryPostTypes::compose_title_from( $cpt, $values );

		if ( 'admin' === $context ) {
			$item['origin'] = 'local';
		}

		return $item;
	}
}
