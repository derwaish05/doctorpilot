<?php
/**
 * Advanced Elementor integration (dynamic tags + Related Doctors).
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the advanced Elementor extensions when a supported Elementor is
 * active.
 *
 * Kept separate from `Integration` because the two have different preconditions.
 * Dynamic tags and Theme Builder binding are *Elementor Pro* features — the
 * paid Elementor plugin, unrelated to this one — so they register only when
 * that dynamic-tags API is present. Everything here is guarded through the
 * Compatibility class, and a site without Elementor loads none of it while the
 * native templates and shortcodes carry on unaffected.
 */
final class ProElementor {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! class_exists( '\\BrainStudioz\\DoctorPilot\\Elementor\\Compatibility' )
			|| ! \BrainStudioz\DoctorPilot\Elementor\Compatibility::is_supported() ) {
			return;
		}

		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );

		// Dynamic tags require Elementor Pro.
		if ( \BrainStudioz\DoctorPilot\Elementor\Compatibility::is_pro_supported() ) {
			add_action( 'elementor/dynamic_tags/register', array( $this, 'register_tags' ) );
		}
	}

	/**
	 * Register Pro widgets.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
	 * @return void
	 */
	public function register_widgets( $widgets_manager ): void {
		$widgets_manager->register( new RelatedDoctors() );
	}

	/**
	 * Register Pro dynamic tags.
	 *
	 * @param object $dynamic_tags Elementor dynamic tags manager.
	 * @return void
	 */
	public function register_tags( $dynamic_tags ): void {
		if ( ! method_exists( $dynamic_tags, 'register' ) ) {
			return;
		}
		$dynamic_tags->register( new DoctorFieldTag() );
	}
}
