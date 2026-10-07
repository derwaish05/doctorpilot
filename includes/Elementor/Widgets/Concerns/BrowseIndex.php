<?php
/**
 * Browsable A–Z index building, shared by the widgets that list who and what
 * exists rather than doctor results.
 *
 * NOTE: `DoctorGridList` still carries its own private copies of this logic
 * (`name_index()`, `term_index()`, `letter_for()`, `letters_in()`,
 * `sortable_name()`, `decode()`, `index_subtitle()`). Migrating it onto this
 * trait is deliberate follow-up work: the two must not drift, so whoever
 * touches one should move the other across rather than editing in parallel.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor\Widgets\Concerns;

use BrainStudioz\DoctorPilot\Fields\FieldRegistry;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;
use BrainStudioz\DoctorPilot\Support\Multilingual;
use BrainStudioz\DoctorPilot\Taxonomies\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the doctor-name and taxonomy indexes an A–Z browser lists.
 *
 * Every index is the full catalogue: filtering by letter or by typed text hides
 * rows client-side rather than re-querying, so one mechanism serves both the
 * no-JavaScript links and instant filtering.
 */
trait BrowseIndex {

	/**
	 * Maximum entries listed in one browsable index.
	 *
	 * Expressed as a method rather than a constant so the trait stays usable on
	 * PHP versions before constants in traits.
	 *
	 * @return int
	 */
	private static function browse_cap(): int {
		return 500;
	}

	/**
	 * Taxonomy-backed index modes: mode => taxonomy key.
	 *
	 * @return array<string, string>
	 */
	private static function browse_taxonomies(): array {
		return array(
			'department' => Taxonomies::DEPARTMENT,
			'specialty'  => Taxonomies::SPECIALTY,
			'symptom'    => Taxonomies::SYMPTOM,
		);
	}

	/**
	 * Every browsable mode => the search parameter its entries set.
	 *
	 * These are the parameter names `SearchRequestFactory::from_array()` reads,
	 * so an entry's link narrows the search page through the same filter
	 * contract as every other control.
	 *
	 * @return array<string, string>
	 */
	private static function browse_params(): array {
		return array(
			'name'       => 'doctor',
			'department' => 'department',
			'specialty'  => 'specialty',
			'symptom'    => 'symptom',
		);
	}

	/**
	 * The letter row as value => label: an "all" entry first, then A–Z.
	 *
	 * The all entry's value is the empty string — the value "no letter is
	 * active" already has — so picking it clears the filter instead of
	 * selecting a twenty-seventh bucket. That also makes it the entry that is
	 * highlighted on load, which is what a row of filters with nothing applied
	 * should look like.
	 *
	 * Its label is translatable so a site can spell it "All" without touching
	 * the filtering; '#' is the default because every button in the row is one
	 * glyph wide and "All" would break that rhythm.
	 *
	 * Always the full alphabet; letters with no entries render disabled rather
	 * than disappearing, so the row does not reflow as the catalogue changes.
	 *
	 * @return array<string, string>
	 */
	private static function browse_letters(): array {
		$letters = array( '' => _x( '#', 'A–Z index: show every entry', 'doctorpilot' ) );

		foreach ( range( 'A', 'Z' ) as $letter ) {
			$letters[ (string) $letter ] = (string) $letter;
		}

		return $letters;
	}

	/**
	 * Build a browsable index of every published doctor's name.
	 *
	 * Ordering is done in PHP against the honorific-stripped name the A–Z bar
	 * groups by, so "Dr. Ali Khalil" files under K in both places.
	 *
	 * Subtitles are expensive — priming the meta cache for 500 doctors pulls
	 * every meta row, the search blobs included — so the caches are primed and
	 * the subtitle resolved only when the caller actually renders one.
	 *
	 * @param bool   $az_show  Whether the A–Z bar is enabled.
	 * @param bool   $subtitle Whether a per-doctor subtitle is rendered.
	 * @param string $sort     'name' (default) or 'name_desc'.
	 * @param bool   $facets   Whether each entry carries its gender and languages.
	 * @return array<string, mixed>
	 */
	private function browse_name_index( bool $az_show, bool $subtitle = false, string $sort = 'name', bool $facets = false ): array {
		// Both flags pull the same caches, so either one priming them is enough.
		$prime = $subtitle || $facets;

		$query = new \WP_Query(
			array(
				'post_type'              => DoctorPostType::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => self::browse_cap(),
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache'  => $prime,
				'update_post_term_cache'  => $prime,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			if ( ! ( $post instanceof \WP_Post ) ) {
				continue;
			}

			$name    = self::browse_decode( (string) $post->post_title );
			$url     = get_permalink( $post );
			$items[] = array(
				'name'   => $name,
				'value'  => (string) $post->post_name,
				'meta'   => $subtitle ? $this->browse_subtitle( (int) $post->ID ) : '',
				'letter' => self::browse_letter_for( $name ),
				'sort'   => self::browse_sortable_name( $name ),
				// A doctor entry links straight to the profile, never to the
				// search page: the person is the destination, not a filter.
				'url'    => is_string( $url ) ? $url : '',
				// Carried so a Gender or Language choice can narrow the list in
				// the browser. Read from the caches primed above, so this costs
				// no query per row.
				'gender' => $facets ? (string) get_post_meta( (int) $post->ID, FieldRegistry::META_PREFIX . 'gender', true ) : '',
				'langs'  => $facets ? self::browse_language_slugs( (int) $post->ID ) : array(),
			);
		}

		usort(
			$items,
			static function ( array $a, array $b ): int {
				return strcmp( (string) $a['sort'], (string) $b['sort'] );
			}
		);

		if ( 'name_desc' === $sort ) {
			$items = array_reverse( $items );
		}

		return array(
			'type'      => 'names',
			'param'     => self::browse_params()['name'],
			'items'     => $items,
			'az'        => $az_show,
			'letters'   => $az_show ? self::browse_letters_in( $items ) : array(),
			'label'     => __( 'Doctors', 'doctorpilot' ),
			'single'    => __( 'Doctor', 'doctorpilot' ),
			'truncated' => (int) $query->found_posts > count( $items ),
		);
	}

	/**
	 * Build a browsable index of one taxonomy's terms.
	 *
	 * Only terms with published doctors are listed (`hide_empty`), so an entry
	 * never leads to an empty result page.
	 *
	 * @param string $mode    Term mode (department/specialty/symptom).
	 * @param bool   $az_show Whether the A–Z bar is enabled.
	 * @param string $sort    'name' (default) or 'name_desc'.
	 * @return array<string, mixed>
	 */
	private function browse_term_index( string $mode, bool $az_show, string $sort = 'name' ): array {
		$taxonomy = self::browse_taxonomies()[ $mode ] ?? '';
		$object   = '' !== $taxonomy ? get_taxonomy( $taxonomy ) : null;

		// One over the cap: the extra row is never rendered, it only tells
		// "exactly at the cap" from "more than the cap".
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'name_desc' === $sort ? 'DESC' : 'ASC',
				'number'     => self::browse_cap() + 1,
			)
		);

		$truncated = ! is_wp_error( $terms ) && count( $terms ) > self::browse_cap();
		if ( $truncated ) {
			$terms = array_slice( (array) $terms, 0, self::browse_cap() );
		}

		$items = array();

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( ! ( $term instanceof \WP_Term ) ) {
					continue;
				}

				$name    = self::browse_decode( $term->name );
				$items[] = array(
					'name'   => $name,
					'value'  => (string) $term->slug,
					'meta'   => '',
					'letter' => self::browse_letter_for( $name ),
					'sort'   => self::browse_sortable_name( $name ),
					// Filled in by the caller, which knows the target page.
					'url'    => '',
				);
			}
		}

		return array(
			'type'      => 'terms',
			'param'     => self::browse_params()[ $mode ] ?? $mode,
			'items'     => $items,
			'az'        => $az_show,
			'letters'   => $az_show ? self::browse_letters_in( $items ) : array(),
			'label'     => $object instanceof \WP_Taxonomy ? (string) $object->labels->name : ucfirst( $mode ),
			'single'    => $object instanceof \WP_Taxonomy ? (string) $object->labels->singular_name : ucfirst( $mode ),
			'truncated' => $truncated,
		);
	}

	/**
	 * Sub-label shown under a doctor's name.
	 *
	 * Prefers the primary specialty/department the card shows, then the first
	 * assigned term. Terms come from the cache `WP_Query` already primed.
	 *
	 * @param int $post_id Doctor post ID.
	 * @return string
	 */
	private function browse_subtitle( int $post_id ): string {
		$candidates = array(
			array( FieldRegistry::META_PREFIX . 'primary_specialty_id', Taxonomies::SPECIALTY ),
			array( FieldRegistry::META_PREFIX . 'primary_department_id', Taxonomies::DEPARTMENT ),
		);

		foreach ( $candidates as list( $meta_key, $taxonomy ) ) {
			$term_id = (int) get_post_meta( $post_id, $meta_key, true );
			if ( $term_id <= 0 ) {
				continue;
			}

			$term = get_term( Multilingual::term_id( $term_id, $taxonomy ), $taxonomy );
			if ( $term instanceof \WP_Term ) {
				return self::browse_decode( $term->name );
			}
		}

		foreach ( array( Taxonomies::SPECIALTY, Taxonomies::DEPARTMENT ) as $taxonomy ) {
			$terms = get_the_terms( $post_id, $taxonomy );
			if ( is_array( $terms ) && isset( $terms[0] ) && $terms[0] instanceof \WP_Term ) {
				return self::browse_decode( $terms[0]->name );
			}
		}

		return '';
	}

	/**
	 * A doctor's spoken-language slugs.
	 *
	 * Slugs rather than names because the Languages filter's options are the
	 * same facet slugs the search route accepts, so the two can be compared
	 * without a lookup — and matched again on the search page after a click.
	 *
	 * @param int $post_id Doctor post ID.
	 * @return array<int, string>
	 */
	private static function browse_language_slugs( int $post_id ): array {
		$terms = get_the_terms( $post_id, Taxonomies::LANGUAGE );
		if ( ! is_array( $terms ) ) {
			return array();
		}

		$slugs = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$slugs[] = (string) $term->slug;
			}
		}

		return $slugs;
	}

	/**
	 * Index letter for a display name, ignoring an honorific prefix.
	 *
	 * A name that does not start with A–Z gets '#', which no longer has a
	 * button of its own — that glyph now labels the "show all" entry. Such a
	 * name therefore has no letter that selects it and appears only in the
	 * unfiltered list. That is the intended trade: a row of letters where one
	 * of them silently means "and also everything else" was the more confusing
	 * of the two.
	 *
	 * @param string $name Display name.
	 * @return string Single uppercase letter, or '#' for "no letter bucket".
	 */
	private static function browse_letter_for( string $name ): string {
		$name = (string) preg_replace( '/^\s*(dr|prof|mr|mrs|ms)\.?\s+/i', '', trim( $name ) );

		if ( '' === $name ) {
			return '#';
		}

		$first = function_exists( 'mb_substr' )
			? mb_strtoupper( mb_substr( $name, 0, 1 ) )
			: strtoupper( substr( $name, 0, 1 ) );

		return 1 === preg_match( '/^[A-Z]$/', $first ) ? $first : '#';
	}

	/**
	 * Comparable form of a display name: honorific stripped, case-folded.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	private static function browse_sortable_name( string $name ): string {
		$clean = (string) preg_replace( '/^\s*(dr|prof|mr|mrs|ms)\.?\s+/i', '', trim( $name ) );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $clean ) : strtolower( $clean );
	}

	/**
	 * Collect the distinct index letters present in a list of items.
	 *
	 * @param array<int, array<string, mixed>> $items Items carrying a letter.
	 * @return array<int, string>
	 */
	private static function browse_letters_in( array $items ): array {
		$letters = array();

		foreach ( $items as $item ) {
			$letter = (string) ( $item['letter'] ?? '' );
			if ( '' !== $letter && ! in_array( $letter, $letters, true ) ) {
				$letters[] = $letter;
			}
		}

		return $letters;
	}

	/**
	 * Decode stored entities so a name renders as authored ("A &amp; B" → "A & B")
	 * before it is escaped again for output.
	 *
	 * @param string $value Raw name.
	 * @return string
	 */
	private static function browse_decode( string $value ): string {
		return html_entity_decode( $value, ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' );
	}
}
