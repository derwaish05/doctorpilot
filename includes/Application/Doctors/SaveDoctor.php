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
use BrainStudioz\DoctorPilot\Support\Capabilities;
use BrainStudioz\DoctorPilot\Taxonomies\Taxonomies;

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
	 * Doctor IDs whose derived values need rebuilding before the request ends.
	 *
	 * Used as a set, keyed by post ID. Collecting rather than rebuilding
	 * immediately is what keeps the cost of the generic listeners at one rebuild
	 * per doctor per request, however many hooks fired for it — an import row
	 * fires `wp_after_insert_post` once and `set_object_terms` once per
	 * taxonomy, so rebuilding eagerly would recompute the same doctor seven
	 * times, the first of them before any of its data had been written.
	 *
	 * Deferring also fixes the ordering. At `shutdown` every field, term and
	 * relationship is on disk no matter which order the writer used, so the
	 * index is built from the finished record instead of a half-written one.
	 *
	 * @var array<int, bool>
	 */
	private array $pending = array();

	/**
	 * Whether the `shutdown` flush is already scheduled.
	 *
	 * @var bool
	 */
	private bool $flush_scheduled = false;

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
	 * Most terms one field may create in a single save.
	 *
	 * Bounds a pasted list so a mistaken paste cannot flood a taxonomy.
	 */
	private const MAX_NEW_TERMS = 100;

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

		/*
		 * Derived search values, for doctors this plugin's own form did not
		 * write. `handle()` requires the editor nonce so that an unrelated save
		 * cannot be mistaken for a form submission — correct, but it means a
		 * doctor created by anything else has no search index at all, and a
		 * doctor with no index is dropped from every search rather than ranked
		 * low. That covers a WPML or Polylang translation, a WP-CLI import, a
		 * REST write, and a Quick Edit.
		 *
		 * Split from `handle()` rather than folded into it: this path must not
		 * touch submitted fields, validate a nonce, or run the primary-term
		 * reconciler. It only recomputes values that are derived from data
		 * already in the database, which is safe to do on any save.
		 */
		add_action( 'wp_after_insert_post', array( $this, 'refresh_derived' ), 20, 4 );

		// Term assignment does not go through `wp_after_insert_post` — the
		// block editor and WPML both set terms in a separate request — and the
		// index is mostly built from term names.
		add_action( 'set_object_terms', array( $this, 'refresh_derived_for_terms' ), 20, 6 );
	}

	/**
	 * Rebuild derived values after any save of a doctor.
	 *
	 * @param int           $post_id     Post ID.
	 * @param \WP_Post      $post        Post object.
	 * @param bool          $update      Whether this is an update.
	 * @param \WP_Post|null $post_before Post before the update.
	 * @return void
	 */
	public function refresh_derived( int $post_id, $post, $update = false, $post_before = null ): void {
		if ( ! $post instanceof \WP_Post || DoctorPostType::POST_TYPE !== $post->post_type ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$this->queue( $post_id, $post->post_status );
	}

	/**
	 * Rebuild derived values when a doctor's terms change.
	 *
	 * @param int    $object_id  Object ID.
	 * @param array  $terms      Terms.
	 * @param array  $tt_ids     Term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy.
	 * @param bool   $append     Whether terms were appended.
	 * @param array  $old_tt_ids Previous term taxonomy IDs.
	 * @return void
	 */
	public function refresh_derived_for_terms( $object_id, $terms, $tt_ids, $taxonomy, $append = false, $old_tt_ids = array() ): void {
		// Only the taxonomies the index is actually built from. Assigning a
		// location changes nothing `SearchIndexBuilder` reads, so rebuilding for
		// it would recompute byte-identical values.
		if ( ! in_array( (string) $taxonomy, SearchIndexBuilder::INDEXED_TAXONOMIES, true ) ) {
			return;
		}

		if ( DoctorPostType::POST_TYPE !== get_post_type( (int) $object_id ) ) {
			return;
		}

		// Unchanged assignment — WordPress fires this on every save regardless.
		if ( ! $append && $this->same_terms( (array) $tt_ids, (array) $old_tt_ids ) ) {
			return;
		}

		$this->queue( (int) $object_id );
	}

	/**
	 * Mark a doctor as needing its derived values rebuilt.
	 *
	 * @param int    $post_id Doctor post ID.
	 * @param string $status  Post status, when the caller already knows it.
	 * @return void
	 */
	private function queue( int $post_id, string $status = '' ): void {
		if ( $post_id <= 0 ) {
			return;
		}

		if ( '' === $status ) {
			$status = (string) get_post_status( $post_id );
		}

		/*
		 * An auto-draft is the empty row WordPress creates when someone opens
		 * "Add New" and may never save. Indexing it writes four meta rows onto a
		 * record with no title and no terms, on every visit to that screen.
		 * Trashed and inherited (revision, attachment) rows are equally not
		 * worth indexing.
		 */
		if ( in_array( $status, array( 'auto-draft', 'trash', 'inherit' ), true ) ) {
			return;
		}

		// Already rebuilt this request by the editor form or the CSV writer, both
		// of which do it after writing everything.
		if ( SearchIndexBuilder::was_rebuilt( $post_id ) ) {
			return;
		}

		$this->pending[ $post_id ] = true;

		if ( ! $this->flush_scheduled ) {
			$this->flush_scheduled = true;
			add_action( 'shutdown', array( $this, 'flush_pending' ), 5 );
		}
	}

	/**
	 * Rebuild everything queued during this request.
	 *
	 * Public because it is a hook callback. Safe to call twice: the queue is
	 * emptied first, so a re-entrant call finds nothing to do.
	 *
	 * @return void
	 */
	public function flush_pending(): void {
		$ids = array_keys( $this->pending );

		$this->pending = array();

		foreach ( $ids as $post_id ) {
			// Status is re-checked: a doctor queued while published may have
			// been trashed later in the same request.
			if ( SearchIndexBuilder::was_rebuilt( $post_id ) ) {
				continue;
			}

			if ( in_array( (string) get_post_status( $post_id ), array( 'auto-draft', 'trash', 'inherit' ), true ) ) {
				continue;
			}

			$this->save_derived( (int) $post_id );
		}
	}

	/**
	 * Whether two term-taxonomy ID lists describe the same assignment.
	 *
	 * @param array<int, mixed> $a First list.
	 * @param array<int, mixed> $b Second list.
	 * @return bool
	 */
	private function same_terms( array $a, array $b ): bool {
		$a = array_map( 'intval', $a );
		$b = array_map( 'intval', $b );
		sort( $a );
		sort( $b );

		return $a === $b;
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

		// Done synchronously and authoritatively; the listeners' queue entry for
		// this doctor would only repeat it at shutdown.
		unset( $this->pending[ $post_id ] );
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

			$new_ids = $this->create_requested_terms( $taxonomy, $raw[ 'new_' . $key ] ?? array() );

			$ids   = array_unique( array_merge( $chip_ids, $sidebar_ids, $new_ids ) );
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
	 * Resolve names submitted from the editor's Bulk edit into term IDs, creating
	 * the ones that do not exist yet.
	 *
	 * Creating a term needs the taxonomy's `manage_terms` capability
	 * (`manage_doctorpilot_terms`), which assigning a term to a doctor does not,
	 * so an editor without it gets no new terms and the names are ignored. A name
	 * that already exists (case-insensitively, as WordPress matches names) is
	 * reused rather than duplicated, which also makes a retried save idempotent.
	 *
	 * @param string $taxonomy Taxonomy key.
	 * @param mixed  $names    Submitted names (untrusted).
	 * @return array<int, int> Term IDs to assign.
	 */
	private function create_requested_terms( string $taxonomy, $names ): array {
		if ( ! is_array( $names ) || array() === $names || ! current_user_can( Capabilities::MANAGE_TERMS ) ) {
			return array();
		}

		$ids  = array();
		$seen = array();

		foreach ( $names as $name ) {
			if ( ! is_string( $name ) ) {
				continue;
			}

			$name = trim( sanitize_text_field( $name ) );
			$fold = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name ) : strtolower( $name );
			if ( '' === $name || isset( $seen[ $fold ] ) ) {
				continue;
			}
			// wp_terms.name is varchar(200); a longer value is truncated, so refuse it.
			if ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $name ) : strlen( $name ) ) > 200 ) {
				continue;
			}
			if ( count( $seen ) >= self::MAX_NEW_TERMS ) {
				break;
			}
			$seen[ $fold ] = true;

			// By name, not term_exists(): that treats a numeric string as a term ID.
			$existing = get_term_by( 'name', $name, $taxonomy );
			if ( $existing instanceof \WP_Term ) {
				$ids[] = (int) $existing->term_id;
				continue;
			}

			$created = wp_insert_term( $name, $taxonomy );
			if ( is_wp_error( $created ) ) {
				// A concurrent request may have created it first.
				$data = $created->get_error_data( 'term_exists' );
				if ( is_numeric( $data ) ) {
					$ids[] = (int) $data;
				}
				continue;
			}

			$ids[] = (int) $created['term_id'];
		}

		return $ids;
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
