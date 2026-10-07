<?php
/**
 * Search suggestions.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Application\Search;

use BrainStudioz\DoctorPilot\Domain\Search\Normalizer;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;
use BrainStudioz\DoctorPilot\Taxonomies\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Produces bounded, safe type-ahead suggestions.
 *
 * Suggestions come only from published doctor names and curated taxonomy terms
 * (department, specialty, symptom). No clinical equivalence is inferred and no
 * diagnosis is implied.
 */
final class SuggestionService {

	/**
	 * Build suggestions for a partial query.
	 *
	 * @param string $query    Partial query.
	 * @param int    $limit    Maximum suggestions.
	 * @param int    $min_chars Minimum characters before suggesting.
	 * @return array<int, array<string, string>> List of {type, label, value, slug, url}.
	 */
	public function suggest( string $query, int $limit = 8, int $min_chars = 2 ): array {
		$normalized = Normalizer::normalize( $query );
		$length     = function_exists( 'mb_strlen' ) ? mb_strlen( $normalized ) : strlen( $normalized );

		if ( $length < max( 1, $min_chars ) ) {
			return array();
		}

		$limit       = max( 1, min( 20, $limit ) );
		$suggestions = array();

		// Doctor name matches (published only).
		$doctors = get_posts(
			array(
				'post_type'        => DoctorPostType::POST_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => $limit,
				's'                => $query,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);
		foreach ( (array) $doctors as $doctor_id ) {
			$doctor_id  = (int) $doctor_id;
			$doctor_url = get_permalink( $doctor_id );
			$doctor     = get_post( $doctor_id );

			$suggestions[] = array(
				'type'  => 'doctor',
				'label' => get_the_title( $doctor_id ),
				'value' => get_the_title( $doctor_id ),
				'slug'  => ( $doctor instanceof \WP_Post ) ? (string) $doctor->post_name : '',
				'url'   => is_string( $doctor_url ) ? $doctor_url : '',
			);
		}

		// Taxonomy term matches.
		$taxonomies = array(
			'specialty'  => Taxonomies::SPECIALTY,
			'department' => Taxonomies::DEPARTMENT,
			'symptom'    => Taxonomies::SYMPTOM,
		);
		foreach ( $taxonomies as $type => $taxonomy ) {
			if ( count( $suggestions ) >= $limit ) {
				break;
			}

			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
					'number'     => $limit,
					'name__like' => $query,
				)
			);
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$suggestions[] = array(
					'type'  => $type,
					'label' => $term->name,
					'value' => $term->slug,
					'slug'  => $term->slug,
					// Deliberately empty: the front end builds the search URL itself, because the target page is a widget-level choice, not a service-level one.
					'url'   => '',
				);
			}
		}

		return array_slice( $suggestions, 0, $limit );
	}
}
