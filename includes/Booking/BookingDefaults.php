<?php
/**
 * Site-wide booking defaults.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Booking;

use BrainStudioz\DoctorPilot\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the Settings page's Booking section on behalf of the providers.
 *
 * A doctor's own field always wins; blank means inherit. Keeping that rule in
 * one place stops each provider from re-deciding what "empty" means, and the
 * per-request cache keeps a directory page of 48 cards from re-reading and
 * re-merging the settings option 48 times.
 */
final class BookingDefaults {

	/**
	 * Cached booking settings for this request.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * The booking settings group, fully populated.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$settings    = Settings::get();
			self::$cache = is_array( $settings['booking'] ?? null ) ? $settings['booking'] : array();
		}

		return self::$cache;
	}

	/**
	 * Forget the cached settings (used by tests and after a settings save).
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$cache = null;
	}

	/**
	 * The booking method a doctor inherits when they have not chosen one.
	 *
	 * @return string
	 */
	public static function method(): string {
		return (string) ( self::all()['default_type'] ?? 'external_url' );
	}

	/**
	 * Site-wide booking URL, or an empty string.
	 *
	 * @return string
	 */
	public static function url(): string {
		return trim( (string) ( self::all()['default_url'] ?? '' ) );
	}

	/**
	 * Site-wide booking phone number, or an empty string.
	 *
	 * @return string
	 */
	public static function phone(): string {
		return trim( (string) ( self::all()['default_phone'] ?? '' ) );
	}

	/**
	 * Link target for external booking URLs.
	 *
	 * @return string
	 */
	public static function target(): string {
		$target = (string) ( self::all()['default_target'] ?? '_blank' );

		return in_array( $target, array( '_self', '_blank' ), true ) ? $target : '_blank';
	}

	/**
	 * Resolve a button label: the doctor's own, then the site default, then the
	 * wording that belongs to this particular method.
	 *
	 * @param string $doctor_label   The doctor's label, possibly empty.
	 * @param string $method_default Fallback wording for the calling provider.
	 * @return string
	 */
	public static function label( string $doctor_label, string $method_default ): string {
		$doctor_label = trim( $doctor_label );
		if ( '' !== $doctor_label ) {
			return $doctor_label;
		}

		$global = trim( (string) ( self::all()['default_label'] ?? '' ) );

		return '' !== $global ? $global : $method_default;
	}
}
