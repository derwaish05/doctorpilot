<?php
/**
 * Interactive search shortcode and server-rendered fallback.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Frontend;

use BrainStudioz\DoctorPilot\Admin\Settings;
use BrainStudioz\DoctorPilot\Application\Doctors\DoctorView;
use BrainStudioz\DoctorPilot\Application\Search\FacetService;
use BrainStudioz\DoctorPilot\Application\Search\SearchService;
use BrainStudioz\DoctorPilot\Domain\Search\SearchRequestFactory;
use BrainStudioz\DoctorPilot\Rest\RouteRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders `[doctorpilot_search]`: an accessible, shareable directory search.
 *
 * The initial state is server-rendered from the query string so the feature
 * works with JavaScript disabled (a plain GET form). When JavaScript is
 * available, the same markup is progressively enhanced into no-reload search
 * with History API state, debounced input, and stale-request cancellation.
 */
final class SearchRenderer {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'doctorpilot_search', array( $this, 'render' ) );
	}

	/**
	 * Render the search shortcode.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array( 'modes' => 'universal,name,department,symptom' ),
			is_array( $atts ) ? $atts : array(),
			'doctorpilot_search'
		);

		$this->enqueue();

		$settings = Settings::get();
		$per_page = (int) ( $settings['general']['per_page'] ?? 12 );

		// Read shareable state from the query string (GET fallback + initial state).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only GET search state.
		$raw     = wp_unslash( $_GET );
		$request = SearchRequestFactory::from_array( is_array( $raw ) ? $raw : array(), $per_page );

		$result = ( new SearchService() )->search( $request );

		$view  = new DoctorView();
		$cards = array();
		foreach ( $result['ids'] as $id ) {
			$card = $view->card( (int) $id );
			if ( null !== $card ) {
				$cards[] = $card;
			}
		}

		$facets = ( new FacetService() )->facets();

		$data = array(
			'request'  => $request,
			'result'   => $result,
			'cards'    => $cards,
			'facets'   => $facets,
			'settings' => $settings,
		);

		ob_start();
		include Templates::locate( 'search.php' );

		return (string) ob_get_clean();
	}

	/**
	 * Enqueue and configure the search assets.
	 *
	 * @return void
	 */
	private function enqueue(): void {
		wp_enqueue_style(
			'doctorpilot-frontend',
			DOCTORPILOT_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			DOCTORPILOT_VERSION
		);

		wp_enqueue_script(
			'doctorpilot-search',
			DOCTORPILOT_PLUGIN_URL . 'assets/js/frontend-search.js',
			array(),
			DOCTORPILOT_VERSION,
			true
		);

		wp_localize_script(
			'doctorpilot-search',
			'doctorPilotSearch',
			array(
				'restBase'  => esc_url_raw( rest_url( RouteRegistry::NAMESPACE ) ),
				'strings'   => array(
					'resultsCount' => __( '%d doctors found', 'doctorpilot' ),
					'loading'      => __( 'Searching…', 'doctorpilot' ),
					'error'        => __( 'Something went wrong. Please try again.', 'doctorpilot' ),
					'empty'        => __( 'No doctors matched your search.', 'doctorpilot' ),
					'viewProfile'  => __( 'View Profile', 'doctorpilot' ),
					'clearAll'     => __( 'Clear all filters', 'doctorpilot' ),
				),
			)
		);
	}
}
