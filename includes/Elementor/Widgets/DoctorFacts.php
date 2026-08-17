<?php
/**
 * Doctor facts Elementor widget (sidebar quick facts).
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
 * A quick-facts card for the doctor sidebar: labelled, icon-led rows for
 * languages, availability, department, email, and phone. Each row can be toggled
 * and given its own icon. Data is resolved dynamically for the selected or
 * current queried doctor.
 */
final class DoctorFacts extends AbstractWidget {

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
		return 'doctorpilot-doctor-facts';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Doctor Facts', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-info-circle-o';
	}

	/**
	 * The rows and their default icons/labels.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function rows(): array {
		return array(
			'languages'    => array( 'label' => __( 'Languages', 'doctorpilot' ), 'icon' => 'fas fa-language' ),
			'availability' => array( 'label' => __( 'Availability', 'doctorpilot' ), 'icon' => 'far fa-clock' ),
			'department'   => array( 'label' => __( 'Department', 'doctorpilot' ), 'icon' => 'far fa-building' ),
			'email'        => array( 'label' => __( 'Email', 'doctorpilot' ), 'icon' => 'far fa-envelope' ),
			'phone'        => array( 'label' => __( 'Phone', 'doctorpilot' ), 'icon' => 'fas fa-phone' ),
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
		$this->start_controls_section( 'section_content', array( 'label' => __( 'Facts', 'doctorpilot' ) ) );

		$this->add_control(
			'doctor_id',
			array(
				'label'       => __( 'Doctor ID', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'description' => __( 'Optional — for editor preview or placing off a doctor page. On a doctor page the current doctor is always used.', 'doctorpilot' ),
				'min'         => 0,
			)
		);

		foreach ( $this->rows() as $key => $row ) {
			$this->add_control(
				$key . '_show',
				array(
					/* translators: %s: row label. */
					'label'        => sprintf( __( 'Show %s', 'doctorpilot' ), $row['label'] ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => 'yes',
					'separator'    => 'before',
				)
			);

			$this->add_control(
				$key . '_icon',
				array(
					/* translators: %s: row label. */
					'label'     => sprintf( __( '%s icon', 'doctorpilot' ), $row['label'] ),
					'type'      => \Elementor\Controls_Manager::ICONS,
					'default'   => array( 'value' => $row['icon'], 'library' => 0 === strpos( $row['icon'], 'far' ) ? 'fa-regular' : 'fa-solid' ),
					'condition' => array( $key . '_show' => 'yes' ),
				)
			);

			if ( 'availability' === $key ) {
				$this->add_control(
					'availability_text',
					array(
						'label'       => __( 'Availability text', 'doctorpilot' ),
						'type'        => \Elementor\Controls_Manager::TEXT,
						'placeholder' => __( 'Sunday – Thursday', 'doctorpilot' ),
						'description' => __( 'Optional. Leave blank to use the location office hours.', 'doctorpilot' ),
						'condition'   => array( 'availability_show' => 'yes' ),
					)
				);
			}
		}

		$this->register_heading_controls();

		$this->end_controls_section();

		$this->register_heading_style();

		$this->register_card_style();
		$this->register_icon_style();
		$this->register_text_style();
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
			array( 'name' => 'card_bg', 'types' => array( 'classic', 'gradient' ), 'selector' => '{{WRAPPER}} .dp-facts' )
		);
		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => __( 'Padding', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .dp-facts' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'card_radius',
			array(
				'label'      => __( 'Border radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .dp-facts' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array( 'name' => 'card_border', 'selector' => '{{WRAPPER}} .dp-facts' )
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array( 'name' => 'card_shadow', 'selector' => '{{WRAPPER}} .dp-facts' )
		);
		$this->add_responsive_control(
			'row_gap',
			array(
				'label'      => __( 'Row gap', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 48 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-facts' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Icon style controls.
	 *
	 * @return void
	 */
	private function register_icon_style(): void {
		$icon = '{{WRAPPER}} .dp-facts__icon';

		$this->start_controls_section(
			'section_icon_style',
			array( 'label' => __( 'Icon', 'doctorpilot' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE )
		);

		$this->add_control(
			'icon_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#16b47f',
				'selectors' => array( $icon => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'icon_bg',
			array(
				'label'     => __( 'Background', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#E9F6F0',
				'selectors' => array( $icon => 'background: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'icon_size',
			array(
				'label'      => __( 'Icon size', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 10, 'max' => 28 ) ),
				'selectors'  => array( $icon => 'font-size: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'icon_box',
			array(
				'label'      => __( 'Box size', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 24, 'max' => 60 ) ),
				'selectors'  => array( $icon => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'icon_radius',
			array(
				'label'      => __( 'Box radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ), '%' => array( 'min' => 0, 'max' => 50 ) ),
				'selectors'  => array( $icon => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->register_icon_box_controls( $icon, '{{WRAPPER}} .dp-facts__row:hover .dp-facts__icon' );

		$this->end_controls_section();
	}

	/**
	 * Label + value text style controls.
	 *
	 * @return void
	 */
	private function register_text_style(): void {
		$this->start_controls_section(
			'section_text_style',
			array( 'label' => __( 'Text', 'doctorpilot' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE )
		);

		$this->add_control( 'label_heading', array( 'label' => __( 'Label', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING ) );
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'label_typography', 'selector' => '{{WRAPPER}} .dp-facts__label' )
		);
		$this->add_control(
			'label_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-facts__label' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control( 'value_heading', array( 'label' => __( 'Value', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'value_typography', 'selector' => '{{WRAPPER}} .dp-facts__value' )
		);
		$this->add_control(
			'value_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-facts__value, {{WRAPPER}} .dp-facts__value a' => 'color: {{VALUE}};' ),
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

		$values = $this->values( $data, $settings );

		$html = '';
		foreach ( $this->rows() as $key => $row ) {
			if ( 'yes' !== ( $settings[ $key . '_show' ] ?? '' ) ) {
				continue;
			}
			$value = $values[ $key ] ?? array( 'text' => '', 'href' => '' );
			if ( '' === (string) $value['text'] ) {
				continue;
			}

			$icon = '';
			if ( is_array( $settings[ $key . '_icon' ] ?? null ) && '' !== (string) ( $settings[ $key . '_icon' ]['value'] ?? '' ) ) {
				ob_start();
				\Elementor\Icons_Manager::render_icon( $settings[ $key . '_icon' ], array( 'aria-hidden' => 'true' ) );
				$icon = (string) ob_get_clean();
			}

			$value_html = '' !== (string) $value['href']
				? '<a href="' . esc_attr( (string) $value['href'] ) . '">' . esc_html( (string) $value['text'] ) . '</a>'
				: esc_html( (string) $value['text'] );

			$html .= '<li class="dp-facts__row">';
			$html .= '<span class="dp-facts__icon" aria-hidden="true">' . $icon . '</span>';
			$html .= '<div class="dp-facts__body">';
			$html .= '<span class="dp-facts__label">' . esc_html( (string) $row['label'] ) . '</span>';
			$html .= '<span class="dp-facts__value">' . $value_html . '</span>';
			$html .= '</div></li>';
		}

		if ( '' === $html ) {
			return;
		}

		// The rows are built before this point precisely so an all-empty widget
		// can return above without having printed a heading.
		$this->render_heading( $settings );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All dynamic parts escaped above; icons from Icons_Manager.
		echo '<ul class="dp-facts">' . $html . '</ul>';
	}

	/**
	 * Resolve each row's value + optional link from the doctor data.
	 *
	 * @param array<string, mixed> $data     Doctor view model.
	 * @param array<string, mixed> $settings Widget settings.
	 * @return array<string, array<string, string>>
	 */
	private function values( array $data, array $settings ): array {
		$languages = array();
		foreach ( (array) ( $data['languages'] ?? array() ) as $term ) {
			if ( isset( $term['name'] ) ) {
				$languages[] = (string) $term['name'];
			}
		}

		$email = '';
		$phone = '';
		$hours = '';
		foreach ( (array) ( $data['locations'] ?? array() ) as $loc ) {
			if ( '' === $email && ! empty( $loc['public_email'] ) ) {
				$email = (string) $loc['public_email'];
			}
			if ( '' === $phone && ! empty( $loc['phone'] ) ) {
				$phone = (string) $loc['phone'];
			}
			if ( '' === $hours && ! empty( $loc['office_hours_text'] ) ) {
				$hours = (string) $loc['office_hours_text'];
			}
		}

		$availability = (string) ( $settings['availability_text'] ?? '' );
		if ( '' === $availability ) {
			$availability = $hours;
		}

		$tel = (string) preg_replace( '/[^0-9+]/', '', $phone );

		return array(
			'languages'    => array( 'text' => implode( ', ', $languages ), 'href' => '' ),
			'availability' => array( 'text' => $availability, 'href' => '' ),
			'department'   => array( 'text' => (string) ( $data['primary_department']['name'] ?? '' ), 'href' => '' ),
			'email'        => array( 'text' => $email, 'href' => '' !== $email ? 'mailto:' . $email : '' ),
			'phone'        => array( 'text' => $phone, 'href' => '' !== $tel ? 'tel:' . $tel : '' ),
		);
	}
}
