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

		wp_enqueue_style(
			'doctorpilot-frontend',
			DOCTORPILOT_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			DOCTORPILOT_VERSION
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
