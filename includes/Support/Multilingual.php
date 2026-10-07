<?php
/**
 * Multilingual plugin bridge.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one place this plugin knows a multilingual plugin might exist.
 *
 * Every method is an identity function when no multilingual plugin is active:
 * an ID maps to itself, a slug maps to itself, the current language is an empty
 * string. That is the whole design. A single-language site — which is every site
 * running this plugin today — takes exactly one `has_filter()` check per call
 * and gets back what it passed in, so no output changes and nothing new can
 * break. The multilingual paths only ever execute on a site that has opted into
 * them by installing WPML.
 *
 * The integration is built on WPML's *public filter API* rather than on its
 * classes or globals:
 *
 * - `wpml_object_id` — map a post or term ID to its translation. WPML documents
 *   this as the supported way to do it, and it is a filter, so nothing here
 *   needs WPML to be loaded, or even installed, to be safe to call.
 * - `wpml_current_language` — the current language code.
 * - `wpml_active_languages` — used only to decide whether more than one language
 *   actually exists.
 *
 * Polylang is deliberately not wired up here, but the seam is the reason this
 * class exists: adding it means implementing these five methods against
 * `pll_get_post`/`pll_get_term`/`pll_current_language` and nothing else in the
 * plugin changes.
 *
 * @see https://wpml.org/wpml-hook/wpml_object_id/
 */
final class Multilingual {

	/**
	 * Cached slug lookups, keyed `taxonomy|slug|language`.
	 *
	 * A directory request resolves up to ten slugs per taxonomy across eight
	 * taxonomies, and the browse index re-resolves the same handful on every
	 * render. Each miss is a `get_term_by()` plus a filter round trip, so the
	 * repeats are worth avoiding within a request. Not persisted: a term
	 * translation can change mid-request in the admin, and a stale slug here
	 * would silently mis-filter a directory.
	 *
	 * @var array<string, string>
	 */
	private static array $slug_cache = array();

	/**
	 * Whether a multilingual plugin is managing this site.
	 *
	 * Checks for the filter rather than for a class or constant, so it stays
	 * true for anything that implements WPML's API — WPML itself, or a
	 * compatibility shim — and false for a site that merely has the plugin
	 * files present but inactive.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return has_filter( 'wpml_object_id' ) || has_filter( 'wpml_current_language' );
	}

	/**
	 * The current language code, or an empty string when not multilingual.
	 *
	 * @return string
	 */
	public static function current_language(): string {
		if ( ! self::is_active() ) {
			return '';
		}

		$language = apply_filters( 'wpml_current_language', null );

		return is_string( $language ) ? $language : '';
	}

	/**
	 * Whether the site has more than one active language.
	 *
	 * A site can have WPML installed with a single language configured, in which
	 * case none of the translation work below is worth doing.
	 *
	 * @return bool
	 */
	public static function has_multiple_languages(): bool {
		if ( ! self::is_active() ) {
			return false;
		}

		$languages = apply_filters( 'wpml_active_languages', null );

		return is_array( $languages ) && count( $languages ) > 1;
	}

	/**
	 * Map a post ID to its translation in the current language.
	 *
	 * Returns the original ID unchanged when there is no translation, rather
	 * than null: a doctor card pointing at the source-language profile is a
	 * worse outcome than nothing, but a card that vanishes is worse still, and
	 * WPML's own default for `$return_original_if_missing` is to fall back.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 * @return int
	 */
	public static function post_id( int $post_id, string $post_type ): int {
		return self::object_id( $post_id, $post_type );
	}

	/**
	 * Map a term ID to its translation in the current language.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy key.
	 * @return int
	 */
	public static function term_id( int $term_id, string $taxonomy ): int {
		return self::object_id( $term_id, $taxonomy );
	}

	/**
	 * Map a term slug to the equivalent slug in the current language.
	 *
	 * This is the one that makes saved Elementor widgets and shared URLs work.
	 * A widget saved on an English page stores `cardiology`; rendered on the
	 * Arabic translation of that page it has to become the Arabic department's
	 * slug or the query matches nothing and the section silently disappears.
	 *
	 * Resolution is deliberately forgiving, because the alternative to a wrong
	 * answer here is an empty directory:
	 *
	 * 1. If the slug already exists in the current language, use it. This is the
	 *    overwhelmingly common case and costs one cached term lookup.
	 * 2. Otherwise find the term in *any* language and translate its ID.
	 * 3. If neither works, return the slug untouched and let the query decide.
	 *    An unknown slug matching nothing is correct behaviour for a genuinely
	 *    bad URL.
	 *
	 * @param string $slug     Term slug.
	 * @param string $taxonomy Taxonomy key.
	 * @return string
	 */
	public static function term_slug( string $slug, string $taxonomy ): string {
		// A site can run WPML with a single language configured, where there is
		// nothing to resolve and the lookup below is pure cost.
		if ( '' === $slug || ! self::has_multiple_languages() ) {
			return $slug;
		}

		$key = $taxonomy . '|' . $slug . '|' . self::current_language();

		if ( isset( self::$slug_cache[ $key ] ) ) {
			return self::$slug_cache[ $key ];
		}

		self::$slug_cache[ $key ] = self::resolve_slug( $slug, $taxonomy );

		return self::$slug_cache[ $key ];
	}

	/**
	 * Map a list of term slugs, preserving order and dropping nothing.
	 *
	 * @param array<int, string> $slugs    Term slugs.
	 * @param string             $taxonomy Taxonomy key.
	 * @return array<int, string>
	 */
	public static function term_slugs( array $slugs, string $taxonomy ): array {
		if ( array() === $slugs || ! self::has_multiple_languages() ) {
			return $slugs;
		}

		$translated = array();

		foreach ( $slugs as $slug ) {
			$translated[] = self::term_slug( (string) $slug, $taxonomy );
		}

		// A source and target slug can collide after translation when two
		// languages share a slug, and a duplicate in a tax_query is harmless but
		// makes the active-filter chips repeat.
		return array_values( array_unique( $translated ) );
	}

	/**
	 * Switch the active language for the rest of the request.
	 *
	 * Needed by the REST routes. WPML normally infers the language from the
	 * request URL, but whether a REST URL carries a language prefix depends on
	 * the site's URL mode — in query-string mode it carries none — and a route
	 * that answers in the default language while the page around it is
	 * translated is the worst possible outcome, because it looks like the
	 * directory is broken rather than untranslated.
	 *
	 * Validated against the site's active languages rather than trusted: this
	 * value arrives from a public, unauthenticated query parameter, and handing
	 * an arbitrary string to a language switch is not something to do on the
	 * strength of it looking like a language code.
	 *
	 * @param string $language Language code, or an empty string to do nothing.
	 * @return bool Whether the language was switched.
	 */
	public static function switch_language( string $language ): bool {
		if ( '' === $language || ! self::is_active() ) {
			return false;
		}

		$language = sanitize_key( $language );

		if ( '' === $language || ! in_array( $language, self::available_languages(), true ) ) {
			return false;
		}

		if ( $language === self::current_language() ) {
			return false;
		}

		do_action( 'wpml_switch_language', $language );

		// The slug cache is keyed by language, so it survives a switch — but
		// term lookups made before the switch were resolved against the old
		// language, and nothing else in the request should see them.
		self::flush_cache();

		return true;
	}

	/**
	 * The site's active language codes.
	 *
	 * @return array<int, string>
	 */
	public static function available_languages(): array {
		if ( ! self::is_active() ) {
			return array();
		}

		$languages = apply_filters( 'wpml_active_languages', null );

		if ( ! is_array( $languages ) ) {
			return array();
		}

		$codes = array();

		foreach ( $languages as $key => $language ) {
			// WPML keys the array by language code and repeats it in a
			// `language_code` member; accept either shape.
			if ( is_array( $language ) && isset( $language['language_code'] ) ) {
				$codes[] = (string) $language['language_code'];
			} elseif ( is_string( $key ) && '' !== $key ) {
				$codes[] = $key;
			}
		}

		return array_values( array_unique( $codes ) );
	}

	/**
	 * Reset the in-request slug cache.
	 *
	 * Called by `switch_language()`, because entries resolved before a switch
	 * answer for the wrong language. Also used by the test harness.
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		self::$slug_cache = array();
	}

	/**
	 * The shared `wpml_object_id` call behind `post_id()` and `term_id()`.
	 *
	 * @param int    $object_id   Post or term ID.
	 * @param string $object_type Post type or taxonomy key.
	 * @return int
	 */
	private static function object_id( int $object_id, string $object_type ): int {
		if ( $object_id <= 0 || ! self::is_active() ) {
			return $object_id;
		}

		/**
		 * Fourth argument is WPML's `$language_code`; null means "the current
		 * language". Third is `$return_original_if_missing`.
		 */
		$translated = apply_filters( 'wpml_object_id', $object_id, $object_type, true, null );

		return is_numeric( $translated ) ? (int) $translated : $object_id;
	}

	/**
	 * Uncached slug resolution. See `term_slug()` for the rules.
	 *
	 * @param string $slug     Term slug.
	 * @param string $taxonomy Taxonomy key.
	 * @return string
	 */
	private static function resolve_slug( string $slug, string $taxonomy ): string {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return $slug;
		}

		// `get_term_by()` is language-filtered by WPML, so a hit here means the
		// slug is already correct for this language.
		$term = get_term_by( 'slug', $slug, $taxonomy );

		if ( $term instanceof \WP_Term ) {
			return $slug;
		}

		// Miss: the slug belongs to another language. Find it without the
		// language filter, then translate the ID into this one.
		$source_id = self::find_term_id_in_any_language( $slug, $taxonomy );

		if ( 0 === $source_id ) {
			return $slug;
		}

		$translated_id = self::term_id( $source_id, $taxonomy );

		if ( $translated_id === $source_id ) {
			return $slug;
		}

		$translated = get_term( $translated_id, $taxonomy );

		return $translated instanceof \WP_Term ? (string) $translated->slug : $slug;
	}

	/**
	 * Find a term ID by slug regardless of language.
	 *
	 * Deliberately a direct query rather than `get_terms()`. Every term API is
	 * language-filtered by WPML — that is the entire point of WPML — and the
	 * documented ways to suspend the filter work by mutating global state
	 * (switching the active language, unhooking filters) that has to be restored
	 * afterwards. Getting that restore wrong on one code path would make every
	 * later term query in the request return all languages at once, which is a
	 * much worse bug than the one being fixed, and it would only show up under
	 * the exact conditions that are hardest to reproduce.
	 *
	 * A prepared read of two core tables has none of that risk, is one query,
	 * and cannot be affected by whatever else is filtering terms.
	 *
	 * WPML filters `wp_unique_term_slug`, so the same slug can legitimately
	 * exist twice in one taxonomy in different languages. The `ORDER BY` makes
	 * which one wins deterministic instead of whatever the storage engine
	 * returns first — either is translated to the same place when they are in
	 * one translation group, and a stable answer matters more than a clever one
	 * when they are not.
	 *
	 * @param string $slug     Term slug.
	 * @param string $taxonomy Taxonomy key.
	 * @return int Term ID, or 0 when the slug exists in no language.
	 */
	private static function find_term_id_in_any_language( string $slug, string $taxonomy ): int {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return 0;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Language-agnostic lookup; see the docblock.
		$term_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT t.term_id
				   FROM {$wpdb->terms} AS t
				   INNER JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_id = t.term_id
				  WHERE t.slug = %s
				    AND tt.taxonomy = %s
				  ORDER BY t.term_id ASC
				  LIMIT 1",
				$slug,
				$taxonomy
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return null === $term_id ? 0 : (int) $term_id;
	}
}
