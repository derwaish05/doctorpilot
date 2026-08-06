<?php
/**
 * Frontend asset loading.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Frontend;

use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;
use BrainStudioz\DoctorPilot\Taxonomies\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues the small frontend stylesheet only on doctor-related views and on
 * posts that contain a DoctorPilot shortcode, so no assets load site-wide.
 */
final class Assets {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		// Priority 1: register Elementor's frontend handles before the theme (and
		// Elementor's per-template CSS) enqueue assets that depend on them —
		// otherwise WordPress warns "dependencies that are not registered:
		// elementor-frontend" and the load order breaks.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_elementor_template_assets' ), 1 );
	}

	/**
	 * Force Elementor's frontend assets on a single-doctor page that renders an
	 * Elementor template.
	 *
	 * The single-doctor template calls `get_builder_content_for_display()` to print
	 * the chosen template, but the main queried object is a doctor — not an
	 * Elementor document — so Elementor never bootstraps its own frontend. Two
	 * problems follow: (1) the base flexbox container CSS (`.e-con`) never loads, so
	 * multi-column layouts collapse; and (2) the `elementor-frontend` style/script
	 * handles are never registered, so the theme's Elementor-template assets (e.g.
	 * mega-menu templates) enqueue against a missing dependency and WordPress emits
	 * "dependencies that are not registered" notices.
	 *
	 * Registering the handles first (early) and then enqueuing fixes both. Guarded
	 * for Elementor's presence and API across versions.
	 *
	 * @return void
	 */
	public function enqueue_elementor_template_assets(): void {
		if ( ! is_singular( DoctorPostType::POST_TYPE ) ) {
			return;
		}

		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}

		$template_id = (int) \BrainStudioz\DoctorPilot\Admin\Settings::single_doctor_template();
		if ( $template_id <= 0 ) {
			return;
		}

		$elementor = \Elementor\Plugin::instance();

		if ( isset( $elementor->frontend ) ) {
			$frontend = $elementor->frontend;

			// Register the base handles first so dependents (theme + per-template
			// CSS) always find `elementor-frontend` registered.
			if ( method_exists( $frontend, 'register_styles' ) ) {
				$frontend->register_styles();
			}
			if ( method_exists( $frontend, 'register_scripts' ) ) {
				$frontend->register_scripts();
			}

			// Then enqueue the base frontend styles (flexbox container CSS) and
			// scripts (for any interactive widgets).
			if ( method_exists( $frontend, 'enqueue_styles' ) ) {
				$frontend->enqueue_styles();
			}
			if ( method_exists( $frontend, 'enqueue_scripts' ) ) {
				$frontend->enqueue_scripts();
			}
		}

		// The template's own generated CSS (element/container overrides).
		if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			\Elementor\Core\Files\CSS\Post::create( $template_id )->enqueue();
		}
	}

	/**
	 * Conditionally enqueue the frontend stylesheet.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! $this->should_load() ) {
			return;
		}

		$path = DOCTORPILOT_PLUGIN_DIR . 'assets/css/frontend.css';
		$ver  = is_readable( $path ) ? (string) filemtime( $path ) : DOCTORPILOT_VERSION;

		wp_enqueue_style(
			'doctorpilot-frontend',
			DOCTORPILOT_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			$ver
		);
	}

	/**
	 * Whether the current request needs DoctorPilot frontend assets.
	 *
	 * @return bool
	 */
	private function should_load(): bool {
		if ( is_singular( DoctorPostType::POST_TYPE ) || is_post_type_archive( DoctorPostType::POST_TYPE ) ) {
			return true;
		}

		foreach ( Taxonomies::all() as $taxonomy ) {
			if ( is_tax( $taxonomy ) ) {
				return true;
			}
		}

		$post = get_post();
		if ( $post instanceof \WP_Post ) {
			if ( has_shortcode( (string) $post->post_content, 'doctorpilot_directory' )
				|| has_shortcode( (string) $post->post_content, 'doctorpilot_profile' ) ) {
				return true;
			}
		}

		return false;
	}
}
