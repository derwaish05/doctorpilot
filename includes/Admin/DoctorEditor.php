<?php
/**
 * Guided doctor editor meta boxes.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Admin;

use BrainStudioz\DoctorPilot\Application\Doctors\SaveDoctor;
use BrainStudioz\DoctorPilot\Domain\Doctor\Completeness;
use BrainStudioz\DoctorPilot\Fields\FieldRegistry;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the tabbed doctor editor and the review/completeness side panel.
 *
 * Fields are rendered from the registry and grouped into accessible tabs.
 * Persistence is owned by {@see SaveDoctor}; this class only renders inputs and
 * enqueues the editor assets on the doctor screen.
 */
final class DoctorEditor {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes_' . DoctorPostType::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the editor meta boxes.
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'doctorpilot-details',
			__( 'Doctor Details', 'doctorpilot' ),
			array( $this, 'render_details' ),
			DoctorPostType::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'doctorpilot-status',
			__( 'Review & Completeness', 'doctorpilot' ),
			array( $this, 'render_status' ),
			DoctorPostType::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Enqueue editor assets only on the doctor edit screen.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( null === $screen || DoctorPostType::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'doctorpilot-admin-editor',
			DOCTORPILOT_PLUGIN_URL . 'assets/css/admin-editor.css',
			array(),
			self::asset_version( 'assets/css/admin-editor.css' )
		);

		wp_enqueue_script(
			'doctorpilot-admin-editor',
			DOCTORPILOT_PLUGIN_URL . 'assets/js/admin-editor.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			self::asset_version( 'assets/js/admin-editor.js' ),
			true
		);

		wp_localize_script(
			'doctorpilot-admin-editor',
			'doctorPilotEditor',
			array(
				'chooseImage' => __( 'Choose image', 'doctorpilot' ),
				'removeImage' => __( 'Remove', 'doctorpilot' ),
				'confirmRow'  => __( 'Remove this row?', 'doctorpilot' ),
			)
		);
	}

	/**
	 * Cache-busting version for an admin asset: file modification time when
	 * available, otherwise the plugin version. Ensures edited CSS/JS is never
	 * served stale from a browser or page cache.
	 *
	 * @param string $relative_path Path relative to the plugin directory.
	 * @return string
	 */
	private static function asset_version( string $relative_path ): string {
		$file = DOCTORPILOT_PLUGIN_DIR . $relative_path;
		$mtime = is_readable( $file ) ? (int) filemtime( $file ) : 0;

		return $mtime > 0 ? (string) $mtime : DOCTORPILOT_VERSION;
	}

	/**
	 * Render the tabbed details meta box.
	 *
	 * @param \WP_Post $post Doctor post.
	 * @return void
	 */
	public function render_details( \WP_Post $post ): void {
		wp_nonce_field( SaveDoctor::NONCE_ACTION, SaveDoctor::NONCE_NAME );

		$groups = FieldRegistry::groups();
		$help   = self::group_help();
		$total  = count( $groups );

		echo '<div class="dp-editor">';

		// WordPress-native tab strip (role="tablist" of buttons).
		echo '<div class="dp-tabs" role="tablist" aria-label="' . esc_attr__( 'Doctor detail sections', 'doctorpilot' ) . '">';
		$first = true;
		foreach ( $groups as $group_key => $group_label ) {
			$count = $this->group_count( $post->ID, $group_key );

			printf(
				'<button type="button" class="dp-tab%1$s" role="tab" aria-selected="%2$s" aria-controls="dp-panel-%3$s" id="dp-tab-%3$s" data-panel="dp-panel-%3$s">%4$s',
				$first ? ' is-active' : '',
				$first ? 'true' : 'false',
				esc_attr( $group_key ),
				esc_html( $group_label )
			);

			if ( $count > 0 ) {
				printf(
					'<span class="dp-tab__count" aria-label="%1$s">%2$d</span>',
					/* translators: %d: number of items in the section. */
					esc_attr( sprintf( _n( '%d item', '%d items', $count, 'doctorpilot' ), $count ) ),
					(int) $count
				);
			}

			echo '</button>';
			$first = false;
		}
		echo '</div>';

		// Panels, each with a pane header (title, help, section meta).
		$first = true;
		$index = 0;
		foreach ( $groups as $group_key => $group_label ) {
			++$index;

			printf(
				'<div class="dp-panel%1$s" role="tabpanel" id="dp-panel-%2$s" aria-labelledby="dp-tab-%2$s"%3$s>',
				$first ? ' is-active' : '',
				esc_attr( $group_key ),
				$first ? '' : ' hidden'
			);

			echo '<div class="dp-pane-head">';
			echo '<div class="dp-pane-head__text">';
			echo '<h3 class="dp-pane-title">' . esc_html( $group_label ) . '</h3>';
			if ( isset( $help[ $group_key ] ) && '' !== $help[ $group_key ] ) {
				echo '<p class="dp-pane-help">' . esc_html( $help[ $group_key ] ) . '</p>';
			}
			echo '</div>';
			printf(
				'<span class="dp-pane-meta">%s</span>',
				esc_html(
					sprintf(
						/* translators: 1: current section number, 2: total number of sections. */
						__( 'Section %1$d of %2$d', 'doctorpilot' ),
						$index,
						$total
					)
				)
			);
			echo '</div>';

			echo '<div class="dp-pane-body">';
			foreach ( FieldRegistry::by_group( $group_key ) as $field ) {
				$this->render_field( $post->ID, $field );
			}
			if ( 'seo' === $group_key ) {
				$this->render_seo_preview( $post->ID );
			}
			echo '</div>';

			echo '</div>';
			$first = false;
		}

		echo '</div>';
	}

	/**
	 * Short help text shown under each pane title.
	 *
	 * @return array<string, string>
	 */
	private static function group_help(): array {
		return array(
			'profile'        => __( 'Name, photo, and how this doctor appears on listing cards.', 'doctorpilot' ),
			'professional'   => __( 'Departments, specialties, experience, and languages.', 'doctorpilot' ),
			'clinical'       => __( 'Symptoms, conditions, treatments, and procedures.', 'doctorpilot' ),
			'history'        => __( 'Education and professional experience.', 'doctorpilot' ),
			'certifications' => __( 'Certifications, memberships, and publications.', 'doctorpilot' ),
			'location'       => __( 'Where this doctor practises and how to reach them.', 'doctorpilot' ),
			'booking'        => __( 'How patients book an appointment with this doctor.', 'doctorpilot' ),
			'seo'            => __( 'Search engine title, description, and indexing.', 'doctorpilot' ),
			'faqs'           => __( 'Frequently asked questions for this doctor.', 'doctorpilot' ),
		);
	}

	/**
	 * Count the assigned terms, repeater rows, and list items in a group.
	 *
	 * Powers the numeric badge on each tab so editors can see, at a glance,
	 * which sections already have content.
	 *
	 * @param int    $post_id   Doctor post ID.
	 * @param string $group_key Group key.
	 * @return int
	 */
	private function group_count( int $post_id, string $group_key ): int {
		$count = 0;

		foreach ( FieldRegistry::by_group( $group_key ) as $field ) {
			$input = (string) $field['input'];

			if ( 'terms' === $input ) {
				$terms  = wp_get_object_terms( $post_id, (string) $field['taxonomy'], array( 'fields' => 'ids' ) );
				$count += is_wp_error( $terms ) ? 0 : count( $terms );
			} elseif ( 'repeater' === $input || 'lines' === $input ) {
				$value  = get_post_meta( $post_id, (string) $field['key'], true );
				$count += is_array( $value ) ? count( $value ) : 0;
			}
		}

		return $count;
	}

	/**
	 * Render a single field row.
	 *
	 * @param int                  $post_id Doctor post ID.
	 * @param array<string, mixed> $field   Field definition.
	 * @return void
	 */
	private function render_field( int $post_id, array $field ): void {
		$storage = (string) $field['storage'];
		$input   = (string) $field['input'];

		// Native columns and derived/internal fields are not rendered here.
		if ( in_array( $storage, array( 'post_title', 'post_content', 'post_excerpt', 'thumbnail', 'derived' ), true ) ) {
			return;
		}
		if ( 'none' === $input ) {
			return;
		}

		$key     = (string) $field['key'];
		$label   = (string) $field['label'];
		$desc    = (string) $field['description'];
		$name    = 'doctorpilot[' . $key . ']';
		$id      = 'dp-' . sanitize_html_class( str_replace( array( '_', 'terms' ), array( '-', 'terms' ), $key ) );
		$private = ! empty( $field['private'] );

		// Term and repeater fields get a header row with a live count on the right.
		$is_block = in_array( $input, array( 'terms', 'repeater' ), true );

		echo '<div class="dp-field dp-field--' . esc_attr( $input ) . '">';

		if ( $is_block ) {
			$unit  = 'terms' === $input ? 'selected' : 'row';
			$count = $this->field_count( $post_id, $field );

			echo '<div class="dp-field__head">';
			echo '<span class="dp-field__label">' . esc_html( $label ) . $this->private_badge( $private ) . '</span>';
			printf(
				'<span class="dp-field__count" data-dp-count="%1$s">%2$s</span>',
				esc_attr( $unit ),
				esc_html( $this->count_label( $count, $unit ) )
			);
			echo '</div>';
		} elseif ( 'checkbox' !== $input ) {
			echo '<label class="dp-label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . $this->private_badge( $private ) . '</label>';
		}

		switch ( $input ) {
			case 'terms':
				$this->render_terms_field( $post_id, $field, $name, $id );
				break;
			case 'select':
				$this->render_select_field( $post_id, $field, $name, $id );
				break;
			case 'checkbox':
				$this->render_checkbox_field( $post_id, $field, $name, $id, $label, $desc );
				break;
			case 'number':
				$this->render_scalar_input( $post_id, $key, $name, $id, 'number' );
				break;
			case 'textarea':
				$this->render_textarea( $post_id, $key, $name, $id );
				break;
			case 'lines':
				$this->render_lines( $post_id, $key, $name, $id );
				break;
			case 'repeater':
				$this->render_repeater( $post_id, $field, $key );
				break;
			case 'text':
			default:
				$this->render_scalar_input( $post_id, $key, $name, $id, 'text' );
				break;
		}

		// Repeaters show their hint next to "Add row"; checkboxes show it inline.
		if ( '' !== $desc && ! in_array( $input, array( 'repeater', 'checkbox' ), true ) ) {
			echo '<p class="dp-desc">' . esc_html( $desc ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * A small "Private" badge for private fields, or an empty string.
	 *
	 * @param bool $private Whether the field is private.
	 * @return string
	 */
	private function private_badge( bool $private ): string {
		if ( ! $private ) {
			return '';
		}

		return ' <span class="dp-badge dp-badge--private">' . esc_html__( 'Private', 'doctorpilot' ) . '</span>';
	}

	/**
	 * Count the assigned terms or repeater rows for a single field.
	 *
	 * @param int                  $post_id Doctor post ID.
	 * @param array<string, mixed> $field   Field definition.
	 * @return int
	 */
	private function field_count( int $post_id, array $field ): int {
		$input = (string) $field['input'];

		if ( 'terms' === $input ) {
			$terms = wp_get_object_terms( $post_id, (string) $field['taxonomy'], array( 'fields' => 'ids' ) );
			return is_wp_error( $terms ) ? 0 : count( $terms );
		}

		if ( 'repeater' === $input || 'lines' === $input ) {
			$value = get_post_meta( $post_id, (string) $field['key'], true );
			return is_array( $value ) ? count( $value ) : 0;
		}

		return 0;
	}

	/**
	 * Build the count label shown on a field header ("3 selected" / "2 rows").
	 *
	 * @param int    $count Count.
	 * @param string $unit  'selected' or 'row'.
	 * @return string
	 */
	private function count_label( int $count, string $unit ): string {
		if ( 'row' === $unit ) {
			/* translators: %d: number of repeater rows. */
			return sprintf( _n( '%d row', '%d rows', $count, 'doctorpilot' ), $count );
		}

		/* translators: %d: number of selected items. */
		return sprintf( __( '%d selected', 'doctorpilot' ), $count );
	}

	/**
	 * Derive a first- or last-name part from an existing post title.
	 *
	 * The last whitespace-separated token is treated as the last name and the
	 * remainder as the first name.
	 *
	 * @param int  $post_id Doctor post ID.
	 * @param bool $last    Whether to return the last-name part.
	 * @return string
	 */
	private function name_part_from_title( int $post_id, bool $last ): string {
		$title = trim( (string) get_post_field( 'post_title', $post_id ) );
		if ( '' === $title ) {
			return '';
		}

		$pos = strrpos( $title, ' ' );
		if ( false === $pos ) {
			return $last ? '' : $title;
		}

		return $last ? trim( substr( $title, $pos + 1 ) ) : trim( substr( $title, 0, $pos ) );
	}

	/**
	 * The character limit for SEO fields, or 0 when a field is uncounted.
	 *
	 * @param string $key Meta key.
	 * @return int
	 */
	private function seo_maxlength( string $key ): int {
		if ( str_ends_with( $key, 'seo_title' ) ) {
			return 60;
		}
		if ( str_ends_with( $key, 'seo_description' ) ) {
			return 155;
		}

		return 0;
	}

	/**
	 * Render an "X / N characters" counter.
	 *
	 * @param int $length Current length.
	 * @param int $max    Maximum length.
	 * @return void
	 */
	private function render_counter( int $length, int $max ): void {
		printf(
			'<span class="dp-counter"><span class="dp-counter__n">%1$d</span> / %2$d %3$s</span>',
			(int) $length,
			(int) $max,
			esc_html__( 'characters', 'doctorpilot' )
		);
	}

	/**
	 * Render the Google-style search preview shown under the SEO fields.
	 *
	 * @param int $post_id Doctor post ID.
	 * @return void
	 */
	private function render_seo_preview( int $post_id ): void {
		$post  = get_post( $post_id );
		$title = (string) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'seo_title', true );
		if ( '' === $title ) {
			$title = get_the_title( $post_id );
			$title = '' !== $title ? $title : __( 'Doctor name', 'doctorpilot' );
		}

		$desc        = (string) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'seo_description', true );
		$placeholder = __( 'Add a meta description to control the snippet shown here.', 'doctorpilot' );
		$host        = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$slug        = ( $post instanceof \WP_Post && '' !== $post->post_name ) ? $post->post_name : 'doctor';

		echo '<div class="dp-serp">';
		echo '<span class="dp-serp__label">' . esc_html__( 'Search preview', 'doctorpilot' ) . '</span>';
		echo '<span class="dp-serp__url">' . esc_html( $host ) . ' &rsaquo; ' . esc_html( DoctorPostType::directory_base() ) . ' &rsaquo; ' . esc_html( $slug ) . '</span>';
		echo '<span class="dp-serp__title" data-dp-serp-title>' . esc_html( $title ) . '</span>';
		printf(
			'<span class="dp-serp__desc" data-dp-serp-desc data-dp-serp-placeholder="%1$s">%2$s</span>',
			esc_attr( $placeholder ),
			esc_html( '' !== $desc ? $desc : $placeholder )
		);
		echo '</div>';
	}

	/**
	 * Render a plain text or number input.
	 *
	 * @param int    $post_id Doctor post ID.
	 * @param string $key     Meta key.
	 * @param string $name    Input name.
	 * @param string $id      Input id.
	 * @param string $type    HTML input type.
	 * @return void
	 */
	private function render_scalar_input( int $post_id, string $key, string $name, string $id, string $type ): void {
		$value = get_post_meta( $post_id, $key, true );
		$value = is_scalar( $value ) ? (string) $value : '';

		// Back-compat: pre-fill First/Last name from an existing post title so
		// doctors created before the name split still edit cleanly.
		if ( '' === $value && ( str_ends_with( $key, 'first_name' ) || str_ends_with( $key, 'last_name' ) ) ) {
			$value = $this->name_part_from_title( $post_id, str_ends_with( $key, 'last_name' ) );
		}

		$max = $this->seo_maxlength( $key );

		// Suppress password-manager / autofill overlays (e.g. the LastPass badge)
		// on these admin fields; they are not login or payment inputs.
		printf(
			'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text" autocomplete="off" data-lpignore="true" data-1p-ignore data-form-type="other"%5$s%6$s />',
			esc_attr( $type ),
			esc_attr( $id ),
			esc_attr( $name ),
			esc_attr( $value ),
			'number' === $type ? ' min="0"' : '',
			$max > 0 ? ' maxlength="' . (int) $max . '" data-dp-counter="' . (int) $max . '"' : ''
		);

		if ( $max > 0 ) {
			$this->render_counter( strlen( $value ), $max );
		}
	}

	/**
	 * Render a textarea.
	 *
	 * @param int    $post_id Doctor post ID.
	 * @param string $key     Meta key.
	 * @param string $name    Input name.
	 * @param string $id      Input id.
	 * @return void
	 */
	private function render_textarea( int $post_id, string $key, string $name, string $id ): void {
		$value = get_post_meta( $post_id, $key, true );
		$value = is_scalar( $value ) ? (string) $value : '';
		$max   = $this->seo_maxlength( $key );

		printf(
			'<textarea id="%1$s" name="%2$s" rows="3" class="large-text"%4$s>%3$s</textarea>',
			esc_attr( $id ),
			esc_attr( $name ),
			esc_textarea( $value ),
			$max > 0 ? ' maxlength="' . (int) $max . '" data-dp-counter="' . (int) $max . '"' : ''
		);

		if ( $max > 0 ) {
			$this->render_counter( strlen( $value ), $max );
		}
	}

	/**
	 * Render a newline list textarea.
	 *
	 * @param int    $post_id Doctor post ID.
	 * @param string $key     Meta key.
	 * @param string $name    Input name.
	 * @param string $id      Input id.
	 * @return void
	 */
	private function render_lines( int $post_id, string $key, string $name, string $id ): void {
		$value = get_post_meta( $post_id, $key, true );
		$lines = array();

		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( is_array( $item ) ) {
					// Back-compat: an older repeater row is flattened to one line.
					$parts   = array_filter( array_map( 'strval', $item ), 'strlen' );
					$lines[] = implode( ' — ', $parts );
				} else {
					$lines[] = (string) $item;
				}
			}
		}

		printf(
			'<textarea id="%1$s" name="%2$s" rows="4" class="large-text">%3$s</textarea>',
			esc_attr( $id ),
			esc_attr( $name ),
			esc_textarea( implode( "\n", array_filter( $lines, 'strlen' ) ) )
		);
	}

	/**
	 * Render a select input.
	 *
	 * @param int                  $post_id Doctor post ID.
	 * @param array<string, mixed> $field   Field definition.
	 * @param string               $name    Input name.
	 * @param string               $id      Input id.
	 * @return void
	 */
	private function render_select_field( int $post_id, array $field, string $name, string $id ): void {
		$key = (string) $field['key'];

		// Primary term selects derive their options from the taxonomy terms.
		$options = $field['options'];
		if ( '' === $options || array() === $options ) {
			$options = $this->primary_term_options( $key );
		}

		$current = (string) get_post_meta( $post_id, $key, true );
		if ( '' === $current && '' !== (string) $field['default'] ) {
			$current = (string) $field['default'];
		}

		printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
		foreach ( (array) $options as $value => $option_label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( (string) $value ),
				selected( $current, (string) $value, false ),
				esc_html( (string) $option_label )
			);
		}
		echo '</select>';
	}

	/**
	 * Options for a primary department/specialty select.
	 *
	 * @param string $key Meta key.
	 * @return array<string, string>
	 */
	private function primary_term_options( string $key ): array {
		$taxonomy = false !== strpos( $key, 'primary_department' )
			? \BrainStudioz\DoctorPilot\Taxonomies\Taxonomies::DEPARTMENT
			: \BrainStudioz\DoctorPilot\Taxonomies\Taxonomies::SPECIALTY;

		$options = array( '' => __( '— Select —', 'doctorpilot' ) );
		$terms   = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 300,
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[ (string) $term->term_id ] = $term->name;
			}
		}

		return $options;
	}

	/**
	 * Render a checkbox input.
	 *
	 * @param int                  $post_id Doctor post ID.
	 * @param array<string, mixed> $field   Field definition.
	 * @param string               $name    Input name.
	 * @param string               $id      Input id.
	 * @param string               $label   Field label.
	 * @return void
	 */
	private function render_checkbox_field( int $post_id, array $field, string $name, string $id, string $label, string $desc = '' ): void {
		$checked = ! empty( get_post_meta( $post_id, (string) $field['key'], true ) );

		echo '<label class="dp-flag">';
		printf(
			'<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s />',
			esc_attr( $id ),
			esc_attr( $name ),
			checked( $checked, true, false )
		);
		echo '<span class="dp-flag__text">';
		echo '<span class="dp-flag__title">' . esc_html( $label ) . '</span>';
		if ( '' !== $desc ) {
			echo '<span class="dp-flag__desc">' . esc_html( $desc ) . '</span>';
		}
		echo '</span></label>';
	}

	/**
	 * Render a bounded taxonomy assignment field as checkboxes.
	 *
	 * @param int                  $post_id Doctor post ID.
	 * @param array<string, mixed> $field   Field definition.
	 * @param string               $name    Base input name.
	 * @return void
	 */
	private function render_terms_field( int $post_id, array $field, string $name, string $id ): void {
		$taxonomy = (string) $field['taxonomy'];
		$terms    = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 300,
			)
		);

		// Empty-taxonomy state with a direct "add terms" link.
		if ( is_wp_error( $terms ) || array() === $terms ) {
			$add_url = admin_url(
				'edit-tags.php?taxonomy=' . rawurlencode( $taxonomy ) . '&post_type=' . rawurlencode( DoctorPostType::POST_TYPE )
			);

			echo '<div class="dp-empty-tax">';
			echo '<span>' . esc_html__( 'No terms in this taxonomy yet.', 'doctorpilot' ) . '</span>';
			printf(
				'<a class="dp-empty-tax__add" href="%1$s">%2$s <span aria-hidden="true">&rarr;</span></a>',
				esc_url( $add_url ),
				esc_html__( 'Add terms', 'doctorpilot' )
			);
			echo '</div>';
			return;
		}

		$assigned = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
		$assigned = is_wp_error( $assigned ) ? array() : array_map( 'intval', $assigned );

		// Selectable pill/chip group. Each chip wraps a visually-hidden but
		// focusable checkbox, so keyboard and screen-reader users can toggle it.
		echo '<div class="dp-chips" role="group" id="' . esc_attr( $id ) . '">';
		foreach ( $terms as $term ) {
			$selected = in_array( (int) $term->term_id, $assigned, true );
			printf(
				'<label class="dp-chip%1$s"><input type="checkbox" name="%2$s[]" value="%3$d" %4$s /><span class="dp-chip__check dashicons dashicons-yes" aria-hidden="true"></span><span class="dp-chip__label">%5$s</span></label>',
				$selected ? ' is-selected' : '',
				esc_attr( $name ),
				(int) $term->term_id,
				checked( $selected, true, false ),
				esc_html( $term->name )
			);
		}
		echo '</div>';
	}

	/**
	 * Render a repeater field.
	 *
	 * @param int                  $post_id Doctor post ID.
	 * @param array<string, mixed> $field   Field definition.
	 * @param string               $key     Meta key.
	 * @return void
	 */
	private function render_repeater( int $post_id, array $field, string $key ): void {
		$schema = is_array( $field['repeater'] ) ? $field['repeater'] : array();
		$rows   = get_post_meta( $post_id, $key, true );
		$rows   = is_array( $rows ) ? $rows : array();

		$desc = (string) $field['description'];

		echo '<div class="dp-repeater" data-key="' . esc_attr( $key ) . '">';
		echo '<div class="dp-rows">';

		$index = 0;
		foreach ( $rows as $row ) {
			$this->render_repeater_row( $key, $schema, is_array( $row ) ? $row : array(), (string) $index );
			++$index;
		}

		echo '</div>';

		// Row template (index placeholder replaced by JS).
		echo '<script type="text/html" class="dp-row-template">';
		$this->render_repeater_row( $key, $schema, array(), '__INDEX__' );
		echo '</script>';

		echo '<div class="dp-repeater__foot">';
		printf(
			'<button type="button" class="button-link dp-add-row"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> %s</button>',
			esc_html__( 'Add row', 'doctorpilot' )
		);
		if ( '' !== $desc ) {
			echo '<span class="dp-repeater__hint">' . esc_html( $desc ) . '</span>';
		}
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render one repeater row.
	 *
	 * @param string                            $key    Meta key.
	 * @param array<string, array<string,mixed>> $schema Item schema.
	 * @param array<string, mixed>              $row    Row values.
	 * @param string                            $index  Row index or placeholder.
	 * @return void
	 */
	private function render_repeater_row( string $key, array $schema, array $row, string $index ): void {
		echo '<div class="dp-row">';

		// Gutter: drag handle + auto-numbered index (via CSS counter).
		echo '<div class="dp-row__gutter">';
		printf(
			'<span class="dp-row__handle dashicons dashicons-menu" aria-hidden="true" title="%s"></span>',
			esc_attr__( 'Drag to reorder', 'doctorpilot' )
		);
		echo '<span class="dp-row__num" aria-hidden="true"></span>';
		echo '</div>';

		echo '<div class="dp-row__fields">';
		foreach ( $schema as $sub_key => $sub_def ) {
			$sub_label  = isset( $sub_def['label'] ) ? (string) $sub_def['label'] : $sub_key;
			$sanitize   = isset( $sub_def['sanitize'] ) ? (string) $sub_def['sanitize'] : 'text';
			$field_name = 'doctorpilot[' . $key . '][' . $index . '][' . $sub_key . ']';
			$value      = $row[ $sub_key ] ?? '';

			echo '<label class="dp-row-field dp-row-field--' . esc_attr( $sanitize ) . '">';
			echo '<span class="dp-row-field__label">' . esc_html( $sub_label ) . '</span>';

			if ( 'bool' === $sanitize ) {
				printf(
					'<input type="checkbox" name="%1$s" value="1" %2$s />',
					esc_attr( $field_name ),
					checked( ! empty( $value ), true, false )
				);
			} elseif ( 'textarea' === $sanitize ) {
				printf(
					'<textarea name="%1$s" rows="2">%2$s</textarea>',
					esc_attr( $field_name ),
					esc_textarea( is_scalar( $value ) ? (string) $value : '' )
				);
			} else {
				printf(
					'<input type="text" name="%1$s" value="%2$s" />',
					esc_attr( $field_name ),
					esc_attr( is_scalar( $value ) ? (string) $value : '' )
				);
			}
			echo '</label>';
		}
		echo '</div>';

		printf(
			'<button type="button" class="button-link dp-remove-row">%s</button>',
			esc_html__( 'Remove', 'doctorpilot' )
		);
		echo '</div>';
	}

	/**
	 * Render the review status and completeness side box.
	 *
	 * @param \WP_Post $post Doctor post.
	 * @return void
	 */
	public function render_status( \WP_Post $post ): void {
		$review = (string) get_post_meta( $post->ID, FieldRegistry::META_PREFIX . 'review_status', true );
		$review = '' === $review ? 'draft' : $review;

		$completeness = Completeness::for_post( $post->ID );

		echo '<p><label class="dp-label" for="dp-review-status">' . esc_html__( 'Review status', 'doctorpilot' ) . '</label>';
		echo '<select id="dp-review-status" name="doctorpilot[' . esc_attr( FieldRegistry::META_PREFIX . 'review_status' ) . ']">';
		$statuses = array(
			'draft'        => __( 'Draft', 'doctorpilot' ),
			'needs_review' => __( 'Needs review', 'doctorpilot' ),
			'approved'     => __( 'Approved', 'doctorpilot' ),
		);
		foreach ( $statuses as $value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $review, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select></p>';

		printf(
			'<p class="dp-completeness"><strong>%s</strong> %d%%</p>',
			esc_html__( 'Completeness:', 'doctorpilot' ),
			(int) $completeness['score']
		);

		if ( array() !== $completeness['missing'] ) {
			echo '<p>' . esc_html__( 'Missing:', 'doctorpilot' ) . '</p><ul class="dp-missing">';
			foreach ( $completeness['missing'] as $missing ) {
				echo '<li>' . esc_html( $missing ) . '</li>';
			}
			echo '</ul>';
		}

		echo '<p class="description">' . esc_html__( 'Publish status is controlled by the standard WordPress Publish box. Only published doctors appear publicly.', 'doctorpilot' ) . '</p>';
	}
}
