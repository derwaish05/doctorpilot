<?php
/**
 * Plugin Name:       DoctorPilot – Doctor Directory & Search
 * Plugin URI:        https://brainstudioz.com/doctorpilot/
 * Description:        Structured doctor directory and intelligent search for hospitals. Manage doctors, departments, specialties, symptoms, and locations as structured WordPress data and present them with Elementor.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            BrainStudioz
 * Author URI:        https://brainstudioz.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       doctorpilot
 * Domain Path:       /languages
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent double-loading (e.g. Free + a mistaken duplicate copy).
if ( defined( 'DOCTORPILOT_VERSION' ) ) {
	return;
}

/*
 * ---------------------------------------------------------------------------
 * Version, runtime, and path constants.
 * ---------------------------------------------------------------------------
 */
define( 'DOCTORPILOT_VERSION', '0.1.0' );

// Base extension API version. Pro add-ons declare the base version they need.
define( 'DOCTORPILOT_BASE_API_VERSION', 1 );

// Data schema version. Incremented deliberately when migrations are added.
define( 'DOCTORPILOT_SCHEMA_VERSION', 1 );

// Minimum supported runtime (kept in sync with the plugin header and readme).
define( 'DOCTORPILOT_MIN_WP', '6.4' );
define( 'DOCTORPILOT_MIN_PHP', '8.1' );

define( 'DOCTORPILOT_PLUGIN_FILE', __FILE__ );
define( 'DOCTORPILOT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'DOCTORPILOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DOCTORPILOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/*
 * ---------------------------------------------------------------------------
 * Autoloader. Loaded manually because it cannot autoload itself.
 * ---------------------------------------------------------------------------
 */
require_once DOCTORPILOT_PLUGIN_DIR . 'includes/Support/Autoloader.php';

\BrainStudioz\DoctorPilot\Support\Autoloader::register(
	'BrainStudioz\\DoctorPilot\\',
	DOCTORPILOT_PLUGIN_DIR . 'includes/'
);

/*
 * ---------------------------------------------------------------------------
 * Lifecycle hooks. Registered before the runtime guard so that a site on an
 * unsupported runtime still receives a safe, actionable activation failure
 * instead of a fatal error.
 * ---------------------------------------------------------------------------
 */
register_activation_hook(
	__FILE__,
	static function (): void {
		\BrainStudioz\DoctorPilot\Infrastructure\Lifecycle\Activator::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		\BrainStudioz\DoctorPilot\Infrastructure\Lifecycle\Deactivator::deactivate();
	}
);

/*
 * ---------------------------------------------------------------------------
 * Runtime guard. If the environment is unsupported we register an actionable
 * admin notice and stop. We never fatal and never touch stored data.
 * ---------------------------------------------------------------------------
 */
if ( ! \BrainStudioz\DoctorPilot\Support\RuntimeGuard::is_supported() ) {
	add_action( 'admin_notices', array( '\BrainStudioz\DoctorPilot\Support\RuntimeGuard', 'render_admin_notice' ) );
	return;
}

/*
 * ---------------------------------------------------------------------------
 * Boot the plugin on plugins_loaded so other plugins (Elementor, Pro) have a
 * chance to register the hooks DoctorPilot listens for.
 * ---------------------------------------------------------------------------
 */
add_action(
	'plugins_loaded',
	static function (): void {
		\BrainStudioz\DoctorPilot\Infrastructure\Plugin::instance()->boot();
	},
	5
);
