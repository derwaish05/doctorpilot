<?php
/**
 * Admin navigation owner and foundation screens.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Admin;

use BrainStudioz\DoctorPilot\Library\LibraryPostTypes;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;
use BrainStudioz\DoctorPilot\Support\Capabilities;
use BrainStudioz\DoctorPilot\Support\ExtensionRegistry;
use BrainStudioz\DoctorPilot\Taxonomies\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The single owner of DoctorPilot admin navigation.
 *
 * Registers two top-level menus: DoctorPilot, holding the doctor lists, the
 * Health dashboard and Settings; and Taxonomies, holding every term vocabulary
 * attached to the doctor post type. No other module registers a competing
 * top-level menu; later features add submenus to one of these two rather than
 * standing up a third.
 */
final class Menu {

	/**
	 * Top-level menu slug.
	 *
	 * @var string
	 */
	public const SLUG = 'doctorpilot';

	/**
	 * Settings submenu slug.
	 *
	 * @var string
	 */
	public const SETTINGS_SLUG = 'doctorpilot-settings';

	/**
	 * Top-level menu slug for the taxonomy screens, or '' when there are none.
	 *
	 * A real admin file rather than a page slug, so the parent lands on a term
	 * list instead of a placeholder page nobody wants. Core has no precedent for
	 * a query string in a *top-level* slug — the `edit.php?post_type=…` case it
	 * does have carries no `&` — so what makes this work is narrower than it
	 * looks: `_wp_menu_output()` takes the parent's href from its FIRST SUBMENU
	 * item, raw and unescaped, and only when the menu has no page callback. Pass
	 * a callback to `add_menu_page()` below and the href silently becomes
	 * `admin.php?page=edit-tags.php?…`, which is broken. The empty callback is
	 * load-bearing.
	 *
	 * Computed at registration rather than hard-coded, because it has to equal
	 * the first submenu's slug. Pinning it to Department while the children are
	 * derived from what is registered lets the two disagree — unregister or hide
	 * Department and core prepends a duplicate of the parent to the submenu,
	 * whose href then points at a taxonomy that does not exist, so clicking the
	 * top-level item hits `edit-tags.php`'s "Invalid taxonomy" `wp_die()`.
	 *
	 * @var string
	 */
	private string $taxonomy_parent = '';

	/**
	 * The order doctor taxonomies appear in, most-used first.
	 *
	 * Only an ordering hint. `menu_taxonomies()` builds the real list from what
	 * is registered against the doctor post type, so a taxonomy added by an
	 * add-on — Insurance arrived with the Pro merge that way — shows up without
	 * being named here.
	 *
	 * @var array<int, string>
	 */
	private const TAXONOMY_ORDER = array(
		Taxonomies::DEPARTMENT,
		Taxonomies::SPECIALTY,
		Taxonomies::SYMPTOM,
		Taxonomies::CONDITION,
		Taxonomies::TREATMENT,
		Taxonomies::LANGUAGE,
		Taxonomies::LOCATION,
	);

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );

		// After every submenu exists (including the auto-registered CPT items and
		// any Pro screens), sort them into the intended display order.
		add_action( 'admin_menu', array( $this, 'order_submenu' ), 100 );

		// Keep the DoctorPilot menu open and the right item highlighted while a
		// doctor taxonomy screen is showing.
		add_filter( 'parent_file', array( $this, 'taxonomy_parent_file' ) );
	}

	/**
	 * The submenu slug WordPress uses for a doctor taxonomy screen.
	 *
	 * This string is a key, not a URL: `wp-admin/menu-header.php` marks a
	 * submenu item current by comparing it verbatim against the `$submenu_file`
	 * global, and `edit-tags.php` and `term.php` both set that global to
	 * `edit-tags.php?taxonomy=…&amp;post_type=…` — HTML-encoded ampersand,
	 * arguments in that order. Registering the item with a plain `&` would give
	 * a working link that never highlights, so the encoding is reproduced here
	 * rather than tidied away. The same string is echoed straight into the
	 * `href`, where `&amp;` is what valid markup wants anyway.
	 *
	 * @param string $taxonomy Taxonomy key.
	 * @return string
	 */
	private static function taxonomy_slug( string $taxonomy ): string {
		return 'edit-tags.php?taxonomy=' . $taxonomy . '&amp;post_type=' . DoctorPostType::POST_TYPE;
	}

	/**
	 * Point a doctor taxonomy screen at the Taxonomies menu.
	 *
	 * `edit-tags.php` and `term.php` hard-code the parent as
	 * `edit.php?post_type=doctorpilot_doctor`. That is a submenu item of the
	 * DoctorPilot menu, not a top-level menu of its own, so without this filter
	 * the Taxonomies menu stays shut while one of its own screens is open.
	 * Nothing is highlighted either way: once `edit-tags.php` sets
	 * `$submenu_file`, `menu-header.php` marks an item current only on an exact
	 * match, so the fix for the highlight is the matching slug in
	 * `taxonomy_slug()`, and the fix for the open state is this.
	 *
	 * Both the taxonomy and the post type have to match. The taxonomy alone is
	 * not enough: `register_taxonomy_for_object_type()` is a public API, so
	 * another plugin can attach a doctor taxonomy to a second post type, and
	 * that screen belongs to that post type's menu, not to ours. `$typenow` is
	 * set from the request on every admin page load, `term.php` included.
	 *
	 * @param string $parent_file Current parent file.
	 * @return string
	 */
	public function taxonomy_parent_file( $parent_file ) {
		global $pagenow, $taxnow, $typenow;

		// Only the term screens. `$taxnow` is set from the request on every
		// admin page, so without this the filter also fires on the doctor list:
		// a term's post-count link is `edit.php?taxonomy=…&term=…&post_type=…`
		// for any taxonomy without a `query_var` — Language, Location and
		// Insurance are all registered that way — which would collapse the
		// DoctorPilot menu and open this one with nothing highlighted, on the
		// very screens this menu exists to reach.
		if ( 'edit-tags.php' !== (string) $pagenow && 'term.php' !== (string) $pagenow ) {
			return $parent_file;
		}

		if ( '' === $this->taxonomy_parent || DoctorPostType::POST_TYPE !== (string) $typenow ) {
			return $parent_file;
		}

		$taxonomy = get_taxonomy( (string) $taxnow );

		if ( $taxonomy instanceof \WP_Taxonomy && $taxonomy->show_ui ) {
			return $this->taxonomy_parent;
		}

		return $parent_file;
	}

	/**
	 * Register the top-level menu and submenus.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'DoctorPilot', 'doctorpilot' ),
			__( 'DoctorPilot', 'doctorpilot' ),
			Capabilities::MANAGE,
			self::SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-businessperson',
			30
		);

		add_submenu_page(
			self::SLUG,
			__( 'Health', 'doctorpilot' ),
			__( 'Health', 'doctorpilot' ),
			Capabilities::MANAGE,
			self::SLUG,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Settings', 'doctorpilot' ),
			__( 'Settings', 'doctorpilot' ),
			Capabilities::MANAGE,
			self::SETTINGS_SLUG,
			array( $this, 'render_settings' )
		);

		$this->register_taxonomy_menu();
	}

	/**
	 * Every doctor taxonomy that should appear in the taxonomies menu.
	 *
	 * Derived from what is actually registered against the doctor post type, so
	 * an add-on's taxonomy is included without editing this class, then sorted
	 * by `TAXONOMY_ORDER` with anything unlisted kept in registration order at
	 * the end.
	 *
	 * @return array<int, \WP_Taxonomy>
	 */
	private function menu_taxonomies(): array {
		$rank  = array_flip( self::TAXONOMY_ORDER );
		$found = array();

		foreach ( get_object_taxonomies( DoctorPostType::POST_TYPE, 'objects' ) as $key => $object ) {
			// A taxonomy with no UI has no screen to link to, and one the
			// current user cannot manage would render an empty item they cannot
			// open. `add_submenu_page()` checks the capability too; this keeps
			// the ordering list honest either way.
			if ( ! $object instanceof \WP_Taxonomy || ! $object->show_ui ) {
				continue;
			}

			$found[] = array(
				'key'    => isset( $rank[ $key ] ) ? (int) $rank[ $key ] : count( self::TAXONOMY_ORDER ) + count( $found ),
				'object' => $object,
			);
		}

		usort(
			$found,
			static function ( array $a, array $b ): int {
				return $a['key'] <=> $b['key'];
			}
		);

		return array_column( $found, 'object' );
	}

	/**
	 * Register the taxonomies as their own top-level menu.
	 *
	 * They used to be two items inside the DoctorPilot menu — Department and
	 * Specialty only — because listing all seven pushed Health, Analytics and
	 * Settings below the fold. Giving them a menu of their own removes that
	 * trade-off: every vocabulary is one click away and the DoctorPilot menu is
	 * back to the screens that are not term lists.
	 *
	 * Labels and capabilities are read from the registered taxonomy rather than
	 * restated, so a change in `Taxonomies` reaches the menu without a second
	 * edit. No callback is passed anywhere: every slug is a real admin file, so
	 * WordPress links straight to it.
	 *
	 * @return void
	 */
	private function register_taxonomy_menu(): void {
		$taxonomies = $this->menu_taxonomies();

		if ( array() === $taxonomies ) {
			return;
		}

		// The parent must be the first child's slug, not a fixed taxonomy — see
		// `$taxonomy_parent`.
		$this->taxonomy_parent = self::taxonomy_slug( $taxonomies[0]->name );

		// Escaped for the same reason core escapes its own taxonomy menu
		// titles: `menu-header.php` echoes the label into the anchor and the
		// submenu head without escaping it there.
		//
		// The empty callback is required, not an omission — see `$taxonomy_parent`.
		// A side effect of the query-string slug is that the generated body class
		// (`toplevel_page_edit-tags?taxonomy=…`) is not a usable CSS selector;
		// nothing styles this menu by class, so that is left alone.
		add_menu_page(
			esc_attr__( 'Doctor Taxonomies', 'doctorpilot' ),
			esc_attr__( 'Taxonomies', 'doctorpilot' ),
			Capabilities::MANAGE_TERMS,
			$this->taxonomy_parent,
			'',
			'dashicons-tag',
			31
		);

		foreach ( $taxonomies as $object ) {
			add_submenu_page(
				$this->taxonomy_parent,
				esc_attr( $object->labels->name ),
				esc_attr( $object->labels->menu_name ),
				$object->cap->manage_terms,
				self::taxonomy_slug( $object->name )
			);
		}
	}

	/**
	 * Sort the DoctorPilot submenu into the intended display order.
	 *
	 * WordPress appends items to the parent in registration order (CPT lists,
	 * our own pages, Pro screens), which is inconsistent. This runs late on
	 * `admin_menu`, after every item exists, and reorders by slug:
	 * All Doctors, the global content types, Health, Analytics, Migration,
	 * Settings. Items not in the list keep their relative order at the end.
	 * Taxonomies are absent on purpose — they have their own top-level menu.
	 *
	 * @return void
	 */
	public function order_submenu(): void {
		global $submenu;

		if ( empty( $submenu[ self::SLUG ] ) || ! is_array( $submenu[ self::SLUG ] ) ) {
			return;
		}

		$order = array(
			'edit.php?post_type=' . DoctorPostType::POST_TYPE,       // All Doctors.
			'edit.php?post_type=' . LibraryPostTypes::EDUCATION,     // Education.
			'edit.php?post_type=' . LibraryPostTypes::EXPERIENCE,    // Experience.
			'edit.php?post_type=' . LibraryPostTypes::PROCEDURE,     // Procedures.
			'edit.php?post_type=' . LibraryPostTypes::PUBLICATION,   // Publications.
			'edit.php?post_type=' . LibraryPostTypes::CERTIFICATION, // Certifications.
			'edit.php?post_type=' . LibraryPostTypes::MEMBERSHIP,    // Society memberships.
			'edit.php?post_type=' . LibraryPostTypes::FAQ,           // FAQs.
			self::SLUG,               // Health (dashboard).
			'doctorpilot-analytics',  // Analytics (Pro).
			'doctorpilot-migration',  // Migration (Pro).
			self::SETTINGS_SLUG,      // Settings.
		);

		$rank    = array_flip( $order );
		$fallback = count( $order );

		// Assign each item a sort key: its rank if known, otherwise a position
		// after all known items that preserves its original order. Keys are all
		// distinct, so ordering is deterministic regardless of sort stability.
		$indexed = array();
		foreach ( array_values( $submenu[ self::SLUG ] ) as $pos => $item ) {
			$slug = isset( $item[2] ) ? (string) $item[2] : '';
			$key  = isset( $rank[ $slug ] ) ? (int) $rank[ $slug ] : ( $fallback + $pos );

			$indexed[] = array( 'key' => $key, 'item' => $item );
		}

		usort(
			$indexed,
			static function ( array $a, array $b ): int {
				return $a['key'] <=> $b['key'];
			}
		);

		$sorted = array();
		foreach ( $indexed as $entry ) {
			$sorted[] = $entry['item'];
		}

		$submenu[ self::SLUG ] = $sorted;
	}

	/**
	 * Render the health/dashboard foundation screen.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'doctorpilot' ) );
		}

		$plugin_version = DOCTORPILOT_VERSION;
		$schema_version = (int) get_option( 'bstdz_doctorpilot_schema_version', 0 );
		$cache_gen      = (int) get_option( 'bstdz_doctorpilot_cache_generation', 0 );
		$elementor      = did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' );
		$incompatible   = ExtensionRegistry::instance()->incompatible();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'DoctorPilot Health', 'doctorpilot' ) . '</h1>';
		echo '<p>' . esc_html__( 'Foundation status for the DoctorPilot directory plugin.', 'doctorpilot' ) . '</p>';

		echo '<table class="widefat striped" style="max-width:640px;margin-top:1em;">';
		echo '<tbody>';
		self::status_row( __( 'Plugin version', 'doctorpilot' ), $plugin_version );
		self::status_row( __( 'Schema version', 'doctorpilot' ), (string) $schema_version );
		self::status_row( __( 'Base API version', 'doctorpilot' ), (string) DOCTORPILOT_BASE_API_VERSION );
		self::status_row( __( 'Cache generation', 'doctorpilot' ), (string) $cache_gen );
		self::status_row(
			__( 'Elementor detected', 'doctorpilot' ),
			$elementor ? __( 'Yes', 'doctorpilot' ) : __( 'No (native output active)', 'doctorpilot' )
		);
		echo '</tbody></table>';

		if ( array() !== $incompatible ) {
			echo '<div class="notice notice-warning inline" style="margin-top:1em;"><p>';
			echo esc_html__( 'One or more add-ons require a newer DoctorPilot base and were not loaded:', 'doctorpilot' );
			echo '</p><ul style="list-style:disc;margin-left:20px;">';
			foreach ( $incompatible as $addon ) {
				echo '<li>' . esc_html( $addon['name'] ) . '</li>';
			}
			echo '</ul></div>';
		}

		echo '</div>';
	}

	/**
	 * Render the settings screen using the WordPress Settings API.
	 *
	 * @return void
	 */
	public function render_settings(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage these settings.', 'doctorpilot' ) );
		}

		$settings = Settings::get();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'DoctorPilot Settings', 'doctorpilot' ) . '</h1>';

		echo '<form method="post" action="options.php">';
		settings_fields( 'bstdz_doctorpilot_settings_group' );

		echo '<table class="form-table" role="presentation"><tbody>';

		// Directory base slug.
		echo '<tr><th scope="row"><label for="dp-directory-base">' . esc_html__( 'Directory base', 'doctorpilot' ) . '</label></th><td>';
		printf(
			'<input name="%1$s[general][directory_base]" id="dp-directory-base" type="text" class="regular-text" value="%2$s" />',
			esc_attr( Settings::OPTION_KEY ),
			esc_attr( (string) $settings['general']['directory_base'] )
		);
		echo '<p class="description">' . esc_html__( 'Base path for doctor profile URLs, e.g. find-a-doctor.', 'doctorpilot' ) . '</p>';
		echo '</td></tr>';

		// Doctors per page.
		echo '<tr><th scope="row"><label for="dp-per-page">' . esc_html__( 'Doctors per page', 'doctorpilot' ) . '</label></th><td>';
		printf(
			'<input name="%1$s[general][per_page]" id="dp-per-page" type="number" min="1" max="48" value="%2$d" />',
			esc_attr( Settings::OPTION_KEY ),
			(int) $settings['general']['per_page']
		);
		echo '</td></tr>';

		// Default sort.
		echo '<tr><th scope="row"><label for="dp-default-sort">' . esc_html__( 'Default sort', 'doctorpilot' ) . '</label></th><td>';
		printf( '<select name="%1$s[general][default_sort]" id="dp-default-sort">', esc_attr( Settings::OPTION_KEY ) );
		$sorts = array(
			'relevance'  => __( 'Relevance', 'doctorpilot' ),
			'name'       => __( 'Name', 'doctorpilot' ),
			'featured'   => __( 'Featured', 'doctorpilot' ),
			'experience' => __( 'Most experienced', 'doctorpilot' ),
			'recent'     => __( 'Recently added', 'doctorpilot' ),
		);
		foreach ( $sorts as $value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $settings['general']['default_sort'], $value, false ),
				esc_html( $label )
			);
		}
		echo '</select></td></tr>';

		// Single doctor layout — render a chosen Elementor template/page on every
		// single-doctor view (works with free Elementor via the Doctor Profile
		// widget, which auto-fills the current doctor).
		if ( did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' ) ) {
			$current_tpl = (int) $settings['general']['single_doctor_template'];
			$candidates  = get_posts(
				array(
					'post_type'        => array( 'elementor_library', 'page' ),
					'post_status'      => array( 'publish', 'private' ),
					'posts_per_page'   => 200,
					'orderby'          => 'title',
					'order'            => 'ASC',
					'no_found_rows'    => true,
					'suppress_filters' => false,
				)
			);

			echo '<tr><th scope="row"><label for="dp-single-template">' . esc_html__( 'Single doctor layout', 'doctorpilot' ) . '</label></th><td>';
			printf( '<select name="%1$s[general][single_doctor_template]" id="dp-single-template">', esc_attr( Settings::OPTION_KEY ) );
			printf(
				'<option value="0" %1$s>%2$s</option>',
				selected( 0, $current_tpl, false ),
				esc_html__( 'Native template (default)', 'doctorpilot' )
			);
			foreach ( $candidates as $candidate ) {
				$type_label = 'page' === $candidate->post_type ? __( 'Page', 'doctorpilot' ) : __( 'Template', 'doctorpilot' );
				printf(
					'<option value="%1$d" %2$s>%3$s</option>',
					(int) $candidate->ID,
					selected( (int) $candidate->ID, $current_tpl, false ),
					esc_html( sprintf( '%1$s (%2$s #%3$d)', '' !== $candidate->post_title ? $candidate->post_title : __( '(untitled)', 'doctorpilot' ), $type_label, (int) $candidate->ID ) )
				);
			}
			echo '</select>';
			echo '<p class="description">' . esc_html__( 'Design a page or Elementor template using the "Doctor Profile" widget (DoctorPilot category); it renders for every doctor. Choose "Native template" to use the built-in layout.', 'doctorpilot' ) . '</p>';
			echo '</td></tr>';
		}

		// Search page — where the Doctor Search Index widget sends a visitor who
		// picks a department, specialty or symptom.
		Settings::render_search_page_field();

		// ---------------------------------------------------------------------
		// Booking. Site-wide defaults every doctor inherits unless their own
		// Booking tab overrides them, so a new doctor books correctly with no
		// per-doctor setup.
		// ---------------------------------------------------------------------
		echo '<tr><th scope="row" colspan="2"><h2 class="title" style="margin:1em 0 0;">' . esc_html__( 'Booking', 'doctorpilot' ) . '</h2>';
		echo '<p class="description" style="font-weight:400;">' . esc_html__( 'Defaults for the booking button. A doctor’s own Booking tab wins wherever it is filled in; leave a doctor’s field blank to inherit the value set here.', 'doctorpilot' ) . '</p></th></tr>';

		$dp_methods = array(
			'external_url' => __( 'External URL', 'doctorpilot' ),
			'phone'        => __( 'Phone', 'doctorpilot' ),
			'enquiry'      => __( 'Enquiry form', 'doctorpilot' ),
		);

		echo '<tr><th scope="row"><label for="dp-booking-method">' . esc_html__( 'Default booking method', 'doctorpilot' ) . '</label></th><td>';
		printf( '<select name="%1$s[booking][default_type]" id="dp-booking-method">', esc_attr( Settings::OPTION_KEY ) );
		foreach ( $dp_methods as $dp_value => $dp_label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $dp_value ),
				selected( (string) ( $settings['booking']['default_type'] ?? 'external_url' ), $dp_value, false ),
				esc_html( $dp_label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Used for any doctor who has not chosen a method of their own.', 'doctorpilot' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="dp-booking-url">' . esc_html__( 'Default booking URL', 'doctorpilot' ) . '</label></th><td>';
		printf(
			'<input name="%1$s[booking][default_url]" id="dp-booking-url" type="url" class="regular-text" value="%2$s" placeholder="https://" />',
			esc_attr( Settings::OPTION_KEY ),
			esc_attr( (string) ( $settings['booking']['default_url'] ?? '' ) )
		);
		echo '<p class="description">' . esc_html__( 'Site-wide booking page, used when a doctor books by URL but has none of their own.', 'doctorpilot' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="dp-booking-phone">' . esc_html__( 'Default booking phone', 'doctorpilot' ) . '</label></th><td>';
		printf(
			'<input name="%1$s[booking][default_phone]" id="dp-booking-phone" type="text" class="regular-text" value="%2$s" placeholder="+974 4000 0000" />',
			esc_attr( Settings::OPTION_KEY ),
			esc_attr( (string) ( $settings['booking']['default_phone'] ?? '' ) )
		);
		echo '<p class="description">' . esc_html__( 'Switchboard number, used when a doctor books by phone but has none of their own.', 'doctorpilot' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="dp-booking-label">' . esc_html__( 'Default button label', 'doctorpilot' ) . '</label></th><td>';
		printf(
			'<input name="%1$s[booking][default_label]" id="dp-booking-label" type="text" class="regular-text" value="%2$s" placeholder="%3$s" />',
			esc_attr( Settings::OPTION_KEY ),
			esc_attr( (string) ( $settings['booking']['default_label'] ?? '' ) ),
			esc_attr__( 'Book Appointment', 'doctorpilot' )
		);
		echo '<p class="description">' . esc_html__( 'Leave blank to let each method use its own wording — “Book Appointment”, “Call to book”, “Request an appointment”.', 'doctorpilot' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Open booking links', 'doctorpilot' ) . '</th><td>';
		printf( '<select name="%1$s[booking][default_target]">', esc_attr( Settings::OPTION_KEY ) );
		foreach ( array( '_blank' => __( 'In a new tab', 'doctorpilot' ), '_self' => __( 'In the same tab', 'doctorpilot' ) ) as $dp_value => $dp_label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $dp_value ),
				selected( (string) ( $settings['booking']['default_target'] ?? '_blank' ), $dp_value, false ),
				esc_html( $dp_label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Applies to external booking URLs. Phone and enquiry links always stay in the same tab.', 'doctorpilot' ) . '</p>';
		echo '</td></tr>';

		// ---------------------------------------------------------------------
		// Search analytics. Off by default and opt-in; the retention window is
		// shown alongside it because a site that turns collection on is the
		// site that needs to say how long the counts are kept.
		// ---------------------------------------------------------------------
		echo '<tr><th scope="row" colspan="2"><h2 class="title" style="margin:1em 0 0;">' . esc_html__( 'Search analytics', 'doctorpilot' ) . '</h2>';
		echo '<p class="description" style="font-weight:400;">' . esc_html__( 'Counts of the symptoms patients filter by, and the searches that returned nothing. Stored as daily totals only — no IP address, user, session or cookie is recorded, and no search can be traced back to a person.', 'doctorpilot' ) . '</p></th></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Collect search analytics', 'doctorpilot' ) . '</th><td>';
		printf(
			'<label><input name="%1$s[privacy][analytics_enabled]" type="checkbox" value="1" %2$s /> %3$s</label>',
			esc_attr( Settings::OPTION_KEY ),
			checked( ! empty( $settings['privacy']['analytics_enabled'] ), true, false ),
			esc_html__( 'Record anonymized, aggregate search counts. Off by default.', 'doctorpilot' )
		);
		echo '<p class="description">' . esc_html__( 'Results appear under DoctorPilot → Analytics. The list of searches that found nothing is the useful one — it shows what patients look for that your directory cannot answer yet.', 'doctorpilot' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="dp-analytics-retention">' . esc_html__( 'Keep analytics for', 'doctorpilot' ) . '</label></th><td>';
		printf(
			'<input name="%1$s[privacy][analytics_retention_days]" id="dp-analytics-retention" type="number" min="1" max="3650" step="1" class="small-text" value="%2$d" /> %3$s',
			esc_attr( Settings::OPTION_KEY ),
			(int) ( $settings['privacy']['analytics_retention_days'] ?? 90 ),
			esc_html__( 'days', 'doctorpilot' )
		);
		echo '<p class="description">' . esc_html__( 'A daily cleanup deletes counts older than this. Between 1 and 3650 days; 90 by default.', 'doctorpilot' ) . '</p>';
		echo '</td></tr>';

		// Destructive uninstall opt-in.
		echo '<tr><th scope="row">' . esc_html__( 'Delete data on uninstall', 'doctorpilot' ) . '</th><td>';
		printf(
			'<label><input name="%1$s[data][delete_on_uninstall]" type="checkbox" value="1" %2$s /> %3$s</label>',
			esc_attr( Settings::OPTION_KEY ),
			checked( ! empty( $settings['data']['delete_on_uninstall'] ), true, false ),
			esc_html__( 'Permanently remove all DoctorPilot data when the plugin is deleted. Off by default.', 'doctorpilot' )
		);
		echo '<p class="description">' . esc_html__( 'When off, your doctors and settings are preserved even if the plugin is deleted.', 'doctorpilot' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';

		submit_button();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render a single status table row.
	 *
	 * @param string $label Row label.
	 * @param string $value Row value.
	 * @return void
	 */
	private static function status_row( string $label, string $value ): void {
		echo '<tr><th scope="row" style="width:220px;">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
	}
}
