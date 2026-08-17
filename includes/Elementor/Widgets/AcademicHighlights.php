<?php
/**
 * Academic highlights Elementor widget (sidebar stat grid).
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor\Widgets;

use BrainStudioz\DoctorPilot\Application\Doctors\DoctorView;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A small stat grid for the doctor sidebar (e.g. "90+ Peer-Reviewed
 * Publications"). Manual content via a repeater — there is no per-doctor stats
 * field — with an optional eyebrow title and configurable columns.
 */
final class AcademicHighlights extends AbstractWidget {

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
	public function get_name(): string {
		return 'doctorpilot-academic-highlights';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Academic Highlights', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-counter';
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
		$this->start_controls_section( 'section_content', array( 'label' => __( 'Highlights', 'doctorpilot' ) ) );

		$this->add_control(
			'title',
			array(
				'label'   => __( 'Title', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Academic Highlights', 'doctorpilot' ),
			)
		);

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
				'description' => __( 'Optional — for editor preview or off a doctor page. On a doctor page the current doctor is always used.', 'doctorpilot' ),
				'min'         => 0,
				'condition'   => array( 'data_source' => 'doctor' ),
			)
		);

		$repeater = new \Elementor\Repeater();
		$repeater->add_control(
			'value',
			array(
				'label'   => __( 'Value', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => '90+',
				'dynamic' => array( 'active' => true ),
			)
		);
		$repeater->add_control(
			'label',
			array(
				'label'   => __( 'Label', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Peer-Reviewed Publications', 'doctorpilot' ),
				'dynamic' => array( 'active' => true ),
			)
		);

		$this->add_control(
			'items',
			array(
				'label'       => __( 'Highlights', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ value }}} {{{ label }}}',
				'condition'   => array( 'data_source' => 'manual' ),
				'default'     => array(
					array( 'value' => '90+', 'label' => __( 'Peer-Reviewed Publications', 'doctorpilot' ) ),
					array( 'value' => '20+', 'label' => __( 'Conference Posters & Presentations', 'doctorpilot' ) ),
					array( 'value' => '12+', 'label' => __( 'Prizes & Awards', 'doctorpilot' ) ),
					array( 'value' => '25+', 'label' => __( 'Professional Courses', 'doctorpilot' ) ),
				),
			)
		);

		$this->add_responsive_control(
			'columns',
			array(
				'label'          => __( 'Columns', 'doctorpilot' ),
				'type'           => \Elementor\Controls_Manager::SELECT,
				'default'        => '2',
				'mobile_default' => '1',
				'options'        => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6' ),
				'selectors'      => array( '{{WRAPPER}} .dp-stats__grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ),
			)
		);

		$this->register_heading_controls();

		$this->end_controls_section();

		$this->register_heading_style();

		$this->register_card_style();
		$this->register_item_style();
		$this->register_text_style();
	}

	/**
	 * Per-box (item) style controls: alignment, padding, radius, and
	 * background / border / box-shadow with normal + hover states.
	 *
	 * @return void
	 */
	private function register_item_style(): void {
		$sel   = '{{WRAPPER}} .dp-stats__item';
		$hover = '{{WRAPPER}} .dp-stats__item:hover';

		$this->start_controls_section(
			'section_item_style',
			array( 'label' => __( 'Item box', 'doctorpilot' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE )
		);

		$this->add_responsive_control(
			'item_align',
			array(
				'label'     => __( 'Alignment', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array( 'title' => __( 'Left', 'doctorpilot' ), 'icon' => 'eicon-text-align-left' ),
					'center' => array( 'title' => __( 'Center', 'doctorpilot' ), 'icon' => 'eicon-text-align-center' ),
					'right'  => array( 'title' => __( 'Right', 'doctorpilot' ), 'icon' => 'eicon-text-align-right' ),
				),
				'selectors' => array( $sel => 'text-align: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'item_padding',
			array(
				'label'      => __( 'Padding', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( $sel => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'item_radius',
			array(
				'label'      => __( 'Border radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( $sel => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'item_transition',
			array(
				'label'     => __( 'Transition duration (s)', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 3, 'step' => 0.1 ) ),
				'selectors' => array( $sel => 'transition: all {{SIZE}}s ease;' ),
			)
		);

		$this->start_controls_tabs( 'item_tabs' );

		$this->start_controls_tab( 'item_tab_normal', array( 'label' => __( 'Normal', 'doctorpilot' ) ) );
		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array( 'name' => 'item_bg', 'types' => array( 'classic', 'gradient' ), 'selector' => $sel )
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array( 'name' => 'item_border', 'selector' => $sel )
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array( 'name' => 'item_shadow', 'selector' => $sel )
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'item_tab_hover', array( 'label' => __( 'Hover', 'doctorpilot' ) ) );
		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array( 'name' => 'item_bg_hover', 'types' => array( 'classic', 'gradient' ), 'selector' => $hover )
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array( 'name' => 'item_border_hover', 'selector' => $hover )
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array( 'name' => 'item_shadow_hover', 'selector' => $hover )
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Card style controls.
	 *
	 * @return void
	 */
	private function register_card_style(): void {
		$this->start_controls_section(
			'section_card_style',
			array( 'label' => __( 'Card', 'doctorpilot' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE )
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array( 'name' => 'card_bg', 'types' => array( 'classic', 'gradient' ), 'selector' => '{{WRAPPER}} .dp-stats' )
		);
		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => __( 'Padding', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .dp-stats' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'card_radius',
			array(
				'label'      => __( 'Border radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .dp-stats' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array( 'name' => 'card_border', 'selector' => '{{WRAPPER}} .dp-stats' )
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array( 'name' => 'card_shadow', 'selector' => '{{WRAPPER}} .dp-stats' )
		);
		$this->add_responsive_control(
			'grid_gap',
			array(
				'label'      => __( 'Gap', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 48 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-stats__grid' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Text style controls.
	 *
	 * @return void
	 */
	private function register_text_style(): void {
		$this->start_controls_section(
			'section_text_style',
			array( 'label' => __( 'Text', 'doctorpilot' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE )
		);

		$this->add_control( 'title_heading', array( 'label' => __( 'Title', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING ) );
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'title_typography', 'selector' => '{{WRAPPER}} .dp-stats__title' )
		);
		$this->add_control(
			'title_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-stats__title' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control( 'value_heading', array( 'label' => __( 'Value', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'value_typography', 'selector' => '{{WRAPPER}} .dp-stats__value' )
		);
		$this->add_control(
			'value_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-stats__value' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control( 'label_heading', array( 'label' => __( 'Label', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'label_typography', 'selector' => '{{WRAPPER}} .dp-stats__label' )
		);
		$this->add_control(
			'label_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-stats__label' => 'color: {{VALUE}};' ),
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
		$items    = $this->items( $settings );

		if ( array() === $items ) {
			if ( \Elementor\Plugin::instance()->editor->is_edit_mode() && 'doctor' === ( $settings['data_source'] ?? '' ) ) {
				echo '<p>' . esc_html__( 'No academic highlights set for this doctor. Add them in the doctor editor, or switch Data source to Manual.', 'doctorpilot' ) . '</p>';
			}
			return;
		}

		// After the empty return: a heading over an empty stats block would be a
		// promise the widget cannot keep.
		$this->render_heading( $settings );

		echo '<div class="dp-stats">';

		if ( '' !== (string) ( $settings['title'] ?? '' ) ) {
			echo '<div class="dp-stats__title">' . esc_html( (string) $settings['title'] ) . '</div>';
		}

		echo '<div class="dp-stats__grid">';
		foreach ( $items as $item ) {
			$value = (string) ( $item['value'] ?? '' );
			$label = (string) ( $item['label'] ?? '' );
			if ( '' === $value && '' === $label ) {
				continue;
			}
			echo '<div class="dp-stats__item">';
			if ( '' !== $value ) {
				echo '<div class="dp-stats__value">' . esc_html( $value ) . '</div>';
			}
			if ( '' !== $label ) {
				echo '<div class="dp-stats__label">' . esc_html( $label ) . '</div>';
			}
			echo '</div>';
		}
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Resolve the stat items from the doctor field or the manual repeater.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @return array<int, array<string, string>>
	 */
	private function items( array $settings ): array {
		if ( 'manual' === ( $settings['data_source'] ?? 'doctor' ) ) {
			return is_array( $settings['items'] ?? null ) ? $settings['items'] : array();
		}

		$doctor_id = $this->resolve_doctor_id( $settings );

		if ( $doctor_id <= 0 ) {
			return array();
		}

		$data = ( new DoctorView() )->full( $doctor_id );
		$rows = null !== $data && is_array( $data['academic_highlights'] ?? null ) ? $data['academic_highlights'] : array();

		$items = array();
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$items[] = array(
					'value' => (string) ( $row['value'] ?? '' ),
					'label' => (string) ( $row['label'] ?? '' ),
				);
			}
		}

		return $items;
	}
}
