<?php
/**
 * Doctor Grid / List Elementor widget.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor\Widgets;

use BrainStudioz\DoctorPilot\Application\Doctors\DoctorView;
use BrainStudioz\DoctorPilot\Application\Search\FacetService;
use BrainStudioz\DoctorPilot\Application\Search\SearchService;
use BrainStudioz\DoctorPilot\Domain\Search\SearchRequest;
use BrainStudioz\DoctorPilot\Domain\Search\SearchRequestFactory;
use BrainStudioz\DoctorPilot\Frontend\Templates;
use BrainStudioz\DoctorPilot\Rest\RouteRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays doctors as a card grid or compact directory list, with an optional
 * search bar, mode tabs, filter row, and A–Z index.
 *
 * Querying, ranking, and card data all come from the shared search services and
 * the same card template as native output, so the widget never runs a parallel
 * query engine. Search state is read from the query string, which keeps the
 * no-JavaScript GET form working and makes filtered views shareable; the
 * bundled script progressively enhances the same markup into no-reload search
 * against the public `/doctors` REST route.
 */
final class DoctorGridList extends AbstractWidget {

	/**
	 * Script handle for the progressive-enhancement layer.
	 *
	 * @var string
	 */
	public const SCRIPT_HANDLE = 'doctorpilot-directory';

	/**
	 * Maximum pages walked when the A–Z index needs the full result set.
	 *
	 * @var int
	 */
	private const AZ_MAX_PAGES = 10;

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'doctorpilot-grid-list';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Doctor Grid / List', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-posts-grid';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_script_depends(): array {
		return array( self::SCRIPT_HANDLE );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_keywords(): array {
		return array_merge( parent::get_keywords(), array( 'search', 'filter', 'grid', 'list', 'find a doctor' ) );
	}

	/**
	 * Search modes offered by the mode tabs.
	 *
	 * @return array<string, string>
	 */
	private function mode_labels(): array {
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
	private function sort_labels(): array {
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
	private function filter_labels(): array {
		return array(
			'sort'          => __( 'Sort By', 'doctorpilot' ),
			'gender'        => __( 'Gender', 'doctorpilot' ),
			'language'      => __( 'Languages Spoken', 'doctorpilot' ),
			'department'    => __( 'Department', 'doctorpilot' ),
			'specialty'     => __( 'Specialty', 'doctorpilot' ),
			'symptom'       => __( 'Symptom', 'doctorpilot' ),
			'accepting_new' => __( 'Accepting new patients', 'doctorpilot' ),
			'online'        => __( 'Online consultation', 'doctorpilot' ),
		);
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->register_layout_section();
		$this->register_search_section();

		$this->register_heading_style();
		$this->register_search_style();
		$this->register_az_style();
		$this->add_card_style_controls();
		$this->register_list_style();
	}

	/**
	 * Layout and query controls.
	 *
	 * @return void
	 */
	private function register_layout_section(): void {
		$this->start_controls_section(
			'section_query',
			array( 'label' => __( 'Layout', 'doctorpilot' ) )
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'default' => 'grid',
				'options' => array(
					'grid' => array(
						'title' => __( 'Card grid', 'doctorpilot' ),
						'icon'  => 'eicon-gallery-grid',
					),
					'list' => array(
						'title' => __( 'Compact list', 'doctorpilot' ),
						'icon'  => 'eicon-editor-list-ul',
					),
				),
			)
		);

		$this->add_columns_control( 3, array( '{{WRAPPER}} .dp-dir__rows' ) );

		$this->add_control(
			'per_page',
			array(
				'label'       => __( 'Doctors per page', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 1,
				'max'         => SearchRequestFactory::MAX_PER_PAGE,
				'default'     => 12,
				'description' => __( 'Ignored while the A–Z index is on, which lists every match.', 'doctorpilot' ),
			)
		);

		$this->add_control(
			'sort',
			array(
				'label'       => __( 'Default sort', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 'name',
				'options'     => $this->sort_labels(),
				'description' => __( 'Used until the visitor picks a different sort.', 'doctorpilot' ),
			)
		);

		$this->add_control(
			'pagination',
			array(
				'label'   => __( 'Show pagination', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->register_heading_controls();

		$this->end_controls_section();
	}

	/**
	 * Search, mode tab, filter, and A–Z controls.
	 *
	 * @return void
	 */
	private function register_search_section(): void {
		$this->start_controls_section(
			'section_search',
			array( 'label' => __( 'Search & filters', 'doctorpilot' ) )
		);

		$this->add_control(
			'search_show',
			array(
				'label'        => __( 'Show search bar', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'search_placeholder',
			array(
				'label'     => __( 'Placeholder', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Type your Search', 'doctorpilot' ),
				'condition' => array( 'search_show' => 'yes' ),
			)
		);

		$this->add_control(
			'modes',
			array(
				'label'       => __( 'Search-mode tabs', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $this->mode_labels(),
				'default'     => array( 'name', 'department', 'symptom' ),
				'description' => __( 'Narrows what the query matches. Leave empty to hide the tabs.', 'doctorpilot' ),
				'condition'   => array( 'search_show' => 'yes' ),
			)
		);

		$this->add_control(
			'filters',
			array(
				'label'       => __( 'Filters', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $this->filter_labels(),
				'default'     => array( 'sort', 'gender', 'language' ),
				'description' => __( 'Taxonomy filters only appear when the site has matching terms.', 'doctorpilot' ),
				'separator'   => 'before',
			)
		);

		$this->add_control(
			'filters_collapsible',
			array(
				'label'        => __( 'Hide filters behind a button', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'filters_label',
			array(
				'label'     => __( 'Filters button label', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'More Filters', 'doctorpilot' ),
				'condition' => array( 'filters_collapsible' => 'yes' ),
			)
		);

		$this->add_control(
			'clear_show',
			array(
				'label'        => __( 'Show clear button', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'count_show',
			array(
				'label'        => __( 'Show result count', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'separator'    => 'before',
			)
		);

		$this->add_control(
			'az_show',
			array(
				'label'        => __( 'Show A–Z index', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'Compact list only. Lists every match, grouped by first letter.', 'doctorpilot' ),
				'condition'    => array( 'layout' => 'list' ),
			)
		);

		$this->add_control(
			'empty_text',
			array(
				'label'     => __( 'Empty state text', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'No doctors matched your search.', 'doctorpilot' ),
				'separator' => 'before',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style controls for the search bar, mode tabs, and filter row.
	 *
	 * @return void
	 */
	private function register_search_style(): void {
		$this->start_controls_section(
			'section_search_style',
			array(
				'label' => __( 'Search bar', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'bar_bg',
			array(
				'label'     => __( 'Bar background', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-dir__form' => 'background: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'bar_padding',
			array(
				'label'      => __( 'Bar padding', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .dp-dir__form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'bar_radius',
			array(
				'label'      => __( 'Bar radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .dp-dir__form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'input_heading',
			array(
				'label'     => __( 'Input', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'input_bg',
			array(
				'label'     => __( 'Background', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-dir__search' => 'background: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'input_color',
			array(
				'label'     => __( 'Text color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-dir__input' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'input_border',
				'selector' => '{{WRAPPER}} .dp-dir__search',
			)
		);

		$this->add_control(
			'modes_heading',
			array(
				'label'     => __( 'Mode tabs', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'mode_typography',
				'selector' => '{{WRAPPER}} .dp-dir__mode',
			)
		);

		$this->add_control(
			'mode_color',
			array(
				'label'     => __( 'Text color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-dir__mode' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'mode_active_color',
			array(
				'label'     => __( 'Active text color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-dir__mode-input:checked + .dp-dir__mode' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'mode_active_bg',
			array(
				'label'     => __( 'Active background', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-dir__mode-input:checked + .dp-dir__mode' => 'background: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'filters_heading',
			array(
				'label'     => __( 'Filter row', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'filter_label_color',
			array(
				'label'     => __( 'Label color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-dir__field-label' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'filter_label_typography',
				'selector' => '{{WRAPPER}} .dp-dir__field-label',
			)
		);

		$this->add_control(
			'filter_button_color',
			array(
				'label'     => __( 'Filters button text', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-dir__more' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'filter_button_bg',
			array(
				'label'     => __( 'Filters button background', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-dir__more' => 'background: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style controls for the A–Z index.
	 *
	 * @return void
	 */
	private function register_az_style(): void {
		$this->start_controls_section(
			'section_az_style',
			array(
				'label'     => __( 'A–Z index', 'doctorpilot' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array(
					'az_show' => 'yes',
					'layout'  => 'list',
				),
			)
		);

		$this->add_control(
			'az_color',
			array(
				'label'     => __( 'Letter color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-dir__az-item' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'az_border',
			array(
				'label'     => __( 'Letter border', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-dir__az-item' => 'border-color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'az_active_color',
			array(
				'label'     => __( 'Active letter color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-dir__az-item.is-active' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'az_active_bg',
			array(
				'label'     => __( 'Active letter background', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-dir__az-item.is-active' => 'background: {{VALUE}}; border-color: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style controls for the compact list rows.
	 *
	 * @return void
	 */
	private function register_list_style(): void {
		$this->start_controls_section(
			'section_list_style',
			array(
				'label'     => __( 'List row', 'doctorpilot' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'layout' => 'list' ),
			)
		);

		$this->add_control(
			'row_name_color',
			array(
				'label'     => __( 'Name color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-dir__row-name' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'row_name_typography',
				'selector' => '{{WRAPPER}} .dp-dir__row-name',
			)
		);

		$this->add_control(
			'row_meta_color',
			array(
				'label'     => __( 'Specialty color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-dir__row-meta' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'row_meta_typography',
				'selector' => '{{WRAPPER}} .dp-dir__row-meta',
			)
		);

		$this->add_responsive_control(
			'row_gap',
			array(
				'label'      => __( 'Row gap', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-dir__rows' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();

		$layout      = ( 'list' === ( $settings['layout'] ?? 'grid' ) ) ? 'list' : 'grid';
		$search_on   = 'yes' === ( $settings['search_show'] ?? '' );
		$az_on       = 'list' === $layout && 'yes' === ( $settings['az_show'] ?? '' );
		$per_page    = max( 1, min( SearchRequestFactory::MAX_PER_PAGE, (int) ( $settings['per_page'] ?? 12 ) ) );
		$modes       = $this->allowed_modes( $settings );
		$filters     = $this->allowed_filters( $settings );
		$default_sort = $this->valid_sort( (string) ( $settings['sort'] ?? 'name' ) );

		$raw = $this->request_params( $settings, $default_sort, $search_on || array() !== $filters );

		if ( $az_on ) {
			$result = $this->collect_all( $raw );
		} else {
			$request = SearchRequestFactory::from_array( $raw, $per_page );
			$result  = ( new SearchService() )->search( $request );
		}

		$request = SearchRequestFactory::from_array( $raw, $az_on ? SearchRequestFactory::MAX_PER_PAGE : $per_page );

		$view  = new DoctorView();
		$cards = array();
		foreach ( $result['ids'] as $id ) {
			$card = $view->card( (int) $id );
			if ( null !== $card ) {
				$cards[] = $card;
			}
		}

		$data = array(
			'settings'     => $settings,
			'request'      => $request,
			'result'       => $result,
			'cards'        => $cards,
			'layout'       => $layout,
			'search_on'    => $search_on,
			'az_on'        => $az_on,
			'modes'        => $modes,
			'filters'      => $filters,
			'facets'       => array() !== $filters ? ( new FacetService() )->facets() : array(),
			'sort_labels'  => $this->sort_labels(),
			'mode_labels'  => $this->mode_labels(),
			'filter_labels' => $this->filter_labels(),
			'default_sort' => $default_sort,
			'uid'          => 'dp-dir-' . (string) $this->get_id(),
			'rest_base'    => esc_url_raw( rest_url( RouteRegistry::NAMESPACE ) ),
			'per_page'     => $per_page,
			'is_editor'    => $this->is_edit_mode(),
		);

		$this->render_heading( $settings );

		include Templates::locate( 'directory.php' );
	}

	/**
	 * Build the raw search parameters: widget defaults, overridden by the query
	 * string when the widget exposes any interactive control.
	 *
	 * @param array<string, mixed> $settings      Widget settings.
	 * @param string               $default_sort  Configured default sort.
	 * @param bool                 $read_query    Whether to honour the query string.
	 * @return array<string, mixed>
	 */
	private function request_params( array $settings, string $default_sort, bool $read_query ): array {
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
			array( 'q', 'mode', 'gender', 'accepting_new', 'online', 'sort', 'page' ),
			array_keys( SearchRequestFactory::filter_taxonomies() )
		);

		foreach ( $accepted as $key ) {
			if ( ! isset( $query[ $key ] ) || '' === $query[ $key ] ) {
				continue;
			}
			$raw[ $key ] = $query[ $key ];
		}

		// An empty sort in the URL means "widget default", not "relevance".
		if ( ! isset( $raw['sort'] ) || '' === $raw['sort'] ) {
			$raw['sort'] = $default_sort;
		}

		return $raw;
	}

	/**
	 * Run the search across pages so the A–Z index can group every match.
	 *
	 * Bounded to {@see self::AZ_MAX_PAGES} pages of the maximum page size so a
	 * very large directory can never turn one render into an unbounded query
	 * loop. Truncation is reported back so the template can stay honest.
	 *
	 * @param array<string, mixed> $raw Raw search parameters.
	 * @return array{total:int, page:int, per_page:int, total_pages:int, sort:string, ids:array<int,int>, active_filters:array<int,array<string,mixed>>, truncated:bool}
	 */
	private function collect_all( array $raw ): array {
		$service  = new SearchService();
		$chunk    = SearchRequestFactory::MAX_PER_PAGE;
		$ids      = array();
		$page     = 1;
		$result   = array();

		do {
			$raw['page'] = $page;
			$result      = $service->search( SearchRequestFactory::from_array( $raw, $chunk ) );
			$ids         = array_merge( $ids, $result['ids'] );
			++$page;
		} while ( $page <= (int) $result['total_pages'] && $page <= self::AZ_MAX_PAGES );

		return array(
			'total'          => (int) $result['total'],
			'page'           => 1,
			'per_page'       => count( $ids ),
			'total_pages'    => 1,
			'sort'           => (string) $result['sort'],
			'ids'            => $ids,
			'active_filters' => $result['active_filters'],
			'truncated'      => count( $ids ) < (int) $result['total'],
		);
	}

	/**
	 * Whether Elementor is currently rendering inside the editor canvas.
	 *
	 * Guarded across Elementor versions: the editor component is optional API
	 * surface, and the widget must still render on the frontend without it.
	 *
	 * @return bool
	 */
	private function is_edit_mode(): bool {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->editor ) ) {
			return false;
		}

		$editor = \Elementor\Plugin::$instance->editor;

		return method_exists( $editor, 'is_edit_mode' ) && (bool) $editor->is_edit_mode();
	}

	/**
	 * Validate a sort value against the allowlist.
	 *
	 * @param string $sort Raw sort value.
	 * @return string
	 */
	private function valid_sort( string $sort ): string {
		return in_array( $sort, SearchRequest::SORTS, true ) ? $sort : 'name';
	}

	/**
	 * Mode tabs to render, in a stable order.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @return array<int, string>
	 */
	private function allowed_modes( array $settings ): array {
		$chosen = isset( $settings['modes'] ) && is_array( $settings['modes'] ) ? $settings['modes'] : array();
		$modes  = array();

		foreach ( array_keys( $this->mode_labels() ) as $mode ) {
			if ( in_array( $mode, $chosen, true ) ) {
				$modes[] = $mode;
			}
		}

		return $modes;
	}

	/**
	 * Filters to render, in the order the labels declare them.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @return array<int, string>
	 */
	private function allowed_filters( array $settings ): array {
		$chosen  = isset( $settings['filters'] ) && is_array( $settings['filters'] ) ? $settings['filters'] : array();
		$filters = array();

		foreach ( array_keys( $this->filter_labels() ) as $filter ) {
			if ( in_array( $filter, $chosen, true ) ) {
				$filters[] = $filter;
			}
		}

		return $filters;
	}
}
