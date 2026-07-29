<?php
/**
 * DoctorPilot uninstall handler.
 *
 * Data is preserved by default. DoctorPilot-owned data is removed only when the
 * administrator has explicitly enabled "delete data on uninstall" in settings.
 * Shared WordPress/user data is never touched.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

// Only run in a genuine WordPress uninstall context.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remove DoctorPilot-owned data for a single site.
 *
 * @return void
 */
function bstdz_doctorpilot_uninstall_site(): void {
	$settings = get_option( 'bstdz_doctorpilot_settings', array() );

	$delete_enabled = is_array( $settings )
		&& isset( $settings['data']['delete_on_uninstall'] )
		&& true === (bool) $settings['data']['delete_on_uninstall'];

	// Default behaviour: keep all data.
	if ( ! $delete_enabled ) {
		return;
	}

	// Remove plugin options only. Posts, terms, and meta removal is added
	// alongside the modules that create them (post type, taxonomies, tables),
	// each guarded by this same opt-in flag.
	$options = array(
		'bstdz_doctorpilot_plugin_version',
		'bstdz_doctorpilot_schema_version',
		'bstdz_doctorpilot_cache_generation',
		'bstdz_doctorpilot_settings',
		'bstdz_doctorpilot_needs_rewrite_flush',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Clear scheduled maintenance if still registered.
	wp_clear_scheduled_hook( 'bstdz_doctorpilot_daily_maintenance' );
}

if ( is_multisite() ) {
	// Bounded iteration to avoid unbounded network loops on large installs.
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 1000,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		bstdz_doctorpilot_uninstall_site();
		restore_current_blog();
	}
} else {
	bstdz_doctorpilot_uninstall_site();
}
