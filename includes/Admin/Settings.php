<?php
/**
 * Versioned plugin settings object and validation.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Admin;

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
			),
			'search'  => array(
				'minimum_characters' => 2,
				'suggestion_limit'   => 8,
				'enabled_filters'    => array( 'department', 'specialty', 'symptom', 'gender', 'language' ),
				'cache_ttl_seconds'  => 300,
			),
			'booking' => array(
				'default_type'   => 'external_url',
				'default_label'  => 'Book Appointment',
				'default_target' => '_self',
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

		// Search.
		$clean['search']['minimum_characters'] = self::clamp_int( $search['minimum_characters'] ?? $current['search']['minimum_characters'], 1, 5, 2 );
		$clean['search']['suggestion_limit']   = self::clamp_int( $search['suggestion_limit'] ?? $current['search']['suggestion_limit'], 1, 20, 8 );
		$clean['search']['cache_ttl_seconds']  = self::clamp_int( $search['cache_ttl_seconds'] ?? $current['search']['cache_ttl_seconds'], 0, 3600, 300 );
		$clean['search']['enabled_filters']    = self::sanitize_filters( $search['enabled_filters'] ?? $current['search']['enabled_filters'] );

		// Booking.
		$clean['booking']['default_type']   = sanitize_key( (string) ( $booking['default_type'] ?? 'external_url' ) );
		$clean['booking']['default_label']  = sanitize_text_field( (string) ( $booking['default_label'] ?? 'Book Appointment' ) );
		$clean['booking']['default_target'] = self::enum( $booking['default_target'] ?? '_self', self::ALLOWED_TARGETS, '_self' );

		// Design.
		$clean['design']['card_radius'] = sanitize_text_field( (string) ( $design['card_radius'] ?? '8px' ) );
		$clean['design']['image_ratio'] = sanitize_text_field( (string) ( $design['image_ratio'] ?? '4/5' ) );

		// Privacy.
		$clean['privacy']['analytics_enabled']        = ! empty( $privacy['analytics_enabled'] );
		$clean['privacy']['analytics_retention_days'] = self::clamp_int( $privacy['analytics_retention_days'] ?? 90, 1, 3650, 90 );

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
