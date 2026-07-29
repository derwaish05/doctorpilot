<?php
/**
 * Deterministic candidate ranking.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Application\Search;

use BrainStudioz\DoctorPilot\Domain\Search\Normalizer;
use BrainStudioz\DoctorPilot\Domain\Search\SearchRequest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scores and orders candidates deterministically (spec §8.2).
 *
 * Scoring uses the pre-built, normalized search index buckets so no per-request
 * term queries are needed. Ties break by score, then featured, then manual
 * order, then normalized name, then post ID — always producing a stable order.
 */
final class Ranker {

	private const SCORE_EXACT_NAME        = 1000;
	private const SCORE_NAME_PREFIX       = 850;
	private const SCORE_NAME_TOKENS       = 750;
	private const SCORE_EXACT_SPECIALTY   = 650;
	private const SCORE_ASSIGNED_SPECIALTY = 600;
	private const SCORE_SYMPTOM           = 550;
	private const SCORE_CONDITION         = 400;
	private const SCORE_KEYWORD           = 250;
	private const FEATURED_BOOST          = 30;

	/**
	 * Order candidate rows for a request, returning ordered IDs.
	 *
	 * @param SearchRequest                     $request    Search request.
	 * @param array<int, array<string, mixed>>  $candidates Candidate rows.
	 * @return array<int, int> Ordered post IDs.
	 */
	public function order( SearchRequest $request, array $candidates ): array {
		$scored = array();

		$has_query = $request->has_query();
		$query     = Normalizer::normalize( $request->query() );
		$tokens    = Normalizer::tokens( $request->query() );

		foreach ( $candidates as $row ) {
			$score = $has_query ? $this->score( $query, $tokens, $row ) : 0;

			// When a query is present, drop non-matching candidates.
			if ( $has_query && 0 === $score ) {
				continue;
			}

			$scored[] = array(
				'id'       => (int) $row['id'],
				'score'    => $score,
				'featured' => ! empty( $row['featured'] ),
				'order'    => (int) $row['order'],
				'name'     => (string) $row['name'],
				'exp'      => (int) $row['experience'],
				'created'  => (int) $row['created'],
			);
		}

		usort(
			$scored,
			function ( array $a, array $b ) use ( $request ): int {
				return $this->compare( $a, $b, $request->sort() );
			}
		);

		return array_map(
			static function ( array $row ): int {
				return $row['id'];
			},
			$scored
		);
	}

	/**
	 * Compute a candidate's relevance score for a query.
	 *
	 * @param string                $query  Normalized query.
	 * @param array<int, string>    $tokens Query tokens.
	 * @param array<string, mixed>  $row    Candidate row.
	 * @return int
	 */
	private function score( string $query, array $tokens, array $row ): int {
		$index = is_array( $row['index'] ) ? $row['index'] : array();
		$name  = isset( $index['name'] ) ? (string) $index['name'] : (string) $row['name'];
		$score = 0;

		// Name matches.
		if ( '' !== $name && $name === $query ) {
			$score = max( $score, self::SCORE_EXACT_NAME );
		} elseif ( '' !== $query && 0 === strpos( $name, $query ) ) {
			$score = max( $score, self::SCORE_NAME_PREFIX );
		} elseif ( array() !== $tokens && $this->all_tokens_in( $tokens, isset( $index['name_tokens'] ) ? (array) $index['name_tokens'] : array() ) ) {
			$score = max( $score, self::SCORE_NAME_TOKENS );
		}

		// Specialty / department.
		if ( $this->in_bucket( $query, $index, 'specialties' ) || $this->in_bucket( $query, $index, 'departments' ) ) {
			$score = max( $score, self::SCORE_EXACT_SPECIALTY );
		} elseif ( $this->tokens_touch_bucket( $tokens, $index, 'specialties' ) || $this->tokens_touch_bucket( $tokens, $index, 'departments' ) ) {
			$score = max( $score, self::SCORE_ASSIGNED_SPECIALTY );
		}

		// Symptom.
		if ( $this->in_bucket( $query, $index, 'symptoms' ) || $this->tokens_touch_bucket( $tokens, $index, 'symptoms' ) ) {
			$score = max( $score, self::SCORE_SYMPTOM );
		}

		// Condition / treatment.
		if ( $this->in_bucket( $query, $index, 'conditions' ) || $this->tokens_touch_bucket( $tokens, $index, 'conditions' ) ) {
			$score = max( $score, self::SCORE_CONDITION );
		}

		// Keyword / document fallback.
		if ( 0 === $score && '' !== (string) $row['document'] && false !== strpos( (string) $row['document'], $query ) ) {
			$score = self::SCORE_KEYWORD;
		}

		if ( $score > 0 && ! empty( $row['featured'] ) ) {
			$score += self::FEATURED_BOOST;
		}

		return $score;
	}

	/**
	 * Whether all query tokens appear in a token list.
	 *
	 * @param array<int, string> $tokens    Query tokens.
	 * @param array<int, string> $haystack  Candidate tokens.
	 * @return bool
	 */
	private function all_tokens_in( array $tokens, array $haystack ): bool {
		if ( array() === $tokens || array() === $haystack ) {
			return false;
		}

		foreach ( $tokens as $token ) {
			if ( ! in_array( $token, $haystack, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether the whole query exactly equals a bucket entry.
	 *
	 * @param string               $query  Normalized query.
	 * @param array<string, mixed> $index  Candidate index.
	 * @param string               $bucket Bucket key.
	 * @return bool
	 */
	private function in_bucket( string $query, array $index, string $bucket ): bool {
		if ( '' === $query || empty( $index[ $bucket ] ) || ! is_array( $index[ $bucket ] ) ) {
			return false;
		}

		return in_array( $query, $index[ $bucket ], true );
	}

	/**
	 * Whether any query token is contained in any bucket entry.
	 *
	 * @param array<int, string>   $tokens Query tokens.
	 * @param array<string, mixed> $index  Candidate index.
	 * @param string               $bucket Bucket key.
	 * @return bool
	 */
	private function tokens_touch_bucket( array $tokens, array $index, string $bucket ): bool {
		if ( array() === $tokens || empty( $index[ $bucket ] ) || ! is_array( $index[ $bucket ] ) ) {
			return false;
		}

		$haystack = implode( ' ', array_map( 'strval', $index[ $bucket ] ) );
		foreach ( $tokens as $token ) {
			if ( false !== strpos( $haystack, $token ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Comparison function honoring the requested sort and tie rules.
	 *
	 * @param array<string, mixed> $a    First row.
	 * @param array<string, mixed> $b    Second row.
	 * @param string               $sort Sort mode.
	 * @return int
	 */
	private function compare( array $a, array $b, string $sort ): int {
		switch ( $sort ) {
			case 'name':
				$primary = strcmp( (string) $a['name'], (string) $b['name'] );
				break;
			case 'experience':
				$primary = (int) $b['exp'] <=> (int) $a['exp'];
				break;
			case 'recent':
				$primary = (int) $b['created'] <=> (int) $a['created'];
				break;
			case 'featured':
				$primary = ( (int) $b['featured'] ) <=> ( (int) $a['featured'] );
				break;
			case 'relevance':
			default:
				$primary = (int) $b['score'] <=> (int) $a['score'];
				break;
		}

		if ( 0 !== $primary ) {
			return $primary;
		}

		// Tie order: featured desc, manual order asc, name asc, ID asc.
		$featured = ( (int) $b['featured'] ) <=> ( (int) $a['featured'] );
		if ( 0 !== $featured ) {
			return $featured;
		}

		$order = (int) $a['order'] <=> (int) $b['order'];
		if ( 0 !== $order ) {
			return $order;
		}

		$name = strcmp( (string) $a['name'], (string) $b['name'] );
		if ( 0 !== $name ) {
			return $name;
		}

		return (int) $a['id'] <=> (int) $b['id'];
	}
}
