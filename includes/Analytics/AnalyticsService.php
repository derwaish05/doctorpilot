<?php
/**
 * Analytics consent gate and event listener.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records anonymized aggregates from server-side search events — only when the
 * administrator has explicitly enabled analytics.
 *
 * Consent is read from the `analytics_enabled` setting (default off).
 * Events are derived from the normalized search request server-side, so no IP,
 * user, session, or query-to-person association is ever collected.
 */
final class AnalyticsService {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'doctorpilot/search/performed', array( $this, 'on_search' ), 10, 2 );
	}

	/**
	 * Whether analytics collection is enabled.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		$settings = get_option( 'bstdz_doctorpilot_settings', array() );

		return is_array( $settings )
			&& ! empty( $settings['privacy']['analytics_enabled'] );
	}

	/**
	 * Record aggregates for a performed search.
	 *
	 * @param object $request The base SearchRequest.
	 * @param int    $total   Result total.
	 * @return void
	 */
	public function on_search( $request, int $total ): void {
		if ( ! self::enabled() || ! is_object( $request ) ) {
			return;
		}

		$repo = new AnalyticsRepository();

		// Popular symptom filters (approved, non-identifying dimensions).
		if ( method_exists( $request, 'taxonomy_filters' ) ) {
			$filters = (array) $request->taxonomy_filters();
			foreach ( $filters as $taxonomy => $slugs ) {
				if ( false === strpos( (string) $taxonomy, 'symptom' ) ) {
					continue;
				}
				foreach ( (array) $slugs as $slug ) {
					$repo->increment( 'symptom', (string) $slug );
				}
			}
		}

		// No-result phrases (already normalized, bounded, non-identifying).
		if ( 0 === $total && method_exists( $request, 'query' ) ) {
			$query = trim( (string) $request->query() );
			if ( '' !== $query ) {
				$repo->increment( 'no_result', $query );
			}
		}
	}
}
