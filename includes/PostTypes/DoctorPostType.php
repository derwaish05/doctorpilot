<?php
/**
 * Doctor custom post type.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\PostTypes;

use BrainStudioz\DoctorPilot\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the internal `doctorpilot_doctor` post type.
 *
 * The type is prefixed to avoid collisions, uses mapped capabilities, and keeps
 * `show_in_rest` false so the core REST controller never exposes doctor meta.
 * DoctorPilot's own public controller (added in later milestones) owns the
 * allowlist. WordPress `post_status` is the sole publication authority.
 */
final class DoctorPostType {

	/**
	 * Post type key.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'doctorpilot_doctor';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ), 5 );
	}

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$base = self::directory_base();

		$labels = array(
			'name'               => _x( 'Doctors', 'post type general name', 'doctorpilot' ),
			'singular_name'      => _x( 'Doctor', 'post type singular name', 'doctorpilot' ),
			'menu_name'          => _x( 'Doctors', 'admin menu', 'doctorpilot' ),
			'add_new'            => __( 'Add New', 'doctorpilot' ),
			'add_new_item'       => __( 'Add New Doctor', 'doctorpilot' ),
			'edit_item'          => __( 'Edit Doctor', 'doctorpilot' ),
			'new_item'           => __( 'New Doctor', 'doctorpilot' ),
			'view_item'          => __( 'View Doctor', 'doctorpilot' ),
			'search_items'       => __( 'Search Doctors', 'doctorpilot' ),
			'not_found'          => __( 'No doctors found.', 'doctorpilot' ),
			'not_found_in_trash' => __( 'No doctors found in Trash.', 'doctorpilot' ),
			'all_items'          => __( 'All Doctors', 'doctorpilot' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				// Nest under the single DoctorPilot admin menu to avoid a duplicate top-level menu.
				'show_in_menu'        => 'doctorpilot',
				'show_in_nav_menus'   => true,
				'show_in_rest'        => false,
				'has_archive'         => true,
				'hierarchical'        => false,
				'exclude_from_search' => false,
				'menu_icon'           => 'dashicons-businessperson',
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'revisions' ),
				'rewrite'             => array(
					'slug'       => $base,
					'with_front' => false,
					'feeds'      => false,
				),
				'capability_type'     => array( 'doctorpilot_doctor', 'doctorpilot_doctors' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * The configured directory base slug for doctor URLs.
	 *
	 * @return string
	 */
	public static function directory_base(): string {
		$settings = Settings::get();
		$base     = isset( $settings['general']['directory_base'] ) ? (string) $settings['general']['directory_base'] : 'find-a-doctor';

		return '' === $base ? 'find-a-doctor' : $base;
	}
}
