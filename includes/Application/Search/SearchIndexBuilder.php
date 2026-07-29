<?php
/**
 * Builds the derived search index for a doctor.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Application\Search;

use BrainStudioz\DoctorPilot\Domain\Search\Normalizer;
use BrainStudioz\DoctorPilot\Fields\FieldRegistry;
use BrainStudioz\DoctorPilot\Taxonomies\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes rebuildable derived search values for a doctor.
 *
 * Produces three meta values used by ranking and sorting:
 * - sort_name: normalized display name (prefix stripped) for name sorting;
 * - search_document: a flat normalized keyword string;
 * - search_index: normalized buckets that let the ranker score exact
 *   specialty/department/symptom matches without extra per-request queries.
 *
 * All values are derived and always rebuildable from canonical data.
 */
final class SearchIndexBuilder {

	public const SORT_NAME  = FieldRegistry::META_PREFIX . 'sort_name';
	public const DOCUMENT   = FieldRegistry::META_PREFIX . 'search_document';
	public const INDEX      = FieldRegistry::META_PREFIX . 'search_index';

	/**
	 * Rebuild derived search values for a single doctor.
	 *
	 * @param int $post_id Doctor post ID.
	 * @return void
	 */
	public static function rebuild( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! ( $post instanceof \WP_Post ) ) {
			return;
		}

		$departments = self::term_names( $post_id, Taxonomies::DEPARTMENT );
		$specialties = self::term_names( $post_id, Taxonomies::SPECIALTY );
		$symptoms    = self::term_names( $post_id, Taxonomies::SYMPTOM );
		$conditions  = self::term_names( $post_id, Taxonomies::CONDITION );
		$treatments  = self::term_names( $post_id, Taxonomies::TREATMENT );
		$languages   = self::term_names( $post_id, Taxonomies::LANGUAGE );

		$credentials = (string) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'credentials', true );
		$interests   = get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'clinical_interests', true );
		$interests   = is_array( $interests ) ? array_map( 'strval', $interests ) : array();

		$name = (string) $post->post_title;

		$index = array(
			'name'        => Normalizer::normalize_name( $name ),
			'name_tokens' => Normalizer::tokens( $name ),
			'departments' => array_map( array( Normalizer::class, 'normalize' ), $departments ),
			'specialties' => array_map( array( Normalizer::class, 'normalize' ), $specialties ),
			'symptoms'    => array_map( array( Normalizer::class, 'normalize' ), $symptoms ),
			'conditions'  => array_map( array( Normalizer::class, 'normalize' ), array_merge( $conditions, $treatments ) ),
			'keywords'    => array_map( array( Normalizer::class, 'normalize' ), array_merge( array( $credentials ), $interests, $languages ) ),
		);

		update_post_meta( $post_id, self::SORT_NAME, $index['name'] );
		update_post_meta( $post_id, self::INDEX, $index );

		$document = implode(
			' ',
			array_filter(
				array_merge(
					array( $name, wp_strip_all_tags( (string) $post->post_excerpt ), $credentials ),
					$departments,
					$specialties,
					$symptoms,
					$conditions,
					$treatments,
					$languages,
					$interests
				)
			)
		);
		update_post_meta( $post_id, self::DOCUMENT, Normalizer::normalize( $document ) );
	}

	/**
	 * Get term names for a taxonomy on a doctor.
	 *
	 * @param int    $post_id  Doctor post ID.
	 * @param string $taxonomy Taxonomy key.
	 * @return array<int, string>
	 */
	private static function term_names( int $post_id, string $taxonomy ): array {
		$names = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );

		return is_wp_error( $names ) ? array() : array_map( 'strval', $names );
	}
}
