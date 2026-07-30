<?php
/**
 * Doctor save orchestration.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Application\Doctors;

use BrainStudioz\DoctorPilot\Application\Search\SearchIndexBuilder;
use BrainStudioz\DoctorPilot\Domain\Doctor\Completeness;
use BrainStudioz\DoctorPilot\Domain\Doctor\PrimaryTermValidator;
use BrainStudioz\DoctorPilot\Fields\FieldRegistry;
use BrainStudioz\DoctorPilot\Fields\Sanitizer;
use BrainStudioz\DoctorPilot\Library\DoctorContentResolver;
use BrainStudioz\DoctorPilot\Library\ReverseIndex;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists doctor field data submitted from the editor.
 *
 * Every value is written through the field registry and sanitizers. Title,
 * biography, excerpt, and featured image are handled by core; this service owns
 * structured meta, taxonomy relationships, the separate review status, and the
 * derived completeness / search / sort values. Client-supplied completeness or
 * publication escalation is ignored.
 */
final class SaveDoctor {

	/**
	 * Nonce action for the editor meta box.
	 *
	 * @var string
	 */
	public const NONCE_ACTION = 'doctorpilot_save_doctor';

	/**
	 * Nonce field name.
	 *
	 * @var string
	 */
	public const NONCE_NAME = 'doctorpilot_doctor_nonce';

	/**
	 * Hook into save_post for the doctor post type.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'save_post_' . DoctorPostType::POST_TYPE, array( $this, 'handle' ), 10, 2 );

		// Compose the post title from the First/Last name fields before the post
		// is written. Using this filter avoids a recursive wp_update_post call.
		add_filter( 'wp_insert_post_data', array( $this, 'compose_title' ), 10, 2 );
	}

	/**
	 * Set the doctor's post title from the submitted First/Last name fields.
	 *
	 * @param array<string, mixed> $data    Sanitized, slashed post data.
	 * @param array<string, mixed> $postarr Raw post array.
	 * @return array<string, mixed>
	 */
	public function compose_title( array $data, array $postarr ): array {
		if ( DoctorPostType::POST_TYPE !== ( $data['post_type'] ?? '' ) ) {
			return $data;
		}

		// Only act on our own editor submissions.
		$nonce = isset( $_POST[ self::NONCE_NAME ] )
			? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_NAME ] ) )
			: '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return $data;
		}

		// The WordPress title field is authoritative when filled; only compose a
		// title from First/Last when the title box was left empty.
		$existing = trim( wp_unslash( (string) ( $data['post_title'] ?? '' ) ) );
		if ( '' !== $existing ) {
			return $data;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$raw   = isset( $_POST['doctorpilot'] ) && is_array( $_POST['doctorpilot'] ) ? wp_unslash( $_POST['doctorpilot'] ) : array();
		$first = isset( $raw[ FieldRegistry::META_PREFIX . 'first_name' ] ) ? sanitize_text_field( (string) $raw[ FieldRegistry::META_PREFIX . 'first_name' ] ) : '';
		$last  = isset( $raw[ FieldRegistry::META_PREFIX . 'last_name' ] ) ? sanitize_text_field( (string) $raw[ FieldRegistry::META_PREFIX . 'last_name' ] ) : '';

		$title = trim( $first . ' ' . $last );
		if ( '' === $title ) {
			return $data;
		}

		$data['post_title'] = wp_slash( $title );

		// Generate a slug from the name when none exists yet.
		if ( empty( $postarr['post_name'] ) && empty( $data['post_name'] ) ) {
			$data['post_name'] = sanitize_title( $title );
		}

		return $data;
	}

	/**
	 * Handle a doctor save.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function handle( int $post_id, \WP_Post $post ): void {
		// Ignore autosaves, revisions, and bulk edits without our form.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( DoctorPostType::POST_TYPE !== $post->post_type ) {
			return;
		}

		// Require our nonce so unrelated saves are not processed.
		$nonce = isset( $_POST[ self::NONCE_NAME ] )
			? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_NAME ] ) )
			: '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		// Enforce capability on the specific object.
		if ( ! current_user_can( 'edit_doctorpilot_doctor', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$raw = isset( $_POST['doctorpilot'] ) && is_array( $_POST['doctorpilot'] )
			? wp_unslash( $_POST['doctorpilot'] )
			: array();

		$this->save_terms( $post_id, $raw );
		$this->save_meta( $post_id, $raw );
		$this->save_library( $post_id, $raw );

		// Keep primary references valid against the assigned terms.
		PrimaryTermValidator::reconcile( $post_id );

		$this->save_derived( $post_id );
	}

	/**
	 * Save taxonomy relationships.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $raw     Raw submitted field data.
	 * @return void
	 */
	private function save_terms( int $post_id, array $raw ): void {
		// The default sidebar taxonomy metaboxes submit under `tax_input`. The
		// chip UI and the sidebar are kept in sync in the editor, but a term added
		// from the sidebar's "Add New" control has no chip yet, so merge both
		// sources here to avoid dropping a sidebar-only selection.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle().
		$tax_input = isset( $_POST['tax_input'] ) && is_array( $_POST['tax_input'] )
			? wp_unslash( $_POST['tax_input'] )
			: array();

		foreach ( FieldRegistry::term_fields() as $field ) {
			$key      = (string) $field['key'];
			$taxonomy = (string) $field['taxonomy'];

			$chip_ids = array_map( 'intval', (array) Sanitizer::apply( $field, $raw[ $key ] ?? array() ) );

			// Sidebar metabox IDs for hierarchical taxonomies are integer term IDs.
			$sidebar_ids = array();
			if ( isset( $tax_input[ $taxonomy ] ) ) {
				foreach ( (array) $tax_input[ $taxonomy ] as $candidate ) {
					$sidebar_ids[] = (int) $candidate;
				}
			}

			$ids   = array_unique( array_merge( $chip_ids, $sidebar_ids ) );
			$valid = array();
			foreach ( $ids as $id ) {
				if ( $id > 0 && term_exists( $id, $taxonomy ) ) {
					$valid[] = $id;
				}
			}

			wp_set_object_terms( $post_id, $valid, $taxonomy, false );
		}
	}

	/**
	 * Save meta fields.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $raw     Raw submitted field data.
	 * @return void
	 */
	private function save_meta( int $post_id, array $raw ): void {
		foreach ( FieldRegistry::meta_fields() as $field ) {
			$key = (string) $field['key'];

			// Library assignment fields are written by save_library(), which also
			// validates the target CPT and maintains the reverse-reference index.
			if ( 'library' === ( $field['input'] ?? '' ) ) {
				continue;
			}

			// Derived and non-editable fields are never written from input.
			if ( 'none' === ( $field['input'] ?? '' ) && 'review_status' !== ( $field['sanitize'] ?? '' ) ) {
				continue;
			}

			$value = $raw[ $key ] ?? ( 'bool' === $field['type'] ? 0 : $field['default'] );
			$clean = Sanitizer::apply( $field, $value );

			// Enforce the location count limit (Free = 1; Pro may raise it).
			if ( FieldRegistry::META_PREFIX . 'locations' === $key && is_array( $clean ) ) {
				/**
				 * Filter the maximum number of stored location records.
				 *
				 * Free stores a single location; DoctorPilot Pro raises this to
				 * support multiple hospital locations per doctor.
				 *
				 * @param int $max Maximum location records.
				 */
				$max = (int) apply_filters( 'doctorpilot/locations/max', 1 );
				if ( $max > 0 && count( $clean ) > $max ) {
					$clean = array_slice( $clean, 0, $max );
				}
			}

			update_post_meta( $post_id, $key, $clean );
		}
	}

	/**
	 * Save global content-library assignments (Experience, Education, Procedures,
	 * Publications, FAQs, Locations).
	 *
	 * For each `library` field: keep only IDs that are the correct CPT, de-dupe,
	 * store the ordered list, then diff against the previous assignment and update
	 * the reverse-reference index so each shared record knows which doctors use it.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $raw     Raw submitted field data.
	 * @return void
	 */
	private function save_library( int $post_id, array $raw ): void {
		foreach ( FieldRegistry::all() as $field ) {
			if ( 'library' !== ( $field['input'] ?? '' ) ) {
				continue;
			}

			$key = (string) $field['key'];
			$cpt = DoctorContentResolver::cpt( (string) ( $field['library_type'] ?? '' ) );
			if ( '' === $cpt ) {
				continue;
			}

			$submitted = isset( $raw[ $key ] ) ? (array) $raw[ $key ] : array();
			$new       = array();
			foreach ( $submitted as $candidate ) {
				$id = (int) $candidate;
				if ( $id > 0 && $cpt === get_post_type( $id ) && ! in_array( $id, $new, true ) ) {
					$new[] = $id;
				}
			}

			$old = get_post_meta( $post_id, $key, true );
			$old = is_array( $old ) ? array_map( 'intval', $old ) : array();

			update_post_meta( $post_id, $key, $new );

			$added   = array_values( array_diff( $new, $old ) );
			$removed = array_values( array_diff( $old, $new ) );
			if ( array() !== $added || array() !== $removed ) {
				ReverseIndex::apply_diff( $post_id, $added, $removed );
			}
		}
	}

	/**
	 * Recompute derived values: completeness plus the search index.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function save_derived( int $post_id ): void {
		$completeness = Completeness::for_post( $post_id );
		update_post_meta( $post_id, FieldRegistry::META_PREFIX . 'completeness_score', (int) $completeness['score'] );

		// Sort name, search document, and search index are all rebuilt here.
		SearchIndexBuilder::rebuild( $post_id );
	}
}
