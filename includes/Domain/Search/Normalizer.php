<?php
/**
 * Search text normalization.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Domain\Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Multibyte-safe normalization for search comparison.
 *
 * Normalization is only used for matching; the original display values (which
 * may contain accents and non-Latin characters) are preserved elsewhere. No
 * automatic clinical equivalence is inferred — only curated synonyms match.
 */
final class Normalizer {

	/**
	 * Title prefixes stripped for comparison (not from display names).
	 *
	 * @var array<int, string>
	 */
	private const PREFIXES = array( 'dr', 'prof', 'mr', 'mrs', 'ms', 'mx' );

	/**
	 * Minimum meaningful token length (shorter tokens are ignored unless a name/initial).
	 *
	 * @var int
	 */
	public const MIN_TOKEN = 2;

	/**
	 * Normalize a string: trim, collapse whitespace, strip accents, casefold.
	 *
	 * @param string $value Raw string.
	 * @return string
	 */
	public static function normalize( string $value ): string {
		$value = wp_strip_all_tags( $value );
		$value = remove_accents( $value );
		$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
		$value = preg_replace( '/\s+/u', ' ', $value );

		return trim( (string) $value );
	}

	/**
	 * Normalize and strip a leading title prefix for name comparison.
	 *
	 * @param string $value Raw name.
	 * @return string
	 */
	public static function normalize_name( string $value ): string {
		$normalized = self::normalize( $value );
		$pattern    = '/^(' . implode( '|', self::PREFIXES ) . ')\.?\s+/u';

		return trim( (string) preg_replace( $pattern, '', $normalized ) );
	}

	/**
	 * Tokenize a normalized string into meaningful tokens.
	 *
	 * @param string $value Raw string.
	 * @return array<int, string>
	 */
	public static function tokens( string $value ): array {
		$normalized = self::normalize( $value );
		if ( '' === $normalized ) {
			return array();
		}

		$parts  = preg_split( '/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY );
		$tokens = array();

		foreach ( (array) $parts as $part ) {
			$part = (string) $part;
			$len  = function_exists( 'mb_strlen' ) ? mb_strlen( $part ) : strlen( $part );
			if ( $len >= self::MIN_TOKEN ) {
				$tokens[] = $part;
			}
		}

		return $tokens;
	}
}
