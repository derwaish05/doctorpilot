<?php
/**
 * Public doctors REST controller.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Rest;

use BrainStudioz\DoctorPilot\Admin\Settings;
use BrainStudioz\DoctorPilot\Application\Doctors\DoctorView;
use BrainStudioz\DoctorPilot\Application\Search\FacetService;
use BrainStudioz\DoctorPilot\Application\Search\NoResultService;
use BrainStudioz\DoctorPilot\Application\Search\SearchService;
use BrainStudioz\DoctorPilot\Domain\Search\SearchRequestFactory;
use BrainStudioz\DoctorPilot\Infrastructure\Cache\SearchCache;
use BrainStudioz\DoctorPilot\Support\ErrorFactory;
use BrainStudioz\DoctorPilot\Support\RateLimiter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers `/doctors` (search) and `/doctors/{id}` (single) public routes.
 *
 * Both are cacheable public GET routes with explicit public permission
 * callbacks (never a missing handler), strict argument bounds, published-only
 * output, and stable error codes that never leak SQL, paths, or secrets.
 */
final class DoctorsController {

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			RouteRegistry::NAMESPACE,
			'/doctors',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search' ),
				'permission_callback' => array( RouteRegistry::class, 'allow_public' ),
				'args'                => $this->args(),
			)
		);

		register_rest_route(
			RouteRegistry::NAMESPACE,
			'/doctors/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'single' ),
				'permission_callback' => array( RouteRegistry::class, 'allow_public' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => static function ( $value ): bool {
							return is_numeric( $value ) && (int) $value > 0;
						},
					),
				),
			)
		);
	}

	/**
	 * Handle a search request.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function search( \WP_REST_Request $request ) {
		if ( ! RateLimiter::allow( 'search', 120, 60 ) ) {
			return ErrorFactory::create(
				ErrorFactory::RATE_LIMITED,
				__( 'Too many requests. Please slow down and try again shortly.', 'doctorpilot' ),
				429
			);
		}

		$settings      = Settings::get();
		$default_pp    = (int) ( $settings['general']['per_page'] ?? 12 );
		$ttl           = (int) ( $settings['search']['cache_ttl_seconds'] ?? 300 );
		$search_request = SearchRequestFactory::from_array( $request->get_params(), $default_pp );

		$cache_key = SearchCache::key( $search_request, 'doctors' );
		$cached    = SearchCache::get( $cache_key );
		if ( null !== $cached ) {
			return $this->respond( $cached );
		}

		$service = new SearchService();
		$result  = $service->search( $search_request );

		$view = new DoctorView();
		$data = array();
		foreach ( $result['ids'] as $id ) {
			$card = $view->card( (int) $id );
			if ( null !== $card ) {
				$data[] = $card;
			}
		}

		$facets    = ( new FacetService() )->facets();
		$no_result = array();
		if ( 0 === $result['total'] ) {
			$no_result = ( new NoResultService() )->build( $search_request->query() );
		}

		$payload = array(
			'data'           => $data,
			'meta'           => array(
				'page'        => $result['page'],
				'per_page'    => $result['per_page'],
				'total'       => $result['total'],
				'total_pages' => $result['total_pages'],
				'sort'        => $result['sort'],
				'request_id'  => ErrorFactory::request_id(),
			),
			'facets'         => $facets,
			'active_filters' => $result['active_filters'],
			'no_result'      => $no_result,
			'suggestions'    => array(),
		);

		SearchCache::set( $cache_key, $payload, $ttl );

		/**
		 * Fires after a public search is executed (cache miss).
		 *
		 * Add-ons may record anonymized, aggregate analytics from this. No
		 * identifying data is passed — only the normalized request and the total
		 * result count.
		 *
		 * @param \BrainStudioz\DoctorPilot\Domain\Search\SearchRequest $search_request Normalized request.
		 * @param int                                                   $total          Total matches.
		 */
		do_action( 'doctorpilot/search/performed', $search_request, (int) $result['total'] );

		return $this->respond( $payload );
	}

	/**
	 * Handle a single-doctor request.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function single( \WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$view = new DoctorView();
		$data = $view->full( $id );

		if ( null === $data ) {
			return ErrorFactory::create(
				ErrorFactory::NOT_FOUND,
				__( 'Doctor not found.', 'doctorpilot' ),
				404
			);
		}

		return new \WP_REST_Response( array( 'data' => $data ), 200 );
	}

	/**
	 * Build a response with pagination headers.
	 *
	 * @param array<string, mixed> $payload Envelope payload.
	 * @return \WP_REST_Response
	 */
	private function respond( array $payload ): \WP_REST_Response {
		$response = new \WP_REST_Response( $payload, 200 );
		$response->header( 'X-WP-Total', (string) ( $payload['meta']['total'] ?? 0 ) );
		$response->header( 'X-WP-TotalPages', (string) ( $payload['meta']['total_pages'] ?? 1 ) );

		return $response;
	}

	/**
	 * Argument schema for the search route.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function args(): array {
		$string_arg = array(
			'type'              => 'string',
			'required'          => false,
			'sanitize_callback' => 'sanitize_text_field',
		);

		return array(
			'q'             => array_merge( $string_arg, array( 'default' => '' ) ),
			'mode'          => array_merge( $string_arg, array( 'default' => 'universal' ) ),
			'department'    => $string_arg,
			'specialty'     => $string_arg,
			'symptom'       => $string_arg,
			'language'      => $string_arg,
			'gender'        => $string_arg,
			'accepting_new' => array( 'type' => 'string', 'required' => false ),
			'online'        => array( 'type' => 'string', 'required' => false ),
			'sort'          => array_merge( $string_arg, array( 'default' => 'relevance' ) ),
			'view'          => array_merge( $string_arg, array( 'default' => 'grid' ) ),
			'page'          => array(
				'type'              => 'integer',
				'required'          => false,
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page'      => array(
				'type'              => 'integer',
				'required'          => false,
				'sanitize_callback' => 'absint',
			),
		);
	}
}
