<?php
/**
 * Public suggestions REST controller.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Rest;

use BrainStudioz\DoctorPilot\Admin\Settings;
use BrainStudioz\DoctorPilot\Application\Search\SuggestionService;
use BrainStudioz\DoctorPilot\Support\ErrorFactory;
use BrainStudioz\DoctorPilot\Support\RateLimiter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers `/doctors/suggest` for bounded type-ahead suggestions.
 */
final class SuggestController {

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
			'/doctors/suggest',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'suggest' ),
				'permission_callback' => array( RouteRegistry::class, 'allow_public' ),
				'args'                => array(
					'q' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Return suggestions for a partial query.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function suggest( \WP_REST_Request $request ) {
		if ( ! RateLimiter::allow( 'suggest', 240, 60 ) ) {
			return ErrorFactory::create(
				ErrorFactory::RATE_LIMITED,
				__( 'Too many requests. Please slow down.', 'doctorpilot' ),
				429
			);
		}

		$settings  = Settings::get();
		$limit     = (int) ( $settings['search']['suggestion_limit'] ?? 8 );
		$min_chars = (int) ( $settings['search']['minimum_characters'] ?? 2 );

		$service     = new SuggestionService();
		$suggestions = $service->suggest( (string) $request['q'], $limit, $min_chars );

		return new \WP_REST_Response( array( 'data' => $suggestions ), 200 );
	}
}
