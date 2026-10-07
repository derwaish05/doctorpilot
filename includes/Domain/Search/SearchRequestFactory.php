<?php
/**
 * Builds a bounded SearchRequest from raw parameters.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Domain\Search;

use BrainStudioz\DoctorPilot\Support\Multilingual;
use BrainStudioz\DoctorPilot\Taxonomies\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes and bounds raw request parameters into a SearchRequest.
 *
 * The same factory serves both REST and the server-rendered GET fallback so a
 * given set of parameters always produces one canonical request, cache key, and
 * shareable URL. Only Free filters are accepted here; Pro filters are ignored.
 */
final class SearchRequestFactory {

	/**
	 * Maximum query length in characters.
	 *
	 * @var int
	 */
	private const MAX_QUERY = 100;

	/**
	 * Maximum values accepted per taxonomy filter.
	 *
	 * @var int
	 */
	private const MAX_FILTER_VALUES = 10;

	/**
	 * Maximum results per page.
	 *
	 * @var int
	 */
	public const MAX_PER_PAGE = 48;

	/**
	 * Public filter name => taxonomy key for the Free filters.
	 *
	 * @return array<string, string>
	 */
	public static function filter_taxonomies(): array {
		$defaults = array(
			'department' => Taxonomies::DEPARTMENT,
			'specialty'  => Taxonomies::SPECIALTY,
			'symptom'    => Taxonomies::SYMPTOM,
			'language'   => Taxonomies::LANGUAGE,
		);

		/**
		 * Filter the param => taxonomy map used for search filters.
		 *
		 * Add-ons (e.g. DoctorPilot Pro) may register additional filter
		 * taxonomies such as condition, treatment, location, or insurance.
		 * Candidate querying, active-filter chips, and facets all read from this
		 * map, so extensions get full parity without a parallel query engine.
		 *
		 * @param array<string, string> $defaults Param => taxonomy key.
		 */
		return (array) apply_filters( 'doctorpilot/search/filter_taxonomies', $defaults );
	}

	/**
	 * Build a SearchRequest from raw parameters.
	 *
	 * @param array<string, mixed> $raw      Raw parameters (from REST or query string).
	 * @param int                  $per_page Default per-page from settings.
	 * @return SearchRequest
	 */
	public static function from_array( array $raw, int $per_page = 12 ): SearchRequest {
		$query = isset( $raw['q'] ) ? (string) $raw['q'] : '';
		$query = trim( wp_strip_all_tags( $query ) );
		if ( function_exists( 'mb_substr' ) ) {
			$query = mb_substr( $query, 0, self::MAX_QUERY );
		} else {
			$query = substr( $query, 0, self::MAX_QUERY );
		}

		$mode = isset( $raw['mode'] ) ? sanitize_key( (string) $raw['mode'] ) : 'universal';
		if ( ! in_array( $mode, SearchRequest::MODES, true ) ) {
			$mode = 'universal';
		}

		$taxonomy_filters = array();
		foreach ( self::filter_taxonomies() as $param => $taxonomy ) {
			$slugs = self::slug_list( $raw[ $param ] ?? '' );

			/*
			 * Resolve each slug into the current language. This is the single
			 * point where a language-specific value enters the query, so it is
			 * the single point that has to know about translation.
			 *
			 * Three callers benefit at once, and all three are broken without
			 * it. An Elementor widget stores the slug that existed when an
			 * editor picked it, and the translated copy of that page still holds
			 * the source-language slug. A visitor who switches language keeps
			 * the query string, so `?department=cardiology` arrives on the Arabic
			 * site. A shared or bookmarked link does the same. In every case the
			 * untranslated slug matches no term in the current language, and the
			 * result is an empty directory rather than an error.
			 *
			 * On a single-language site this is an identity function behind one
			 * `has_filter()` check.
			 */
			$slugs = Multilingual::term_slugs( $slugs, $taxonomy );

			if ( array() !== $slugs ) {
				$taxonomy_filters[ $taxonomy ] = $slugs;
			}
		}

		// A specific doctor picked from the "By Doctor Name" index. Slugs, not
		// IDs, so the state stays shareable and readable in the URL.
		$doctors = self::slug_list( $raw['doctor'] ?? '' );

		$gender = isset( $raw['gender'] ) ? sanitize_key( (string) $raw['gender'] ) : '';
		if ( ! in_array( $gender, array( 'female', 'male', 'other' ), true ) ) {
			$gender = '';
		}

		$online        = self::tri_bool( $raw['online'] ?? null );
		$international = self::tri_bool( $raw['international'] ?? null );

		$sort = isset( $raw['sort'] ) ? sanitize_key( (string) $raw['sort'] ) : 'relevance';
		if ( ! in_array( $sort, SearchRequest::SORTS, true ) ) {
			$sort = 'relevance';
		}

		$view = isset( $raw['view'] ) ? sanitize_key( (string) $raw['view'] ) : 'grid';
		if ( ! in_array( $view, SearchRequest::VIEWS, true ) ) {
			$view = 'grid';
		}

		$page = isset( $raw['page'] ) ? (int) $raw['page'] : 1;
		$page = max( 1, min( 1000, $page ) );

		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_page ) );

		return new SearchRequest(
			$query,
			$mode,
			$taxonomy_filters,
			$gender,
			$online,
			$sort,
			$view,
			$page,
			$per_page,
			$doctors,
			$international
		);
	}

	/**
	 * Parse a comma-separated or array value into a bounded, sorted slug list.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, string>
	 */
	private static function slug_list( $value ): array {
		if ( is_array( $value ) ) {
			$parts = $value;
		} else {
			$parts = explode( ',', (string) $value );
		}

		$slugs = array();
		foreach ( $parts as $part ) {
			$slug = sanitize_title( (string) $part );
			if ( '' !== $slug && ! in_array( $slug, $slugs, true ) ) {
				$slugs[] = $slug;
			}
			if ( count( $slugs ) >= self::MAX_FILTER_VALUES ) {
				break;
			}
		}

		// Sort so equivalent filter states produce one canonical signature/URL.
		sort( $slugs );

		return $slugs;
	}

	/**
	 * Interpret a tri-state boolean parameter.
	 *
	 * @param mixed $value Raw value.
	 * @return bool|null True/false when present, null when absent/blank.
	 */
	private static function tri_bool( $value ): ?bool {
		if ( null === $value || '' === $value ) {
			return null;
		}

		return in_array( (string) $value, array( '1', 'true', 'yes', 'on' ), true );
	}
}
