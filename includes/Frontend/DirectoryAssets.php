<?php
/**
 * Registration and enqueueing of the doctor directory's stylesheet and script.
 *
 * Two things render the directory — the Doctor Grid / List Elementor widget and
 * the doctor archive — and both need the same premium card layer and the same
 * `directory.js`. Elementor enqueues them through the widget's dependencies; the
 * archive has no widget, so it enqueues them here. Registration lives in one
 * place so the two paths cannot drift apart, and so the archive keeps working
 * on a site with no Elementor at all.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Directory asset handles and their registration.
 */
final class DirectoryAssets {

	/**
	 * Shared frontend stylesheet handle.
	 *
	 * @var string
	 */
	public const STYLE = 'doctorpilot-frontend';

	/**
	 * Premium card layer handle. Separate from the shared stylesheet so pages
	 * without the directory never download it.
	 *
	 * @var string
	 */
	public const PREMIUM_STYLE = 'doctorpilot-directory-premium';

	/**
	 * Directory script handle.
	 *
	 * @var string
	 */
	public const SCRIPT = 'doctorpilot-directory';

	/**
	 * Register the shared and premium stylesheets.
	 *
	 * @return void
	 */
	public static function register_styles(): void {
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style(
				self::STYLE,
				DOCTORPILOT_PLUGIN_URL . 'assets/css/frontend.css',
				array(),
				self::version( 'assets/css/frontend.css' )
			);
		}

		if ( ! wp_style_is( self::PREMIUM_STYLE, 'registered' ) ) {
			wp_register_style(
				self::PREMIUM_STYLE,
				DOCTORPILOT_PLUGIN_URL . 'assets/css/directory-premium.css',
				array( self::STYLE ),
				self::version( 'assets/css/directory-premium.css' )
			);
		}
	}

	/**
	 * Register the directory script and its localized strings.
	 *
	 * @return void
	 */
	public static function register_script(): void {
		if ( wp_script_is( self::SCRIPT, 'registered' ) ) {
			return;
		}

		wp_register_script(
			self::SCRIPT,
			DOCTORPILOT_PLUGIN_URL . 'assets/js/directory.js',
			array(),
			self::version( 'assets/js/directory.js' ),
			true
		);

		wp_localize_script( self::SCRIPT, 'doctorPilotDirectory', array( 'strings' => self::strings() ) );
	}

	/**
	 * Register everything, then enqueue it.
	 *
	 * The premium layer depends on the shared stylesheet, so enqueueing it pulls
	 * both in the right order.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		self::register_styles();
		self::register_script();

		wp_enqueue_style( self::PREMIUM_STYLE );
		wp_enqueue_script( self::SCRIPT );
	}

	/**
	 * Strings the directory script renders client-side.
	 *
	 * These mirror the server-rendered markup: a card built by `directory.js`
	 * after a filter change must read exactly like the one PHP printed, so any
	 * change here belongs in `templates/card-doctor.php` too.
	 *
	 * @return array<string, string>
	 */
	public static function strings(): array {
		return array(
			/* translators: %d: number of matching doctors. */
			'countSingular' => __( '%d doctor found', 'doctorpilot' ),
			/* translators: %d: number of matching doctors. */
			'countPlural'   => __( '%d doctors found', 'doctorpilot' ),
			'empty'         => __( 'No doctors matched your search.', 'doctorpilot' ),
			/* translators: %s: index name, e.g. "departments". */
			'emptyTerms'    => __( 'No %s matched your search.', 'doctorpilot' ),
			'error'         => __( 'Something went wrong. Please try again.', 'doctorpilot' ),
			'viewProfile'   => __( 'View Profile', 'doctorpilot' ),
			'featured'      => __( 'Featured', 'doctorpilot' ),
			'international' => __( 'International', 'doctorpilot' ),
			/* translators: %d: years of experience. */
			'yearSingular'  => __( '%d year experience', 'doctorpilot' ),
			/* translators: %d: years of experience. */
			'yearPlural'    => __( '%d years experience', 'doctorpilot' ),
			'pagination'    => __( 'Doctor results pages', 'doctorpilot' ),
			/* translators: separator between the specialty and department on a compact result row. Must match the one in templates/row-doctor.php. */
			'metaSeparator' => __( ' · ', 'doctorpilot' ),
			/* translators: %s: doctor name. Appended, visually hidden, to a "View Profile" link. */
			'profileFor'    => __( 'for %s', 'doctorpilot' ),
			/* translators: %s: doctor name. Appended, visually hidden, to a booking button. */
			'bookingWith'   => __( 'with %s', 'doctorpilot' ),
			/* translators: booking button label when the doctor has no label of their own. Must match the one in templates/card-doctor.php. */
			'bookNow'       => __( 'Book an Appointment', 'doctorpilot' ),
		);
	}

	/**
	 * Cache-busting version for a plugin-relative asset.
	 *
	 * @param string $relative Path below the plugin directory.
	 * @return string
	 */
	private static function version( string $relative ): string {
		$path = DOCTORPILOT_PLUGIN_DIR . $relative;

		return is_readable( $path ) ? (string) filemtime( $path ) : DOCTORPILOT_VERSION;
	}
}
