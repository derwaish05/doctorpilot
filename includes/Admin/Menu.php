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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The single owner of DoctorPilot admin navigation.
 *
 * Registers one top-level menu with a Health dashboard and a Settings screen.
 * No other module registers a competing top-level menu; later features add
 * submenus here to avoid duplicated navigation.
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
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );

		// After every submenu exists (including the auto-registered CPT items and
		// any Pro screens), sort them into the intended display order.
		add_action( 'admin_menu', array( $this, 'order_submenu' ), 100 );
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
	}

	/**
	 * Sort the DoctorPilot submenu into the intended display order.
	 *
	 * WordPress appends items to the parent in registration order (CPT lists,
	 * our own pages, Pro screens), which is inconsistent. This runs late on
	 * `admin_menu`, after every item exists, and reorders by slug:
	 * All Doctors, the global content types, Health, Analytics, Migration,
	 * Settings. Items not in the list keep their relative order at the end.
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

		// Analytics opt-in (Pro feature; disabled by default).
		echo '<tr><th scope="row">' . esc_html__( 'Search analytics', 'doctorpilot' ) . '</th><td>';
		printf(
			'<label><input name="%1$s[privacy][analytics_enabled]" type="checkbox" value="1" %2$s /> %3$s</label>',
			esc_attr( Settings::OPTION_KEY ),
			checked( ! empty( $settings['privacy']['analytics_enabled'] ), true, false ),
			esc_html__( 'Enable anonymized, opt-in search analytics (requires DoctorPilot Pro).', 'doctorpilot' )
		);
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
