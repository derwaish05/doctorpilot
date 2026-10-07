<?php
/**
 * About Doctor Elementor widget (dynamic biography).
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
 * Outputs the doctor's biography (or headline) for the selected or current
 * queried doctor. Heading/eyebrow are intentionally left to a separate Heading
 * Block, so this widget renders only the body text and its styling.
 */
final class AboutDoctor extends AbstractWidget {

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
		return 'doctorpilot-about-doctor';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'About Doctor', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-post-content';
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
		$this->start_controls_section( 'section_about', array( 'label' => __( 'About', 'doctorpilot' ) ) );

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
			'content_source',
			array(
				'label'   => __( 'Body content', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'biography',
				'options' => array(
					'biography' => __( 'Biography', 'doctorpilot' ),
					'headline'  => __( 'Headline / excerpt', 'doctorpilot' ),
				),
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'     => __( 'Alignment', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'left'    => array( 'title' => __( 'Left', 'doctorpilot' ), 'icon' => 'eicon-text-align-left' ),
					'center'  => array( 'title' => __( 'Center', 'doctorpilot' ), 'icon' => 'eicon-text-align-center' ),
					'right'   => array( 'title' => __( 'Right', 'doctorpilot' ), 'icon' => 'eicon-text-align-right' ),
					'justify' => array( 'title' => __( 'Justify', 'doctorpilot' ), 'icon' => 'eicon-text-align-justify' ),
				),
				'selectors' => array( '{{WRAPPER}} .dp-ah' => 'text-align: {{VALUE}};' ),
			)
		);

		$this->register_heading_controls();

		$this->end_controls_section();

		$this->register_heading_style();
		$this->register_text_style();
		$this->register_box_style();
	}

	/**
	 * Body text style: typography, color (normal/hover), text shadow, spacing.
	 *
	 * @return void
	 */
	private function register_text_style(): void {
		$selector = '{{WRAPPER}} .dp-ah__description';

		$this->start_controls_section(
			'section_text_style',
			array(
				'label' => __( 'Body text', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'text_typography',
				'selector' => $selector . ', ' . $selector . ' p',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Text_Shadow::get_type(),
			array(
				'name'     => 'text_shadow',
				'selector' => $selector,
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#000000',
				'selectors' => array( $selector . ', ' . $selector . ' p' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'link_color',
			array(
				'label'     => __( 'Link color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $selector . ' a' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'paragraph_spacing',
			array(
				'label'      => __( 'Paragraph spacing', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( $selector . ' p:not(:last-child)' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
				'separator'  => 'before',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Box (wrapper) style controls.
	 *
	 * @return void
	 */
	private function register_box_style(): void {
		$this->start_controls_section(
			'section_box_style',
			array(
				'label' => __( 'Box', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'box_bg',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .dp-ah',
			)
		);

		$this->add_responsive_control(
			'box_padding',
			array(
				'label'      => __( 'Padding', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array( '{{WRAPPER}} .dp-ah' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'box_radius',
			array(
				'label'      => __( 'Border radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .dp-ah' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'box_border',
				'selector' => '{{WRAPPER}} .dp-ah',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'box_shadow',
				'selector' => '{{WRAPPER}} .dp-ah',
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

		if ( 'headline' === ( $settings['content_source'] ?? '' ) ) {
			$body = esc_html( (string) ( $data['intro'] ?? '' ) );
		} else {
			$body = wp_kses_post( (string) ( $data['biography'] ?? '' ) );
		}

		if ( '' === trim( wp_strip_all_tags( $body ) ) ) {
			return;
		}

		$this->render_heading( $settings );

		echo '<div class="dp-ah dp-about">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above (wp_kses_post / esc_html).
		echo '<div class="dp-ah__description">' . $body . '</div>';
		echo '</div>';
	}
}
