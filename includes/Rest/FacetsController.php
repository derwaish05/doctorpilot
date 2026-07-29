<?php
/**
 * Public facets REST controller.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Rest;

use BrainStudioz\DoctorPilot\Application\Search\FacetService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers `/facets` exposing the available public filter options and counts.
 */
final class FacetsController {

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			RouteRegistry::NAMESPACE,
			'/facets',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'facets' ),
				'permission_callback' => array( RouteRegistry::class, 'allow_public' ),
				'args'                => array(),
			)
		);
	}

	/**
	 * Return the facet options.
	 *
	 * @return \WP_REST_Response
	 */
	public function facets(): \WP_REST_Response {
		return new \WP_REST_Response( array( 'data' => ( new FacetService() )->facets() ), 200 );
	}
}
