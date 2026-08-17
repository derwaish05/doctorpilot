<?php
/**
 * Certifications Elementor widget (sidebar list).
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
 * Lists a doctor's certifications & memberships for the sidebar: an optional
 * eyebrow title and an icon-led list of credentials (linked to the credential
 * URL when present). Resolves the selected or current queried doctor.
 */
final class Certifications extends AbstractWidget {

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
		return 'doctorpilot-certifications';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Certifications', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-medal';
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
		$this->start_controls_section( 'section_content', array( 'label' => __( 'Certifications', 'doctorpilot' ) ) );

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
			'title',
			array(
				'label'   => __( 'Title', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Certifications', 'doctorpilot' ),
			)
		);

		$this->add_control(
			'icon',
			array(
				'label'   => __( 'Icon', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::ICONS,
				'default' => array( 'value' => 'fas fa-award', 'library' => 'fa-solid' ),
			)
		);

		$this->add_control(
			'show_org',
			array(
				'label'        => __( 'Show organization', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_dates',
			array(
				'label'        => __( 'Show issued / expiry dates', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'link_credential',
			array(
				'label'        => __( 'Link to credential URL', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->register_heading_controls();

		$this->end_controls_section();

		$this->register_heading_style();

		$this->register_card_style();
		$this->register_item_style();
	}

	/**
	 * Card + title style controls.
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
			array( 'name' => 'card_bg', 'types' => array( 'classic', 'gradient' ), 'selector' => '{{WRAPPER}} .dp-certs' )
		);
		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => __( 'Padding', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .dp-certs' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'card_radius',
			array(
				'label'      => __( 'Border radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .dp-certs' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array( 'name' => 'card_border', 'selector' => '{{WRAPPER}} .dp-certs' )
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array( 'name' => 'card_shadow', 'selector' => '{{WRAPPER}} .dp-certs' )
		);

		$this->add_control( 'title_heading', array( 'label' => __( 'Title', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'title_typography', 'selector' => '{{WRAPPER}} .dp-certs__title' )
		);
		$this->add_control(
			'title_color',
			array(
				'label'     => __( 'Title color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-certs__title' => 'color: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * List item style controls.
	 *
	 * @return void
	 */
	private function register_item_style(): void {
		$this->start_controls_section(
			'section_item_style',
			array( 'label' => __( 'Items', 'doctorpilot' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE )
		);

		$this->add_responsive_control(
			'item_gap',
			array(
				'label'      => __( 'Item gap', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-certs__list' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'icon_color',
			array(
				'label'     => __( 'Icon color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#16b47f',
				'selectors' => array( '{{WRAPPER}} .dp-certs__icon' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'icon_size',
			array(
				'label'      => __( 'Icon size', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 10, 'max' => 28 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-certs__icon' => 'font-size: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->register_icon_box_controls( '{{WRAPPER}} .dp-certs__icon', '{{WRAPPER}} .dp-certs__item:hover .dp-certs__icon' );

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'name_typography', 'selector' => '{{WRAPPER}} .dp-certs__name' )
		);
		$this->add_control(
			'name_color',
			array(
				'label'     => __( 'Name color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-certs__name, {{WRAPPER}} .dp-certs__name a' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'org_color',
			array(
				'label'     => __( 'Organization color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-certs__org' => 'color: {{VALUE}};' ),
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

		$data  = ( new DoctorView() )->full( $doctor_id );
		$certs = null !== $data && is_array( $data['certifications'] ?? null ) ? $data['certifications'] : array();

		if ( array() === $certs ) {
			if ( \Elementor\Plugin::instance()->editor->is_edit_mode() ) {
				echo '<p>' . esc_html__( 'This doctor has no certifications yet.', 'doctorpilot' ) . '</p>';
			}
			return;
		}

		$show_org   = 'yes' === ( $settings['show_org'] ?? '' );
		$show_dates = 'yes' === ( $settings['show_dates'] ?? '' );
		$do_link    = 'yes' === ( $settings['link_credential'] ?? '' );

		$icon = '';
		if ( is_array( $settings['icon'] ?? null ) && '' !== (string) ( $settings['icon']['value'] ?? '' ) ) {
			ob_start();
			\Elementor\Icons_Manager::render_icon( $settings['icon'], array( 'aria-hidden' => 'true' ) );
			$icon = (string) ob_get_clean();
		}

		// After both early returns, so an unassigned doctor or a doctor with no
		// certifications renders nothing rather than a bare heading.
		$this->render_heading( $settings );

		echo '<div class="dp-certs">';

		if ( '' !== (string) ( $settings['title'] ?? '' ) ) {
			echo '<div class="dp-certs__title">' . esc_html( (string) $settings['title'] ) . '</div>';
		}

		echo '<ul class="dp-certs__list">';
		foreach ( $certs as $cert ) {
			if ( ! is_array( $cert ) ) {
				continue;
			}
			$name = trim( (string) ( $cert['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$org = trim( (string) ( $cert['organization'] ?? '' ) );
			$url = trim( (string) ( $cert['credential_url'] ?? '' ) );

			$name_html = ( $do_link && '' !== $url )
				? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $name ) . '</a>'
				: esc_html( $name );

			$dates = '';
			if ( $show_dates ) {
				$issued = trim( (string) ( $cert['issued_date'] ?? '' ) );
				$expiry = trim( (string) ( $cert['expiry_date'] ?? '' ) );
				$dates  = trim( implode( ' – ', array_filter( array( $issued, $expiry ) ) ) );
			}

			echo '<li class="dp-certs__item">';
			echo '<span class="dp-certs__icon" aria-hidden="true">' . $icon . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons_Manager markup.
			echo '<span class="dp-certs__text">';
			echo '<span class="dp-certs__name">' . $name_html . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html/esc_url above.
			if ( $show_org && '' !== $org ) {
				echo '<span class="dp-certs__org">' . esc_html( $org ) . '</span>';
			}
			if ( '' !== $dates ) {
				echo '<span class="dp-certs__dates">' . esc_html( $dates ) . '</span>';
			}
			echo '</span>';
			echo '</li>';
		}
		echo '</ul>';

		echo '</div>';
	}
}
