<?php
/**
 * Builds the doctor directory's render payload — search state, browse indexes,
 * result cards — and renders it through `templates/directory.php`.
 *
 * Two things render the directory: the Doctor Grid / List Elementor widget, and
 * the doctor archive at the post type's own URL. They differ only in where the
 * settings come from — the widget's controls, or this class's defaults — so the
 * assembly lives here and both call it. Selection always goes through
 * `SearchRequestFactory` + `SearchService` + `DoctorView::card()`; nothing here
 * queries doctors directly.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Frontend;

use BrainStudioz\DoctorPilot\Application\Doctors\DoctorView;
use BrainStudioz\DoctorPilot\Application\Search\FacetService;
use BrainStudioz\DoctorPilot\Application\Search\SearchService;
use BrainStudioz\DoctorPilot\Domain\Search\SearchRequest;
use BrainStudioz\DoctorPilot\Domain\Search\SearchRequestFactory;
use BrainStudioz\DoctorPilot\Elementor\Widgets\Concerns\BrowseIndex;
use BrainStudioz\DoctorPilot\Rest\RouteRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assembles and renders the directory.
 */
final class DirectoryRenderer {

	use BrowseIndex;

	/**
	 * Every browsable mode => the search parameter its entries set.
	 *
	 * @var array<string, string>
	 */
	public const INDEX_MODES = array(
		'name'       => 'doctor',
		'department' => 'department',
		'specialty'  => 'specialty',
		'symptom'    => 'symptom',
	);

	/**
	 * The archive's configuration: what the directory looks like when no
	 * Elementor widget is choosing. Deliberately the same shape the widget's
	 * controls produce, so one assembly serves both.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'layout'       => 'grid',
			'columns'      => 3,
			'per_page'     => 12,
			'search_on'    => true,
			'az_show'      => true,
			'read_query'   => true,
			'default_sort' => 'name',
			'modes'        => array( 'name', 'department', 'symptom' ),
			'filters'      => array( 'sort', 'gender', 'language' ),
			'prefill'      => array(),
			'enqueue'      => true,
			'settings'     => array(),
		);
	}

	/**
	 * Build the payload `templates/directory.php` expects.
	 *
	 * @param array<string, mixed> $args Configuration; see `defaults()`.
	 * @return array<string, mixed>
	 */
	public static function data( array $args ): array {
		$args = array_merge( self::defaults(), $args );
		$self = new self();

		$settings     = (array) $args['settings'];
		$layout       = 'list' === $args['layout'] ? 'list' : 'grid';
		$search_on    = (bool) $args['search_on'];
		$az_show      = (bool) $args['az_show'];
		$modes        = array_values( (array) $args['modes'] );
		$filters      = array_values( (array) $args['filters'] );
		$per_page     = max( 1, min( SearchRequestFactory::MAX_PER_PAGE, (int) $args['per_page'] ) );
		$default_sort = in_array( (string) $args['default_sort'], SearchRequest::SORTS, true )
			? (string) $args['default_sort']
			: 'name';

		$raw = $self->request_params( $default_sort, (bool) $args['read_query'] );

		// A taxonomy archive carries its term in the main query rather than the
		// query string, so the term is bridged into the matching search
		// parameter here: that is what makes /doctors/department/cardiology/ and
		// /doctors/?department=cardiology land on the same results. An explicit
		// query string still wins, so a visitor can narrow further from there.
		foreach ( (array) $args['prefill'] as $key => $value ) {
			$key   = sanitize_key( (string) $key );
			$value = self::scalar( $value );
			if ( '' !== $key && '' !== $value && ! isset( $raw[ $key ] ) ) {
				$raw[ $key ] = $value;
			}
		}

		// A tab opens an A–Z index above the results; picking an entry writes
		// that index's parameter and narrows them. The tabs render only with the
		// search bar, so an index without its tab would be unreachable.
		$index_modes = $search_on
			? array_values( array_intersect( $modes, array_keys( self::INDEX_MODES ) ) )
			: array();
		// With no tab named in the URL the first one opens, so the A–Z index is
		// on screen the moment the page loads — the same thing the widget does.
		// Only the *displayed* index is defaulted: `$raw['mode']` is left alone,
		// because writing a mode into the search request would narrow it.
		$active_mode = sanitize_key( self::scalar( $raw['mode'] ?? '' ) );
		$active      = in_array( $active_mode, $index_modes, true )
			? $active_mode
			: (string) ( $index_modes[0] ?? '' );

		// Without the A–Z bar nothing could clear a letter, so a `?letter=` in
		// the URL must not be allowed to filter the index.
		$letter = $az_show ? $self->request_letter() : '';
		$query  = trim( wp_strip_all_tags( self::scalar( $raw['q'] ?? '' ) ) );

		$request = SearchRequestFactory::from_array( $raw, $per_page );
		$result  = ( new SearchService() )->search( $request );

		$view  = new DoctorView();
		$cards = array();
		foreach ( (array) $result['ids'] as $id ) {
			$card = $view->card( (int) $id );
			if ( null !== $card ) {
				$cards[] = $card;
			}
		}

		$indexes  = array();
		$selected = array();
		foreach ( $index_modes as $mode ) {
			$indexes[ $mode ]  = 'name' === $mode
				? $self->browse_name_index( $az_show, true, $request->sort() )
				: $self->browse_term_index( $mode, $az_show, $request->sort() );
			$selected[ $mode ] = self::first_slug( $raw[ self::INDEX_MODES[ $mode ] ] ?? '' );
		}

		return array(
			'settings'      => $settings,
			'request'       => $request,
			'indexes'       => $indexes,
			'selected'      => $selected,
			'cards'         => $cards,
			'result'        => $result,
			'active_index'  => $active,
			'letter'        => $letter,
			'query'         => $query,
			'layout'        => $layout,
			'search_on'     => $search_on,
			'index_modes'   => $index_modes,
			'index_params'  => self::INDEX_MODES,
			'modes'         => $modes,
			'filters'       => $filters,
			'facets'        => array() !== $filters ? ( new FacetService() )->facets() : array(),
			'sort_labels'   => self::sort_labels(),
			'mode_labels'   => self::mode_labels(),
			'filter_labels' => self::filter_labels(),
			'default_sort'  => $default_sort,
			'uid'           => (string) ( $args['uid'] ?? 'dp-dir-archive' ),
			'rest_base'     => esc_url_raw( rest_url( RouteRegistry::NAMESPACE ) ),
			'lang'          => (string) ( $args['lang'] ?? '' ),
			'per_page'      => $per_page,
			'is_editor'     => (bool) ( $args['is_editor'] ?? false ),
		);
	}

	/**
	 * Render the directory.
	 *
	 * @param array<string, mixed> $args Configuration; see `defaults()`.
	 * @return void
	 */
	public static function render( array $args = array() ): void {
		// The Elementor widget gets these through its own dependencies; the
		// archive has no widget, so it asks for them here. Registering twice is
		// a no-op, so calling this on either path is safe.
		if ( false !== ( $args['enqueue'] ?? true ) ) {
			DirectoryAssets::enqueue();
		}

		$data = self::data( $args );

		include Templates::locate( 'directory.php' );
	}

	/**
	 * Search modes offered by the tabs.
	 *
	 * @return array<string, string>
	 */
	public static function mode_labels(): array {
		return array(
			'universal'  => __( 'All', 'doctorpilot' ),
			'name'       => __( 'By Doctor Name', 'doctorpilot' ),
			'department' => __( 'By Department', 'doctorpilot' ),
			'specialty'  => __( 'By Specialty', 'doctorpilot' ),
			'symptom'    => __( 'By Symptom', 'doctorpilot' ),
		);
	}

	/**
	 * Sort values offered by the Sort By control.
	 *
	 * @return array<string, string>
	 */
	public static function sort_labels(): array {
		return array(
			'relevance'  => __( 'Relevance', 'doctorpilot' ),
			'name'       => __( 'A → Z', 'doctorpilot' ),
			'name_desc'  => __( 'Z → A', 'doctorpilot' ),
			'featured'   => __( 'Featured', 'doctorpilot' ),
			'experience' => __( 'Most experienced', 'doctorpilot' ),
			'recent'     => __( 'Recently added', 'doctorpilot' ),
		);
	}

	/**
	 * Filters offered inside the "More Filters" panel.
	 *
	 * @return array<string, string>
	 */
	public static function filter_labels(): array {
		return array(
			'sort'          => __( 'Sort By', 'doctorpilot' ),
			'gender'        => __( 'Gender', 'doctorpilot' ),
			'language'      => __( 'Languages Spoken', 'doctorpilot' ),
			'department'    => __( 'Department', 'doctorpilot' ),
			'specialty'     => __( 'Specialty', 'doctorpilot' ),
			'symptom'       => __( 'Symptom', 'doctorpilot' ),
			'international' => __( 'National / International', 'doctorpilot' ),
			'online'        => __( 'Online consultation', 'doctorpilot' ),
		);
	}

	/**
	 * The search parameters implied by the current archive query.
	 *
	 * A DoctorPilot taxonomy archive (`/doctors/department/cardiology/`) already
	 * knows its term; the directory filters on parameters. This translates one
	 * into the other, and returns nothing for any other query.
	 *
	 * @return array<string, string>
	 */
	public static function archive_prefill(): array {
		$term = get_queried_object();
		if ( ! ( $term instanceof \WP_Term ) ) {
			return array();
		}

		foreach ( SearchRequestFactory::filter_taxonomies() as $param => $taxonomy ) {
			if ( $taxonomy === $term->taxonomy ) {
				return array( (string) $param => (string) $term->slug );
			}
		}

		return array();
	}

	/**
	 * Build the raw search parameters from the query string.
	 *
	 * @param string $default_sort Configured default sort.
	 * @param bool   $read_query   Whether to honour the query string.
	 * @return array<string, mixed>
	 */
	private function request_params( string $default_sort, bool $read_query ): array {
		$raw = array(
			'sort' => $default_sort,
			'page' => max( 1, (int) get_query_var( 'paged' ) ?: (int) get_query_var( 'page' ) ),
		);

		if ( ! $read_query ) {
			return $raw;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only GET search state.
		$query = wp_unslash( $_GET );
		if ( ! is_array( $query ) ) {
			return $raw;
		}

		$accepted = array_merge(
			array( 'q', 'mode', 'doctor', 'gender', 'online', 'international', 'sort', 'page' ),
			array_keys( SearchRequestFactory::filter_taxonomies() )
		);

		// Flattened to strings here rather than passed through: `?q[]=a` would
		// otherwise reach the request factory's string casts as an array.
		foreach ( $accepted as $key ) {
			if ( ! isset( $query[ $key ] ) ) {
				continue;
			}
			$value = self::scalar( $query[ $key ] );
			if ( '' === $value ) {
				continue;
			}
			$raw[ $key ] = $value;
		}

		// An empty sort in the URL means "widget default", not "relevance".
		if ( ! isset( $raw['sort'] ) || '' === $raw['sort'] ) {
			$raw['sort'] = $default_sort;
		}

		return $raw;
	}

	/**
	 * Read and validate the A–Z letter from the query string.
	 *
	 * Only A–Z is a filter. Anything else — including a stale `#` from a link
	 * made before that glyph became the "show all" control — falls through to
	 * '', which is what "all" is.
	 *
	 * @return string Single uppercase letter, or '' for "all".
	 */
	private function request_letter(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only GET state.
		$raw = isset( $_GET['letter'] ) ? self::scalar( wp_unslash( $_GET['letter'] ) ) : '';
		$raw = strtoupper( trim( $raw ) );

		return 1 === preg_match( '/^[A-Z]$/', $raw ) ? $raw : '';
	}

	/**
	 * First slug of a raw (possibly comma-separated) filter value.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function first_slug( $value ): string {
		$parts = explode( ',', self::scalar( $value ) );

		return sanitize_title( (string) reset( $parts ) );
	}

	/**
	 * Coerce a raw query-string value to a string.
	 *
	 * `$_GET` values are attacker-controlled shapes: `?mode[]=name` hands this a
	 * an array where a string is expected, and casting that directly raises a
	 * warning. Taking the first scalar keeps the request usable.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function scalar( $value ): string {
		while ( is_array( $value ) ) {
			$value = reset( $value );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}
}
