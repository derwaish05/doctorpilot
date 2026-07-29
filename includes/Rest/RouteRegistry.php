<?php
/**
 * REST route registration conventions.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Rest;

use BrainStudioz\DoctorPilot\Support\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central registrar for the `doctorpilot/v1` REST namespace.
 *
 * Every route registered through here must declare an explicit permission
 * callback, argument schema, and stable error handling. Public GET routes use a
 * deliberate public callback (never a missing permission handler); authenticated
 * mutations require a capability plus the REST nonce. Feature routes (search,
 * facets, suggestions) are added by later milestones through this same registrar.
 */
final class RouteRegistry {

	/**
	 * REST namespace for all plugin routes.
	 *
	 * @var string
	 */
	public const NAMESPACE = 'doctorpilot/v1';

	/**
	 * Hook route registration onto the REST API init.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register foundation routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Admin-only health probe used by the dashboard and monitoring.
		register_rest_route(
			self::NAMESPACE,
			'/health',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'health' ),
				'permission_callback' => static function (): bool {
					return current_user_can( Capabilities::MANAGE );
				},
				'args'                => array(),
			)
		);
	}

	/**
	 * Health endpoint returning non-sensitive status for administrators.
	 *
	 * @return \WP_REST_Response
	 */
	public function health(): \WP_REST_Response {
		$data = array(
			'plugin_version' => DOCTORPILOT_VERSION,
			'schema_version' => (int) get_option( 'bstdz_doctorpilot_schema_version', 0 ),
			'base_api'       => DOCTORPILOT_BASE_API_VERSION,
			'cache_gen'      => (int) get_option( 'bstdz_doctorpilot_cache_generation', 0 ),
		);

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * A public permission callback for cacheable GET routes.
	 *
	 * Explicit so public access is always an intentional decision, never the
	 * accidental result of a missing permission handler.
	 *
	 * @return bool
	 */
	public static function allow_public(): bool {
		return true;
	}
}
