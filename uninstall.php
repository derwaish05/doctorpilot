<?php
/**
 * DoctorPilot uninstall handler.
 *
 * Runs only when the site owner has explicitly enabled "Delete data on
 * uninstall" and then deletes the plugin. With that setting off — the default —
 * deleting the plugin leaves every doctor, every import log and every redirect
 * exactly where it was, so an accidental delete is recoverable by reinstalling.
 *
 * When the setting is on, the checkbox promises "permanently remove all
 * DoctorPilot data", and this file has to keep that promise: posts, meta,
 * terms, tables, options, transients, cron events, roles and capabilities. A
 * handler that removed only some of them would leave the site dirty while
 * telling the owner it was clean.
 *
 * Removal is ordered from the most dependent thing to the least: posts before
 * the taxonomies whose terms they reference, child tables before parents,
 * capabilities before the roles that hold them. If the process is interrupted
 * partway, what remains is still internally consistent.
 *
 * Everything here is per-site — options, posts, terms, roles and the tables all
 * sit behind `$wpdb->prefix` — so on multisite the whole routine runs once per
 * blog inside `switch_to_blog()`, and the opt-in setting is read per blog too.
 * One site in a network opting into deletion must not delete another's data.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) || ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * WordPress loads this file with the plugin unloaded, so the autoloader has to
 * be registered by hand. Doing that rather than restating the table, role and
 * capability lists here means uninstall cannot drift out of step with the code
 * that created them — which is exactly how the analytics table came to be
 * created but never dropped: the old handler named `..._analytics` while the
 * table has always been `..._analytics_daily`, so its `DROP TABLE IF EXISTS`
 * matched nothing and reported success.
 */
require_once __DIR__ . '/includes/Support/Autoloader.php';

\BrainStudioz\DoctorPilot\Support\Autoloader::register(
	'BrainStudioz\\DoctorPilot\\',
	__DIR__ . '/includes/'
);

/**
 * Remove every DoctorPilot-owned thing belonging to the current site.
 *
 * @return void
 */
function bstdz_doctorpilot_uninstall_site(): void {
	global $wpdb;

	$settings = get_option( 'bstdz_doctorpilot_settings', array() );

	$delete_enabled = is_array( $settings )
		&& isset( $settings['data']['delete_on_uninstall'] )
		&& true === (bool) $settings['data']['delete_on_uninstall'];

	// Default behaviour: keep everything.
	if ( ! $delete_enabled ) {
		return;
	}

	/*
	 * 1. Content. Force-deleted so nothing is left in the trash, and in bounded
	 *    batches so a directory with thousands of doctors does not exhaust
	 *    memory in a single query.
	 */
	$post_types = array(
		'doctorpilot_doctor',
		'doctorpilot_exp',
		'doctorpilot_edu',
		'doctorpilot_proc',
		'doctorpilot_pub',
		'doctorpilot_cert',
		'doctorpilot_mem',
		'doctorpilot_faq',
	);

	foreach ( $post_types as $post_type ) {
		do {
			$ids = get_posts(
				array(
					'post_type'        => $post_type,
					'post_status'      => 'any',
					'posts_per_page'   => 200,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => true,
				)
			);

			foreach ( $ids as $id ) {
				wp_delete_post( (int) $id, true );
			}
		} while ( array() !== $ids );
	}

	/*
	 * 2. Taxonomy terms, after the posts, so no term is removed while a post
	 *    still references it.
	 */
	$taxonomies = array(
		'doctorpilot_department',
		'doctorpilot_specialty',
		'doctorpilot_symptom',
		'doctorpilot_condition',
		'doctorpilot_treatment',
		'doctorpilot_language',
		'doctorpilot_location',
		'doctorpilot_insurance',
	);

	foreach ( $taxonomies as $taxonomy ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $terms ) ) {
			continue;
		}

		foreach ( $terms as $term_id ) {
			wp_delete_term( (int) $term_id, $taxonomy );
		}
	}

	/*
	 * 3. Orphan meta. `wp_delete_post` takes a post's own meta with it, but meta
	 *    written against a post of another type — a page carrying a DoctorPilot
	 *    key, for instance — would survive.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_doctorpilot_' ) . '%' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_doctorpilot_' ) . '%' ) );

	/*
	 * 4. Tables, named by the class that creates them rather than restated here.
	 *    `Tables::all()` returns them children-first.
	 */
	\BrainStudioz\DoctorPilot\Support\Tables::drop();

	/*
	 * 5. Capabilities before roles: removing a role first would leave the same
	 *    capabilities granted to every other role behind.
	 */
	\BrainStudioz\DoctorPilot\Support\Capabilities::remove_from_all_roles();
	\BrainStudioz\DoctorPilot\Support\Roles::remove();

	/*
	 * 6. Options. The `_pro_` keys are historical — written by the Pro add-on
	 *    before it was merged into this plugin — and are still the keys on disk,
	 *    so they are deleted under their real names.
	 */
	$options = array(
		'bstdz_doctorpilot_settings',
		'bstdz_doctorpilot_settings_group',
		'bstdz_doctorpilot_schema_version',
		'bstdz_doctorpilot_plugin_version',
		'bstdz_doctorpilot_cache_generation',
		'bstdz_doctorpilot_needs_rewrite_flush',
		'bstdz_doctorpilot_retired_addon',
		'bstdz_doctorpilot_pro_version',
		'bstdz_doctorpilot_pro_db_version',
		'bstdz_doctorpilot_pro_bookpilot',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	/*
	 * 7. Cached search and facet responses. They are transients under a shared
	 *    prefix rather than named options, so they are matched on the key. A site
	 *    on a persistent object cache keeps no `wp_options` rows for them, and
	 *    this simply matches nothing; those entries expire on their own TTL.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_dp_search_' ) . '%', $wpdb->esc_like( '_transient_timeout_dp_search_' ) . '%' ) );

	/*
	 * 8. Scheduled work.
	 */
	wp_clear_scheduled_hook( 'bstdz_doctorpilot_daily_maintenance' );
	wp_clear_scheduled_hook( 'bstdz_doctorpilot_pro_retention' );
	wp_clear_scheduled_hook( 'bstdz_doctorpilot_pro_run_import' );
}

if ( is_multisite() ) {
	/*
	 * Bounded, so a network with tens of thousands of sites cannot turn a plugin
	 * deletion into a request that never returns. A network larger than this
	 * needs WP-CLI, not a page load.
	 */
	$dp_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 1000,
		)
	);

	foreach ( $dp_sites as $dp_site_id ) {
		switch_to_blog( (int) $dp_site_id );
		bstdz_doctorpilot_uninstall_site();
		restore_current_blog();
	}
} else {
	bstdz_doctorpilot_uninstall_site();
}
