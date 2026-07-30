<?php
/**
 * Authenticated library search REST controller.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Rest;

use BrainStudioz\DoctorPilot\Library\DoctorContentResolver;
use BrainStudioz\DoctorPilot\Library\LibraryPostTypes;
use BrainStudioz\DoctorPilot\Support\ErrorFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers `GET doctorpilot/v1/library/search` for the doctor editor's
 * assignment UI.
 *
 * Authenticated and never cached. When a `doctor_id` is supplied the caller must
 * be able to edit that specific doctor; otherwise the broad edit capability is
 * required. Returns only display fields needed to choose a record; published and
 * draft records are included (editors assign known drafts), private/trashed are
 * excluded.
 */
final class LibraryController {

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
			'/library/search',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array(
					'type'      => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_key' ),
					'search'    => array( 'type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
					'doctor_id' => array( 'type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint' ),
					'page'      => array( 'type' => 'integer', 'required' => false, 'default' => 1, 'sanitize_callback' => 'absint' ),
					'per_page'  => array( 'type' => 'integer', 'required' => false, 'default' => 20, 'sanitize_callback' => 'absint' ),
					'exclude'   => array( 'type' => 'array', 'required' => false ),
				),
			)
		);
	}

	/**
	 * Object-scoped permission check.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return bool
	 */
	public function permission( \WP_REST_Request $request ): bool {
		$doctor_id = (int) $request->get_param( 'doctor_id' );

		if ( $doctor_id > 0 ) {
			return current_user_can( 'edit_doctorpilot_doctor', $doctor_id );
		}

		return current_user_can( 'edit_doctorpilot_doctors' );
	}

	/**
	 * Handle a library search.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function search( \WP_REST_Request $request ) {
		$type = (string) $request['type'];
		$cpt  = DoctorContentResolver::cpt( $type );

		if ( '' === $cpt ) {
			return ErrorFactory::create( ErrorFactory::INVALID_PARAMETER, __( 'Unknown library type.', 'doctorpilot' ), 400 );
		}

		$per_page = (int) $request['per_page'];
		$per_page = $per_page > 0 ? min( 50, $per_page ) : 20;
		$page     = max( 1, (int) $request['page'] );

		$exclude = array();
		foreach ( (array) $request->get_param( 'exclude' ) as $id ) {
			$exclude[] = (int) $id;
			if ( count( $exclude ) >= 100 ) {
				break;
			}
		}

		$query = new \WP_Query(
			array(
				'post_type'      => $cpt,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => $per_page,
				'paged'          => $page,
				's'              => (string) $request->get_param( 'search' ),
				'post__not_in'   => $exclude,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$subtitle_key = (string) ( LibraryPostTypes::config( $cpt )['list_subtitle'] ?? '' );

		$data = array();
		foreach ( $query->posts as $post ) {
			$subtitle = '' !== $subtitle_key
				? (string) get_post_meta( $post->ID, LibraryPostTypes::META_PREFIX . $subtitle_key, true )
				: '';

			$data[] = array(
				'id'       => (int) $post->ID,
				'type'     => $type,
				'title'    => (string) $post->post_title,
				'subtitle' => $subtitle,
				'status'   => (string) $post->post_status,
			);
		}

		$response = new \WP_REST_Response(
			array(
				'data' => $data,
				'meta' => array(
					'page'        => $page,
					'per_page'    => $per_page,
					'total'       => (int) $query->found_posts,
					'total_pages' => (int) $query->max_num_pages,
				),
			),
			200
		);
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}
}
