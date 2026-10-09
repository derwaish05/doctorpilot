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
use BrainStudioz\DoctorPilot\Library\DoctorContentResolver;
use BrainStudioz\DoctorPilot\Library\LibraryPostTypes;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;
use BrainStudioz\DoctorPilot\Support\Capabilities;

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

		// The Headline is edited inside the Profile tab, so hide the default
		// excerpt box to avoid a duplicate field.
		remove_meta_box( 'postexcerpt', DoctorPostType::POST_TYPE, 'normal' );
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
				'canCreateTerms' => current_user_can( Capabilities::MANAGE_TERMS ),
				'bulk'        => array(
					'show'       => __( 'Bulk edit', 'doctorpilot' ),
					'hide'       => __( 'Close bulk edit', 'doctorpilot' ),
					'copied'     => __( 'Copied to the clipboard.', 'doctorpilot' ),
					'copyFailed' => __( 'Could not copy automatically. Select the text and copy it manually.', 'doctorpilot' ),
					'applied'    => __( 'Applied to the form. Press Update to save.', 'doctorpilot' ),
					'nothing'    => __( 'Nothing to apply: no “### Field” headings were found.', 'doctorpilot' ),
					'unknown'    => __( 'Unrecognised fields were ignored:', 'doctorpilot' ),
					'unmatched'  => __( 'Not found, so not assigned:', 'doctorpilot' ),
					'created'    => __( 'New, created when you press Update:', 'doctorpilot' ),
					'newTag'     => __( 'new', 'doctorpilot' ),
					'extraCells' => __( 'Rows with more columns than the field has were trimmed:', 'doctorpilot' ),
					'fieldApply' => __( 'Apply', 'doctorpilot' ),
					'fieldClose' => __( 'Close', 'doctorpilot' ),
					'hintChips'  => __( 'One name per line. Names must match an existing option.', 'doctorpilot' ),
					'hintTerms'  => __( 'One name per line. A name that does not exist yet is added as a new one when you press Update.', 'doctorpilot' ),
					'hintRows'   => __( 'One row per line, columns separated by |:', 'doctorpilot' ),
				),
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
		$titles = self::group_pane_titles();

		echo '<div class="dp-editor">';

		$this->render_bulk_edit();

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

		// Panels, each with a pane header (title, help, row count).
		$first = true;
		foreach ( $groups as $group_key => $group_label ) {
			printf(
				'<div class="dp-panel%1$s" role="tabpanel" id="dp-panel-%2$s" aria-labelledby="dp-tab-%2$s"%3$s>',
				$first ? ' is-active' : '',
				esc_attr( $group_key ),
				$first ? '' : ' hidden'
			);

			echo '<div class="dp-pane-head">';
			echo '<div class="dp-pane-head__text">';
			echo '<h3 class="dp-pane-title">' . esc_html( $titles[ $group_key ] ?? $group_label ) . '</h3>';
			if ( isset( $help[ $group_key ] ) && '' !== $help[ $group_key ] ) {
				echo '<p class="dp-pane-help">' . esc_html( $help[ $group_key ] ) . '</p>';
			}
			echo '</div>';

			$rows = $this->group_row_count( $post->ID, $group_key );
			if ( $rows > 0 ) {
				echo '<span class="dp-pane-meta">' . esc_html( $this->count_label( $rows, 'row' ) ) . '</span>';
			}
			echo '</div>';

			echo '<div class="dp-pane-body">';
			$this->render_pane_fields( $post->ID, $group_key );
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
	 * Render the bulk-edit toggle and its (initially hidden) text panel.
	 *
	 * The panel is a view over the form: admin-editor.js writes the current field
	 * values into the textarea and, on Apply, writes pasted text back into the same
	 * inputs. Nothing is saved until the editor presses Update, so persistence and
	 * sanitization stay with {@see SaveDoctor}.
	 *
	 * @return void
	 */
	private function render_bulk_edit(): void {
		echo '<div class="dp-bulk">';
		printf(
			'<button type="button" class="button dp-bulk__toggle" aria-expanded="false" aria-controls="dp-bulk-panel"><span class="dashicons dashicons-edit-page" aria-hidden="true"></span> <span class="dp-bulk__toggle-label">%s</span></button>',
			esc_html__( 'Bulk edit', 'doctorpilot' )
		);
		echo '<span class="dp-bulk__intro">' . esc_html__( 'Edit every section as plain text, so details can be copied from another doctor or a document and pasted in.', 'doctorpilot' ) . '</span>';

		echo '<div class="dp-bulk__panel" id="dp-bulk-panel" hidden>';
		echo '<label class="dp-label" for="dp-bulk-text">' . esc_html__( 'All doctor details', 'doctorpilot' ) . '</label>';
		echo '<textarea id="dp-bulk-text" class="dp-bulk__text large-text" rows="18" spellcheck="false" autocomplete="off" data-lpignore="true" data-1p-ignore></textarea>';
		echo '<ul class="dp-bulk__help">';
		echo '<li>' . wp_kses(
			__( '<code>## Section</code> and <code>### Field</code> headings name where each value goes. Fields you leave out are not changed; a field with nothing under it is cleared.', 'doctorpilot' ),
			array( 'code' => array() )
		) . '</li>';
		echo '<li>' . esc_html__( 'Specialties, departments, languages and other chip lists: one name per line. Names that do not exist yet are added as new terms when you press Update; shared library records must already exist.', 'doctorpilot' ) . '</li>';
		echo '<li>' . wp_kses(
			__( 'Repeater rows (education, experience, FAQs…): one row per line, columns separated by <code>|</code>, in the order shown in the editor. Write <code>\|</code> for a literal pipe and <code>\n</code> for a line break inside a cell.', 'doctorpilot' ),
			array( 'code' => array() )
		) . '</li>';
		echo '<li>' . esc_html__( 'Yes/no options: write yes or no.', 'doctorpilot' ) . '</li>';
		echo '</ul>';
		echo '<div class="dp-bulk__actions">';
		printf( '<button type="button" class="button button-primary dp-bulk__apply">%s</button>', esc_html__( 'Apply to form', 'doctorpilot' ) );
		printf( '<button type="button" class="button dp-bulk__copy">%s</button>', esc_html__( 'Copy', 'doctorpilot' ) );
		printf( '<button type="button" class="button dp-bulk__reset">%s</button>', esc_html__( 'Reload from form', 'doctorpilot' ) );
		echo '</div>';
		echo '<div class="dp-bulk__status" role="status" aria-live="polite"></div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Short help text shown under each pane title.
	 *
	 * @return array<string, string>
	 */
	private static function group_help(): array {
		return array(
			'profile'        => __( 'Name and identity as they appear in search results, listing cards and the public profile header.', 'doctorpilot' ),
			'professional'   => __( 'Taxonomy assignments drive filtering and related-doctor modules. Select every term that applies, then name the primary one.', 'doctorpilot' ),
			'highlights'     => __( 'The stat cards and achievement lists shown in the Highlights section of the public profile.', 'doctorpilot' ),
			'clinical'       => __( 'What patients can search for. Symptoms and treatments feed the “find care for…” index.', 'doctorpilot' ),
			'history'        => __( 'Training, fellowships and appointments. One row per entry.', 'doctorpilot' ),
			'certifications' => __( 'Board certifications, memberships and peer-reviewed work.', 'doctorpilot' ),
			'booking'        => __( 'How patients book an appointment with this doctor.', 'doctorpilot' ),
			'seo'            => __( 'Overrides for the profile’s search appearance. Leave blank to inherit the site defaults.', 'doctorpilot' ),
			'faqs'           => __( 'Answers shown on the profile and exposed as FAQ schema.', 'doctorpilot' ),
		);
	}

	/**
	 * Sentence-case pane titles (the tab labels stay Title Case).
	 *
	 * @return array<string, string>
	 */
	private static function group_pane_titles(): array {
		return array(
			'profile'        => __( 'Profile', 'doctorpilot' ),
			'professional'   => __( 'Professional information', 'doctorpilot' ),
			'highlights'     => __( 'Highlights', 'doctorpilot' ),
			'clinical'       => __( 'Clinical services', 'doctorpilot' ),
			'history'        => __( 'Experience & education', 'doctorpilot' ),
			'certifications' => __( 'Certifications & publications', 'doctorpilot' ),
			'booking'        => __( 'Booking', 'doctorpilot' ),
			'seo'            => __( 'SEO', 'doctorpilot' ),
			'faqs'           => __( 'Frequently asked questions', 'doctorpilot' ),
		);
	}

	/**
	 * Total repeater rows in a group (used for the pane's "N rows" meta).
	 *
	 * @param int    $post_id   Doctor post ID.
	 * @param string $group_key Group key.
	 * @return int
	 */
	private function group_row_count( int $post_id, string $group_key ): int {
		$rows = 0;

		foreach ( FieldRegistry::by_group( $group_key ) as $field ) {
			if ( 'repeater' === (string) $field['input'] ) {
				$value = get_post_meta( $post_id, (string) $field['key'], true );
				$rows += is_array( $value ) ? count( $value ) : 0;
			}
		}

		return $rows;
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
			} elseif ( in_array( $input, array( 'repeater', 'lines', 'library', 'single' ), true ) ) {
				$value  = get_post_meta( $post_id, (string) $field['key'], true );
				$count += is_array( $value ) ? count( $value ) : 0;
			}
		}

		return $count;
	}

	/**
	 * Render a group's fields, grouping consecutive fields that share a `row`
	 * into a grid and consecutive fields that share a `box` into a fieldset.
	 *
	 * @param int    $post_id   Doctor post ID.
	 * @param string $group_key Group key.
	 * @return void
	 */
	private function render_pane_fields( int $post_id, string $group_key ): void {
		$fields = array();
		foreach ( FieldRegistry::by_group( $group_key ) as $field ) {
			if ( $this->is_renderable( $field ) ) {
				$fields[] = $field;
			}
		}

		$total = count( $fields );
		$i     = 0;

		// Top-level walk groups consecutive fields into a box first; inside a box,
		// members are further grouped into rows.
		while ( $i < $total ) {
			$box = (string) ( $fields[ $i ]['box'] ?? '' );

			if ( '' !== $box ) {
				$members = array();
				while ( $i < $total && (string) ( $fields[ $i ]['box'] ?? '' ) === $box ) {
					$members[] = $fields[ $i ];
					++$i;
				}

				list( $label, $private, $box_desc ) = $this->box_meta( $box );

				echo '<fieldset class="dp-fieldset">';
				echo '<legend class="dp-fieldset__legend">' . esc_html( $label ) . $this->private_badge( $private ) . '</legend>';
				$this->render_members( $post_id, $members );
				if ( '' !== $box_desc ) {
					echo '<p class="dp-desc dp-fieldset__desc">' . esc_html( $box_desc ) . '</p>';
				}
				echo '</fieldset>';
			} else {
				// Collect the run of non-box fields and render with row grouping.
				$members = array();
				while ( $i < $total && '' === (string) ( $fields[ $i ]['box'] ?? '' ) ) {
					$members[] = $fields[ $i ];
					++$i;
				}
				$this->render_members( $post_id, $members );
			}
		}
	}

	/**
	 * Render a list of fields, grouping consecutive same-`row` fields into a grid.
	 *
	 * @param int                            $post_id Doctor post ID.
	 * @param array<int, array<string,mixed>> $members Field list.
	 * @return void
	 */
	private function render_members( int $post_id, array $members ): void {
		$total = count( $members );
		$i     = 0;

		while ( $i < $total ) {
			$row = (string) ( $members[ $i ]['row'] ?? '' );

			if ( '' !== $row ) {
				$columns = array();
				$group   = array();
				while ( $i < $total && (string) ( $members[ $i ]['row'] ?? '' ) === $row ) {
					$group[]   = $members[ $i ];
					$columns[] = '' !== (string) ( $members[ $i ]['width'] ?? '' ) ? (string) $members[ $i ]['width'] : 'minmax(0, 1fr)';
					++$i;
				}
				printf( '<div class="dp-grid" style="grid-template-columns:%s">', esc_attr( implode( ' ', $columns ) ) );
				foreach ( $group as $member ) {
					$this->render_field( $post_id, $member );
				}
				echo '</div>';
			} else {
				$this->render_field( $post_id, $members[ $i ] );
				++$i;
			}
		}
	}

	/**
	 * Whether a field produces editor output.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return bool
	 */
	private function is_renderable( array $field ): bool {
		$storage = (string) $field['storage'];
		$input   = (string) $field['input'];

		if ( in_array( $storage, array( 'post_title', 'post_content', 'thumbnail', 'derived' ), true ) ) {
			return false;
		}

		return 'none' !== $input;
	}

	/**
	 * Box legend label, private flag, and description.
	 *
	 * @param string $box Box key.
	 * @return array{0:string,1:bool,2:string}
	 */
	private function box_meta( string $box ): array {
		$boxes = array(
			'listing_flags' => array( __( 'Listing flags', 'doctorpilot' ), false, '' ),
			'licensing'     => array(
				__( 'Licensing', 'doctorpilot' ),
				true,
				__( 'Never shown publicly — stored for internal credential audits.', 'doctorpilot' ),
			),
			'experience'    => array( __( 'Experience', 'doctorpilot' ), false, '' ),
			'education'     => array( __( 'Education', 'doctorpilot' ), false, '' ),
			'outreach'      => array( __( 'Community outreach', 'doctorpilot' ), false, '' ),
			'profession'    => array( __( 'Health care profession', 'doctorpilot' ), false, '' ),
		);

		return $boxes[ $box ] ?? array( ucwords( str_replace( '_', ' ', $box ) ), false, '' );
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
		if ( in_array( $storage, array( 'post_title', 'post_content', 'thumbnail', 'derived' ), true ) ) {
			return;
		}
		if ( 'none' === $input ) {
			return;
		}

		// The Headline is stored in post_excerpt; submit it under the core
		// `excerpt` name so WordPress persists it, and render a single-line input.
		if ( 'post_excerpt' === $storage ) {
			$this->render_headline_field( $post_id, $field );
			return;
		}

		$key   = (string) $field['key'];
		$label = (string) $field['label'];
		$desc  = (string) $field['description'];
		$name  = 'doctorpilot[' . $key . ']';
		$id    = 'dp-' . sanitize_html_class( str_replace( array( '_', 'terms' ), array( '-', 'terms' ), $key ) );

		// The box legend carries the "Private" badge, so suppress the per-field one there.
		$badge = $this->private_badge( ! empty( $field['private'] ) && '' === (string) $field['box'] );

		// Term, library, and repeater fields get a header row with a live count.
		$is_block = in_array( $input, array( 'terms', 'library', 'repeater', 'single' ), true );

		echo '<div class="dp-field dp-field--' . esc_attr( $input ) . '">';

		if ( $is_block ) {
			echo '<div class="dp-field__head">';
			echo '<span class="dp-field__label">' . esc_html( $label ) . $badge . '</span>';

			if ( 'repeater' === $input ) {
				$count = $this->field_count( $post_id, $field );
				printf(
					'<span class="dp-field__count" data-dp-count="row">%s</span>',
					esc_html( $this->count_label( $count, 'row' ) )
				);
			} elseif ( is_array( $field['count_noun'] ) && 2 === count( $field['count_noun'] ) ) {
				$count = $this->field_count( $post_id, $field );
				$noun  = $field['count_noun'];
				printf(
					'<span class="dp-field__count" data-dp-count="selected" data-dp-noun-one="%1$s" data-dp-noun-many="%2$s">%3$s</span>',
					esc_attr( (string) $noun[0] ),
					esc_attr( (string) $noun[1] ),
					esc_html(
						sprintf(
							/* translators: 1: count, 2: noun (e.g. "departments"). */
							__( '%1$d %2$s selected', 'doctorpilot' ),
							$count,
							1 === $count ? (string) $noun[0] : (string) $noun[1]
						)
					)
				);
			}
			echo '</div>';
		} elseif ( 'checkbox' !== $input ) {
			echo '<label class="dp-label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . $badge . '</label>';
		}

		switch ( $input ) {
			case 'terms':
				$this->render_terms_field( $post_id, $field, $name, $id );
				break;
			case 'library':
				$this->render_library_field( $post_id, $field, $name, $id );
				break;
			case 'select':
				$this->render_select_field( $post_id, $field, $name, $id );
				break;
			case 'checkbox':
				$this->render_checkbox_field( $post_id, $field, $name, $id, $label, $desc );
				break;
			case 'number':
				$this->render_scalar_input( $post_id, $key, $name, $id, 'number', $this->effective_placeholder( $post_id, $key, (string) $field['placeholder'] ) );
				break;
			case 'textarea':
				$this->render_textarea( $post_id, $key, $name, $id, $this->effective_placeholder( $post_id, $key, (string) $field['placeholder'] ) );
				break;
			case 'lines':
				$this->render_lines( $post_id, $key, $name, $id, (string) $field['placeholder'] );
				break;
			case 'repeater':
				$this->render_repeater( $post_id, $field, $key );
				break;
			case 'single':
				$this->render_single( $post_id, $field, $key );
				break;
			case 'text':
			default:
				$this->render_scalar_input( $post_id, $key, $name, $id, 'text', $this->effective_placeholder( $post_id, $key, (string) $field['placeholder'] ) );
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

		if ( in_array( $input, array( 'repeater', 'lines', 'library' ), true ) ) {
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
	 * Resolve the placeholder for a field, deriving SEO fields from the post's
	 * own title, description (headline/excerpt or biography), and permalink.
	 *
	 * @param int    $post_id Doctor post ID.
	 * @param string $key     Field key.
	 * @param string $static  The registry's static placeholder.
	 * @return string
	 */
	private function effective_placeholder( int $post_id, string $key, string $static ): string {
		if ( str_ends_with( $key, 'seo_title' ) ) {
			$title = $this->computed_seo_title( $post_id );
			return '' !== $title ? $title : $static;
		}

		if ( str_ends_with( $key, 'seo_description' ) ) {
			$description = $this->default_seo_description( $post_id );
			return '' !== $description ? $description : $static;
		}

		if ( str_ends_with( $key, 'seo_canonical_url' ) ) {
			$permalink = (string) get_permalink( $post_id );
			return '' !== $permalink ? $permalink : $static;
		}

		return $static;
	}

	/**
	 * The doctor's display name with prefix (e.g. "Dr. Layla Haddad").
	 *
	 * @param int $post_id Doctor post ID.
	 * @return string
	 */
	private function display_name( int $post_id ): string {
		$title = trim( (string) get_the_title( $post_id ) );

		$prefix_key = (string) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'prefix', true );
		$map        = array(
			'dr'   => __( 'Dr.', 'doctorpilot' ),
			'prof' => __( 'Prof.', 'doctorpilot' ),
		);
		$prefix = $map[ $prefix_key ] ?? '';

		if ( '' !== $prefix && 0 === stripos( $title, rtrim( $prefix, '.' ) ) ) {
			$prefix = '';
		}

		return trim( $prefix . ' ' . $title );
	}

	/**
	 * Render a global content-library assignment field as selectable chips.
	 *
	 * @param int                  $post_id Doctor post ID.
	 * @param array<string, mixed> $field   Field definition.
	 * @param string               $name    Base input name.
	 * @param string               $id      Group id.
	 * @return void
	 */
	private function render_library_field( int $post_id, array $field, string $name, string $id ): void {
		$cpt = DoctorContentResolver::cpt( (string) $field['library_type'] );
		if ( '' === $cpt ) {
			return;
		}

		$records = get_posts(
			array(
				'post_type'        => $cpt,
				'post_status'      => array( 'publish', 'draft' ),
				'posts_per_page'   => 200,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);

		$assigned = get_post_meta( $post_id, (string) $field['key'], true );
		$assigned = is_array( $assigned ) ? array_map( 'intval', $assigned ) : array();

		$add_url = admin_url( 'post-new.php?post_type=' . rawurlencode( $cpt ) );

		if ( array() === $records ) {
			echo '<div class="dp-empty-tax">';
			echo '<span>' . esc_html__( 'No shared records yet.', 'doctorpilot' ) . '</span>';
			printf(
				'<a class="dp-empty-tax__add" href="%1$s">%2$s <span aria-hidden="true">&rarr;</span></a>',
				esc_url( $add_url ),
				esc_html__( 'Add a record', 'doctorpilot' )
			);
			echo '</div>';
			return;
		}

		echo '<div class="dp-chips" role="group" id="' . esc_attr( $id ) . '">';
		foreach ( $records as $record ) {
			$selected = in_array( (int) $record->ID, $assigned, true );
			$draft    = 'publish' !== $record->post_status;
			printf(
				'<label class="dp-chip%1$s"><input type="checkbox" name="%2$s[]" value="%3$d" %4$s /><span class="dp-chip__check dashicons dashicons-yes" aria-hidden="true"></span><span class="dp-chip__label">%5$s%6$s</span></label>',
				$selected ? ' is-selected' : '',
				esc_attr( $name ),
				(int) $record->ID,
				checked( $selected, true, false ),
				esc_html( '' !== $record->post_title ? $record->post_title : __( '(untitled)', 'doctorpilot' ) ),
				$draft ? ' <em class="dp-chip__draft">(' . esc_html__( 'draft', 'doctorpilot' ) . ')</em>' : ''
			);
		}
		echo '</div>';
		printf(
			'<p class="dp-desc"><a href="%1$s">%2$s &rarr;</a></p>',
			esc_url( $add_url ),
			esc_html__( 'Add or manage shared records', 'doctorpilot' )
		);
	}

	/**
	 * The default SEO title: the doctor's post title.
	 *
	 * @param int $post_id Doctor post ID.
	 * @return string
	 */
	private function computed_seo_title( int $post_id ): string {
		return trim( (string) get_the_title( $post_id ) );
	}

	/**
	 * The default meta description: the doctor's post description — the headline
	 * (excerpt) when present, otherwise a trimmed excerpt of the biography.
	 *
	 * @param int $post_id Doctor post ID.
	 * @return string
	 */
	private function default_seo_description( int $post_id ): string {
		$excerpt = trim( (string) get_post_field( 'post_excerpt', $post_id ) );
		if ( '' !== $excerpt ) {
			return $excerpt;
		}

		$content = trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ) );

		return '' !== $content ? wp_trim_words( $content, 30, '…' ) : '';
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
		$post = get_post( $post_id );

		// Title: the SEO override, else the post title.
		$title_default = $this->computed_seo_title( $post_id );
		if ( '' === $title_default ) {
			$title_default = __( 'Doctor name', 'doctorpilot' );
		}
		$seo_title = (string) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'seo_title', true );
		$title     = '' !== $seo_title ? $seo_title : $title_default;

		// Description: the SEO override, else the doctor's post description.
		$seo_desc     = (string) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'seo_description', true );
		$desc_default = $this->default_seo_description( $post_id );
		$placeholder  = __( 'Add a meta description to control the snippet shown here.', 'doctorpilot' );
		$desc_shown   = '' !== $seo_desc ? $seo_desc : ( '' !== $desc_default ? $desc_default : $placeholder );

		// URL: the canonical override host/path, else the doctor permalink.
		$permalink = (string) get_permalink( $post_id );
		$canonical = (string) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'seo_canonical_url', true );
		$url       = '' !== $canonical ? $canonical : $permalink;
		$host      = (string) wp_parse_url( '' !== $url ? $url : home_url(), PHP_URL_HOST );
		$slug      = ( $post instanceof \WP_Post && '' !== $post->post_name ) ? $post->post_name : 'doctor';

		echo '<div class="dp-serp">';
		echo '<span class="dp-serp__label">' . esc_html__( 'Search preview', 'doctorpilot' ) . '</span>';
		echo '<span class="dp-serp__url">' . esc_html( $host ) . ' &rsaquo; ' . esc_html( DoctorPostType::directory_base() ) . ' &rsaquo; ' . esc_html( $slug ) . '</span>';
		printf(
			'<span class="dp-serp__title" data-dp-serp-title data-dp-serp-title-default="%1$s">%2$s</span>',
			esc_attr( $title_default ),
			esc_html( $title )
		);
		printf(
			'<span class="dp-serp__desc" data-dp-serp-desc data-dp-serp-default="%1$s" data-dp-serp-placeholder="%2$s">%3$s</span>',
			esc_attr( $desc_default ),
			esc_attr( $placeholder ),
			esc_html( $desc_shown )
		);
		echo '</div>';
	}

	/**
	 * Render the Headline field (stored in post_excerpt via the core name).
	 *
	 * @param int                  $post_id Doctor post ID.
	 * @param array<string, mixed> $field   Field definition.
	 * @return void
	 */
	private function render_headline_field( int $post_id, array $field ): void {
		$value = (string) get_post_field( 'post_excerpt', $post_id );
		$desc  = (string) $field['description'];

		echo '<div class="dp-field dp-field--text">';
		echo '<label class="dp-label" for="dp-headline">' . esc_html( (string) $field['label'] ) . '</label>';
		printf(
			'<input type="text" id="dp-headline" name="excerpt" value="%s" class="dp-input--full" autocomplete="off" data-lpignore="true" data-1p-ignore />',
			esc_attr( $value )
		);
		if ( '' !== $desc ) {
			echo '<p class="dp-desc">' . esc_html( $desc ) . '</p>';
		}
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
	private function render_scalar_input( int $post_id, string $key, string $name, string $id, string $type, string $placeholder = '' ): void {
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
			'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text" autocomplete="off" data-lpignore="true" data-1p-ignore data-form-type="other"%5$s%6$s%7$s />',
			esc_attr( $type ),
			esc_attr( $id ),
			esc_attr( $name ),
			esc_attr( $value ),
			'number' === $type ? ' min="0"' : '',
			$max > 0 ? ' maxlength="' . (int) $max . '" data-dp-counter="' . (int) $max . '"' : '',
			'' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : ''
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
	private function render_textarea( int $post_id, string $key, string $name, string $id, string $placeholder = '' ): void {
		$value = get_post_meta( $post_id, $key, true );
		$value = is_scalar( $value ) ? (string) $value : '';
		$max   = $this->seo_maxlength( $key );

		printf(
			'<textarea id="%1$s" name="%2$s" rows="3" class="large-text"%4$s%5$s>%3$s</textarea>',
			esc_attr( $id ),
			esc_attr( $name ),
			esc_textarea( $value ),
			$max > 0 ? ' maxlength="' . (int) $max . '" data-dp-counter="' . (int) $max . '"' : '',
			'' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : ''
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
	private function render_lines( int $post_id, string $key, string $name, string $id, string $placeholder = '' ): void {
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
			'<textarea id="%1$s" name="%2$s" rows="4" class="large-text"%4$s>%3$s</textarea>',
			esc_attr( $id ),
			esc_attr( $name ),
			esc_textarea( implode( "\n", array_filter( $lines, 'strlen' ) ) ),
			'' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : ''
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

		// Primary term selects list ONLY the terms assigned to this doctor, so the
		// choice is always valid (the primary must be one of the doctor's terms).
		$options     = $field['options'];
		$primary_tax = '';
		if ( '' === $options || array() === $options ) {
			$primary_tax = false !== strpos( $key, 'primary_department' )
				? \BrainStudioz\DoctorPilot\Taxonomies\Taxonomies::DEPARTMENT
				: \BrainStudioz\DoctorPilot\Taxonomies\Taxonomies::SPECIALTY;
			$options = $this->primary_term_options( $post_id, $primary_tax );
		}

		$current = (string) get_post_meta( $post_id, $key, true );
		if ( '' === $current && '' !== (string) $field['default'] ) {
			$current = (string) $field['default'];
		}

		$data_tax = '' !== $primary_tax ? ' data-dp-primary-tax="' . esc_attr( $primary_tax ) . '"' : '';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Values escaped inline.
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $data_tax . '>';
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
	 * Options for a primary department/specialty select — only the terms already
	 * assigned to this doctor, so a primary can never point outside the set.
	 *
	 * @param int    $post_id  Doctor post ID.
	 * @param string $taxonomy Taxonomy key.
	 * @return array<string, string>
	 */
	private function primary_term_options( int $post_id, string $taxonomy ): array {
		$options  = array( '' => __( '— Select —', 'doctorpilot' ) );
		$assigned = wp_get_object_terms( $post_id, $taxonomy );

		if ( ! is_wp_error( $assigned ) ) {
			foreach ( $assigned as $term ) {
				if ( $term instanceof \WP_Term ) {
					$options[ (string) $term->term_id ] = $term->name;
				}
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

		// Empty-taxonomy state with a direct "add {plural}" link.
		if ( is_wp_error( $terms ) || array() === $terms ) {
			$add_url = admin_url(
				'edit-tags.php?taxonomy=' . rawurlencode( $taxonomy ) . '&post_type=' . rawurlencode( DoctorPostType::POST_TYPE )
			);

			$tax_object = get_taxonomy( $taxonomy );
			$plural     = ( $tax_object && isset( $tax_object->labels->name ) )
				? strtolower( (string) $tax_object->labels->name )
				: __( 'terms', 'doctorpilot' );

			echo '<div class="dp-empty-tax">';
			echo '<span>' . esc_html__( 'No terms in this taxonomy yet.', 'doctorpilot' ) . '</span>';
			printf(
				'<a class="dp-empty-tax__add" href="%1$s">%2$s <span aria-hidden="true">&rarr;</span></a>',
				esc_url( $add_url ),
				esc_html(
					sprintf(
						/* translators: %s: taxonomy plural name, e.g. "conditions". */
						__( 'Add %s', 'doctorpilot' ),
						$plural
					)
				)
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

		$this->render_row_fields( $key, $schema, $row, $index );

		printf(
			'<button type="button" class="button-link dp-remove-row">%s</button>',
			esc_html__( 'Remove', 'doctorpilot' )
		);
		echo '</div>';
	}

	/**
	 * Render one flat set of inputs for a field whose schema describes a single
	 * record rather than a list — a doctor's practice location.
	 *
	 * Values are submitted at row index 0 under the same name a repeater would
	 * use, so the stored shape (a list of rows), the `repeater` sanitizer and
	 * every reader of the meta stay exactly as they were.
	 *
	 * Rows beyond the first are round-tripped as hidden inputs rather than
	 * omitted. Omitting them would look harmless — the editor only ever shows
	 * one location — but the save handler rewrites the whole meta value from the
	 * submitted form, so any doctor holding two locations would lose the second
	 * the next time anyone pressed Update, from any tab, with no warning. The
	 * `doctorpilot/locations/max` filter is raised to 10 by `AdvancedFilters`,
	 * so multi-location doctors are real data, not a legacy edge case.
	 *
	 * @param int                  $post_id Doctor post ID.
	 * @param array<string, mixed> $field   Field definition.
	 * @param string               $key     Meta key.
	 * @return void
	 */
	private function render_single( int $post_id, array $field, string $key ): void {
		$schema = is_array( $field['repeater'] ) ? $field['repeater'] : array();
		$rows   = get_post_meta( $post_id, $key, true );
		$rows   = array_values( array_filter( is_array( $rows ) ? $rows : array(), 'is_array' ) );

		$first = $rows[0] ?? array();
		$extra = array_slice( $rows, 1 );

		echo '<div class="dp-single" data-key="' . esc_attr( $key ) . '">';
		$this->render_row_fields( $key, $schema, $first, '0' );
		echo '</div>';

		if ( array() === $extra ) {
			return;
		}

		// Preserved verbatim, keyed off the schema so a sub-field that is not
		// part of the record cannot be smuggled back in through the form.
		$index = 1;
		foreach ( $extra as $row ) {
			foreach ( array_keys( $schema ) as $sub_key ) {
				$value = $row[ $sub_key ] ?? '';
				if ( ! is_scalar( $value ) ) {
					continue;
				}
				printf(
					'<input type="hidden" name="%1$s" value="%2$s" />',
					esc_attr( 'doctorpilot[' . $key . '][' . $index . '][' . $sub_key . ']' ),
					// Not esc_attr(): it does not double-encode, so a stored value
					// holding a literal "&amp;" would come back as "&" and the
					// preserved row would degrade a little on every save.
					htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8', true )
				);
			}
			++$index;
		}

		echo '<p class="dp-desc">' . esc_html(
			sprintf(
				/* translators: %d: number of additional stored locations. */
				_n(
					'%d further location is stored for this doctor and still appears on the public profile. It is kept on save but is not editable here.',
					'%d further locations are stored for this doctor and still appear on the public profile. They are kept on save but are not editable here.',
					count( $extra ),
					'doctorpilot'
				),
				count( $extra )
			)
		) . '</p>';
	}

	/**
	 * Render the inputs for one record of a repeater schema.
	 *
	 * Shared by the repeater row and the single-record renderer so both produce
	 * identical controls and field names.
	 *
	 * @param string                             $key    Meta key.
	 * @param array<string, array<string,mixed>> $schema Item schema.
	 * @param array<string, mixed>               $row    Row values.
	 * @param string                             $index  Row index or placeholder.
	 * @return void
	 */
	private function render_row_fields( string $key, array $schema, array $row, string $index ): void {
		echo '<div class="dp-row__fields">';

		foreach ( $schema as $sub_key => $sub_def ) {
			$sub_label   = isset( $sub_def['label'] ) ? (string) $sub_def['label'] : $sub_key;
			$sanitize    = isset( $sub_def['sanitize'] ) ? (string) $sub_def['sanitize'] : 'text';
			$placeholder = isset( $sub_def['placeholder'] ) ? (string) $sub_def['placeholder'] : '';
			$field_name  = 'doctorpilot[' . $key . '][' . $index . '][' . $sub_key . ']';
			$value       = $row[ $sub_key ] ?? '';

			// Flex sizing per column; only textareas take a full-width row.
			$full  = 'textarea' === $sanitize;
			$basis = isset( $sub_def['basis'] ) ? (string) $sub_def['basis'] : ( 'bool' === $sanitize ? '160px' : '160px' );
			$grow  = isset( $sub_def['grow'] ) ? (int) $sub_def['grow'] : ( 'bool' === $sanitize ? 0 : 1 );
			$style = $full ? 'flex:1 1 100%' : 'flex:' . $grow . ' 1 ' . $basis;

			printf(
				'<label class="dp-row-field dp-row-field--%1$s" style="%2$s">',
				esc_attr( $sanitize ),
				esc_attr( $style )
			);
			if ( 'bool' !== $sanitize ) {
				echo '<span class="dp-row-field__label">' . esc_html( $sub_label ) . '</span>';
			}

			$taxonomy = isset( $sub_def['taxonomy'] ) ? (string) $sub_def['taxonomy'] : '';

			if ( 'bool' === $sanitize ) {
				printf(
					'<span class="dp-row-field__check"><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</span>',
					esc_attr( $field_name ),
					checked( ! empty( $value ), true, false ),
					esc_html( $sub_label )
				);
			} elseif ( '' !== $taxonomy ) {
				$this->render_row_term_select( $field_name, $taxonomy, (int) ( is_scalar( $value ) ? $value : 0 ) );
			} elseif ( 'textarea' === $sanitize ) {
				printf(
					'<textarea name="%1$s" rows="2"%3$s>%2$s</textarea>',
					esc_attr( $field_name ),
					esc_textarea( is_scalar( $value ) ? (string) $value : '' ),
					'' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : ''
				);
			} else {
				printf(
					'<input type="text" name="%1$s" value="%2$s"%3$s />',
					esc_attr( $field_name ),
					esc_attr( is_scalar( $value ) ? (string) $value : '' ),
					'' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : ''
				);
			}
			echo '</label>';
		}
		echo '</div>';
	}

	/**
	 * Render a repeater sub-field as a taxonomy-term dropdown.
	 *
	 * The stored value is a term ID, so a free-text box would coerce a typed name
	 * to 0 on save. A select of the taxonomy's terms keeps the value a valid ID.
	 *
	 * @param string $field_name Input name.
	 * @param string $taxonomy   Taxonomy slug.
	 * @param int    $selected   Currently selected term ID.
	 * @return void
	 */
	private function render_row_term_select( string $field_name, string $taxonomy, int $selected ): void {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		printf( '<select name="%1$s" class="dp-row-select">', esc_attr( $field_name ) );
		printf( '<option value="0">%s</option>', esc_html__( '— Select —', 'doctorpilot' ) );

		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( ! ( $term instanceof \WP_Term ) ) {
					continue;
				}
				printf(
					'<option value="%1$d" %2$s>%3$s</option>',
					(int) $term->term_id,
					selected( (int) $term->term_id, $selected, false ),
					esc_html( $term->name )
				);
			}
		}

		echo '</select>';
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
