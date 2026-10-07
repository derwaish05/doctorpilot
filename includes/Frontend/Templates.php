<?php
/**
 * Frontend template loading with theme overrides.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Frontend;

use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Swaps in DoctorPilot templates for the doctor single and archive views.
 *
 * The active theme may override any template by placing a file in a
 * `doctorpilot/` directory (e.g. `doctorpilot/single-doctor.php`); otherwise the
 * plugin default in `templates/` is used.
 */
final class Templates {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'template_include', array( $this, 'template_include' ) );
	}

	/**
	 * Choose the correct template for doctor views.
	 *
	 * @param string $template Resolved template path.
	 * @return string
	 */
	public function template_include( string $template ): string {
		if ( is_singular( DoctorPostType::POST_TYPE ) ) {
			return self::locate( 'single-doctor.php', $template );
		}

		if ( is_post_type_archive( DoctorPostType::POST_TYPE ) || self::is_doctor_taxonomy() ) {
			return self::locate( 'archive-doctor.php', $template );
		}

		return $template;
	}

	/**
	 * Locate a template, preferring a theme override.
	 *
	 * @param string $name     Template file name.
	 * @param string $fallback Fallback template path.
	 * @return string
	 */
	public static function locate( string $name, string $fallback = '' ): string {
		$theme = locate_template( array( 'doctorpilot/' . $name ) );
		if ( '' !== $theme ) {
			return $theme;
		}

		$plugin = DOCTORPILOT_PLUGIN_DIR . 'templates/' . $name;
		if ( is_readable( $plugin ) ) {
			return $plugin;
		}

		return $fallback;
	}

	/**
	 * Whether the current query is a DoctorPilot taxonomy archive.
	 *
	 * Public because the archive template asks the same question when it titles
	 * the page and bridges the queried term into the directory's filters.
	 *
	 * @return bool
	 */
	public static function is_doctor_taxonomy(): bool {
		$taxonomies = \BrainStudioz\DoctorPilot\Taxonomies\Taxonomies::all();

		foreach ( $taxonomies as $taxonomy ) {
			if ( is_tax( $taxonomy ) ) {
				return true;
			}
		}

		return false;
	}
}
