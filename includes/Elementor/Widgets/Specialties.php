<?php
/**
 * Specialties / scope-of-practice Elementor widget.
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
 * Renders a doctor's scope of practice as a check-marked, multi-column list.
 * The source list is selectable (procedures, treatments, specialties,
 * conditions, symptoms, or clinical interests) and pulled dynamically for the
 * selected or current queried doctor.
 */
final class Specialties extends AbstractWidget {

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
		return 'doctorpilot-specialties';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Specialties', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-check-circle-o';
	}

	/**
	 * Selectable data sources mapped to their DoctorView keys.
	 *
	 * @return array<string, string>
	 */
	private function source_options(): array {
		return array(
			'procedures'         => __( 'Procedures', 'doctorpilot' ),
			'treatments'         => __( 'Treatments', 'doctorpilot' ),
			'specialties'        => __( 'Specialties', 'doctorpilot' ),
			'conditions'         => __( 'Conditions', 'doctorpilot' ),
			'symptoms'           => __( 'Symptoms', 'doctorpilot' ),
			'clinical_interests' => __( 'Clinical interests', 'doctorpilot' ),
			'manual'             => __( 'Manual list', 'doctorpilot' ),
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
				'label'   => __( 'List source', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'procedures',
				'options' => $this->source_options(),
			)
		);

		$this->add_control(
			'icon',
			array(
				'label'   => __( 'Icon', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::ICONS,
				'default' => array( 'value' => 'far fa-check-circle', 'library' => 'fa-regular' ),
			)
		);

		$repeater = new \Elementor\Repeater();
		$repeater->add_control(
			'text',
			array(
				'label'   => __( 'Text', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'List item', 'doctorpilot' ),
				'dynamic' => array( 'active' => true ),
			)
		);

		$this->add_control(
			'items',
			array(
				'label'       => __( 'List items', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ text }}}',
				'condition'   => array( 'source' => 'manual' ),
				'default'     => array(
					array( 'text' => __( 'First achievement', 'doctorpilot' ) ),
					array( 'text' => __( 'Second achievement', 'doctorpilot' ) ),
					array( 'text' => __( 'Third achievement', 'doctorpilot' ) ),
				),
			)
		);

		$this->add_responsive_control(
			'columns',
			array(
				'label'          => __( 'Columns', 'doctorpilot' ),
				'type'           => \Elementor\Controls_Manager::SELECT,
				'default'        => '2',
				'tablet_default' => '1',
				'mobile_default' => '1',
				'options'        => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
				'selectors'      => array( '{{WRAPPER}} .dp-scope' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ),
			)
		);

		$this->register_heading_controls();

		$this->end_controls_section();

		$this->register_heading_style();
		$this->register_item_style();
		$this->register_label_style();
		$this->register_icon_style();
	}

	/**
	 * Item (card) style controls.
	 *
	 * @return void
	 */
	private function register_item_style(): void {
		$this->start_controls_section(
			'section_item_style',
			array(
				'label' => __( 'Items', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'grid_gap',
			array(
				'label'      => __( 'Gap', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-scope' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'item_padding',
			array(
				'label'      => __( 'Padding', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .dp-scope__item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'item_radius',
			array(
				'label'      => __( 'Border radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .dp-scope__item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'item_transition',
			array(
				'label'     => __( 'Transition duration (s)', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 3, 'step' => 0.1 ) ),
				'selectors' => array( '{{WRAPPER}} .dp-scope__item' => 'transition: all {{SIZE}}s ease;' ),
			)
		);

		$this->start_controls_tabs( 'item_tabs' );

		$this->start_controls_tab( 'item_tab_normal', array( 'label' => __( 'Normal', 'doctorpilot' ) ) );
		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'item_bg',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .dp-scope__item',
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'item_border',
				'selector' => '{{WRAPPER}} .dp-scope__item',
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'item_shadow',
				'selector' => '{{WRAPPER}} .dp-scope__item',
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'item_tab_hover', array( 'label' => __( 'Hover', 'doctorpilot' ) ) );
		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'item_bg_hover',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .dp-scope__item:hover',
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'item_border_hover',
				'selector' => '{{WRAPPER}} .dp-scope__item:hover',
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'item_shadow_hover',
				'selector' => '{{WRAPPER}} .dp-scope__item:hover',
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Label text style controls.
	 *
	 * @return void
	 */
	private function register_label_style(): void {
		$selector = '{{WRAPPER}} .dp-scope__label';

		$this->start_controls_section(
			'section_label_style',
			array(
				'label' => __( 'Label', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'label_typography',
				'selector' => $selector,
			)
		);

		$this->add_control(
			'label_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $selector => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'label_color_hover',
			array(
				'label'     => __( 'Hover color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-scope__item:hover .dp-scope__label' => 'color: {{VALUE}};' ),
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
		$icon = '{{WRAPPER}} .dp-scope__icon';

		$this->start_controls_section(
			'section_icon_style',
			array(
				'label' => __( 'Icon', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'icon_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#16b47f',
				'selectors' => array( $icon => 'color: {{VALUE}}; fill: currentColor; stroke: currentColor;' ),
			)
		);

		$this->add_control(
			'icon_color_hover',
			array(
				'label'     => __( 'Hover color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-scope__item:hover .dp-scope__icon' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'icon_size',
			array(
				'label'      => __( 'Size', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 10, 'max' => 48 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 18 ),
				'selectors'  => array( $icon => 'font-size: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'icon_gap',
			array(
				'label'      => __( 'Spacing', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-scope__item' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->register_icon_box_controls( '{{WRAPPER}} .dp-scope__icon', '{{WRAPPER}} .dp-scope__item:hover .dp-scope__icon' );

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
		$source   = (string) ( $settings['source'] ?? 'procedures' );

		if ( 'manual' === $source ) {
			$labels = array();
			foreach ( (array) ( $settings['items'] ?? array() ) as $item ) {
				$text = is_array( $item ) ? trim( (string) ( $item['text'] ?? '' ) ) : '';
				if ( '' !== $text ) {
					$labels[] = $text;
				}
			}
		} else {
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

			$labels = $this->labels( $data, $source );
		}

		if ( array() === $labels ) {
			if ( \Elementor\Plugin::instance()->editor->is_edit_mode() ) {
				echo '<p>' . esc_html__( 'This list has no items yet.', 'doctorpilot' ) . '</p>';
			}
			return;
		}

		$this->render_heading( $settings );

		echo '<ul class="dp-scope">';
		foreach ( $labels as $label ) {
			echo '<li class="dp-scope__item">';
			echo '<span class="dp-scope__icon" aria-hidden="true">';
			if ( is_array( $settings['icon'] ?? null ) && '' !== (string) ( $settings['icon']['value'] ?? '' ) ) {
				\Elementor\Icons_Manager::render_icon( $settings['icon'], array( 'aria-hidden' => 'true' ) );
			}
			echo '</span>';
			echo '<span class="dp-scope__label">' . esc_html( $label ) . '</span>';
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Extract the list of labels for the chosen source.
	 *
	 * @param array<string, mixed> $data   Doctor view model.
	 * @param string               $source Source key.
	 * @return array<int, string>
	 */
	private function labels( array $data, string $source ): array {
		$out = array();

		if ( 'clinical_interests' === $source ) {
			foreach ( (array) ( $data['clinical_interests'] ?? array() ) as $item ) {
				$item = trim( (string) $item );
				if ( '' !== $item ) {
					$out[] = $item;
				}
			}

			return $out;
		}

		if ( 'procedures' === $source ) {
			foreach ( (array) ( $data['procedures'] ?? array() ) as $item ) {
				$name = trim( (string) ( $item['name'] ?? '' ) );
				if ( '' !== $name ) {
					$out[] = $name;
				}
			}

			return $out;
		}

		// Taxonomy term lists: treatments, specialties, conditions, symptoms.
		foreach ( (array) ( $data[ $source ] ?? array() ) as $term ) {
			$name = trim( (string) ( $term['name'] ?? '' ) );
			if ( '' !== $name ) {
				$out[] = $name;
			}
		}

		return $out;
	}
}
