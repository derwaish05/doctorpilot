<?php
/**
 * Opt-in aggregate analytics REST endpoint.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers `POST doctorpilot/v1/analytics/search`.
 *
 * Accepts a bounded, approved event and returns 204 No Content. Collection only
 * happens when analytics are enabled; the endpoint never returns stored data,
 * never stores identity, and neutralizes probing by returning 204 regardless of
 * the enabled state.
 */
final class AnalyticsController {

	/**
	 * Allowed event types.
	 *
	 * @var array<int, string>
	 */
	private const TYPES = array( 'doctor_view', 'symptom', 'no_result' );

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_route(): void {
		register_rest_route(
			'doctorpilot/v1',
			'/analytics/search',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'record' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'event' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
					'label' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Record an aggregate event and return 204.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function record( \WP_REST_Request $request ): \WP_REST_Response {
		// Basic abuse control (never authentication); tolerant of shared IPs.
		if ( class_exists( '\\BrainStudioz\\DoctorPilot\\Support\\RateLimiter' )
			&& ! \BrainStudioz\DoctorPilot\Support\RateLimiter::allow( 'analytics', 300, 60 ) ) {
			return new \WP_REST_Response( null, 204 );
		}

		$event = (string) $request['event'];
		$label = (string) ( $request['label'] ?? '' );

		if ( AnalyticsService::enabled() && in_array( $event, self::TYPES, true ) && '' !== $label ) {
			( new AnalyticsRepository() )->increment( $event, $label );
		}

		// Always 204 so the endpoint cannot be probed for the enabled state.
		return new \WP_REST_Response( null, 204 );
	}
}
