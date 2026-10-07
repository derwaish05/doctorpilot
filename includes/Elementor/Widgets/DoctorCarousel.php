<?php
/**
 * Doctor Carousel / Grid Elementor widget.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor\Widgets;

use BrainStudioz\DoctorPilot\Application\Doctors\DoctorView;
use BrainStudioz\DoctorPilot\Application\Search\SearchService;
use BrainStudioz\DoctorPilot\Domain\Search\SearchRequestFactory;
use BrainStudioz\DoctorPilot\Elementor\Integration;
use BrainStudioz\DoctorPilot\Elementor\Widgets\Concerns\CardDesignControls;
use BrainStudioz\DoctorPilot\Elementor\Widgets\Concerns\CardElementControls;
use BrainStudioz\DoctorPilot\Frontend\Templates;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows a curated set of doctors as a carousel or a static grid.
 *
 * This is the Doctor Grid / List card with the directory apparatus removed.
 * There is no search bar, no filter row, no A–Z index and no pagination: the
 * editor decides which doctors appear by picking terms, and a visitor either
 * scrolls the carousel or reads the grid. That is the whole difference — the
 * card, the six design presets, every style control and the per-element
 * show/hide switches are the same code, reached through the same two traits, so
 * a site can pair a full directory page with a curated strip on the home page
 * and the two match without being styled twice.
 *
 * Selection runs through `SearchRequestFactory` and `SearchService` exactly like
 * the directory. Nothing here queries posts directly, so ranking, the sort
 * options, the request cache and the card payload are shared rather than
 * reimplemented, and any taxonomy an add-on registers through
 * `doctorpilot/search/filter_taxonomies` gains a control here for free — which
 * is how Condition, Treatment, Location and Insurance appear when Pro is active
 * and stay absent, rather than broken, when it is not.
 *
 * The carousel is built on CSS scroll snapping rather than a slider library.
 * The track is a scroll container, so with no JavaScript at all it is still a
 * horizontally scrollable, keyboard-reachable list of cards; the script adds
 * the arrows, dots, autoplay and page maths on top of behaviour the browser
 * already provides. Nothing is loaded from a CDN and no bundled third-party
 * asset handle is depended on.
 */
final class DoctorCarousel extends AbstractWidget {

	use CardDesignControls;
	use CardElementControls;

	/**
	 * Script handle for the carousel behaviour.
	 *
	 * @var string
	 */
	public const SCRIPT_HANDLE = 'doctorpilot-carousel';

	/**
	 * Stylesheet handle for the carousel layer.
	 *
	 * @var string
	 */
	public const STYLE_HANDLE = 'doctorpilot-carousel';

	/**
	 * Card design presets this widget accepts, mirroring Doctor Grid / List.
	 *
	 * @var array<int, string>
	 */
	private const PRESETS = array( 'classic', 'modern', 'elevated', 'minimal', 'overlay', 'compact' );

	/**
	 * Hard ceiling on doctors pulled into one widget.
	 *
	 * Matches `SearchRequestFactory::MAX_PER_PAGE`, which the request is capped
	 * to anyway — stated here so the control's own maximum cannot drift from the
	 * value that actually applies.
	 *
	 * @var int
	 */
	private const MAX_ITEMS = 48;

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'doctorpilot-doctor-carousel';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Doctor Carousel / Grid', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-media-carousel';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_keywords(): array {
		return array_merge(
			parent::get_keywords(),
			array( 'carousel', 'slider', 'grid', 'team', 'featured', 'specialists' )
		);
	}

	/**
	 * Style dependencies.
	 *
	 * @return array<int, string>
	 */
	public function get_style_depends(): array {
		return array(
			Integration::STYLE_HANDLE,
			DoctorGridList::PREMIUM_STYLE_HANDLE,
			self::STYLE_HANDLE,
		);
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
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->register_layout_section();
		$this->register_source_section();
		$this->register_carousel_section();
		$this->register_card_design_controls();
		$this->register_card_elements_section();

		$this->register_heading_style();
		$this->register_carousel_style();
	}

	/* ---------------------------------------------------------------------
	 * Controls
	 * ------------------------------------------------------------------ */

	/**
	 * Display mode, columns, count and order.
	 *
	 * @return void
	 */
	private function register_layout_section(): void {
		$this->start_controls_section(
			'section_layout',
			array( 'label' => __( 'Layout', 'doctorpilot' ) )
		);

		$this->add_control(
			'display',
			array(
				'label'   => __( 'Display as', 'doctorpilot' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'carousel',
				// A CHOOSE control is deselectable by default. An empty value
				// would still render a carousel while hiding both Carousel
				// sections from the panel, leaving no way to configure what is
				// on screen.
				'toggle'  => false,
				'options' => array(
					'carousel' => array(
						'title' => __( 'Carousel', 'doctorpilot' ),
						'icon'  => 'eicon-slider-push',
					),
					'grid'     => array(
						'title' => __( 'Grid', 'doctorpilot' ),
						'icon'  => 'eicon-gallery-grid',
					),
				),
			)
		);

		/*
		 * One control for both modes. A grid reads it as a column count; a
		 * carousel reads it as slides in view, through a token the stylesheet
		 * divides the track by. Registering two controls would let a site tune
		 * the grid, flip to carousel, and find its layout unaccountably
		 * different.
		 */
		$this->add_responsive_control(
			'columns',
			array(
				'label'          => __( 'Columns / slides in view', 'doctorpilot' ),
				'type'           => Controls_Manager::SELECT,
				'default'        => '3',
				'tablet_default' => '2',
				'mobile_default' => '1',
				'options'        => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				),
				'selectors'      => array(
					'{{WRAPPER}} .dp-directory' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
					'{{WRAPPER}} .dp-carousel'  => '--dpcar-slides: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'limit',
			array(
				'label'       => __( 'How many doctors', 'doctorpilot' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 1,
				'max'         => self::MAX_ITEMS,
				'default'     => 12,
				'description' => __( 'The most that can be shown in one widget.', 'doctorpilot' ),
			)
		);

		$this->add_control(
			'sort',
			array(
				'label'   => __( 'Order by', 'doctorpilot' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'name',
				'options' => array(
					'name'       => __( 'A → Z', 'doctorpilot' ),
					'name_desc'  => __( 'Z → A', 'doctorpilot' ),
					'featured'   => __( 'Featured first', 'doctorpilot' ),
					'experience' => __( 'Most experienced', 'doctorpilot' ),
					'recent'     => __( 'Recently added', 'doctorpilot' ),
				),
			)
		);

		$this->add_control(
			'empty_text',
			array(
				'label'       => __( 'Empty state text', 'doctorpilot' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'No doctors matched this selection.', 'doctorpilot' ),
				'description' => __( 'Shown to editors only. On the live site a widget with nothing to show renders nothing at all.', 'doctorpilot' ),
				'separator'   => 'before',
			)
		);

		$this->register_heading_controls();

		$this->end_controls_section();
	}

	/**
	 * Which doctors appear: a multi-select per registered filter taxonomy, plus
	 * the archive-context switch.
	 *
	 * @return void
	 */
	private function register_source_section(): void {
		$this->start_controls_section(
			'section_source',
			array( 'label' => __( 'Doctors shown', 'doctorpilot' ) )
		);

		$this->add_control(
			'source_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Leave everything empty to show all published doctors. Picking several terms inside one taxonomy widens the selection; picking terms in more than one taxonomy narrows it — a doctor must match at least one term from every taxonomy you use.', 'doctorpilot' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		/*
		 * Terms are only fetched for the editor panel. Controls are initialised
		 * on the front end too — `get_settings_for_display()` needs them — and
		 * querying every taxonomy there would cost eight term queries per widget
		 * to build a list nothing renders. The control itself is registered
		 * either way: a widget whose control set differed between the panel and
		 * the page would be a widget whose saved settings could go missing.
		 */
		$panel = $this->is_panel_request();

		foreach ( $this->taxonomy_controls() as $param => $taxonomy ) {
			$options = $panel ? $this->term_options( $taxonomy ) : array();

			$description = __( 'Up to 10 terms are applied.', 'doctorpilot' );
			if ( $panel && array() === $options ) {
				$description = sprintf(
					/* translators: %s: taxonomy plural label, e.g. "Locations". */
					__( 'No %s have been created yet.', 'doctorpilot' ),
					$this->taxonomy_label( $taxonomy )
				);
			}

			$this->add_control(
				'tax_' . $param,
				array(
					'label'       => $this->taxonomy_label( $taxonomy ),
					'type'        => Controls_Manager::SELECT2,
					'multiple'    => true,
					'label_block' => true,
					'options'     => $options,
					'description' => $description,
				)
			);
		}

		$this->add_control(
			'use_current_term',
			array(
				'label'        => __( 'Follow the current archive', 'doctorpilot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
				'description'  => __( 'On a department, specialty or other term archive, show that term’s doctors and ignore what is picked above for that one taxonomy. Lets a single widget serve every archive page instead of one copy per term. Everywhere else the picks above apply unchanged.', 'doctorpilot' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Carousel behaviour. Hidden entirely in grid mode.
	 *
	 * @return void
	 */
	private function register_carousel_section(): void {
		$this->start_controls_section(
			'section_carousel',
			array(
				'label'     => __( 'Carousel', 'doctorpilot' ),
				'condition' => array( 'display' => 'carousel' ),
			)
		);

		$this->add_control(
			'arrows_show',
			array(
				'label'        => __( 'Previous / next arrows', 'doctorpilot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'dots_show',
			array(
				'label'        => __( 'Position dots', 'doctorpilot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'rewind',
			array(
				'label'        => __( 'Wrap around at the ends', 'doctorpilot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => __( 'Next on the last page returns to the first. With this off, the arrows disable at each end instead.', 'doctorpilot' ),
			)
		);

		$this->add_control(
			'autoplay',
			array(
				'label'        => __( 'Advance automatically', 'doctorpilot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
				'description'  => __( 'Adds a pause button, and stops on hover, on keyboard focus, while the tab is in the background, and for visitors who have asked their system to reduce motion.', 'doctorpilot' ),
			)
		);

		$this->add_control(
			'autoplay_delay',
			array(
				'label'      => __( 'Seconds between pages', 'doctorpilot' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's' ),
				'range'      => array( 's' => array( 'min' => 2, 'max' => 15, 'step' => 0.5 ) ),
				'default'    => array( 'unit' => 's', 'size' => 5 ),
				'condition'  => array( 'autoplay' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style controls for the arrows, dots and pause button.
	 *
	 * @return void
	 */
	private function register_carousel_style(): void {
		$this->start_controls_section(
			'section_carousel_style',
			array(
				'label'     => __( 'Carousel controls', 'doctorpilot' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'display' => 'carousel' ),
			)
		);

		$this->add_control(
			'arrow_position',
			array(
				'label'     => __( 'Arrow position', 'doctorpilot' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'edges',
				'options'   => array(
					'edges'  => __( 'Over the cards, at the edges', 'doctorpilot' ),
					'outside' => __( 'Outside the cards', 'doctorpilot' ),
					'below'  => __( 'Below, beside the dots', 'doctorpilot' ),
				),
				'condition' => array( 'arrows_show' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'arrow_size',
			array(
				'label'      => __( 'Arrow size', 'doctorpilot' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 28, 'max' => 72 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-carousel' => '--dpcar-nav-size: {{SIZE}}{{UNIT}};' ),
				'condition'  => array( 'arrows_show' => 'yes' ),
			)
		);

		$this->start_controls_tabs( 'tabs_arrow_colors', array( 'condition' => array( 'arrows_show' => 'yes' ) ) );

		$this->start_controls_tab( 'tab_arrow_normal', array( 'label' => __( 'Normal', 'doctorpilot' ) ) );

		$this->add_control(
			'arrow_color',
			array(
				'label'     => __( 'Icon colour', 'doctorpilot' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-carousel' => '--dpcar-nav-color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'arrow_bg',
			array(
				'label'     => __( 'Background', 'doctorpilot' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-carousel' => '--dpcar-nav-bg: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'arrow_border',
			array(
				'label'     => __( 'Border colour', 'doctorpilot' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-carousel' => '--dpcar-nav-border: {{VALUE}};' ),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'tab_arrow_hover', array( 'label' => __( 'Hover', 'doctorpilot' ) ) );

		$this->add_control(
			'arrow_color_hover',
			array(
				'label'     => __( 'Icon colour', 'doctorpilot' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-carousel' => '--dpcar-nav-color-hover: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'arrow_bg_hover',
			array(
				'label'     => __( 'Background', 'doctorpilot' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-carousel' => '--dpcar-nav-bg-hover: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'arrow_border_hover',
			array(
				'label'     => __( 'Border colour', 'doctorpilot' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-carousel' => '--dpcar-nav-border-hover: {{VALUE}};' ),
			)
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_control(
			'dots_heading',
			array(
				'label'     => __( 'Dots', 'doctorpilot' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array( 'dots_show' => 'yes' ),
			)
		);

		$this->add_control(
			'dot_color',
			array(
				'label'     => __( 'Dot colour', 'doctorpilot' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-carousel' => '--dpcar-dot: {{VALUE}};' ),
				'condition' => array( 'dots_show' => 'yes' ),
			)
		);

		$this->add_control(
			'dot_active_color',
			array(
				'label'     => __( 'Active dot colour', 'doctorpilot' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-carousel' => '--dpcar-dot-active: {{VALUE}};' ),
				'condition' => array( 'dots_show' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * Control data
	 * ------------------------------------------------------------------ */

	/**
	 * Filter taxonomies that exist on this install, in a stable display order.
	 *
	 * Read from the same map the search engine reads, so a taxonomy an add-on
	 * registers gains a control here without this widget knowing about it, and a
	 * taxonomy that is not registered — Insurance without Pro — never produces a
	 * control that would silently select nothing.
	 *
	 * @return array<string, string> Search parameter => taxonomy key.
	 */
	private function taxonomy_controls(): array {
		$order = array( 'department', 'specialty', 'symptom', 'condition', 'treatment', 'language', 'location', 'insurance' );
		$map   = SearchRequestFactory::filter_taxonomies();

		$ordered = array();
		foreach ( $order as $param ) {
			if ( isset( $map[ $param ] ) ) {
				$ordered[ $param ] = $map[ $param ];
			}
		}

		// Anything an add-on registered under a name this widget has never heard
		// of still gets a control, appended after the known ones.
		foreach ( $map as $param => $taxonomy ) {
			if ( ! isset( $ordered[ $param ] ) ) {
				$ordered[ (string) $param ] = (string) $taxonomy;
			}
		}

		return array_filter( $ordered, 'taxonomy_exists' );
	}

	/**
	 * A taxonomy's plural label, falling back to its key.
	 *
	 * @param string $taxonomy Taxonomy key.
	 * @return string
	 */
	private function taxonomy_label( string $taxonomy ): string {
		$object = get_taxonomy( $taxonomy );

		return ( $object && isset( $object->labels->name ) ) ? (string) $object->labels->name : $taxonomy;
	}

	/**
	 * Term slug => name options for one taxonomy.
	 *
	 * Slugs, not term IDs, because a slug is what the search request, the REST
	 * route and the shareable directory URL all speak. A widget configured here
	 * therefore describes the same selection as the equivalent filtered
	 * directory link.
	 *
	 * @param string $taxonomy Taxonomy key.
	 * @return array<string, string>
	 */
	private function term_options( string $taxonomy ): array {
		// Several carousels on one page share the panel, and each would
		// otherwise repeat the same eight queries.
		static $cache = array();

		if ( isset( $cache[ $taxonomy ] ) ) {
			return $cache[ $taxonomy ];
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 500,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			$cache[ $taxonomy ] = array();

			return $cache[ $taxonomy ];
		}

		$options = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$options[ $term->slug ] = $term->name;
			}
		}

		$cache[ $taxonomy ] = $options;

		return $options;
	}

	/**
	 * Whether controls are being built for the Elementor panel, where the term
	 * lists are actually read, rather than for a front-end render.
	 *
	 * @return bool
	 */
	private function is_panel_request(): bool {
		return is_admin() || $this->is_edit_mode();
	}

	/* ---------------------------------------------------------------------
	 * Render
	 * ------------------------------------------------------------------ */

	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();

		$display = 'grid' === ( $settings['display'] ?? 'carousel' ) ? 'grid' : 'carousel';
		$limit   = max( 1, min( self::MAX_ITEMS, (int) ( $settings['limit'] ?? 12 ) ) );

		$request = SearchRequestFactory::from_array( $this->request_args( $settings ), $limit );
		$result  = ( new SearchService() )->search( $request );

		$view  = new DoctorView();
		$cards = array();
		foreach ( $result['ids'] as $id ) {
			$card = $view->card( (int) $id );
			if ( null !== $card ) {
				$cards[] = $card;
			}
		}

		/*
		 * An empty curated strip is not an error state a visitor can act on, so
		 * on the front end the widget renders nothing rather than an apology.
		 * In the editor it must say something, or the widget looks broken to the
		 * person who just configured it.
		 */
		if ( array() === $cards && ! $this->is_edit_mode() ) {
			return;
		}

		$card_parts = $this->card_parts( $settings );

		$data = array(
			'settings'     => $settings,
			'cards'        => $cards,
			'display'      => $display,
			'preset'       => $this->preset( $settings ),
			'columns'      => max( 1, min( 6, (int) ( $settings['columns'] ?? 3 ) ) ),
			'uid'          => 'dp-car-' . (string) $this->get_id(),
			'card_classes' => ( 'yes' === ( $settings['card_linked'] ?? '' ) ) ? 'dp-card dp-card--linked' : 'dp-card',
			'meta_icons'   => 'yes' === ( $settings['meta_icons_show'] ?? 'yes' ),
			'card_linked'  => 'yes' === ( $settings['card_linked'] ?? '' ),
			'clamp'        => max( 0, (int) ( $settings['bio_clamp'] ?? 0 ) ),
			'parts'        => $card_parts,
			'arrows'       => 'carousel' === $display && 'yes' === ( $settings['arrows_show'] ?? 'yes' ),
			'dots'         => 'carousel' === $display && 'yes' === ( $settings['dots_show'] ?? 'yes' ),
			'rewind'       => 'yes' === ( $settings['rewind'] ?? 'yes' ),
			'autoplay'     => 'carousel' === $display && 'yes' === ( $settings['autoplay'] ?? '' ),
			'autoplay_ms'  => $this->autoplay_ms( $settings ),
			'arrow_pos'    => $this->arrow_position( $settings ),
			'empty_text'   => (string) ( $settings['empty_text'] ?? '' ),
			'is_editor'    => $this->is_edit_mode(),
		);

		$this->render_heading( $settings );

		include Templates::locate( 'carousel-doctor.php' );
	}

	/**
	 * Build the raw search parameters from the widget's term selections.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @return array<string, mixed>
	 */
	private function request_args( array $settings ): array {
		$args = array(
			'sort' => (string) ( $settings['sort'] ?? 'name' ),
			'page' => 1,
		);

		$current = 'yes' === ( $settings['use_current_term'] ?? '' ) ? $this->current_term() : null;

		foreach ( $this->taxonomy_controls() as $param => $taxonomy ) {
			// The archive's own term replaces the editor's picks for that one
			// taxonomy. Merging the two instead would show a department page
			// doctors from a different department, which reads as a bug.
			if ( null !== $current && $current['taxonomy'] === $taxonomy ) {
				$args[ $param ] = array( $current['slug'] );
				continue;
			}

			$slugs = $settings[ 'tax_' . $param ] ?? array();
			$slugs = is_array( $slugs ) ? $slugs : array( $slugs );

			if ( array() !== $slugs ) {
				$args[ $param ] = $slugs;
			}
		}

		return $args;
	}

	/**
	 * The term being viewed, when it belongs to a taxonomy this widget filters
	 * on.
	 *
	 * @return array{taxonomy:string, slug:string}|null
	 */
	private function current_term(): ?array {
		if ( ! is_tax() && ! is_category() && ! is_tag() ) {
			return null;
		}

		$queried = get_queried_object();
		if ( ! ( $queried instanceof \WP_Term ) ) {
			return null;
		}

		if ( ! in_array( $queried->taxonomy, SearchRequestFactory::filter_taxonomies(), true ) ) {
			return null;
		}

		return array(
			'taxonomy' => (string) $queried->taxonomy,
			'slug'     => (string) $queried->slug,
		);
	}

	/**
	 * Resolve the chosen card preset, defaulting for instances saved before the
	 * control existed.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @return string
	 */
	private function preset( array $settings ): string {
		$preset = isset( $settings['card_preset'] ) ? (string) $settings['card_preset'] : '';

		return in_array( $preset, self::PRESETS, true ) ? $preset : 'modern';
	}

	/**
	 * Autoplay interval in milliseconds, or 0 when autoplay is off.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @return int
	 */
	private function autoplay_ms( array $settings ): int {
		if ( 'yes' !== ( $settings['autoplay'] ?? '' ) ) {
			return 0;
		}

		$seconds = (float) ( $settings['autoplay_delay']['size'] ?? 5 );

		return (int) round( max( 2.0, min( 15.0, $seconds ) ) * 1000 );
	}

	/**
	 * Resolve the arrow placement modifier.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @return string
	 */
	private function arrow_position( array $settings ): string {
		$position = (string) ( $settings['arrow_position'] ?? 'edges' );

		return in_array( $position, array( 'edges', 'outside', 'below' ), true ) ? $position : 'edges';
	}

	/**
	 * Whether Elementor is currently rendering the editor canvas.
	 *
	 * @return bool
	 */
	private function is_edit_mode(): bool {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}

		$editor = \Elementor\Plugin::instance()->editor;

		return method_exists( $editor, 'is_edit_mode' ) && (bool) $editor->is_edit_mode();
	}
}
