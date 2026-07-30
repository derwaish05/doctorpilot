<?php
/**
 * Global library custom post types (Experience, Education, Procedures,
 * Publications, FAQs).
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Library;

use BrainStudioz\DoctorPilot\Support\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the reusable global content-library post types and their editor.
 *
 * Each library type is a prefixed, non-public custom post type (ADR-0017) whose
 * fields are described by a data-driven schema. Most fields are stored as
 * prefixed post meta (`_doctorpilot_lib_{key}`); exactly one field per type is
 * stored in `post_content` (the long-form body). The post title is computed
 * read-only from the type's `title_parts` so display and data never drift.
 * Records are managed centrally and are not publicly queryable on their own —
 * they surface only through a doctor via {@see DoctorContentResolver}.
 */
final class LibraryPostTypes {

	public const EXPERIENCE  = 'doctorpilot_exp';
	public const EDUCATION   = 'doctorpilot_edu';
	public const PROCEDURE   = 'doctorpilot_proc';
	public const PUBLICATION = 'doctorpilot_pub';
	public const FAQ         = 'doctorpilot_faq';
	public const LOCATION    = 'doctorpilot_loc';

	/**
	 * Prefix for every per-field meta key.
	 *
	 * @var string
	 */
	public const META_PREFIX = '_doctorpilot_lib_';

	/**
	 * Reverse-reference index meta key (assigned doctor IDs).
	 *
	 * @var string
	 */
	public const META_DOCTORS = '_doctorpilot_lib_doctors';

	private const NONCE_ACTION = 'doctorpilot_save_library';
	private const NONCE_NAME   = 'doctorpilot_library_nonce';

	/**
	 * All library post type keys. No translation — safe to call any time.
	 *
	 * @return array<int, string>
	 */
	public static function keys(): array {
		return array(
			self::EXPERIENCE,
			self::EDUCATION,
			self::PROCEDURE,
			self::PUBLICATION,
			self::FAQ,
			self::LOCATION,
		);
	}

	/**
	 * The full data-driven schema for every library type.
	 *
	 * Calls translation functions, so only invoke on or after `init`.
	 *
	 * Each field: `label`, `type` (`text|year|url|textarea`), and optional
	 * `store` (`meta` default, or `content` for the single post_content field).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function schema(): array {
		return array(
			self::EXPERIENCE  => array(
				'labels'        => array(
					'singular' => __( 'Experience', 'doctorpilot' ),
					'plural'   => __( 'Experience', 'doctorpilot' ),
					'menu'     => __( 'Experience', 'doctorpilot' ),
				),
				'title_parts'   => array( 'primary', 'secondary' ),
				'list_subtitle' => 'country',
				'fields'        => array(
					'primary'     => array( 'label' => __( 'Position / role', 'doctorpilot' ), 'type' => 'text' ),
					'secondary'   => array( 'label' => __( 'Organization', 'doctorpilot' ), 'type' => 'text' ),
					'start_year'  => array( 'label' => __( 'Start year', 'doctorpilot' ), 'type' => 'year' ),
					'end_year'    => array( 'label' => __( 'End year', 'doctorpilot' ), 'type' => 'year' ),
					'country'     => array( 'label' => __( 'Country', 'doctorpilot' ), 'type' => 'text' ),
					'description' => array( 'label' => __( 'Description', 'doctorpilot' ), 'type' => 'textarea', 'store' => 'content' ),
				),
			),
			self::EDUCATION   => array(
				'labels'        => array(
					'singular' => __( 'Education', 'doctorpilot' ),
					'plural'   => __( 'Education', 'doctorpilot' ),
					'menu'     => __( 'Education', 'doctorpilot' ),
				),
				'title_parts'   => array( 'primary', 'secondary' ),
				'list_subtitle' => 'country',
				'fields'        => array(
					'primary'     => array( 'label' => __( 'Qualification', 'doctorpilot' ), 'type' => 'text' ),
					'secondary'   => array( 'label' => __( 'Institution', 'doctorpilot' ), 'type' => 'text' ),
					'start_year'  => array( 'label' => __( 'Start year', 'doctorpilot' ), 'type' => 'year' ),
					'end_year'    => array( 'label' => __( 'End year', 'doctorpilot' ), 'type' => 'year' ),
					'country'     => array( 'label' => __( 'Country', 'doctorpilot' ), 'type' => 'text' ),
					'description' => array( 'label' => __( 'Description', 'doctorpilot' ), 'type' => 'textarea', 'store' => 'content' ),
				),
			),
			self::PROCEDURE   => array(
				'labels'        => array(
					'singular' => __( 'Procedure', 'doctorpilot' ),
					'plural'   => __( 'Procedures', 'doctorpilot' ),
					'menu'     => __( 'Procedures', 'doctorpilot' ),
				),
				// The procedure name IS the post title; description is the content.
				'title_parts'   => array(),
				'list_subtitle' => null,
				'fields'        => array(
					'name'        => array( 'label' => __( 'Procedure name', 'doctorpilot' ), 'type' => 'text', 'store' => 'title' ),
					'description' => array( 'label' => __( 'Description', 'doctorpilot' ), 'type' => 'textarea', 'store' => 'content' ),
				),
			),
			self::PUBLICATION => array(
				'labels'        => array(
					'singular' => __( 'Publication', 'doctorpilot' ),
					'plural'   => __( 'Publications', 'doctorpilot' ),
					'menu'     => __( 'Publications', 'doctorpilot' ),
				),
				// The publication title IS the post title; summary is the content.
				'title_parts'   => array(),
				'list_subtitle' => 'journal',
				'fields'        => array(
					'title'          => array( 'label' => __( 'Title', 'doctorpilot' ), 'type' => 'text', 'store' => 'title' ),
					'journal'        => array( 'label' => __( 'Journal', 'doctorpilot' ), 'type' => 'text' ),
					'published_date' => array( 'label' => __( 'Published date', 'doctorpilot' ), 'type' => 'text' ),
					'url'            => array( 'label' => __( 'URL', 'doctorpilot' ), 'type' => 'url' ),
					'doi'            => array( 'label' => __( 'DOI', 'doctorpilot' ), 'type' => 'text' ),
					'description'    => array( 'label' => __( 'Summary', 'doctorpilot' ), 'type' => 'textarea', 'store' => 'content' ),
				),
			),
			self::FAQ         => array(
				'labels'        => array(
					'singular' => __( 'FAQ', 'doctorpilot' ),
					'plural'   => __( 'FAQs', 'doctorpilot' ),
					'menu'     => __( 'FAQs', 'doctorpilot' ),
				),
				// The FAQ question IS the post title; the answer is the content.
				// There are no meta-stored fields, so no Details meta box renders.
				'title_parts'   => array(),
				'list_subtitle' => null,
				'fields'        => array(
					'question' => array( 'label' => __( 'Question', 'doctorpilot' ), 'type' => 'text', 'store' => 'title' ),
					'answer'   => array( 'label' => __( 'Answer', 'doctorpilot' ), 'type' => 'textarea', 'store' => 'content' ),
				),
			),
			self::LOCATION    => array(
				'labels'        => array(
					'singular' => __( 'Location', 'doctorpilot' ),
					'plural'   => __( 'Locations', 'doctorpilot' ),
					'menu'     => __( 'Locations', 'doctorpilot' ),
				),
				// The location name IS the post title; opening hours are the content.
				'title_parts'   => array(),
				'list_subtitle' => 'city',
				'fields'        => array(
					'name'    => array( 'label' => __( 'Location name', 'doctorpilot' ), 'type' => 'text', 'store' => 'title' ),
					'area'    => array( 'label' => __( 'Address / area', 'doctorpilot' ), 'type' => 'text' ),
					'city'    => array( 'label' => __( 'City', 'doctorpilot' ), 'type' => 'text' ),
					'phone'   => array( 'label' => __( 'Phone', 'doctorpilot' ), 'type' => 'text' ),
					'email'   => array( 'label' => __( 'Email', 'doctorpilot' ), 'type' => 'email' ),
					'map_url' => array( 'label' => __( 'Map link', 'doctorpilot' ), 'type' => 'url' ),
					'hours'   => array( 'label' => __( 'Opening hours', 'doctorpilot' ), 'type' => 'textarea', 'store' => 'content' ),
				),
			),
		);
	}

	/**
	 * The schema config for a single CPT, or an empty array if unknown.
	 *
	 * @param string $cpt CPT key.
	 * @return array<string, mixed>
	 */
	public static function config( string $cpt ): array {
		$schema = self::schema();

		return $schema[ $cpt ] ?? array();
	}

	/**
	 * The field key stored in post_content for a CPT, or '' when none.
	 *
	 * @param string $cpt CPT key.
	 * @return string
	 */
	public static function content_field( string $cpt ): string {
		foreach ( (array) ( self::config( $cpt )['fields'] ?? array() ) as $key => $field ) {
			if ( 'content' === ( $field['store'] ?? 'meta' ) ) {
				return (string) $key;
			}
		}

		return '';
	}

	/**
	 * The subset of a type's fields that are stored as post meta (i.e. rendered
	 * in the Details meta box). Fields stored in the title or content are
	 * excluded, so a type with none (e.g. FAQ) shows no Details box.
	 *
	 * @param string $cpt CPT key.
	 * @return array<string, array<string, mixed>>
	 */
	public static function meta_fields( string $cpt ): array {
		$out = array();
		foreach ( (array) ( self::config( $cpt )['fields'] ?? array() ) as $key => $field ) {
			if ( 'meta' === ( $field['store'] ?? 'meta' ) ) {
				$out[ $key ] = $field;
			}
		}

		return $out;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_types' ), 6 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );

		foreach ( self::keys() as $key ) {
			add_action( 'save_post_' . $key, array( $this, 'save' ), 10, 2 );
		}
	}

	/**
	 * Give library title fields a type-appropriate placeholder.
	 *
	 * When the title is entered directly (no auto-compose), the placeholder is
	 * the title-stored field label (e.g. "Question" for FAQs). When the title
	 * auto-composes from other fields, it hints that it may be left blank.
	 *
	 * @param string   $text Default placeholder.
	 * @param \WP_Post $post Current post.
	 * @return string
	 */
	public function title_placeholder( string $text, \WP_Post $post ): string {
		$config = self::config( $post->post_type );
		if ( array() === $config ) {
			return $text;
		}

		if ( array() === (array) ( $config['title_parts'] ?? array() ) ) {
			foreach ( $config['fields'] as $field ) {
				if ( 'title' === ( $field['store'] ?? 'meta' ) ) {
					return (string) $field['label'];
				}
			}

			return $text;
		}

		return __( 'Leave blank to name this automatically from the fields below', 'doctorpilot' );
	}

	/**
	 * Register the library post types.
	 *
	 * @return void
	 */
	public function register_post_types(): void {
		foreach ( self::schema() as $key => $config ) {
			$labels = $config['labels'];

			register_post_type(
				$key,
				array(
					'labels'          => array(
						'name'          => $labels['plural'],
						'singular_name' => $labels['singular'],
						'menu_name'     => $labels['menu'],
						/* translators: %s: type singular. */
						'add_new_item'  => sprintf( __( 'Add New %s', 'doctorpilot' ), $labels['singular'] ),
						/* translators: %s: type singular. */
						'edit_item'     => sprintf( __( 'Edit %s', 'doctorpilot' ), $labels['singular'] ),
						'all_items'     => $labels['plural'],
						'not_found'     => __( 'No records yet.', 'doctorpilot' ),
					),
					'public'          => false,
					'show_ui'         => true,
					'show_in_menu'    => 'doctorpilot',
					'show_in_rest'    => false,
					'has_archive'     => false,
					'rewrite'         => false,
					'query_var'       => false,
					'supports'        => array( 'title', 'editor' ),
					'capability_type' => 'doctorpilot_library_item',
					'map_meta_cap'    => true,
					'capabilities'    => $this->capabilities(),
				)
			);
		}
	}

	/**
	 * Map every library capability to the single library-management capability.
	 *
	 * Only the PRIMITIVE capabilities are overridden. The meta capabilities
	 * (`edit_post`, `read_post`, `delete_post`) are deliberately left at their
	 * defaults so `map_meta_cap` resolves them against a specific post and maps
	 * them onto these primitives. Mapping the meta caps directly to a primitive
	 * makes core emit the "check delete_post against a specific post" notice.
	 *
	 * @return array<string, string>
	 */
	private function capabilities(): array {
		$cap = Capabilities::MANAGE_LIBRARY;

		return array(
			'edit_posts'             => $cap,
			'edit_others_posts'      => $cap,
			'publish_posts'          => $cap,
			'read_private_posts'     => $cap,
			'create_posts'           => $cap,
			'delete_posts'           => $cap,
			'delete_private_posts'   => $cap,
			'delete_published_posts' => $cap,
			'delete_others_posts'    => $cap,
			'edit_private_posts'     => $cap,
			'edit_published_posts'   => $cap,
		);
	}

	/**
	 * Add the fields meta box to library edit screens.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		foreach ( self::keys() as $key ) {
			// Types whose only fields are the title and content (e.g. FAQ) have
			// nothing to show in a Details box, so it is not registered.
			if ( array() === self::meta_fields( $key ) ) {
				continue;
			}

			add_meta_box(
				'doctorpilot-library-fields',
				__( 'Details', 'doctorpilot' ),
				array( $this, 'render_meta_box' ),
				$key,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Render the schema-driven library fields for the current type.
	 *
	 * @param \WP_Post $post Library post.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		$config = self::config( $post->post_type );
		if ( array() === $config ) {
			return;
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		echo '<div class="dp-library-fields">';

		foreach ( self::meta_fields( $post->post_type ) as $key => $field ) {
			$value = (string) get_post_meta( $post->ID, self::META_PREFIX . $key, true );
			$this->text_row(
				'dp-lib-' . $key,
				'doctorpilot_lib[' . $key . ']',
				(string) $field['label'],
				$value,
				(string) ( $field['type'] ?? 'text' )
			);
		}

		$content_label = '';
		foreach ( $config['fields'] as $field ) {
			if ( 'content' === ( $field['store'] ?? 'meta' ) ) {
				$content_label = (string) $field['label'];
				break;
			}
		}

		$auto_title = array() !== (array) ( $config['title_parts'] ?? array() );
		if ( '' !== $content_label ) {
			$hint = $auto_title
				/* translators: %s: content field label, e.g. "description". */
				? sprintf( esc_html__( 'Leave the title above blank to generate it automatically from these fields. Use the editor below for the %s.', 'doctorpilot' ), esc_html( strtolower( $content_label ) ) )
				/* translators: %s: content field label, e.g. "answer". */
				: sprintf( esc_html__( 'Use the editor below for the %s.', 'doctorpilot' ), esc_html( strtolower( $content_label ) ) );
			echo '<p class="description">' . $hint . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Composed from escaped parts.
		}

		echo '</div>';
	}

	/**
	 * Render a labelled input row.
	 *
	 * @param string $id    Input id.
	 * @param string $name  Input name.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $type  Schema field type.
	 * @return void
	 */
	private function text_row( string $id, string $name, string $label, string $value, string $type = 'text' ): void {
		$input_type = 'year' === $type ? 'number' : ( 'url' === $type ? 'url' : ( 'email' === $type ? 'email' : 'text' ) );

		echo '<p style="margin:0 0 12px">';
		echo '<label for="' . esc_attr( $id ) . '" style="display:block;font-weight:600;margin-bottom:4px">' . esc_html( $label ) . '</label>';
		printf(
			'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text"%5$s />',
			esc_attr( $input_type ),
			esc_attr( $id ),
			esc_attr( $name ),
			esc_attr( $value ),
			'year' === $type ? ' min="1900" max="2100"' : ''
		);
		echo '</p>';
	}

	/**
	 * Save the library record fields and compose the title.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! in_array( $post->post_type, self::keys(), true ) ) {
			return;
		}

		$config = self::config( $post->post_type );
		if ( array() === $config ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_NAME ] )
			? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_NAME ] ) )
			: '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$raw = isset( $_POST['doctorpilot_lib'] ) && is_array( $_POST['doctorpilot_lib'] )
			? wp_unslash( $_POST['doctorpilot_lib'] )
			: array();

		$values = array();
		foreach ( $config['fields'] as $key => $field ) {
			// Only meta-stored fields are written here; the title and content
			// fields are saved by core.
			if ( 'meta' !== ( $field['store'] ?? 'meta' ) ) {
				continue;
			}

			$clean          = $this->sanitize_value( (string) ( $raw[ $key ] ?? '' ), (string) ( $field['type'] ?? 'text' ) );
			$values[ $key ] = $clean;
			update_post_meta( $post_id, self::META_PREFIX . $key, $clean );
		}

		// The title field is editable. Only auto-compose it from the record's
		// fields when the type has title parts and the editor left the title
		// blank, so a manually entered title is preserved.
		if ( array() !== (array) ( $config['title_parts'] ?? array() ) ) {
			$existing = trim( (string) get_post_field( 'post_title', $post_id ) );
			if ( '' === $existing ) {
				$this->update_title( $post_id, self::compose_title_from( $post->post_type, $values ) );
			}
		}
	}

	/**
	 * Sanitize a raw value for a schema field type.
	 *
	 * @param string $value Raw value.
	 * @param string $type  Field type.
	 * @return string
	 */
	private function sanitize_value( string $value, string $type ): string {
		switch ( $type ) {
			case 'year':
				return self::year( $value );
			case 'url':
				return esc_url_raw( $value );
			case 'email':
				return sanitize_email( $value );
			case 'textarea':
				return sanitize_textarea_field( $value );
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Compose the post title from a type's title parts without recursion.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $title   Composed title.
	 * @return void
	 */
	private function update_title( int $post_id, string $title ): void {
		if ( '' === $title || $title === get_post_field( 'post_title', $post_id ) ) {
			return;
		}

		remove_action( 'save_post_' . get_post_type( $post_id ), array( $this, 'save' ), 10 );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $title,
			)
		);
		add_action( 'save_post_' . get_post_type( $post_id ), array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Compose a display title from a type's title parts and field values.
	 *
	 * @param string               $cpt    CPT key.
	 * @param array<string, mixed>  $values Field key => value.
	 * @return string
	 */
	public static function compose_title_from( string $cpt, array $values ): string {
		$parts = array();
		foreach ( (array) ( self::config( $cpt )['title_parts'] ?? array() ) as $key ) {
			$value = trim( (string) ( $values[ $key ] ?? '' ) );
			if ( '' !== $value ) {
				$parts[] = $value;
			}
		}

		return implode( ' — ', $parts );
	}

	/**
	 * Sanitize a 4-digit year.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function year( $value ): string {
		$year = (int) $value;

		return ( $year >= 1900 && $year <= 2100 ) ? (string) $year : '';
	}
}
