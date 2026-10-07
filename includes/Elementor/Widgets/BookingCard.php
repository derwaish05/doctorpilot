<?php
/**
 * Booking card Elementor widget (sidebar CTA).
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
 * A prominent "Book an appointment" card for the doctor sidebar: an optional
 * mark icon, a title and subtitle, a primary Book action (from the doctor's
 * booking CTA), and an optional Call action. Resolves the selected or current
 * queried doctor.
 */
final class BookingCard extends AbstractWidget {

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
		return 'doctorpilot-booking-card';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Booking Card', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-price-table';
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
		$this->start_controls_section( 'section_content', array( 'label' => __( 'Booking', 'doctorpilot' ) ) );

		$this->add_control(
			'doctor_id',
			array(
				'label'       => __( 'Doctor ID', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'description' => __( 'Optional — for editor preview or placing off a doctor page. On a doctor page the current doctor is always used.', 'doctorpilot' ),
				'min'         => 0,
			)
		);

		$this->register_icon_source( 'mark_icon', __( 'Mark icon', 'doctorpilot' ) );

		$this->add_control(
			'mark_icon',
			array(
				'label'     => __( 'Mark icon', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::ICONS,
				'default'   => array( 'value' => 'far fa-star', 'library' => 'fa-regular' ),
				'condition' => $this->icon_condition( 'mark_icon', 'elementor' ),
			)
		);

		$this->register_pixfort_icon( 'mark_icon' );

		$this->add_control(
			'title',
			array(
				'label'   => __( 'Title', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Book an Appointment', 'doctorpilot' ),
				'dynamic' => array( 'active' => true ),
			)
		);

		$this->add_control(
			'subtitle',
			array(
				'label'       => __( 'Subtitle', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Schedule a consultation with {name}.', 'doctorpilot' ),
				'description' => __( 'Use {name} for the doctor’s name.', 'doctorpilot' ),
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'book_text',
			array(
				'label'   => __( 'Book button text', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Book Now', 'doctorpilot' ),
			)
		);

		$this->register_icon_source( 'book_icon', __( 'Book button icon', 'doctorpilot' ) );

		$this->add_control(
			'book_icon',
			array(
				'label'     => __( 'Book button icon', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::ICONS,
				'default'   => array( 'value' => 'far fa-calendar-check', 'library' => 'fa-regular' ),
				'condition' => $this->icon_condition( 'book_icon', 'elementor' ),
			)
		);

		$this->register_pixfort_icon( 'book_icon' );

		$this->add_control(
			'call_show',
			array(
				'label'        => __( 'Show call button', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'separator'    => 'before',
			)
		);

		$this->add_control(
			'call_text',
			array(
				'label'       => __( 'Call button text', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'The doctor’s phone number', 'doctorpilot' ),
				'description' => __( 'Leave blank to show the phone number.', 'doctorpilot' ),
				'condition'   => array( 'call_show' => 'yes' ),
			)
		);

		$this->register_icon_source( 'call_icon', __( 'Call button icon', 'doctorpilot' ), array( 'call_show' => 'yes' ) );

		$this->add_control(
			'call_icon',
			array(
				'label'     => __( 'Call button icon', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::ICONS,
				'default'   => array( 'value' => 'fas fa-phone', 'library' => 'fa-solid' ),
				'condition' => $this->icon_condition( 'call_icon', 'elementor', array( 'call_show' => 'yes' ) ),
			)
		);

		$this->register_pixfort_icon( 'call_icon', array( 'call_show' => 'yes' ) );

		$this->register_heading_controls();

		$this->end_controls_section();

		$this->register_heading_style();

		$this->register_card_style();
		$this->register_text_style();
		$this->register_button_style();
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

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'card_bg',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .dp-bcard',
			)
		);

		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => __( 'Padding', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .dp-bcard' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'card_radius',
			array(
				'label'      => __( 'Border radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .dp-bcard' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .dp-bcard',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'card_shadow',
				'selector' => '{{WRAPPER}} .dp-bcard',
			)
		);

		$this->add_control(
			'mark_color',
			array(
				'label'     => __( 'Mark icon color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-bcard__mark' => 'color: {{VALUE}};' ),
				'separator' => 'before',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Title + subtitle style controls.
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

		$this->add_control( 'title_heading', array( 'label' => __( 'Title', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING ) );
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'title_typography', 'selector' => '{{WRAPPER}} .dp-bcard__title' )
		);
		$this->add_control(
			'title_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-bcard__title' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control( 'subtitle_heading', array( 'label' => __( 'Subtitle', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'subtitle_typography', 'selector' => '{{WRAPPER}} .dp-bcard__subtitle' )
		);
		$this->add_control(
			'subtitle_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-bcard__subtitle' => 'color: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Button style controls — the full treatment shared by both buttons plus
	 * per-button (Book = primary, Call = ghost) color/background/icon/border/shadow
	 * with Normal / Hover tabs.
	 *
	 * @return void
	 */
	private function register_button_style(): void {
		$this->start_controls_section(
			'section_button_style',
			array(
				'label' => __( 'Buttons', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'btn_typography', 'selector' => '{{WRAPPER}} .dp-bcard__btn' )
		);

		$this->add_responsive_control(
			'btn_icon_size',
			array(
				'label'      => __( 'Icon size', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array(
					'px'  => array( 'min' => 8, 'max' => 40 ),
					'em'  => array( 'min' => 0.5, 'max' => 4, 'step' => 0.1 ),
					'rem' => array( 'min' => 0.5, 'max' => 4, 'step' => 0.1 ),
				),
				'default'    => array( 'unit' => 'em', 'size' => 1 ),
				'selectors'  => array( '{{WRAPPER}} .dp-bcard__btn i, {{WRAPPER}} .dp-bcard__btn svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; font-size: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'btn_icon_gap',
			array(
				'label'      => __( 'Icon spacing', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-bcard__btn' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'btn_padding',
			array(
				'label'      => __( 'Padding', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .dp-bcard__btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'btn_radius',
			array(
				'label'      => __( 'Border radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .dp-bcard__btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->register_button_variant( 'book', __( 'Book button', 'doctorpilot' ), '.dp-bcard__btn--primary' );
		$this->register_button_variant( 'call', __( 'Call button', 'doctorpilot' ), '.dp-bcard__btn--ghost' );

		$this->end_controls_section();
	}

	/**
	 * Register text/background/icon (Normal + Hover) plus border and box-shadow
	 * controls for one button variant.
	 *
	 * @param string $prefix   Control-name prefix.
	 * @param string $heading  Section heading.
	 * @param string $selector The variant class selector (relative to the wrapper).
	 * @return void
	 */
	private function register_button_variant( string $prefix, string $heading, string $selector ): void {
		$base  = '{{WRAPPER}} ' . $selector;
		$hover = '{{WRAPPER}} ' . $selector . ':hover';
		// Icon targets: Font Awesome <i> and inline <svg>.
		$icon       = $base . ' i, ' . $base . ' svg';
		$icon_hover = $hover . ' i, ' . $hover . ' svg';

		$this->add_control(
			$prefix . '_heading',
			array( 'label' => $heading, 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' )
		);

		$this->start_controls_tabs( $prefix . '_tabs' );

		$this->start_controls_tab( $prefix . '_tab_normal', array( 'label' => __( 'Normal', 'doctorpilot' ) ) );
		$this->add_control(
			$prefix . '_color',
			array(
				'label'     => __( 'Text color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $base => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			$prefix . '_bg',
			array(
				'label'     => __( 'Background color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $base => 'background: {{VALUE}};' ),
			)
		);
		$this->add_control(
			$prefix . '_icon_color',
			array(
				'label'     => __( 'Icon color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $icon => 'color: {{VALUE}}; fill: currentColor; stroke: currentColor;' ),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( $prefix . '_tab_hover', array( 'label' => __( 'Hover', 'doctorpilot' ) ) );
		$this->add_control(
			$prefix . '_color_hover',
			array(
				'label'     => __( 'Text color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $hover => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			$prefix . '_bg_hover',
			array(
				'label'     => __( 'Background color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $hover => 'background: {{VALUE}};' ),
			)
		);
		$this->add_control(
			$prefix . '_icon_color_hover',
			array(
				'label'     => __( 'Icon color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $icon_hover => 'color: {{VALUE}}; fill: currentColor; stroke: currentColor;' ),
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array( 'name' => $prefix . '_border_group', 'selector' => $base )
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array( 'name' => $prefix . '_shadow', 'selector' => $base )
		);
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

		$booking     = is_array( $data['booking'] ?? null ) ? $data['booking'] : array();
		$book_url    = (string) ( $booking['url'] ?? '' );
		$book_target = '_blank' === ( $booking['target'] ?? '' ) ? ' target="_blank" rel="noopener"' : '';

		$phone = '';
		foreach ( (array) ( $data['locations'] ?? array() ) as $loc ) {
			if ( ! empty( $loc['phone'] ) ) {
				$phone = (string) $loc['phone'];
				break;
			}
		}
		$tel = (string) preg_replace( '/[^0-9+]/', '', $phone );

		$subtitle = str_replace( '{name}', (string) ( $data['name'] ?? '' ), (string) ( $settings['subtitle'] ?? '' ) );

		// Distinct from the card's own Title control above: that one prints
		// inside the card, this one is a section heading above the whole widget.
		$this->render_heading( $settings );

		echo '<div class="dp-bcard">';

		$mark_icon = $this->icon_markup( $settings, 'mark_icon' );
		if ( '' !== $mark_icon ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons_Manager or pixfort SVG markup.
			echo '<span class="dp-bcard__mark" aria-hidden="true">' . $mark_icon . '</span>';
		}

		if ( '' !== (string) ( $settings['title'] ?? '' ) ) {
			echo '<div class="dp-bcard__title">' . esc_html( (string) $settings['title'] ) . '</div>';
		}
		if ( '' !== trim( $subtitle ) ) {
			echo '<div class="dp-bcard__subtitle">' . esc_html( $subtitle ) . '</div>';
		}

		if ( '' !== $book_url ) {
			printf(
				'<a class="dp-bcard__btn dp-bcard__btn--primary" href="%1$s"%2$s>',
				esc_url( $book_url ),
				$book_target // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant, safe attribute string.
			);
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons_Manager or pixfort SVG markup.
			echo $this->icon_markup( $settings, 'book_icon' );
			echo '<span>' . esc_html( (string) ( $settings['book_text'] ?? '' ) ) . '</span></a>';
		}

		if ( 'yes' === ( $settings['call_show'] ?? '' ) && '' !== $tel ) {
			$call_text = '' !== (string) ( $settings['call_text'] ?? '' ) ? (string) $settings['call_text'] : $phone;
			printf( '<a class="dp-bcard__btn dp-bcard__btn--ghost" href="tel:%1$s">', esc_attr( $tel ) );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons_Manager or pixfort SVG markup.
			echo $this->icon_markup( $settings, 'call_icon' );
			echo '<span>' . esc_html( $call_text ) . '</span></a>';
		}

		echo '</div>';
	}
}
