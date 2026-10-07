<?php
/**
 * Public search response cache.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Infrastructure\Cache;

use BrainStudioz\DoctorPilot\Support\Multilingual;

use BrainStudioz\DoctorPilot\Domain\Search\SearchRequest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Caches public-safe search/facet responses via the transients API.
 *
 * Cache keys include the API schema version, the cache generation, the site
 * locale, and the normalized request signature so that changing any of them
 * yields a fresh response. Correctness never depends on TTL expiry — the
 * generation counter is the source of truth. Only public-safe view models are
 * ever stored; previews, private fields, and secrets are not.
 */
final class SearchCache {

	/**
	 * Cache key prefix.
	 *
	 * @var string
	 */
	private const PREFIX = 'dp_search_';

	/**
	 * API schema version participating in the key.
	 *
	 * @var int
	 */
	private const API_VERSION = 1;

	/**
	 * Build a cache key for a request and response kind.
	 *
	 * @param SearchRequest $request Search request.
	 * @param string        $kind    Response kind (e.g. doctors, facets).
	 * @return string
	 */
	public static function key( SearchRequest $request, string $kind ): string {
		/*
		 * Locale *and* language code. `get_locale()` is filtered by every
		 * multilingual plugin, so it already separates most languages — but two
		 * WPML languages can share one WordPress locale (a regional variant
		 * mapped to the same `.mo`), and those two would then collide on one
		 * cache entry. The language code costs nothing on a single-language
		 * site, where it is an empty string.
		 */
		$parts = array(
			self::API_VERSION,
			CacheGeneration::current(),
			get_locale(),
			Multilingual::current_language(),
			$kind,
			$request->signature(),
		);

		return self::PREFIX . md5( implode( '|', array_map( 'strval', $parts ) ) );
	}

	/**
	 * Get a cached value, or null when missing.
	 *
	 * @param string $key Cache key.
	 * @return mixed|null
	 */
	public static function get( string $key ) {
		$value = get_transient( $key );

		return false === $value ? null : $value;
	}

	/**
	 * Store a public-safe value.
	 *
	 * @param string $key Cache key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl Time to live in seconds.
	 * @return void
	 */
	public static function set( string $key, $value, int $ttl ): void {
		set_transient( $key, $value, max( 0, $ttl ) );
	}
}
