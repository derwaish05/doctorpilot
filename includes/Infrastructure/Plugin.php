<?php
/**
 * Central plugin orchestrator.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Infrastructure;

use BrainStudioz\DoctorPilot\Admin\DoctorEditor;
use BrainStudioz\DoctorPilot\Admin\Menu;
use BrainStudioz\DoctorPilot\Admin\MigrationMenu;
use BrainStudioz\DoctorPilot\Admin\Settings;
use BrainStudioz\DoctorPilot\Analytics\AnalyticsController;
use BrainStudioz\DoctorPilot\Analytics\AnalyticsDashboard;
use BrainStudioz\DoctorPilot\Analytics\AnalyticsService;
use BrainStudioz\DoctorPilot\Application\Doctors\SaveDoctor;
use BrainStudioz\DoctorPilot\Booking\BookPilotProvider;
use BrainStudioz\DoctorPilot\Directory\AdvancedFilters;
use BrainStudioz\DoctorPilot\Directory\InsuranceTaxonomy;
use BrainStudioz\DoctorPilot\Directory\ProFields;
use BrainStudioz\DoctorPilot\Elementor\Integration;
use BrainStudioz\DoctorPilot\Elementor\ProElementor;
use BrainStudioz\DoctorPilot\ImportExport\ImportRunner;
use BrainStudioz\DoctorPilot\Fields\MetaRegistrar;
use BrainStudioz\DoctorPilot\Frontend\Assets;
use BrainStudioz\DoctorPilot\Frontend\SearchRenderer;
use BrainStudioz\DoctorPilot\Frontend\Shortcodes;
use BrainStudioz\DoctorPilot\Frontend\Templates;
use BrainStudioz\DoctorPilot\Infrastructure\Cache\CacheGeneration;
use BrainStudioz\DoctorPilot\Infrastructure\Lifecycle\Activator;
use BrainStudioz\DoctorPilot\Infrastructure\LegacyAddon;
use BrainStudioz\DoctorPilot\Infrastructure\Migrations\Migrator;
use BrainStudioz\DoctorPilot\Library\LibraryPostTypes;
use BrainStudioz\DoctorPilot\Library\ReverseIndex;
use BrainStudioz\DoctorPilot\Operations\RetentionJob;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;
use BrainStudioz\DoctorPilot\Redirects\RedirectResolver;
use BrainStudioz\DoctorPilot\Rest\DoctorsController;
use BrainStudioz\DoctorPilot\Rest\FacetsController;
use BrainStudioz\DoctorPilot\Rest\LibraryController;
use BrainStudioz\DoctorPilot\Rest\RouteRegistry;
use BrainStudioz\DoctorPilot\Rest\SuggestController;
use BrainStudioz\DoctorPilot\Seo\SeoOwner;
use BrainStudioz\DoctorPilot\Support\ExtensionRegistry;
use BrainStudioz\DoctorPilot\Support\ScopeGuard;
use BrainStudioz\DoctorPilot\Taxonomies\SymptomTermMeta;
use BrainStudioz\DoctorPilot\Taxonomies\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots and holds the plugin's runtime modules.
 *
 * Keeps wiring in one place so ownership of hooks is explicit and every module
 * has a single registration point.
 *
 * The advanced directory, migration, redirect, analytics and operations modules
 * used to live in a separate add-on that bound itself here through
 * `doctorpilot/registered`. They are booted directly now. `ExtensionRegistry`
 * and that action are deliberately kept: they are the published contract for
 * third-party add-ons, and removing them would break anything already built
 * against them for no gain.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Whether boot() has already run.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Private constructor; use instance().
	 */
	private function __construct() {}

	/**
	 * Retrieve the shared plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot the plugin. Safe to call once; subsequent calls are ignored.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// Load translations on init (WordPress 6.7 warns if this happens earlier).
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );

		// Run idempotent upgrade migrations when the stored version is behind.
		add_action( 'init', array( $this, 'maybe_upgrade' ), 1 );

		// Perform the deferred, one-time rewrite flush requested at activation.
		add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 99 );

		// Control-plane foundations (Milestone 1).
		( new Settings() )->register();
		( new RouteRegistry() )->register();

		// Data model: post type, taxonomies, meta (Milestone 2).
		( new DoctorPostType() )->register();
		( new Taxonomies() )->register();
		( new SymptomTermMeta() )->register();
		( new MetaRegistrar() )->register();
		( new SaveDoctor() )->register();

		// Global reusable content library: Experience & Education (ADR-0017).
		( new LibraryPostTypes() )->register();
		( new ReverseIndex() )->register();
		( new LibraryController() )->register();

		// Native frontend rendering and SEO (Milestone 2).
		( new Templates() )->register();
		( new Shortcodes() )->register();
		( new Assets() )->register();
		( new SeoOwner() )->register();

		// Search: cache invalidation, public REST, interactive UI (Milestone 3).
		( new CacheGeneration() )->register();
		( new DoctorsController() )->register();
		( new SuggestController() )->register();
		( new FacetsController() )->register();
		( new SearchRenderer() )->register();

		// Elementor integration (Milestone 4). Safe no-op when Elementor is absent.
		( new Integration() )->register();
		( new ProElementor() )->register();

		// Object and department scope enforcement for the scoped roles.
		( new ScopeGuard() )->register();

		// Advanced directory: insurance taxonomy, extra filters, extra fields.
		( new InsuranceTaxonomy() )->register();
		( new AdvancedFilters() )->register();
		( new ProFields() )->register();

		// BookPilot booking provider (registers through the providers filter).
		( new BookPilotProvider() )->register();

		// Opt-in aggregate analytics.
		( new AnalyticsService() )->register();
		( new AnalyticsController() )->register();

		// Legacy-URL redirect resolution runs on the front end for every request.
		( new RedirectResolver() )->register();

		// Retention cleanup for import logs, expired jobs, and analytics.
		( new RetentionJob() )->register();

		// Advance running import jobs on the scheduled hook.
		( new ImportRunner() )->register();

		if ( is_admin() ) {
			// Retire a leftover doctorpilot-pro install before anything else in
			// the admin runs, and repair any schedule it cleared on its way out.
			( new LegacyAddon() )->register();

			( new Menu() )->register();
			( new DoctorEditor() )->register();
			( new MigrationMenu() )->register();
			( new AnalyticsDashboard() )->register();
		}

		// Allow add-ons to register against a booted, compatible base.
		do_action( 'doctorpilot/registered', ExtensionRegistry::instance() );
	}

	/**
	 * Load the plugin translations. Hooked to `init` (WordPress 6.7 requirement).
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'doctorpilot',
			false,
			dirname( DOCTORPILOT_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Run schema migrations when the stored schema version is behind the code.
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		$stored = (int) get_option( 'bstdz_doctorpilot_schema_version', 0 );

		if ( $stored < DOCTORPILOT_SCHEMA_VERSION ) {
			( new Migrator() )->run();
		}

		// Keep the recorded plugin version current after any code update.
		if ( DOCTORPILOT_VERSION !== get_option( 'bstdz_doctorpilot_plugin_version' ) ) {
			update_option( 'bstdz_doctorpilot_plugin_version', DOCTORPILOT_VERSION, false );
		}
	}

	/**
	 * Flush rewrite rules exactly once after activation or an upgrade.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrites(): void {
		if ( '1' !== get_option( Activator::FLUSH_FLAG ) ) {
			return;
		}

		flush_rewrite_rules();
		delete_option( Activator::FLUSH_FLAG );
	}
}
