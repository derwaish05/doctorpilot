<?php
/**
 * Shared controls and rendering for the single-list achievement widgets.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor\Widgets\Concerns;

use BrainStudioz\DoctorPilot\Application\Doctors\DoctorView;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One icon-led list of achievements, read from a doctor field or typed by hand.
 *
 * `AcademicHighlights` renders the same two lists beneath its stat grid; these
 * widgets exist so each list can be placed, ordered and spaced on its own. The
 * markup is deliberately identical — same `dp-stats__section` block — so a
 * profile built either way looks the same and there is one set of styles to
 * maintain.
 *
 * A using class supplies the three things that differ: which doctor field holds
 * the items, which holds that doctor's own heading, and the fallback heading.
 */
trait AchievementList {

	/**
	 * Doctor view key holding the list items.
	 *
	 * @return string
	 */
	abstract protected function list_key(): string;

	/**
	 * Doctor view key holding the doctor's own heading.
	 *
	 * @return string
	 */
	abstract protected function title_key(): string;

	/**
	 * Heading used when the doctor has not set their own.
	 *
	 * @return string
	 */
	abstract protected function default_title(): string;

	/**
	 * Belongs to the "Single DoctorPilot" category.
	 *
	 * @return array<int, string>
	 */
	public function get_categories(): array {
		return array( 'doctorpilot-single' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-bullet-list';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<int, string>
	 */
	public function get_keywords(): array {
		return array( 'doctor', 'doctorpilot', 'achievements', 'highlights', 'list' );
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
			'data_source',
			array(
				'label'   => __( 'Data source', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'doctor',
				'options' => array(
					'doctor' => __( 'Doctor field (dynamic)', 'doctorpilot' ),
					'manual' => __( 'Manual', 'doctorpilot' ),
				),
			)
		);

		$this->add_control(
			'doctor_id',
			array(
				'label'       => __( 'Doctor ID', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'description' => __( 'Optional — for editor preview or placing off a doctor page. On a doctor page the current doctor is always used.', 'doctorpilot' ),
				'min'         => 0,
				'condition'   => array( 'data_source' => 'doctor' ),
			)
		);

		$this->add_control(
			'list_title',
			array(
				'label'       => __( 'Heading', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => $this->default_title(),
				'description' => __( 'Used unless the doctor set their own heading in the Highlights tab.', 'doctorpilot' ),
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'list_title_tag',
			array(
				'label'   => __( 'Heading tag', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'h3',
				'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'div' ),
			)
		);

		$repeater = new \Elementor\Repeater();
		$repeater->add_control(
			'text',
			array(
				'label'   => __( 'Achievement', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::TEXTAREA,
				'rows'    => 2,
				'dynamic' => array( 'active' => true ),
			)
		);

		$this->add_control(
			'items',
			array(
				'label'       => __( 'Items', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ text }}}',
				'condition'   => array( 'data_source' => 'manual' ),
			)
		);

		$this->register_icon_source( 'list_icon', __( 'Item icon', 'doctorpilot' ) );

		$this->add_control(
			'list_icon',
			array(
				'label'       => __( 'Item icon', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::ICONS,
				'description' => __( 'Leave empty for the built-in check mark.', 'doctorpilot' ),
				'condition'   => $this->icon_condition( 'list_icon', 'elementor' ),
			)
		);

		$this->register_pixfort_icon( 'list_icon' );

		$this->register_heading_controls();

		$this->end_controls_section();

		$this->register_heading_style();
		$this->register_list_style();
	}

	/**
	 * Style controls. Selectors match `AcademicHighlights` so both widgets share
	 * one stylesheet.
	 *
	 * @return void
	 */
	private function register_list_style(): void {
		$this->start_controls_section(
			'section_list_style',
			array( 'label' => __( 'List', 'doctorpilot' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE )
		);

		$this->add_control( 'list_title_style', array( 'label' => __( 'Heading', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING ) );
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'list_title_typography', 'selector' => '{{WRAPPER}} .dp-stats__section-title' )
		);
		$this->add_control(
			'list_title_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#136888',
				'selectors' => array( '{{WRAPPER}} .dp-stats__section-title' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'list_title_spacing',
			array(
				'label'      => __( 'Spacing below', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 48 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-stats__section-title' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control( 'list_item_style', array( 'label' => __( 'Items', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_responsive_control(
			'list_item_gap',
			array(
				'label'      => __( 'Item gap', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-stats__ach' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'list_columns',
			array(
				'label'          => __( 'Columns', 'doctorpilot' ),
				'type'           => \Elementor\Controls_Manager::SELECT,
				'default'        => '1',
				'mobile_default' => '1',
				'options'        => array( '1' => '1', '2' => '2', '3' => '3' ),
				'selectors'      => array( '{{WRAPPER}} .dp-stats__ach' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'list_item_typography', 'selector' => '{{WRAPPER}} .dp-stats__ach-text' )
		);
		$this->add_control(
			'list_item_color',
			array(
				'label'     => __( 'Text color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#000000',
				'selectors' => array( '{{WRAPPER}} .dp-stats__ach-text' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control( 'list_icon_style', array( 'label' => __( 'Icon', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_control(
			'list_icon_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-stats__ach-icon' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'list_icon_size',
			array(
				'label'      => __( 'Size', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array(
					'px'  => array( 'min' => 10, 'max' => 32 ),
					'em'  => array( 'min' => 0.5, 'max' => 4, 'step' => 0.1 ),
					'rem' => array( 'min' => 0.5, 'max' => 4, 'step' => 0.1 ),
				),
				'default'    => array( 'unit' => 'em', 'size' => 1 ),
				'selectors'  => array(
					'{{WRAPPER}} .dp-stats__ach-icon'     => 'font-size: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .dp-stats__ach-icon svg, {{WRAPPER}} .dp-stats__ach-icon i' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				),
			)
		);
		$this->add_responsive_control(
			'list_icon_gap',
			array(
				'label'      => __( 'Gap after icon', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 32 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-stats__ach-item' => 'gap: {{SIZE}}{{UNIT}};' ),
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
		$manual   = 'manual' === ( $settings['data_source'] ?? 'doctor' );
		$data     = $manual ? null : $this->doctor_view( $settings );
		$items    = $manual ? $this->manual_items( $settings ) : $this->doctor_items( $data );

		if ( array() === $items ) {
			if ( \Elementor\Plugin::instance()->editor->is_edit_mode() && ! $manual ) {
				echo '<p>' . esc_html__( 'Nothing to show for this doctor yet. Add items in the doctor editor’s Highlights tab, or switch Data source to Manual.', 'doctorpilot' ) . '</p>';
			}
			return;
		}

		// After the empty return, so a doctor without items leaves no heading.
		$this->render_heading( $settings );

		$own   = null === $data ? '' : trim( (string) ( $data[ $this->title_key() ] ?? '' ) );
		$title = '' !== $own ? $own : trim( (string) ( $settings['list_title'] ?? '' ) );

		$tag = (string) ( $settings['list_title_tag'] ?? 'h3' );
		$tag = in_array( $tag, array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ), true ) ? $tag : 'h3';

		$icon = $this->list_icon_markup( $settings );

		echo '<div class="dp-stats dp-stats--list">';
		echo '<div class="dp-stats__section">';

		if ( '' !== $title ) {
			printf( '<%1$s class="dp-stats__section-title">%2$s</%1$s>', tag_escape( $tag ), esc_html( $title ) );
		}

		echo '<ul class="dp-stats__ach">';
		foreach ( $items as $text ) {
			echo '<li class="dp-stats__ach-item">';
			echo '<span class="dp-stats__ach-icon" aria-hidden="true">' . $icon . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons_Manager markup or the inline SVG below.
			echo '<span class="dp-stats__ach-text">' . esc_html( $text ) . '</span>';
			echo '</li>';
		}
		echo '</ul>';

		echo '</div>';
		echo '</div>';
	}

	/**
	 * The resolved doctor's public view, or null when there is no doctor.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @return array<string, mixed>|null
	 */
	private function doctor_view( array $settings ): ?array {
		$doctor_id = $this->resolve_doctor_id( $settings );

		return $doctor_id > 0 ? ( new DoctorView() )->full( $doctor_id ) : null;
	}

	/**
	 * Non-empty items from the doctor field.
	 *
	 * @param array<string, mixed>|null $data Doctor view payload.
	 * @return array<int, string>
	 */
	private function doctor_items( ?array $data ): array {
		$rows = is_array( $data[ $this->list_key() ] ?? null ) ? $data[ $this->list_key() ] : array();
		$out  = array();

		foreach ( $rows as $value ) {
			$value = is_scalar( $value ) ? trim( (string) $value ) : '';
			if ( '' !== $value ) {
				$out[] = $value;
			}
		}

		return $out;
	}

	/**
	 * Non-empty items from the manual repeater.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @return array<int, string>
	 */
	private function manual_items( array $settings ): array {
		$rows = is_array( $settings['items'] ?? null ) ? $settings['items'] : array();
		$out  = array();

		foreach ( $rows as $row ) {
			$text = is_array( $row ) ? trim( (string) ( $row['text'] ?? '' ) ) : '';
			if ( '' !== $text ) {
				$out[] = $text;
			}
		}

		return $out;
	}
}
