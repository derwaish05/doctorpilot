<?php
/**
 * Retires the DoctorPilot Pro add-on after its code was merged into this plugin.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Infrastructure;

use BrainStudioz\DoctorPilot\Infrastructure\Lifecycle\Activator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivates a leftover `doctorpilot-pro` installation, silently, and keeps the
 * schedules it may already have cleared alive.
 *
 * The add-on's code became part of this plugin in 0.2.0. Its files, however, are
 * still sitting in `wp-content/plugins` on every site that upgrades, still
 * active, and still carrying lifecycle handlers written when it owned data this
 * plugin now owns. Left alone, two of those handlers destroy live data:
 *
 * - Its deactivation handler clears `bstdz_doctorpilot_pro_retention` and
 *   `bstdz_doctorpilot_pro_run_import`. Those are the same hook names this
 *   plugin now uses, so deactivating the old copy — which is exactly what the
 *   upgrade notes ask the site owner to do — stops retention cleanup and kills
 *   any import waiting on its next chunk.
 * - Its uninstall handler reads this plugin's own "delete data on uninstall"
 *   setting and drops the redirects and import tables. Deleting the old copy on
 *   a site that has that setting on would take every legacy-URL redirect with
 *   it, and `RedirectResolver` would start 404ing traffic that used to resolve.
 *
 * Both are handled here rather than by asking the site owner to do things in the
 * right order. Deactivation runs through `deactivate_plugins( $file, true )` —
 * the silent form, which skips the `deactivate_{$plugin}` action and therefore
 * never runs the old deactivation handler at all. That removes the first
 * problem outright, and takes the old copy out of the plugin load path so its
 * modules cannot register a second time.
 *
 * The second problem cannot be prevented from outside: WordPress includes a
 * plugin's `uninstall.php` when the plugin is deleted, whatever this plugin
 * wants. So the notice below tells the owner the one safe way to remove the
 * folder, and the schedule guard repairs the damage if it happens anyway.
 */
final class LegacyAddon {

	/**
	 * Plugin file of the retired add-on, relative to the plugins directory.
	 *
	 * @var string
	 */
	private const FILE = 'doctorpilot-pro/doctorpilot-pro.php';

	/**
	 * Option recording that the add-on was retired, so the notice is shown until
	 * the folder is actually gone rather than once and forgotten.
	 *
	 * @var string
	 */
	private const FLAG = 'bstdz_doctorpilot_retired_addon';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'retire' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );

		/*
		 * Runs on every admin request, not only on upgrade. It is one option
		 * read, and it means a schedule cleared by anything at all — the old
		 * add-on, a migration plugin, a botched restore — comes back on the next
		 * page load instead of staying gone until someone reactivates the
		 * plugin.
		 */
		add_action( 'admin_init', array( $this, 'ensure_schedules' ) );
	}

	/**
	 * Deactivate the add-on if it is still active, without running its
	 * deactivation handler.
	 *
	 * @return void
	 */
	public function retire(): void {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active( self::FILE ) ) {
			return;
		}

		// The silent form. The third argument matches how the plugin was
		// activated, so a network activation is undone network-wide.
		deactivate_plugins( self::FILE, true, is_plugin_active_for_network( self::FILE ) );

		update_option( self::FLAG, '1', false );

		// Whatever state the add-on left the schedules in, put them right.
		$this->ensure_schedules();
	}

	/**
	 * Re-create any missing recurring schedule this plugin owns.
	 *
	 * Idempotent by construction: a scheduled hook is left alone.
	 *
	 * @return void
	 */
	public function ensure_schedules(): void {
		if ( ! wp_next_scheduled( Activator::MAINTENANCE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', Activator::MAINTENANCE_HOOK );
		}

		if ( ! wp_next_scheduled( Activator::RETENTION_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', Activator::RETENTION_HOOK );
		}
	}

	/**
	 * Tell the site owner what happened and how to finish the job safely.
	 *
	 * @return void
	 */
	public function notice(): void {
		if ( '1' !== get_option( self::FLAG ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// The folder is gone: the job is finished and the notice retires itself.
		if ( ! file_exists( WP_PLUGIN_DIR . '/' . self::FILE ) ) {
			delete_option( self::FLAG );
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>';
		esc_html_e( 'DoctorPilot Pro has been deactivated — its features are now built into DoctorPilot.', 'doctorpilot' );
		echo '</strong></p><p>';
		esc_html_e( 'Nothing was lost: your doctors, import history, redirects and analytics all carried over. The old plugin folder can now be removed.', 'doctorpilot' );
		echo '</p><p>';
		esc_html_e( 'One caution before you delete it from the Plugins screen: that old copy still contains its own uninstall script, written when it owned the redirect and import tables. If “Delete data on uninstall” is switched on in DoctorPilot Settings, deleting it there would drop those tables. Either switch that setting off first, or delete the doctorpilot-pro folder directly over FTP or your file manager.', 'doctorpilot' );
		echo '</p></div>';
	}
}
