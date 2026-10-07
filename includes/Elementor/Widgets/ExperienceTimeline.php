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
			'design',
			array(
				'label'   => __( 'Design', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'timeline',
				'options' => array(
					'timeline'  => __( 'Timeline', 'doctorpilot' ),
					'checklist' => __( 'Checklist', 'doctorpilot' ),
				),
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

		$this->register_icon_source( 'node_icon', __( 'Node icon', 'doctorpilot' ), array( 'design' => 'timeline' ) );

		$this->add_control(
			'node_icon',
			array(
				'label'     => __( 'Node icon', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::ICONS,
				'default'   => array( 'value' => 'fas fa-graduation-cap', 'library' => 'fa-solid' ),
				'condition' => $this->icon_condition( 'node_icon', 'elementor', array( 'design' => 'timeline' ) ),
			)
		);

		$this->register_pixfort_icon( 'node_icon', array( 'design' => 'timeline' ) );

		$this->add_control(
			'meta_show',
			array(
				'label'        => __( 'Show organization line', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->register_icon_source( 'meta_icon', __( 'Organization icon', 'doctorpilot' ), array( 'meta_show' => 'yes', 'design' => 'timeline' ) );

		$this->add_control(
			'meta_icon',
			array(
				'label'     => __( 'Organization icon', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::ICONS,
				'default'   => array( 'value' => 'far fa-building', 'library' => 'fa-regular' ),
				'condition' => $this->icon_condition( 'meta_icon', 'elementor', array( 'meta_show' => 'yes', 'design' => 'timeline' ) ),
			)
		);

		$this->register_pixfort_icon( 'meta_icon', array( 'meta_show' => 'yes', 'design' => 'timeline' ) );

		$this->register_icon_source( 'list_icon', __( 'List icon', 'doctorpilot' ), array( 'design' => 'checklist' ) );

		$this->add_control(
			'list_icon',
			array(
				'label'       => __( 'List icon', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::ICONS,
				'description' => __( 'Leave empty for the built-in check mark.', 'doctorpilot' ),
				'condition'   => $this->icon_condition( 'list_icon', 'elementor', array( 'design' => 'checklist' ) ),
			)
		);

		$this->register_pixfort_icon( 'list_icon', array( 'design' => 'checklist' ) );

		$this->add_control(
			'list_separator',
			array(
				'label'       => __( 'Separator', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '–',
				'description' => __( 'Sits between the entry and its organization on the same line.', 'doctorpilot' ),
				'condition'   => array( 'design' => 'checklist', 'meta_show' => 'yes' ),
			)
		);

		$this->register_heading_controls();

		$this->end_controls_section();

		$this->register_heading_style();
		$this->register_rail_style();
		$this->register_card_style();
		$this->register_text_style();
		$this->register_list_style();
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
				'label'     => __( 'Rail', 'doctorpilot' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'design' => 'timeline' ),
			)
		);

		$this->add_control(
			'node_bg',
			array(
				'label'     => __( 'Node background', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#e9f6f0',
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
				'default'   => '#bfe3d5',
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
				'label'     => __( 'Card', 'doctorpilot' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'design' => 'timeline' ),
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
				'label'     => __( 'Text', 'doctorpilot' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'design' => 'timeline' ),
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
				'default'   => '#000000',
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
				'default'   => '#000000',
				'selectors' => array( '{{WRAPPER}} .dp-timeline__meta' => 'color: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Checklist style controls. These target the shared `dp-stats__ach*` classes,
	 * so the checklist matches the achievement lists elsewhere on the profile.
	 *
	 * @return void
	 */
	private function register_list_style(): void {
		$this->start_controls_section(
			'section_list_style',
			array(
				'label'     => __( 'List', 'doctorpilot' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'design' => 'checklist' ),
			)
		);

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

		if ( 'checklist' === (string) ( $settings['design'] ?? 'timeline' ) ) {
			$this->render_checklist( $settings, $items, $show_meta );
			return;
		}

		$node_icon = $this->icon_markup( $settings, 'node_icon' );
		$meta_icon = $this->icon_markup( $settings, 'meta_icon' );

		echo '<ul class="dp-timeline">';
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$title = trim( (string) ( $item['primary'] ?? $item['title'] ?? '' ) );
			$meta  = $this->meta_line( $item );

			echo '<li class="dp-timeline__item">';

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons_Manager or pixfort SVG markup.
			echo '<span class="dp-timeline__node" aria-hidden="true">' . $node_icon . '</span>';

			echo '<div class="dp-timeline__card">';
			if ( '' !== $title ) {
				echo '<div class="dp-timeline__title">' . esc_html( $title ) . '</div>';
			}
			if ( $show_meta && '' !== $meta ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons_Manager or pixfort SVG markup.
				echo '<div class="dp-timeline__meta">' . $meta_icon;
				echo '<span>' . esc_html( $meta ) . '</span>';
				echo '</div>';
			}
			echo '</div>';

			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * The checklist design: one icon-marked line per entry, using the same
	 * `dp-stats__ach*` markup as the achievement lists so both styles stay in
	 * step.
	 *
	 * @param array<string, mixed> $settings  Widget settings.
	 * @param array<int, mixed>    $items     Resolved entries.
	 * @param bool                 $show_meta Whether to append the organization.
	 * @return void
	 */
	private function render_checklist( array $settings, array $items, bool $show_meta ): void {
		$icon = $this->list_icon_markup( $settings );
		$sep  = trim( (string) ( $settings['list_separator'] ?? '' ) );

		echo '<div class="dp-stats dp-stats--list">';
		echo '<div class="dp-stats__section">';
		echo '<ul class="dp-stats__ach">';

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$title = trim( (string) ( $item['primary'] ?? $item['title'] ?? '' ) );
			$meta  = $show_meta ? $this->meta_line( $item ) : '';
			$line  = $this->checklist_line( $title, $meta, $sep );

			if ( '' === $line ) {
				continue;
			}

			echo '<li class="dp-stats__ach-item">';
			echo '<span class="dp-stats__ach-icon" aria-hidden="true">' . $icon . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons_Manager markup or the inline SVG fallback.
			echo '<span class="dp-stats__ach-text">' . esc_html( $line ) . '</span>';
			echo '</li>';
		}

		echo '</ul>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Join an entry and its organization onto one line, tolerating an empty
	 * separator or an entry that carries only one of the two.
	 *
	 * @param string $title Entry title.
	 * @param string $meta  Organization line.
	 * @param string $sep   Separator.
	 * @return string
	 */
	private function checklist_line( string $title, string $meta, string $sep ): string {
		if ( '' === $title || '' === $meta ) {
			return '' !== $title ? $title : $meta;
		}

		return '' !== $sep ? $title . ' ' . $sep . ' ' . $meta : $title . ' ' . $meta;
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
