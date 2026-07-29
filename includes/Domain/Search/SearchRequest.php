<?php
/**
 * Immutable, normalized search request.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Domain\Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A validated, bounded search request.
 *
 * Instances are only created through {@see SearchRequestFactory} so every value
 * is normalized once and can be relied upon downstream without re-checking.
 */
final class SearchRequest {

	/**
	 * Allowed search modes.
	 *
	 * @var array<int, string>
	 */
	public const MODES = array( 'universal', 'name', 'department', 'specialty', 'symptom' );

	/**
	 * Allowed sort values (Free).
	 *
	 * @var array<int, string>
	 */
	public const SORTS = array( 'relevance', 'name', 'featured', 'experience', 'recent' );

	/**
	 * Allowed view values.
	 *
	 * @var array<int, string>
	 */
	public const VIEWS = array( 'grid', 'list' );

	/**
	 * Raw query string (already trimmed, length-bounded).
	 *
	 * @var string
	 */
	private string $query;

	/**
	 * Search mode.
	 *
	 * @var string
	 */
	private string $mode;

	/**
	 * Taxonomy filters: taxonomy key => list of slugs.
	 *
	 * @var array<string, array<int, string>>
	 */
	private array $taxonomy_filters;

	/**
	 * Gender filter, or empty string.
	 *
	 * @var string
	 */
	private string $gender;

	/**
	 * Accepting-new-patients filter: true, false, or null (any).
	 *
	 * @var bool|null
	 */
	private ?bool $accepting_new;

	/**
	 * Online-consultation filter: true, false, or null (any).
	 *
	 * @var bool|null
	 */
	private ?bool $online;

	/**
	 * Sort value.
	 *
	 * @var string
	 */
	private string $sort;

	/**
	 * View value.
	 *
	 * @var string
	 */
	private string $view;

	/**
	 * 1-based page.
	 *
	 * @var int
	 */
	private int $page;

	/**
	 * Results per page.
	 *
	 * @var int
	 */
	private int $per_page;

	/**
	 * Constructor.
	 *
	 * @param string                              $query            Query string.
	 * @param string                              $mode             Search mode.
	 * @param array<string, array<int, string>>   $taxonomy_filters Taxonomy filters.
	 * @param string                              $gender           Gender filter.
	 * @param bool|null                           $accepting_new    Accepting-new filter.
	 * @param bool|null                           $online           Online filter.
	 * @param string                              $sort             Sort.
	 * @param string                              $view             View.
	 * @param int                                 $page             Page.
	 * @param int                                 $per_page         Per page.
	 */
	public function __construct(
		string $query,
		string $mode,
		array $taxonomy_filters,
		string $gender,
		?bool $accepting_new,
		?bool $online,
		string $sort,
		string $view,
		int $page,
		int $per_page
	) {
		$this->query            = $query;
		$this->mode             = $mode;
		$this->taxonomy_filters = $taxonomy_filters;
		$this->gender           = $gender;
		$this->accepting_new    = $accepting_new;
		$this->online           = $online;
		$this->sort             = $sort;
		$this->view             = $view;
		$this->page             = $page;
		$this->per_page         = $per_page;
	}

	/**
	 * Query string.
	 *
	 * @return string
	 */
	public function query(): string {
		return $this->query;
	}

	/**
	 * Whether the request carries a non-empty query.
	 *
	 * @return bool
	 */
	public function has_query(): bool {
		return '' !== $this->query;
	}

	/**
	 * Search mode.
	 *
	 * @return string
	 */
	public function mode(): string {
		return $this->mode;
	}

	/**
	 * Taxonomy filters.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function taxonomy_filters(): array {
		return $this->taxonomy_filters;
	}

	/**
	 * Gender filter.
	 *
	 * @return string
	 */
	public function gender(): string {
		return $this->gender;
	}

	/**
	 * Accepting-new filter.
	 *
	 * @return bool|null
	 */
	public function accepting_new(): ?bool {
		return $this->accepting_new;
	}

	/**
	 * Online filter.
	 *
	 * @return bool|null
	 */
	public function online(): ?bool {
		return $this->online;
	}

	/**
	 * Sort.
	 *
	 * @return string
	 */
	public function sort(): string {
		return $this->sort;
	}

	/**
	 * View.
	 *
	 * @return string
	 */
	public function view(): string {
		return $this->view;
	}

	/**
	 * Page.
	 *
	 * @return int
	 */
	public function page(): int {
		return $this->page;
	}

	/**
	 * Per page.
	 *
	 * @return int
	 */
	public function per_page(): int {
		return $this->per_page;
	}

	/**
	 * A stable, canonical signature used for caching and URL comparison.
	 *
	 * @return string
	 */
	public function signature(): string {
		$data = array(
			'q'    => $this->query,
			'mode' => $this->mode,
			'tax'  => $this->taxonomy_filters,
			'g'    => $this->gender,
			'an'   => $this->accepting_new,
			'on'   => $this->online,
			's'    => $this->sort,
			'pg'   => $this->page,
			'pp'   => $this->per_page,
		);

		return md5( (string) wp_json_encode( $data ) );
	}
}
