<?php
/**
 * Experience & Education timeline Elementor widget.
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
 * Renders a doctor's experience and/or education as a vertical timeline: a rail
 * of icon nodes joined by a line, with a card per entry (role/qualification plus
 * the organization/institution). Data is resolved dynamically for the selected
 * or current queried doctor.
 */
final class ExperienceTimeline extends AbstractWidget {

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
		return 'doctorpilot-timeline';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Experience & Education', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-time-line';
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
			'doctor_id',
			array(
				'label'       => __( 'Doctor ID', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'description' => __( 'Optional — for editor preview or placing off a doctor page. On a doctor page the current doctor is always used.', 'doctorpilot' ),
				'min'         => 0,
			)
		);

		$this->add_control(
			'source',
			array(
				'label'   => __( 'Show', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'both',
				'options' => array(
					'both'       => __( 'Experience & Education', 'doctorpilot' ),
					'experience' => __( 'Experience only', 'doctorpilot' ),
					'education'  => __( 'Education only', 'doctorpilot' ),
				),
			)
		);

		$this->add_control(
			'node_icon',
			array(
				'label'   => __( 'Node icon', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::ICONS,
				'default' => array( 'value' => 'fas fa-graduation-cap', 'library' => 'fa-solid' ),
			)
		);

		$this->add_control(
			'meta_show',
			array(
				'label'        => __( 'Show organization line', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'meta_icon',
			array(
				'label'     => __( 'Organization icon', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::ICONS,
				'default'   => array( 'value' => 'far fa-building', 'library' => 'fa-regular' ),
				'condition' => array( 'meta_show' => 'yes' ),
			)
		);

		$this->register_heading_controls();

		$this->end_controls_section();

		$this->register_heading_style();
		$this->register_rail_style();
		$this->register_card_style();
		$this->register_text_style();
	}

	/**
	 * Rail (node + connecting line) style controls.
	 *
	 * @return void
	 */
	private function register_rail_style(): void {
		$this->start_controls_section(
			'section_rail_style',
			array(
				'label' => __( 'Rail', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'node_bg',
			array(
				'label'     => __( 'Node background', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#E6F4EE',
				'selectors' => array( '{{WRAPPER}} .dp-timeline' => '--dp-tl-node-bg: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'node_color',
			array(
				'label'     => __( 'Node icon / ring color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#16b47f',
				'selectors' => array( '{{WRAPPER}} .dp-timeline' => '--dp-tl-node-color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'node_size',
			array(
				'label'      => __( 'Node size', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 24, 'max' => 64 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 38 ),
				'selectors'  => array( '{{WRAPPER}} .dp-timeline' => '--dp-tl-node-size: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'line_color',
			array(
				'label'     => __( 'Line color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#BFE3D5',
				'selectors' => array( '{{WRAPPER}} .dp-timeline' => '--dp-tl-line: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'rail_gap',
			array(
				'label'      => __( 'Rail-to-card gap', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-timeline__item' => 'column-gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'item_gap',
			array(
				'label'      => __( 'Space between items', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-timeline' => '--dp-tl-item-gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->register_icon_box_controls( '{{WRAPPER}} .dp-timeline__node', '{{WRAPPER}} .dp-timeline__item:hover .dp-timeline__node' );

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
			array(
				'label' => __( 'Card', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => __( 'Padding', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .dp-timeline__card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'card_radius',
			array(
				'label'      => __( 'Border radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .dp-timeline__card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'card_transition',
			array(
				'label'     => __( 'Transition duration (s)', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 3, 'step' => 0.1 ) ),
				'selectors' => array( '{{WRAPPER}} .dp-timeline__card' => 'transition: all {{SIZE}}s ease;' ),
			)
		);

		$this->start_controls_tabs( 'card_tabs' );

		$this->start_controls_tab( 'card_tab_normal', array( 'label' => __( 'Normal', 'doctorpilot' ) ) );
		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'card_bg',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .dp-timeline__card',
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .dp-timeline__card',
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'card_shadow',
				'selector' => '{{WRAPPER}} .dp-timeline__card',
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'card_tab_hover', array( 'label' => __( 'Hover', 'doctorpilot' ) ) );
		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'card_bg_hover',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .dp-timeline__card:hover',
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border_hover',
				'selector' => '{{WRAPPER}} .dp-timeline__card:hover',
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'card_shadow_hover',
				'selector' => '{{WRAPPER}} .dp-timeline__card:hover',
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Title + meta text style controls.
	 *
	 * @return void
	 */
	private function register_text_style(): void {
		$this->start_controls_section(
			'section_text_style',
			array(
				'label' => __( 'Text', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'title_heading',
			array( 'label' => __( 'Title', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING )
		);
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .dp-timeline__title',
			)
		);
		$this->add_control(
			'title_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-timeline__title' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'meta_heading',
			array( 'label' => __( 'Organization line', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' )
		);
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'meta_typography',
				'selector' => '{{WRAPPER}} .dp-timeline__meta',
			)
		);
		$this->add_control(
			'meta_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-timeline__meta' => 'color: {{VALUE}};' ),
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
		$settings  = $this->get_settings_for_display();
		$doctor_id = $this->resolve_doctor_id( $settings );

		if ( $doctor_id <= 0 ) {
			if ( \Elementor\Plugin::instance()->editor->is_edit_mode() ) {
				echo '<p>' . esc_html__( 'Select a doctor or place this widget on a single-doctor template.', 'doctorpilot' ) . '</p>';
			}
			return;
		}

		$data = ( new DoctorView() )->full( $doctor_id );
		if ( null === $data ) {
			return;
		}

		$source = (string) ( $settings['source'] ?? 'both' );
		$items  = array();
		if ( 'education' !== $source ) {
			$items = array_merge( $items, (array) ( $data['experience'] ?? array() ) );
		}
		if ( 'experience' !== $source ) {
			$items = array_merge( $items, (array) ( $data['education'] ?? array() ) );
		}

		if ( array() === $items ) {
			if ( \Elementor\Plugin::instance()->editor->is_edit_mode() ) {
				echo '<p>' . esc_html__( 'This doctor has no experience or education entries.', 'doctorpilot' ) . '</p>';
			}
			return;
		}

		$show_meta = 'yes' === ( $settings['meta_show'] ?? '' );

		$this->render_heading( $settings );

		echo '<ul class="dp-timeline">';
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$title = trim( (string) ( $item['primary'] ?? $item['title'] ?? '' ) );
			$meta  = $this->meta_line( $item );

			echo '<li class="dp-timeline__item">';

			echo '<span class="dp-timeline__node" aria-hidden="true">';
			if ( is_array( $settings['node_icon'] ?? null ) && '' !== (string) ( $settings['node_icon']['value'] ?? '' ) ) {
				\Elementor\Icons_Manager::render_icon( $settings['node_icon'], array( 'aria-hidden' => 'true' ) );
			}
			echo '</span>';

			echo '<div class="dp-timeline__card">';
			if ( '' !== $title ) {
				echo '<div class="dp-timeline__title">' . esc_html( $title ) . '</div>';
			}
			if ( $show_meta && '' !== $meta ) {
				echo '<div class="dp-timeline__meta">';
				if ( is_array( $settings['meta_icon'] ?? null ) && '' !== (string) ( $settings['meta_icon']['value'] ?? '' ) ) {
					\Elementor\Icons_Manager::render_icon( $settings['meta_icon'], array( 'aria-hidden' => 'true' ) );
				}
				echo '<span>' . esc_html( $meta ) . '</span>';
				echo '</div>';
			}
			echo '</div>';

			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Compose the organization line: secondary, plus country when not already
	 * included, plus a year range when present.
	 *
	 * @param array<string, mixed> $item Resolved entry.
	 * @return string
	 */
	private function meta_line( array $item ): string {
		$parts   = array();
		$second  = trim( (string) ( $item['secondary'] ?? '' ) );
		$country = trim( (string) ( $item['country'] ?? '' ) );

		if ( '' !== $second ) {
			$parts[] = $second;
		}
		if ( '' !== $country && ( '' === $second || false === stripos( $second, $country ) ) ) {
			$parts[] = $country;
		}

		$line  = implode( ', ', $parts );
		$range = trim( implode( '–', array_filter( array( (string) ( $item['start_year'] ?? '' ), (string) ( $item['end_year'] ?? '' ) ) ) ) );
		if ( '' !== $range ) {
			$line = '' !== $line ? $line . ' · ' . $range : $range;
		}

		return $line;
	}
}
