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
use BrainStudioz\DoctorPilot\Elementor\Widgets\Concerns\CardDesignControls;
use BrainStudioz\DoctorPilot\Elementor\Widgets\Concerns\CardElementControls;
use BrainStudioz\DoctorPilot\Application\Search\SearchService;
use BrainStudioz\DoctorPilot\Domain\Search\SearchRequest;
use BrainStudioz\DoctorPilot\Domain\Search\SearchRequestFactory;
use BrainStudioz\DoctorPilot\Fields\FieldRegistry;
use BrainStudioz\DoctorPilot\Frontend\Templates;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;
use BrainStudioz\DoctorPilot\Rest\RouteRegistry;
use BrainStudioz\DoctorPilot\Support\Multilingual;
use BrainStudioz\DoctorPilot\Taxonomies\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays doctors as a card grid or compact directory list, with an optional
 * search bar, browse tabs, filter row, and A–Z index.
 *
 * A browse tab opens an A–Z index of doctor names, departments, specialties or
 * symptoms above the results. The results are never replaced by the index:
 * choosing an entry writes that index's search parameter (`doctor`,
 * `department`, `specialty`, `symptom`) and narrows the doctors listed below,
 * so browsing and filtering share one contract, one URL, and one cache key.
 *
 * Querying, ranking, and card data all come from the shared search services and
 * the same card template as native output, so the widget never runs a parallel
 * query engine. Search state is read from the query string, which keeps the
 * no-JavaScript GET form working and makes filtered views shareable; the
 * bundled script progressively enhances the same markup into no-reload search
 * against the public `/doctors` REST route.
 */
final class DoctorGridList extends AbstractWidget {

	use CardDesignControls;
	use CardElementControls;

	/**
	 * Script handle for the progressive-enhancement layer.
	 *
	 * @var string
	 */
	public const SCRIPT_HANDLE = 'doctorpilot-directory';

	/**
	 * Stylesheet handle for the premium card layer.
	 *
	 * Registered separately from the shared frontend stylesheet so it loads
	 * only on pages that actually contain this widget.
	 *
	 * @var string
	 */
	public const PREMIUM_STYLE_HANDLE = 'doctorpilot-directory-premium';

	/**
	 * Card design presets. Key => the modifier class suffix it emits.
	 *
	 * @var array<int, string>
	 */
	private const PRESETS = array( 'classic', 'modern', 'elevated', 'minimal', 'overlay', 'compact' );

	/**
	 * Maximum entries listed in one browsable index.
	 *
	 * @var int
	 */
	private const MAX_INDEX_ITEMS = 500;

	/**
	 * Taxonomy-backed index modes: mode => taxonomy key.
	 *
	 * The `name` mode is handled separately because it indexes doctor posts
	 * rather than terms.
	 *
	 * @var array<string, string>
	 */
	private const TERM_MODES = array(
		'department' => Taxonomies::DEPARTMENT,
		'specialty'  => Taxonomies::SPECIALTY,
		'symptom'    => Taxonomies::SYMPTOM,
	);

	/**
	 * Every browsable index mode => the search parameter its entries set.
	 *
	 * Picking an entry writes that parameter, so the doctor results below the
	 * index narrow through the same filter contract as every other control.
	 *
	 * @var array<string, string>
	 */
	private const INDEX_MODES = array(
		'name'       => 'doctor',
		'department' => 'department',
		'specialty'  => 'specialty',
		'symptom'    => 'symptom',
	);

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
	public function get_style_depends(): array {
		return array( self::PREMIUM_STYLE_HANDLE );
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
			'international' => __( 'National / International', 'doctorpilot' ),
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
		$this->register_card_design_controls();
		$this->register_card_elements_section( true );
		$this->register_search_section();

		$this->register_heading_style();
		$this->register_search_style();
		$this->register_az_style();
		$this->register_list_style();

		/*
		 * `AbstractWidget::add_card_style_controls()` is deliberately NOT
		 * registered here. Its three controls (`name_color`,
		 * `name_typography`, `card_gap`) are superseded by the Card box and
		 * Card text sections above, and registering both would give the widget
		 * two controls for one property — with the legacy element selectors
		 * winning on specificity, so the new controls would appear to do
		 * nothing. Values saved against the old keys are still honoured: see
		 * `legacy_card_tokens()`, which replays them as tokens.
		 */
	}

	/**
	 * Resolve the chosen card preset, defaulting for instances saved before
	 * the control existed.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @return string
	 */
	private function preset( array $settings ): string {
		$preset = isset( $settings['card_preset'] ) ? (string) $settings['card_preset'] : '';

		return in_array( $preset, self::PRESETS, true ) ? $preset : 'modern';
	}

	/**
	 * Modifier classes applied to every card in this instance.
	 *
	 * Returned as a string so both the PHP template and the JavaScript card
	 * renderer can apply the identical class list — the two must agree, or the
	 * first AJAX refresh would silently restyle the grid.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @param string               $layout   Resolved layout (grid|list).
	 * @return string
	 */
	private function card_classes( array $settings, string $layout ): string {
		$classes = array( 'dp-card' );

		if ( 'list' === $layout ) {
			$classes[] = 'dp-card--row';

			$position = isset( $settings['list_media_position'] ) ? (string) $settings['list_media_position'] : 'left';
			if ( in_array( $position, array( 'right', 'top' ), true ) ) {
				$classes[] = 'dp-card--media-' . $position;
			}
		}

		if ( ( $settings['card_linked'] ?? '' ) === 'yes' ) {
			$classes[] = 'dp-card--linked';
		}

		return implode( ' ', $classes );
	}

	/**
	 * Replay legacy card settings as inline design tokens.
	 *
	 * Before this release the card offered `name_color`, `name_typography` and
	 * `card_gap`. Those controls are no longer registered, but instances saved
	 * against them still carry the values, and dropping them would silently
	 * restyle a page the site owner had already tuned. Only the two that map
	 * cleanly onto a token are replayed — typography is a group control with no
	 * single-token equivalent, and Elementor keeps emitting its own CSS for it
	 * from the stored settings regardless.
	 *
	 * Written inline on the root rather than through the Elementor stylesheet
	 * because the control no longer exists to own a selector. A new control
	 * writes the same token at `{{WRAPPER}} .dp-dir` — the same element, but a
	 * stylesheet rule, so it loses to this inline style — deliberately: an explicit legacy value stays
	 * authoritative until the site owner sets the modern control, at which point
	 * they should clear the legacy one. Both are surfaced in the editor.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @return string Inline style attribute value, or an empty string.
	 */
	private function legacy_card_tokens( array $settings ): string {
		$tokens = array();

		$name_color = isset( $settings['name_color'] ) ? trim( (string) $settings['name_color'] ) : '';
		if ( '' !== $name_color && ! isset( $settings['cname_color'] ) ) {
			$tokens[] = '--dpc-name-color:' . $name_color;
		}

		$gap = $settings['card_gap']['size'] ?? null;
		if ( null !== $gap && '' !== (string) $gap && ! isset( $settings['card_row_gap']['size'] ) ) {
			$unit     = (string) ( $settings['card_gap']['unit'] ?? 'px' );
			$tokens[] = '--dpc-row-gap:' . (float) $gap . $unit;
			$tokens[] = '--dpc-col-gap:' . (float) $gap . $unit;
		}

		return array() === $tokens ? '' : implode( ';', $tokens ) . ';';
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
				'description' => __( 'Applies to the doctor results. A browse index is not paginated; it lists up to 500 entries.', 'doctorpilot' ),
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
			'read_query',
			array(
				'label'        => __( 'Apply filters from the page URL', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'Turn this on for a results-only grid whose filters arrive in the link — a Doctor Search Index block on another page, or any hand-built link. A grid that shows its own search bar or filters already reads them.', 'doctorpilot' ),
				'condition'    => array( 'search_show' => '' ),
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
				'description' => __( 'Each tab opens an A–Z index above the results — doctor names, departments, specialties or symptoms — and picking an entry filters the doctors shown below. Leave empty to hide the tabs.', 'doctorpilot' ),
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
				'default'      => 'yes',
				'description'  => __( 'Shows the A–Z letter bar above a tab’s index. The doctor results below always paginate.', 'doctorpilot' ),
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
				'condition' => array( 'az_show' => 'yes' ),
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
				'label' => __( 'List row', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
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

		$layout       = ( 'list' === ( $settings['layout'] ?? 'grid' ) ) ? 'list' : 'grid';
		$search_on    = 'yes' === ( $settings['search_show'] ?? '' );
		$per_page     = max( 1, min( SearchRequestFactory::MAX_PER_PAGE, (int) ( $settings['per_page'] ?? 12 ) ) );
		$modes        = $this->allowed_modes( $settings );
		$filters      = $this->allowed_filters( $settings );
		$default_sort = $this->valid_sort( (string) ( $settings['sort'] ?? 'name' ) );
		$az_show      = 'yes' === ( $settings['az_show'] ?? 'yes' );

		// A grid with its own search bar or filter panel always honours the query
		// string. Without either, it is a results-only grid: it reads the URL only
		// when the author says its filters arrive from elsewhere, so a fixed list
		// stays fixed.
		$read_query = $search_on || array() !== $filters || 'yes' === ( $settings['read_query'] ?? '' );

		$raw = $this->request_params( $settings, $default_sort, $read_query );

		// A tab opens an A–Z index above the doctor results. The results always
		// stay on screen: picking an index entry writes that index's search
		// parameter and narrows them, it does not replace them.
		//
		// Every tab browses. There is no "tabs that only scope the query" mode:
		// `mode` is not a ranking input, so such a tab would change nothing at
		// all. A site that does not want browse tabs clears the Tabs control.
		// The tabs render only with the search bar, so an index without its tab
		// would be unreachable.
		$index_modes = $search_on
			? array_values( array_intersect( $modes, array_keys( self::INDEX_MODES ) ) )
			: array();
		$active_mode = sanitize_key( self::scalar( $raw['mode'] ?? '' ) );
		$active      = in_array( $active_mode, $index_modes, true ) ? $active_mode : '';

		// Without the A–Z bar there is no control that could clear a letter, so
		// a `?letter=` in the URL must not be allowed to filter the index.
		$letter = $az_show ? $this->request_letter() : '';
		$query  = trim( wp_strip_all_tags( self::scalar( $raw['q'] ?? '' ) ) );

		$request = SearchRequestFactory::from_array( $raw, $per_page );
		$result  = ( new SearchService() )->search( $request );

		$view  = new DoctorView();
		$cards = array();
		foreach ( $result['ids'] as $id ) {
			$card = $view->card( (int) $id );
			if ( null !== $card ) {
				$cards[] = $card;
			}
		}

		$indexes  = array();
		$selected = array();
		foreach ( $index_modes as $mode ) {
			$indexes[ $mode ]  = 'name' === $mode
				? $this->name_index( $az_show, $request->sort() )
				: $this->term_index( $mode, $az_show, $request->sort() );
			$selected[ $mode ] = self::first_slug( $raw[ self::INDEX_MODES[ $mode ] ] ?? '' );
		}

		// Resolved once and passed both ways: as flags for the PHP card template
		// and, inverted, as a data attribute the script re-applies to cards it
		// builds after a filter change.
		$card_parts = $this->card_parts( $settings );

		$data = array(
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
			'sort_labels'   => $this->sort_labels(),
			'mode_labels'   => $this->mode_labels(),
			'filter_labels' => $this->filter_labels(),
			'default_sort'  => $default_sort,
			'uid'           => 'dp-dir-' . (string) $this->get_id(),
			'rest_base'     => esc_url_raw( rest_url( RouteRegistry::NAMESPACE ) ),
			// Sent with every no-reload refresh so the REST route answers in the
			// language of the page it is refreshing, whatever WPML's URL mode is.
			'lang'          => Multilingual::current_language(),
			'per_page'      => $per_page,
			'is_editor'     => $this->is_edit_mode(),
			'preset'        => $this->preset( $settings ),
			'legacy_style'  => $this->legacy_card_tokens( $settings ),
			'card_classes'  => $this->card_classes( $settings, $layout ),
			'meta_icons'    => ( $settings['meta_icons_show'] ?? 'yes' ) === 'yes',
			'card_linked'   => ( $settings['card_linked'] ?? '' ) === 'yes',
			'clamp'         => max( 0, (int) ( $settings['bio_clamp'] ?? 0 ) ),
			'parts'         => $card_parts,
			'hidden_parts'  => $this->card_hidden_parts( $card_parts ),
			'shown_parts'   => $this->card_shown_parts( $card_parts ),
			// The widget's Booking button link, resolved once for the PHP card
			// template and for the `data-booking-*` attributes the script reads
			// when it rebuilds cards after a filter change.
			'booking_link'  => $this->link_override( $settings, 'booking_button_link' ),
		);

		$this->render_heading( $settings );

		include Templates::locate( 'directory.php' );
	}

	/**
	 * Build a browsable A–Z index of one taxonomy's terms.
	 *
	 * Only published doctors contribute terms (`hide_empty`), so the index never
	 * offers an entry that would filter the results down to nothing.
	 *
	 * The full term list is always rendered; the active query and letter only
	 * mark rows hidden. That keeps one filtering mechanism for both the
	 * no-JavaScript `?q=` / `?letter=` links and instant client-side filtering,
	 * and means clearing the query client-side can restore the whole list.
	 *
	 * @param string $mode    Term mode (department/specialty/symptom).
	 * @param bool   $az_show Whether the A–Z bar is enabled.
	 * @param string $sort    Active sort value.
	 * @return array<string, mixed>
	 */
	private function term_index( string $mode, bool $az_show, string $sort ): array {
		$taxonomy = self::TERM_MODES[ $mode ] ?? '';
		$object   = '' !== $taxonomy ? get_taxonomy( $taxonomy ) : null;

		// One over the cap: the extra row is never rendered, it only distinguishes
		// "exactly at the cap" from "more than the cap", so a taxonomy with
		// precisely 500 terms is not reported as truncated.
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'name_desc' === $sort ? 'DESC' : 'ASC',
				'number'     => self::MAX_INDEX_ITEMS + 1,
			)
		);

		$truncated = ! is_wp_error( $terms ) && count( $terms ) > self::MAX_INDEX_ITEMS;
		if ( $truncated ) {
			$terms = array_slice( (array) $terms, 0, self::MAX_INDEX_ITEMS );
		}

		$items = array();

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$name    = self::decode( $term->name );
				$items[] = array(
					'name'   => $name,
					'value'  => (string) $term->slug,
					'meta'   => '',
					'letter' => self::letter_for( $name ),
				);
			}
		}

		return array(
			'type'      => 'terms',
			'param'     => self::INDEX_MODES[ $mode ] ?? $mode,
			'items'     => $items,
			'az'        => $az_show,
			'letters'   => $az_show ? self::letters_in( $items ) : array(),
			'label'     => $object instanceof \WP_Taxonomy ? (string) $object->labels->name : ucfirst( $mode ),
			'single'    => $object instanceof \WP_Taxonomy ? (string) $object->labels->singular_name : ucfirst( $mode ),
			'truncated' => $truncated,
		);
	}

	/**
	 * Build a browsable A–Z index of every published doctor's name.
	 *
	 * This is the catalogue the "By Doctor Name" tab browses, so it deliberately
	 * ignores the active filters: it lists who exists, and picking a name is
	 * what filters the results below. Ordering is done in PHP against the same
	 * honorific-stripped name the A–Z bar groups by, so "Dr. Ali Khalil" files
	 * under K here exactly as it does there — a meta ordered query could not,
	 * and would silently drop any doctor whose derived index is not yet built.
	 *
	 * @param bool   $az_show Whether the A–Z bar is enabled.
	 * @param string $sort    Active sort value.
	 * @return array<string, mixed>
	 */
	private function name_index( bool $az_show, string $sort ): array {
		$query = new \WP_Query(
			array(
				'post_type'           => DoctorPostType::POST_TYPE,
				'post_status'         => 'publish',
				'posts_per_page'      => self::MAX_INDEX_ITEMS,
				'orderby'             => 'title',
				'order'               => 'ASC',
				'ignore_sticky_posts' => true,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			if ( ! ( $post instanceof \WP_Post ) ) {
				continue;
			}

			$name    = self::decode( (string) $post->post_title );
			$items[] = array(
				'name'   => $name,
				'value'  => (string) $post->post_name,
				'meta'   => $this->index_subtitle( (int) $post->ID ),
				'letter' => self::letter_for( $name ),
				'sort'   => self::sortable_name( $name ),
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
			'param'     => self::INDEX_MODES['name'],
			'items'     => $items,
			'az'        => $az_show,
			'letters'   => $az_show ? self::letters_in( $items ) : array(),
			'label'     => __( 'Doctors', 'doctorpilot' ),
			'single'    => __( 'Doctor', 'doctorpilot' ),
			'truncated' => (int) $query->found_posts > count( $items ),
		);
	}

	/**
	 * Sub-label shown under a doctor's name in the index.
	 *
	 * Prefers the same primary specialty/department the card shows, then falls
	 * back to the first assigned term so a doctor whose primary is unset still
	 * gets a useful label. Terms come from the cache WP_Query already primed,
	 * so this costs no extra query per row.
	 *
	 * @param int $post_id Doctor post ID.
	 * @return string
	 */
	private function index_subtitle( int $post_id ): string {
		$candidates = array(
			array( FieldRegistry::META_PREFIX . 'primary_specialty_id', Taxonomies::SPECIALTY ),
			array( FieldRegistry::META_PREFIX . 'primary_department_id', Taxonomies::DEPARTMENT ),
		);

		foreach ( $candidates as list( $meta_key, $taxonomy ) ) {
			$term_id = (int) get_post_meta( $post_id, $meta_key, true );
			if ( $term_id <= 0 ) {
				continue;
			}
			// Same stored-term-ID problem as `DoctorView::primary_term()`.
			$term = get_term( Multilingual::term_id( $term_id, $taxonomy ), $taxonomy );
			if ( $term instanceof \WP_Term ) {
				return self::decode( $term->name );
			}
		}

		foreach ( array( Taxonomies::SPECIALTY, Taxonomies::DEPARTMENT ) as $taxonomy ) {
			$terms = get_the_terms( $post_id, $taxonomy );
			if ( is_array( $terms ) && isset( $terms[0] ) && $terms[0] instanceof \WP_Term ) {
				return self::decode( $terms[0]->name );
			}
		}

		return '';
	}

	/**
	 * Comparable form of a display name: honorific stripped, case-folded.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	private static function sortable_name( string $name ): string {
		$clean = (string) preg_replace( '/^\s*(dr|prof|mr|mrs|ms)\.?\s+/i', '', trim( $name ) );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $clean ) : strtolower( $clean );
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
	 * `$_GET` values are attacker-controlled shapes: `?mode[]=name` hands the
	 * widget an array where it expects a string, and casting that directly
	 * raises a PHP warning. Taking the first scalar keeps the request usable
	 * without ever letting an array reach a string context.
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
	private static function letter_for( string $name ): string {
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
	 * Collect the distinct index letters present in a list of items.
	 *
	 * @param array<int, array<string, mixed>> $items Items carrying a letter.
	 * @return array<int, string>
	 */
	private static function letters_in( array $items ): array {
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
	 * Decode stored entities so a term name renders as authored ("A &amp; B" → "A & B")
	 * before it is escaped again for output.
	 *
	 * @param string $value Raw term name.
	 * @return string
	 */
	private static function decode( string $value ): string {
		return html_entity_decode( $value, ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' );
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
			array( 'q', 'mode', 'doctor', 'gender', 'online', 'international', 'sort', 'page' ),
			array_keys( SearchRequestFactory::filter_taxonomies() )
		);

		// Flattened to strings here rather than passed through: `?q[]=a` would
		// otherwise reach the request factory's string casts as an array.
		// Multi-value filters stay expressible as comma-separated slugs.
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
