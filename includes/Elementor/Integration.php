<?php
/**
 * Elementor integration bootstrap.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the DoctorPilot widget category and Free widgets — but only when a
 * supported Elementor is active.
 *
 * Widget classes are referenced solely inside hooks that Elementor fires, so the
 * `\Elementor\Widget_Base` parent is always available when they load. If
 * Elementor is missing or outdated, nothing here runs and native output stands.
 */
final class Integration {

	/**
	 * Shared frontend style handle used by widgets.
	 *
	 * @var string
	 */
	public const STYLE_HANDLE = 'doctorpilot-frontend';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Surface an actionable notice if Elementor is present but too old.
		if ( Compatibility::is_outdated() ) {
			add_action( 'admin_notices', array( $this, 'outdated_notice' ) );
			return;
		}

		if ( ! Compatibility::is_supported() ) {
			return;
		}

		// Ensure the shared style handle is registered for widget dependencies.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_style' ), 1 );
		add_action( 'elementor/editor/after_enqueue_styles', array( $this, 'register_style' ) );

		// Widget scripts are registered (not enqueued); Elementor enqueues them
		// only on pages where the depending widget is actually rendered.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ), 1 );
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'register_scripts' ) );

		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * Register the shared style handle (registered, enqueued on demand).
	 *
	 * @return void
	 */
	public function register_style(): void {
		if ( ! wp_style_is( self::STYLE_HANDLE, 'registered' ) ) {
			$path = DOCTORPILOT_PLUGIN_DIR . 'assets/css/frontend.css';
			$ver  = is_readable( $path ) ? (string) filemtime( $path ) : DOCTORPILOT_VERSION;

			wp_register_style(
				self::STYLE_HANDLE,
				DOCTORPILOT_PLUGIN_URL . 'assets/css/frontend.css',
				array(),
				$ver
			);
		}
	}

	/**
	 * Register widget scripts and their localized strings.
	 *
	 * @return void
	 */
	public function register_scripts(): void {
		$handle = Widgets\DoctorGridList::SCRIPT_HANDLE;

		if ( wp_script_is( $handle, 'registered' ) ) {
			return;
		}

		$path = DOCTORPILOT_PLUGIN_DIR . 'assets/js/directory.js';
		$ver  = is_readable( $path ) ? (string) filemtime( $path ) : DOCTORPILOT_VERSION;

		wp_register_script(
			$handle,
			DOCTORPILOT_PLUGIN_URL . 'assets/js/directory.js',
			array(),
			$ver,
			true
		);

		wp_localize_script(
			$handle,
			'doctorPilotDirectory',
			array(
				'strings' => array(
					/* translators: %d: number of matching doctors. */
					'countSingular' => __( '%d doctor found', 'doctorpilot' ),
					/* translators: %d: number of matching doctors. */
					'countPlural'   => __( '%d doctors found', 'doctorpilot' ),
					'empty'         => __( 'No doctors matched your search.', 'doctorpilot' ),
					'error'         => __( 'Something went wrong. Please try again.', 'doctorpilot' ),
					'viewProfile'   => __( 'View Profile', 'doctorpilot' ),
					'featured'      => __( 'Featured', 'doctorpilot' ),
					'international' => __( 'International', 'doctorpilot' ),
					'acceptingNew'  => __( 'Accepting new patients', 'doctorpilot' ),
					/* translators: %d: years of experience. */
					'yearSingular'  => __( '%d year experience', 'doctorpilot' ),
					/* translators: %d: years of experience. */
					'yearPlural'    => __( '%d years experience', 'doctorpilot' ),
					'pagination'    => __( 'Doctor results pages', 'doctorpilot' ),
					/* translators: %d: total number of matches. */
					'truncated'     => __( 'Showing the first results of %d doctors. Narrow the search to see the rest.', 'doctorpilot' ),
				),
			)
		);
	}

	/**
	 * Register the DoctorPilot widget category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 * @return void
	 */
	public function register_category( $elements_manager ): void {
		$elements_manager->add_category(
			'doctorpilot',
			array(
				'title' => __( 'DoctorPilot', 'doctorpilot' ),
				'icon'  => 'eicon-person',
			)
		);

		$elements_manager->add_category(
			'doctorpilot-single',
			array(
				'title' => __( 'Single DoctorPilot', 'doctorpilot' ),
				'icon'  => 'eicon-single-post',
			)
		);
	}

	/**
	 * Register the Free widgets.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public function register_widgets( $widgets_manager ): void {
		$widgets = array(
			Widgets\DoctorSearch::class,
			Widgets\DoctorGridList::class,
			Widgets\DepartmentGrid::class,
			Widgets\SymptomDirectory::class,
			Widgets\DoctorProfile::class,
			Widgets\PageHeader::class,
			Widgets\HeadingBlock::class,
			Widgets\AboutDoctor::class,
			Widgets\Specialties::class,
			Widgets\ExperienceTimeline::class,
			Widgets\BookingCard::class,
			Widgets\DoctorFacts::class,
			Widgets\AcademicHighlights::class,
			Widgets\Certifications::class,
			Widgets\CertsPublications::class,
			Widgets\BookingButton::class,
		);

		foreach ( $widgets as $widget_class ) {
			$widgets_manager->register( new $widget_class() );
		}
	}

	/**
	 * Admin notice when Elementor is active but outdated.
	 *
	 * @return void
	 */
	public function outdated_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		printf(
			/* translators: %s: minimum Elementor version. */
			esc_html__( 'DoctorPilot Elementor widgets require Elementor %s or newer. Native directory output is still available.', 'doctorpilot' ),
			esc_html( Compatibility::MIN_ELEMENTOR )
		);
		echo '</p></div>';
	}
}
