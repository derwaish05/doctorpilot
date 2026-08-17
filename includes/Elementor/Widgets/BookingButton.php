<?php
/**
 * Booking Button Elementor widget.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor\Widgets;

use BrainStudioz\DoctorPilot\Booking\BookingResolver;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a doctor's booking call-to-action via the shared booking resolver.
 *
 * The destination is always resolved and safety-revalidated by the resolver;
 * the widget only styles and outputs the resulting link. If no safe CTA exists,
 * nothing is rendered.
 */
final class BookingButton extends AbstractWidget {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'doctorpilot-booking-button';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Booking Button', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-button';
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array( 'label' => __( 'Booking', 'doctorpilot' ) )
		);

		$this->add_control(
			'doctor_id',
			array(
				'label'       => __( 'Doctor ID', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'description' => __( 'Leave empty to use the current doctor.', 'doctorpilot' ),
				'min'         => 0,
			)
		);

		$this->add_control(
			'align',
			array(
				'label'     => __( 'Alignment', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array( 'title' => __( 'Left', 'doctorpilot' ), 'icon' => 'eicon-text-align-left' ),
					'center'     => array( 'title' => __( 'Center', 'doctorpilot' ), 'icon' => 'eicon-text-align-center' ),
					'flex-end'   => array( 'title' => __( 'Right', 'doctorpilot' ), 'icon' => 'eicon-text-align-right' ),
				),
				'default'   => 'flex-start',
				'selectors' => array(
					'{{WRAPPER}} .dp-booking-widget' => 'display:flex; justify-content: {{VALUE}};',
				),
			)
		);

		$this->register_heading_controls();

		$this->end_controls_section();

		$this->register_heading_style();

		$this->start_controls_section(
			'section_button_style',
			array(
				'label' => __( 'Button', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'bg_color',
			array(
				'label'     => __( 'Background', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .dp-btn--primary' => 'background: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => __( 'Text color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .dp-btn--primary' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'selector' => '{{WRAPPER}} .dp-btn--primary',
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
		$settings  = $this->get_settings_for_display();
		$doctor_id = isset( $settings['doctor_id'] ) ? (int) $settings['doctor_id'] : 0;

		if ( $doctor_id <= 0 ) {
			$queried = get_queried_object();
			if ( $queried instanceof \WP_Post && DoctorPostType::POST_TYPE === $queried->post_type ) {
				$doctor_id = (int) $queried->ID;
			}
		}

		if ( $doctor_id <= 0 ) {
			return;
		}

		// Only expose booking for published doctors.
		if ( 'publish' !== get_post_status( $doctor_id ) ) {
			return;
		}

		$cta = ( new BookingResolver() )->resolve_for( $doctor_id );
		if ( null === $cta ) {
			return;
		}

		$target = '_blank' === $cta->target() ? ' target="_blank" rel="noopener"' : '';

		// Last, after every early return: an unpublished doctor or a doctor with
		// no booking destination renders nothing at all, and a heading floating
		// above nothing would be worse than no heading.
		$this->render_heading( $settings );

		echo '<div class="dp-booking-widget">';
		printf(
			'<a class="dp-btn dp-btn--primary" href="%1$s"%2$s>%3$s</a>',
			esc_url( $cta->destination() ),
			$target, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static, controlled markup.
			esc_html( $cta->label() )
		);
		echo '</div>';
	}
}
