<?php
/**
 * Versioned plugin settings object and validation.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Admin;

use BrainStudioz\DoctorPilot\Fields\FieldRegistry;
use BrainStudioz\DoctorPilot\Infrastructure\Lifecycle\Activator;
use BrainStudioz\DoctorPilot\Support\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the single versioned `bstdz_doctorpilot_settings` option.
 *
 * All reads go through get() so callers always receive a fully-populated,
 * validated structure. Unknown keys are discarded and every value is range- and
 * enum-checked on save. Secrets are intentionally not stored here.
 */
final class Settings {

	/**
	 * Option key for the settings object.
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'bstdz_doctorpilot_settings';

	/**
	 * Settings schema version.
	 *
	 * @var int
	 */
	public const SCHEMA_VERSION = 1;

	/**
	 * Allowed sort values shared with the search contract.
	 *
	 * @var array<int, string>
	 */
	private const ALLOWED_SORTS = array( 'relevance', 'name', 'featured', 'experience', 'recent' );

	/**
	 * Allowed booking CTA target values.
	 *
	 * @var array<int, string>
	 */
	private const ALLOWED_TARGETS = array( '_self', '_blank' );

	/**
	 * Booking methods a site default may name.
	 *
	 * Mirrors the providers `BookingResolver` registers. A method outside this
	 * list would leave every inheriting doctor with no provider and therefore no
	 * button, so the default is enum-checked rather than merely key-sanitized.
	 *
	 * @var array<int, string>
	 */
	private const ALLOWED_BOOKING_METHODS = array( 'external_url', 'phone', 'enquiry' );

	/**
	 * Register the setting with WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		register_setting(
			'bstdz_doctorpilot_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Default settings structure (spec §13).
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'version' => self::SCHEMA_VERSION,
			'general' => array(
				'directory_base'            => 'find-a-doctor',
				'per_page'                  => 12,
				'default_sort'              => 'relevance',
				'placeholder_attachment_id' => 0,
				// 0 = native template; otherwise an Elementor template/page ID
				// rendered on single-doctor views.
				'single_doctor_template'    => 0,
				// Same, for doctors flagged International. 0 = use the layout
				// above, so a site that never sets it behaves exactly as before.
				'single_doctor_template_international' => 0,
				// 0 = unset; otherwise the published page the Doctor Search
				// Index widget links department, specialty and symptom entries to.
				'search_page_id'            => 0,
			),
			'search'  => array(
				'minimum_characters' => 2,
				'suggestion_limit'   => 8,
				'enabled_filters'    => array( 'department', 'specialty', 'symptom', 'gender', 'language' ),
				'cache_ttl_seconds'  => 300,
			),
			'booking' => array(
				'default_type'   => 'enquiry',
				// Blank, not 'Book Appointment': a non-empty value here overrides
				// every method's own wording, which would rename "Call to book"
				// and "Request an appointment" on every site that never chose it.
				'default_label'  => '',
				// `_blank` because that is what the External URL provider has
				// always hard-coded. The key existed but was never read or
				// exposed, so no site ever actually chose the old `_self`.
				'default_target' => '_blank',
				'default_url'    => '',
				'default_phone'  => '',
			),
			'design'  => array(
				'card_radius' => '8px',
				'image_ratio' => '4/5',
			),
			'privacy' => array(
				'analytics_enabled'        => false,
				'analytics_retention_days' => 90,
			),
			'data'    => array(
				'delete_on_uninstall' => false,
			),
		);
	}

	/**
	 * Retrieve the current, fully-populated settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		// Merge over defaults so missing keys are always present and safe.
		return self::merge_defaults( self::defaults(), $stored );
	}

	/**
	 * The Elementor template/page ID to render on single-doctor views, or 0 to
	 * use the native template.
	 *
	 * @return int
	 */
	public static function single_doctor_template(): int {
		$settings = self::get();

		return (int) ( $settings['general']['single_doctor_template'] ?? 0 );
	}

	/**
	 * The Elementor template/page ID to render for one doctor's single view, or 0
	 * to use the native template.
	 *
	 * A doctor flagged International gets the International layout when one is
	 * set; every other doctor, and a flagged doctor on a site with no
	 * International layout, gets the standard layout. This is the one place that
	 * decision is made, so the page output and the stylesheet loading cannot
	 * disagree about which template a doctor uses.
	 *
	 * @param int $doctor_id Doctor post ID.
	 * @return int
	 */
	public static function single_doctor_template_for( int $doctor_id ): int {
		$settings = self::get();

		$international = (int) ( $settings['general']['single_doctor_template_international'] ?? 0 );
		if (
			$international > 0
			&& $doctor_id > 0
			&& ! empty( get_post_meta( $doctor_id, FieldRegistry::META_PREFIX . 'is_international', true ) )
			// A layout that has since been deleted or unpublished must not leave
			// flagged doctors with an empty page; they fall back to the standard one.
			&& in_array( get_post_status( $international ), array( 'publish', 'private' ), true )
		) {
			return $international;
		}

		return (int) ( $settings['general']['single_doctor_template'] ?? 0 );
	}

	/**
	 * Permalink of the page the Doctor Search Index widget sends people to when
	 * they pick a department, specialty or symptom.
	 *
	 * With no page configured — or when the chosen one has since been deleted or
	 * unpublished — this falls back to the doctor archive, which renders the same
	 * search header and filtered grid natively. So the index widget links
	 * somewhere real out of the box, and a site only sets the option when it
	 * wants its own designed page to win instead.
	 *
	 * @return string Permalink, or '' when neither a page nor the archive resolves.
	 */
	public static function search_page_url(): string {
		$settings = self::get();
		$page_id  = (int) ( $settings['general']['search_page_id'] ?? 0 );

		if ( $page_id > 0 ) {
			$page = get_post( $page_id );
			if ( $page instanceof \WP_Post && 'publish' === $page->post_status ) {
				// get_permalink() returns false for a post it cannot resolve.
				$url = get_permalink( $page );
				if ( is_string( $url ) && '' !== $url ) {
					return $url;
				}
			}
		}

		$archive = get_post_type_archive_link( \BrainStudioz\DoctorPilot\PostTypes\DoctorPostType::POST_TYPE );

		return is_string( $archive ) ? $archive : '';
	}

	/**
	 * Print the General section's "Search page" row for the settings screen.
	 *
	 * Lives here beside the setting's default and sanitizer so the three stay in
	 * step; the settings screen calls it where the other page/template pickers
	 * are rendered.
	 *
	 * @return void
	 */
	public static function render_search_page_field(): void {
		$settings = self::get();
		$page_id  = (int) ( $settings['general']['search_page_id'] ?? 0 );

		echo '<tr><th scope="row"><label for="dp-search-page">' . esc_html__( 'Search page', 'doctorpilot' ) . '</label></th><td>';
		wp_dropdown_pages(
			array(
				'name'              => self::OPTION_KEY . '[general][search_page_id]',
				'id'                => 'dp-search-page',
				'selected'          => $page_id,
				'post_status'       => 'publish',
				'show_option_none'  => __( '— Not set —', 'doctorpilot' ),
				'option_none_value' => '0',
			)
		);
		echo '<p class="description">' . esc_html__( 'Where the Doctor Search Index widget sends people when they pick a department, specialty or symptom. Leave it unset and those entries link to the current page instead.', 'doctorpilot' ) . '</p>';
		echo '</td></tr>';
	}

	/**
	 * Sanitize and validate an incoming settings array.
	 *
	 * Unknown keys are dropped; each value is clamped to a safe range or enum.
	 *
	 * @param mixed $input Raw input from the settings form.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		$current = self::get();
		$input   = is_array( $input ) ? $input : array();

		$general = isset( $input['general'] ) && is_array( $input['general'] ) ? $input['general'] : array();
		$search  = isset( $input['search'] ) && is_array( $input['search'] ) ? $input['search'] : array();
		$booking = isset( $input['booking'] ) && is_array( $input['booking'] ) ? $input['booking'] : array();
		$design  = isset( $input['design'] ) && is_array( $input['design'] ) ? $input['design'] : array();
		$privacy = isset( $input['privacy'] ) && is_array( $input['privacy'] ) ? $input['privacy'] : array();
		$data    = isset( $input['data'] ) && is_array( $input['data'] ) ? $input['data'] : array();

		$clean = self::defaults();

		// General.
		$clean['general']['directory_base']            = self::sanitize_base_slug( $general['directory_base'] ?? $current['general']['directory_base'] );
		$clean['general']['per_page']                  = self::clamp_int( $general['per_page'] ?? $current['general']['per_page'], 1, 48, 12 );
		$clean['general']['default_sort']              = self::enum( $general['default_sort'] ?? $current['general']['default_sort'], self::ALLOWED_SORTS, 'relevance' );
		$clean['general']['placeholder_attachment_id'] = max( 0, (int) ( $general['placeholder_attachment_id'] ?? 0 ) );
		$clean['general']['single_doctor_template']    = max( 0, (int) ( $general['single_doctor_template'] ?? $current['general']['single_doctor_template'] ) );
		$clean['general']['single_doctor_template_international'] = max( 0, (int) ( $general['single_doctor_template_international'] ?? $current['general']['single_doctor_template_international'] ) );
		$clean['general']['search_page_id']            = absint( $general['search_page_id'] ?? $current['general']['search_page_id'] );

		// Search.
		$clean['search']['minimum_characters'] = self::clamp_int( $search['minimum_characters'] ?? $current['search']['minimum_characters'], 1, 5, 2 );
		$clean['search']['suggestion_limit']   = self::clamp_int( $search['suggestion_limit'] ?? $current['search']['suggestion_limit'], 1, 20, 8 );
		$clean['search']['cache_ttl_seconds']  = self::clamp_int( $search['cache_ttl_seconds'] ?? $current['search']['cache_ttl_seconds'], 0, 3600, 300 );
		$clean['search']['enabled_filters']    = self::sanitize_filters( $search['enabled_filters'] ?? $current['search']['enabled_filters'] );

		// Booking.
		$clean['booking']['default_type']   = self::enum( $booking['default_type'] ?? $current['booking']['default_type'], self::ALLOWED_BOOKING_METHODS, 'enquiry' );
		$clean['booking']['default_label']  = sanitize_text_field( (string) ( $booking['default_label'] ?? $current['booking']['default_label'] ) );
		$clean['booking']['default_target'] = self::enum( $booking['default_target'] ?? $current['booking']['default_target'], self::ALLOWED_TARGETS, '_blank' );
		// Stored as plain text rather than forced through esc_url_raw(): the
		// field accepts relative paths and in-page anchors (popup triggers)
		// that strict URL sanitization would mangle. Safety is enforced where
		// it matters — BookingResolver::is_safe() revalidates the destination
		// before any button renders, and templates escape with esc_url().
		$clean['booking']['default_url']    = sanitize_text_field( (string) ( $booking['default_url'] ?? $current['booking']['default_url'] ?? '' ) );
		$clean['booking']['default_phone']  = self::sanitize_phone( $booking['default_phone'] ?? $current['booking']['default_phone'] ?? '' );

		// Design.
		$clean['design']['card_radius'] = sanitize_text_field( (string) ( $design['card_radius'] ?? '8px' ) );
		$clean['design']['image_ratio'] = sanitize_text_field( (string) ( $design['image_ratio'] ?? '4/5' ) );

		// Privacy.
		$clean['privacy']['analytics_enabled']        = ! empty( $privacy['analytics_enabled'] );
		$clean['privacy']['analytics_retention_days'] = self::clamp_int( $privacy['analytics_retention_days'] ?? $current['privacy']['analytics_retention_days'], 1, 3650, 90 );

		// Data. The destructive uninstall flag is only writable by a manager.
		$clean['data']['delete_on_uninstall'] = current_user_can( Capabilities::MANAGE )
			? ! empty( $data['delete_on_uninstall'] )
			: (bool) $current['data']['delete_on_uninstall'];

		$clean['version'] = self::SCHEMA_VERSION;

		// If the directory base changed, request a one-time rewrite flush so the
		// doctor permalinks update without requiring manual re-saving.
		if ( $clean['general']['directory_base'] !== $current['general']['directory_base'] ) {
			update_option( Activator::FLUSH_FLAG, '1', false );
		}

		return $clean;
	}

	/**
	 * Recursively merge stored values over defaults, keeping only known keys.
	 *
	 * @param array<string, mixed> $defaults Default structure.
	 * @param array<string, mixed> $stored   Stored values.
	 * @return array<string, mixed>
	 */
	private static function merge_defaults( array $defaults, array $stored ): array {
		foreach ( $defaults as $key => $default_value ) {
			if ( ! array_key_exists( $key, $stored ) ) {
				continue;
			}

			if ( is_array( $default_value ) && is_array( $stored[ $key ] ) ) {
				$defaults[ $key ] = self::merge_defaults( $default_value, $stored[ $key ] );
			} else {
				$defaults[ $key ] = $stored[ $key ];
			}
		}

		return $defaults;
	}

	/**
	 * Keep only the characters a dialable number can contain.
	 *
	 * Matches the per-doctor `phone` sanitizer so a global fallback and a
	 * doctor's own number are stored identically.
	 *
	 * @param mixed $value Raw phone value.
	 * @return string
	 */
	private static function sanitize_phone( $value ): string {
		$value = is_scalar( $value ) ? (string) $value : '';

		return trim( (string) preg_replace( '/[^0-9+()\-\s]/', '', $value ) );
	}

	/**
	 * Clamp a value to an integer within an inclusive range.
	 *
	 * @param mixed $value    Raw value.
	 * @param int   $min      Minimum allowed.
	 * @param int   $max      Maximum allowed.
	 * @param int   $fallback Fallback when the value is not numeric.
	 * @return int
	 */
	private static function clamp_int( $value, int $min, int $max, int $fallback ): int {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		return max( $min, min( $max, (int) $value ) );
	}

	/**
	 * Return the value if it is an allowed enum member, otherwise the fallback.
	 *
	 * @param mixed             $value    Raw value.
	 * @param array<int, string> $allowed  Allowed values.
	 * @param string            $fallback Fallback value.
	 * @return string
	 */
	private static function enum( $value, array $allowed, string $fallback ): string {
		$value = is_string( $value ) ? $value : '';

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Sanitize a rewrite base slug, rejecting reserved and unsafe values.
	 *
	 * @param mixed $value Raw slug.
	 * @return string
	 */
	private static function sanitize_base_slug( $value ): string {
		$slug     = sanitize_title( (string) $value );
		$reserved = array( '', 'wp-admin', 'wp-content', 'wp-includes', 'feed', 'admin' );

		if ( in_array( $slug, $reserved, true ) ) {
			return 'find-a-doctor';
		}

		return $slug;
	}

	/**
	 * Sanitize the list of enabled filters against the known filter keys.
	 *
	 * @param mixed $value Raw filter list.
	 * @return array<int, string>
	 */
	private static function sanitize_filters( $value ): array {
		$allowed = array( 'department', 'specialty', 'symptom', 'condition', 'treatment', 'gender', 'language', 'location' );
		$value   = is_array( $value ) ? $value : array();
		$clean   = array();

		foreach ( $value as $filter ) {
			$filter = sanitize_key( (string) $filter );
			if ( in_array( $filter, $allowed, true ) && ! in_array( $filter, $clean, true ) ) {
				$clean[] = $filter;
			}
		}

		return array() === $clean ? array( 'department', 'specialty', 'symptom', 'gender', 'language' ) : $clean;
	}
}
