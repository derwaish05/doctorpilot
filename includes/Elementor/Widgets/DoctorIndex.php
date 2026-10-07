<?php
/**
 * Doctor Search Index Elementor widget.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor\Widgets;

use BrainStudioz\DoctorPilot\Admin\Settings;
use BrainStudioz\DoctorPilot\Application\Search\FacetService;
use BrainStudioz\DoctorPilot\Elementor\Widgets\Concerns\BrowseIndex;
use BrainStudioz\DoctorPilot\Frontend\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A placeable "Find a Doctor" block: search box, mode tabs, an A–Z row and the
 * browsable lists — and no results grid.
 *
 * The grid widget answers "who matches this filter" on the search page itself.
 * This one answers "what can I search for" from anywhere on the site: every
 * entry is a link out. A department, specialty or symptom goes to the search
 * page with that filter applied; a doctor goes to their profile.
 */
final class DoctorIndex extends AbstractWidget {

	use BrowseIndex;

	/**
	 * Script handle for the index behaviour.
	 *
	 * @var string
	 */
	public const SCRIPT_HANDLE = 'doctorpilot-doctor-index';

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'doctorpilot-doctor-index';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Doctor Search Index', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-search-results';
	}

	/**
	 * Script dependencies.
	 *
	 * @return array<int, string>
	 */
	public function get_script_depends(): array {
		return array( self::SCRIPT_HANDLE );
	}

	/**
	 * Modes this widget can browse.
	 *
	 * @return array<string, string>
	 */
	private function mode_labels(): array {
		return array(
			'name'       => __( 'By Doctor Name', 'doctorpilot' ),
			'department' => __( 'By Department', 'doctorpilot' ),
			'specialty'  => __( 'By Specialty', 'doctorpilot' ),
			'symptom'    => __( 'By Symptom', 'doctorpilot' ),
		);
	}

	// -------------------------------------------------------------------------
	// Controls.
	// -------------------------------------------------------------------------

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section( 'section_content', array( 'label' => __( 'Content', 'doctorpilot' ) ) );

		$this->add_control(
			'modes',
			array(
				'label'       => __( 'Browse by', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $this->mode_labels(),
				'default'     => array( 'name', 'department', 'symptom' ),
				'description' => __( 'One tab per entry. The first is the tab that opens.', 'doctorpilot' ),
			)
		);

		$this->add_control(
			'search_show',
			array(
				'label'        => __( 'Show search box', 'doctorpilot' ),
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
			'suggest_show',
			array(
				'label'        => __( 'Suggestions while typing', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => __( 'Shows matching doctors, departments and symptoms under the box.', 'doctorpilot' ),
				'condition'    => array( 'search_show' => 'yes' ),
			)
		);

		$this->add_control(
			'az_show',
			array(
				'label'        => __( 'Show A–Z bar', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'count_show',
			array(
				'label'        => __( 'Show entry count', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'search_page',
			array(
				'label'       => __( 'Search page', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'options'     => array( 'url' ),
				'description' => __( 'Where a department, specialty or symptom leads. Leave blank to use the Search page set in DoctorPilot → Settings.', 'doctorpilot' ),
			)
		);

		// Only worth saying when the site really has no target: with the setting
		// filled in, a blank field here is the normal case, not a problem.
		if ( '' === Settings::search_page_url() ) {
			$this->add_control(
				'search_page_notice',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'No Search page is set in DoctorPilot → Settings. Fill one in above, or departments and symptoms will link back to this page and the search box will have nowhere to submit to.', 'doctorpilot' ),
					'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
				)
			);
		}

		$this->add_control(
			'filters',
			array(
				'label'       => __( 'Filters', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $this->filter_labels(),
				'default'     => array( 'sort', 'gender', 'language' ),
				'description' => __( 'Sort By reorders the list. Gender and Languages Spoken narrow the By Doctor Name list, and travel with a department, specialty or symptom link so the search page opens already filtered. Leave empty to hide the row.', 'doctorpilot' ),
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
				'condition'    => array( 'filters!' => array() ),
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
			'empty_text',
			array(
				'label'     => __( 'No-match text', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Nothing matched your search.', 'doctorpilot' ),
				'separator' => 'before',
			)
		);

		$this->add_columns_control( 4, array( '{{WRAPPER}} .dp-index .dp-dir__rows' ) );

		$this->register_heading_controls();

		$this->end_controls_section();

		$this->register_heading_style();
		$this->register_bar_style();
		$this->register_az_style();
		$this->register_rows_style();
	}

	/**
	 * Bar, input and tab styling.
	 *
	 * Every control writes one of the block's custom properties rather than a
	 * property on an element, so one value cascades to every part that uses it
	 * and the stylesheet keeps its own fallbacks.
	 *
	 * @return void
	 */
	private function register_bar_style(): void {
		$root = '{{WRAPPER}} .dp-index';

		$this->start_controls_section(
			'section_bar_style',
			array( 'label' => __( 'Search bar', 'doctorpilot' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE )
		);

		$this->add_control(
			'bar_bg',
			array(
				'label'     => __( 'Bar background', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $root => '--dpi-bar-bg: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'bar_padding',
			array(
				'label'      => __( 'Bar padding', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( $root => '--dpi-bar-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'bar_radius',
			array(
				'label'      => __( 'Bar radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 999 ) ),
				'selectors'  => array( $root => '--dpi-bar-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control( 'input_heading', array( 'label' => __( 'Input', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_control(
			'input_bg',
			array(
				'label'     => __( 'Background', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $root => '--dpi-input-bg: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'input_color',
			array(
				'label'     => __( 'Text color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#000000',
				'selectors' => array( $root => '--dpi-input-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'input_border_color',
			array(
				'label'     => __( 'Border color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $root => '--dpi-input-border: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'input_radius',
			array(
				'label'      => __( 'Radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 999 ) ),
				'selectors'  => array( $root => '--dpi-input-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'input_width',
			array(
				'label'      => __( 'Maximum width', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array( 'px' => array( 'min' => 160, 'max' => 900 ) ),
				'selectors'  => array( $root => '--dpi-input-max-width: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control( 'modes_heading', array( 'label' => __( 'Tabs', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'mode_typography', 'selector' => '{{WRAPPER}} .dp-index .dp-dir__mode' )
		);
		$this->add_control(
			'mode_color',
			array(
				'label'     => __( 'Text color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $root => '--dpi-mode-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'mode_active_color',
			array(
				'label'     => __( 'Active text color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $root => '--dpi-mode-active-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'mode_active_bg',
			array(
				'label'     => __( 'Active background', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $root => '--dpi-mode-active-bg: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * A–Z bar styling.
	 *
	 * @return void
	 */
	private function register_az_style(): void {
		$root = '{{WRAPPER}} .dp-index';

		$this->start_controls_section(
			'section_az_style',
			array(
				'label'     => __( 'A–Z bar', 'doctorpilot' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'az_show' => 'yes' ),
			)
		);

		$this->add_control(
			'az_color',
			array(
				'label'     => __( 'Letter color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $root => '--dpi-az-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'az_border',
			array(
				'label'     => __( 'Letter border', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $root => '--dpi-az-border: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'az_active_color',
			array(
				'label'     => __( 'Active letter color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $root => '--dpi-az-active-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'az_active_bg',
			array(
				'label'     => __( 'Active letter background', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $root => '--dpi-az-active-bg: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'az_radius',
			array(
				'label'      => __( 'Radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( $root => '--dpi-az-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Entry list styling.
	 *
	 * @return void
	 */
	private function register_rows_style(): void {
		$root = '{{WRAPPER}} .dp-index';

		$this->start_controls_section(
			'section_rows_style',
			array( 'label' => __( 'Entries', 'doctorpilot' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE )
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'row_typography', 'selector' => '{{WRAPPER}} .dp-index .dp-dir__row-name' )
		);
		$this->add_control(
			'row_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#000000',
				'selectors' => array( $root => '--dpi-row-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'row_hover_color',
			array(
				'label'     => __( 'Hover color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $root => '--dpi-row-hover-color: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'rows_column_gap',
			array(
				'label'      => __( 'Column gap', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 120 ) ),
				'selectors'  => array( $root => '--dpi-rows-column-gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'meta_show',
			array(
				'label'        => __( 'Show specialty under a doctor', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->end_controls_section();
	}

	// -------------------------------------------------------------------------
	// Render.
	// -------------------------------------------------------------------------

	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();

		$chosen = (array) ( $settings['modes'] ?? array() );
		$modes  = array_values( array_filter( $chosen, function ( $mode ): bool {
			return array_key_exists( (string) $mode, $this->mode_labels() );
		} ) );

		if ( array() === $modes ) {
			if ( $this->is_editing() ) {
				echo '<p>' . esc_html__( 'Pick at least one thing to browse by.', 'doctorpilot' ) . '</p>';
			}
			return;
		}

		$az_show  = 'yes' === ( $settings['az_show'] ?? '' );
		$subtitle = 'yes' === ( $settings['meta_show'] ?? '' );

		// Gender and Languages narrow the doctor-name list in the browser, so
		// each name has to carry its own values. Only fetch them when a filter
		// that uses them is actually on: the extra data is not free.
		$filters     = $this->allowed_filters( $settings );
		$facets      = ( new FacetService() )->facets();
		$filters     = $this->usable_filters( $filters, $facets );
		$name_facets = array() !== array_intersect( $filters, array( 'gender', 'language' ) );
		$sort        = $this->request_sort( $filters );

		$gender   = $this->request_choice( 'gender', array( 'female', 'male', 'other' ), $filters );
		$language = $this->request_choice(
			'language',
			array_map(
				static function ( array $option ): string {
					return (string) ( $option['slug'] ?? '' );
				},
				(array) ( $facets['language'] ?? array() )
			),
			$filters
		);

		// One base for both halves: the configured Search page, or this page when
		// there is none. The script must not disagree with the rendered links.
		$search_page = $this->search_page_url( $settings );
		$base        = '' !== $search_page ? $search_page : $this->current_url();

		$indexes = array();

		foreach ( $modes as $mode ) {
			$mode  = (string) $mode;
			$index = 'name' === $mode
				? $this->browse_name_index( $az_show, $subtitle, $sort, $name_facets )
				: $this->browse_term_index( $mode, $az_show, $sort );

			// A term entry is a filter on the search page; a doctor entry is a
			// profile, and already carries its own URL.
			if ( 'terms' === $index['type'] ) {
				$index['items'] = $this->link_terms( (array) $index['items'], (string) $index['param'], $base );
			} else {
				$index['items'] = $this->narrow_names( (array) $index['items'], $gender, $language );
			}

			// An entry with no name or no destination cannot render, so it must
			// not reach the template — otherwise the count and the truncation
			// notice describe rows nobody can see.
			$index['items'] = array_values(
				array_filter(
					(array) $index['items'],
					static function ( $item ): bool {
						return is_array( $item )
							&& '' !== trim( (string) ( $item['name'] ?? '' ) )
							&& '' !== trim( (string) ( $item['url'] ?? '' ) );
					}
				)
			);

			// A mode with nothing in it would render a tab over a row of dead
			// letters, so it is dropped entirely.
			if ( array() === $index['items'] ) {
				continue;
			}

			$index['letters'] = $az_show ? self::browse_letters_in( $index['items'] ) : array();
			$index['tab']     = (string) ( $this->mode_labels()[ $mode ] ?? $mode );
			$indexes[ $mode ] = $index;
		}

		// Nothing published to browse: leave no heading behind.
		if ( array() === $indexes ) {
			if ( $this->is_editing() ) {
				echo '<p>' . esc_html__( 'No published doctors or terms to list yet.', 'doctorpilot' ) . '</p>';
			}
			return;
		}

		$this->render_heading( $settings );

		$data = array(
			'uid'         => 'dpi-' . (string) $this->get_id(),
			'indexes'     => $indexes,
			// Nothing open on load: the block presents a search bar, and the
			// browse lists are what you get after asking for one. Opening an
			// index by default put a wall of names in front of a visitor who
			// arrived intending to type.
			'active'      => '',
			'letters'     => self::browse_letters(),
			'columns'     => max( 1, (int) ( $settings['columns'] ?? 4 ) ),
			'search'      => 'yes' === ( $settings['search_show'] ?? '' ),
			'suggest'     => 'yes' === ( $settings['search_show'] ?? '' ) && 'yes' === ( $settings['suggest_show'] ?? '' ),
			'placeholder' => (string) ( $settings['search_placeholder'] ?? '' ),
			'az'          => $az_show,
			'count'       => 'yes' === ( $settings['count_show'] ?? '' ),
			'meta'        => 'yes' === ( $settings['meta_show'] ?? '' ),
			'empty_text'  => (string) ( $settings['empty_text'] ?? '' ),
			'search_page' => $base,
			'filters'     => $filters,
			'facets'      => $facets,
			'sort'        => $sort,
			'gender'      => $gender,
			'language'    => $language,
			'collapsible' => 'yes' === ( $settings['filters_collapsible'] ?? 'yes' ) && array() !== $filters,
			'filters_label' => (string) ( $settings['filters_label'] ?? __( 'More Filters', 'doctorpilot' ) ),
			'filter_labels' => $this->filter_labels(),
			'sort_labels'   => $this->sort_labels(),
		);

		// The template reads $data from this scope, as every other one does.
		include Templates::locate( 'doctor-index.php' );
	}

	/**
	 * Whether Elementor is rendering this for an author rather than a visitor.
	 *
	 * The preview runs in an iframe that is not "edit mode", so both checks are
	 * needed for an editor-only hint to actually appear.
	 *
	 * @return bool
	 */
	private function is_editing(): bool {
		$plugin = \Elementor\Plugin::$instance ?? null;

		if ( ! is_object( $plugin ) || ! isset( $plugin->editor ) ) {
			return false;
		}

		return $plugin->editor->is_edit_mode() || ( isset( $plugin->preview ) && $plugin->preview->is_preview_mode() );
	}

	/**
	 * The page a filter entry leads to: the widget's own override, else the
	 * site-wide Search page, else '' — the caller then falls back to this page.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @return string
	 */
	private function search_page_url( array $settings ): string {
		$own = is_array( $settings['search_page'] ?? null ) ? trim( (string) ( $settings['search_page']['url'] ?? '' ) ) : '';

		return '' !== $own ? $own : Settings::search_page_url();
	}

	/**
	 * Give every term entry the URL that applies it on the search page.
	 *
	 * The parameter names come from the same contract
	 * `SearchRequestFactory::from_array()` reads, so the landing page filters
	 * exactly as if the visitor had picked the term there.
	 *
	 * The link carries the term and nothing else. Gender and Languages are not
	 * appended: the filter row is unavailable on a term tab, so those values
	 * cannot be seen or changed from here, and smuggling them into the link
	 * would apply a filter the visitor has no control over at the moment they
	 * click.
	 *
	 * @param array<int, array<string, mixed>> $items  Term entries.
	 * @param string                           $param  Search parameter name.
	 * @param string                           $target Search page URL, or ''.
	 * @return array<int, array<string, mixed>>
	 */
	private function link_terms( array $items, string $param, string $target ): array {
		$base = '' !== $target ? $target : $this->current_url();

		foreach ( $items as $i => $item ) {
			$slug = (string) ( $item['value'] ?? '' );
			if ( '' === $slug ) {
				continue;
			}

			$items[ $i ]['url'] = (string) add_query_arg( array( $param => $slug ), $base );
		}

		return $items;
	}

	/**
	 * A validated single-choice value from the query string.
	 *
	 * Returns '' when the filter is not offered, so a stale parameter in a
	 * bookmarked URL cannot narrow a list through a control that is not there.
	 *
	 * @param string             $param   Parameter name.
	 * @param array<int, string> $allowed Accepted values.
	 * @param array<int, string> $filters Filters this block offers.
	 * @return string
	 */
	private function request_choice( string $param, array $allowed, array $filters ): string {
		if ( ! in_array( $param, $filters, true ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only GET state.
		$raw = isset( $_GET[ $param ] ) ? sanitize_title( (string) wp_unslash( $_GET[ $param ] ) ) : '';

		return in_array( $raw, $allowed, true ) ? $raw : '';
	}

	/**
	 * Narrow the doctor-name entries by the active Gender and Languages.
	 *
	 * Done on the server as well as in the browser, so the block still filters
	 * with JavaScript off. Both sides read the same values off the same entries,
	 * so they cannot disagree about what matches.
	 *
	 * @param array<int, array<string, mixed>> $items    Name entries.
	 * @param string                           $gender   Active gender, or ''.
	 * @param string                           $language Active language slug, or ''.
	 * @return array<int, array<string, mixed>>
	 */
	private function narrow_names( array $items, string $gender, string $language ): array {
		if ( '' === $gender && '' === $language ) {
			return $items;
		}

		return array_values(
			array_filter(
				$items,
				static function ( array $item ) use ( $gender, $language ): bool {
					if ( '' !== $gender && (string) ( $item['gender'] ?? '' ) !== $gender ) {
						return false;
					}

					return '' === $language
						|| in_array( $language, array_map( 'strval', (array) ( $item['langs'] ?? array() ) ), true );
				}
			)
		);
	}

	/**
	 * The current page's own URL, without its query string.
	 *
	 * @return string
	 */
	private function current_url(): string {
		// Only a singular view has a permalink. On an archive
		// `get_queried_object_id()` is a term or user ID, and `get_permalink()`
		// would happily resolve it as the post that happens to share that ID.
		$object = get_queried_object();

		if ( $object instanceof \WP_Post ) {
			$link = get_permalink( $object );
			if ( is_string( $link ) && '' !== $link ) {
				return $link;
			}
		}

		return (string) home_url( '/' );
	}

	/**
	 * Filters this block offers, in the order the labels declare them.
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

	/**
	 * Drop filters that would render as an empty control.
	 *
	 * Languages is the case that matters: a site with no language terms would
	 * otherwise get a select whose only option is "All", which looks broken and
	 * can never change the list. Sort and Gender are always usable because
	 * their options are fixed.
	 *
	 * @param array<int, string>   $filters Chosen filters.
	 * @param array<string, mixed> $facets  Facet options keyed by filter.
	 * @return array<int, string>
	 */
	private function usable_filters( array $filters, array $facets ): array {
		return array_values(
			array_filter(
				$filters,
				static function ( string $filter ) use ( $facets ): bool {
					if ( in_array( $filter, array( 'sort', 'gender' ), true ) ) {
						return true;
					}

					return isset( $facets[ $filter ] ) && array() !== (array) $facets[ $filter ];
				}
			)
		);
	}

	/**
	 * The active sort, read from the query string.
	 *
	 * Only `sort` is read server-side. Gender and Languages are applied in the
	 * browser and travel onward in a term link, so reading them here would
	 * duplicate that work; sort has to be honoured on the server because it
	 * decides the order rows are printed in, which no amount of hiding can fix.
	 *
	 * @param array<int, string> $filters Filters this block offers.
	 * @return string 'name' or 'name_desc'.
	 */
	private function request_sort( array $filters ): string {
		if ( ! in_array( 'sort', $filters, true ) ) {
			return 'name';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only GET state.
		$raw = isset( $_GET['sort'] ) ? sanitize_key( (string) wp_unslash( $_GET['sort'] ) ) : '';

		return 'name_desc' === $raw ? 'name_desc' : 'name';
	}

	/**
	 * Sort options. Only the two the index can honour: it lists a catalogue, so
	 * relevance, featured and experience have nothing to order it by.
	 *
	 * @return array<string, string>
	 */
	private function sort_labels(): array {
		return array(
			'name'      => __( 'A → Z', 'doctorpilot' ),
			'name_desc' => __( 'Z → A', 'doctorpilot' ),
		);
	}

	/**
	 * Filter labels, in render order.
	 *
	 * @return array<string, string>
	 */
	private function filter_labels(): array {
		return array(
			'sort'     => __( 'Sort By', 'doctorpilot' ),
			'gender'   => __( 'Gender', 'doctorpilot' ),
			'language' => __( 'Languages Spoken', 'doctorpilot' ),
		);
	}
}
