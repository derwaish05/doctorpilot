<?php
/**
 * Search orchestration.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Application\Search;

use BrainStudioz\DoctorPilot\Domain\Search\SearchRequest;
use BrainStudioz\DoctorPilot\Domain\Search\SearchRequestFactory;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates candidate retrieval, ranking, and pagination.
 *
 * Returns ordered IDs plus pagination metadata and active-filter chips.
 * Hydration into public cards is left to the caller (which uses DoctorView) so
 * this service stays free of presentation concerns.
 */
final class SearchService {

	/**
	 * Candidate repository.
	 *
	 * @var CandidateRepository
	 */
	private CandidateRepository $candidates;

	/**
	 * Ranker.
	 *
	 * @var Ranker
	 */
	private Ranker $ranker;

	/**
	 * Constructor.
	 *
	 * @param CandidateRepository|null $candidates Candidate repository.
	 * @param Ranker|null              $ranker     Ranker.
	 */
	public function __construct( ?CandidateRepository $candidates = null, ?Ranker $ranker = null ) {
		$this->candidates = $candidates ?? new CandidateRepository();
		$this->ranker     = $ranker ?? new Ranker();
	}

	/**
	 * Execute a search.
	 *
	 * @param SearchRequest $request Search request.
	 * @return array{total:int, page:int, per_page:int, total_pages:int, sort:string, ids:array<int,int>, active_filters:array<int,array<string,mixed>>}
	 */
	public function search( SearchRequest $request ): array {
		$rows    = $this->candidates->candidates( $request );
		$ordered = $this->ranker->order( $request, $rows );

		$total       = count( $ordered );
		$per_page    = $request->per_page();
		$total_pages = (int) max( 1, (int) ceil( $total / $per_page ) );
		$page        = min( $request->page(), $total_pages );

		$offset   = ( $page - 1 ) * $per_page;
		$page_ids = array_slice( $ordered, $offset, $per_page );

		return array(
			'total'          => $total,
			'page'           => $page,
			'per_page'       => $per_page,
			'total_pages'    => $total_pages,
			'sort'           => $request->sort(),
			'ids'            => $page_ids,
			'active_filters' => $this->active_filters( $request ),
		);
	}

	/**
	 * Build the active-filter chip list for a request.
	 *
	 * @param SearchRequest $request Search request.
	 * @return array<int, array<string, mixed>>
	 */
	private function active_filters( SearchRequest $request ): array {
		$chips = array();

		$param_by_taxonomy = array_flip( SearchRequestFactory::filter_taxonomies() );

		foreach ( $request->taxonomy_filters() as $taxonomy => $slugs ) {
			$type = $param_by_taxonomy[ $taxonomy ] ?? $taxonomy;
			foreach ( $slugs as $slug ) {
				/*
				 * The slug reaching here has already been resolved into the
				 * current language by `SearchRequestFactory`, so this lookup
				 * hits. The fallback to the raw slug remains for a genuinely
				 * unknown term — a hand-edited URL — where showing what was
				 * asked for is more useful than showing nothing.
				 */
				$term  = get_term_by( 'slug', $slug, $taxonomy );
				$label = ( $term instanceof \WP_Term ) ? $term->name : $slug;
				$chips[] = array(
					'type'  => $type,
					'value' => $slug,
					'label' => $label,
				);
			}
		}

		foreach ( $request->doctors() as $slug ) {
			$doctor  = get_page_by_path( $slug, OBJECT, DoctorPostType::POST_TYPE );
			$chips[] = array(
				'type'  => 'doctor',
				'value' => $slug,
				'label' => ( $doctor instanceof \WP_Post ) ? $doctor->post_title : $slug,
			);
		}

		if ( '' !== $request->gender() ) {
			$chips[] = array(
				'type'  => 'gender',
				'value' => $request->gender(),
				'label' => ucfirst( $request->gender() ),
			);
		}

		if ( true === $request->online() ) {
			$chips[] = array(
				'type'  => 'online',
				'value' => '1',
				'label' => __( 'Online consultation', 'doctorpilot' ),
			);
		}

		// Both truth values are a filter the visitor chose, so both get a chip.
		if ( null !== $request->international() ) {
			$chips[] = array(
				'type'  => 'international',
				'value' => $request->international() ? '1' : '0',
				'label' => $request->international()
					? __( 'International', 'doctorpilot' )
					: __( 'National', 'doctorpilot' ),
			);
		}

		return $chips;
	}
}
