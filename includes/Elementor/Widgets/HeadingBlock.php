<?php
/**
 * Advanced heading block Elementor widget.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A production-ready heading block: a main heading (optionally linked), a
 * subheading, and a description. Each element has its own HTML tag, show/hide
 * toggle, and display order, plus a full set of typography, color, gradient,
 * shadow, spacing, background, and border style controls with normal/hover
 * states. An optional decorative line can sit above, below, left, or right of
 * the content. All output is escaped; the widget is translation-ready and
 * relies only on core Elementor control APIs, so it works with or without Pro.
 */
final class HeadingBlock extends AbstractWidget {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'doctorpilot-heading-block';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Heading Block', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-t-letter';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_keywords(): array {
		return array( 'heading', 'title', 'subheading', 'description', 'text', 'doctorpilot' );
	}

	/**
	 * Allowed HTML tags for text elements.
	 *
	 * @return array<string, string>
	 */
	private function tag_options(): array {
		return array(
			'h1'   => 'H1',
			'h2'   => 'H2',
			'h3'   => 'H3',
			'h4'   => 'H4',
			'h5'   => 'H5',
			'h6'   => 'H6',
			'div'  => 'div',
			'span' => 'span',
			'p'    => 'p',
		);
	}

	// -------------------------------------------------------------------------
	// Controls.
	// -------------------------------------------------------------------------

	/**
	 * Register all controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->content_heading_controls();
		$this->content_subheading_controls();
		$this->content_description_controls();
		$this->content_layout_controls();

		$this->style_wrapper_controls();
		$this->register_text_style( 'heading', __( 'Heading', 'doctorpilot' ), '{{WRAPPER}} .dp-ah__heading' );
		$this->register_text_style( 'subheading', __( 'Subheading', 'doctorpilot' ), '{{WRAPPER}} .dp-ah__subheading' );
		$this->register_text_style( 'description', __( 'Description', 'doctorpilot' ), '{{WRAPPER}} .dp-ah__description' );
		$this->style_line_controls();
	}

	/**
	 * Main heading content controls.
	 *
	 * @return void
	 */
	private function content_heading_controls(): void {
		$this->start_controls_section( 'section_heading', array( 'label' => __( 'Heading', 'doctorpilot' ) ) );

		$this->add_control(
			'heading_show',
			array(
				'label'        => __( 'Show', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'heading_text',
			array(
				'label'     => __( 'Heading', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::TEXTAREA,
				'rows'      => 2,
				'default'   => __( 'Main heading', 'doctorpilot' ),
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'heading_show' => 'yes' ),
			)
		);

		$this->add_control(
			'heading_tag',
			array(
				'label'     => __( 'HTML tag', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => $this->tag_options(),
				'default'   => 'h2',
				'condition' => array( 'heading_show' => 'yes' ),
			)
		);

		$this->add_control(
			'heading_link',
			array(
				'label'         => __( 'Link', 'doctorpilot' ),
				'type'          => \Elementor\Controls_Manager::URL,
				'placeholder'   => 'https://',
				'dynamic'       => array( 'active' => true ),
				'condition'     => array( 'heading_show' => 'yes' ),
			)
		);

		$this->add_control(
			'heading_order',
			array(
				'label'     => __( 'Order', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'default'   => 1,
				'min'       => 1,
				'max'       => 3,
				'condition' => array( 'heading_show' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Subheading content controls.
	 *
	 * @return void
	 */
	private function content_subheading_controls(): void {
		$this->start_controls_section( 'section_subheading', array( 'label' => __( 'Subheading', 'doctorpilot' ) ) );

		$this->add_control(
			'subheading_show',
			array(
				'label'        => __( 'Show', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'subheading_text',
			array(
				'label'     => __( 'Subheading', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::TEXTAREA,
				'rows'      => 2,
				'default'   => __( 'Supporting subheading', 'doctorpilot' ),
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'subheading_show' => 'yes' ),
			)
		);

		$this->add_control(
			'subheading_tag',
			array(
				'label'     => __( 'HTML tag', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => $this->tag_options(),
				'default'   => 'h4',
				'condition' => array( 'subheading_show' => 'yes' ),
			)
		);

		$this->add_control(
			'subheading_order',
			array(
				'label'     => __( 'Order', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'default'   => 2,
				'min'       => 1,
				'max'       => 3,
				'condition' => array( 'subheading_show' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Description content controls.
	 *
	 * @return void
	 */
	private function content_description_controls(): void {
		$this->start_controls_section( 'section_description', array( 'label' => __( 'Description', 'doctorpilot' ) ) );

		$this->add_control(
			'description_show',
			array(
				'label'        => __( 'Show', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'description_text',
			array(
				'label'     => __( 'Description', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::TEXTAREA,
				'rows'      => 4,
				'default'   => __( 'A short supporting description that adds context beneath the heading.', 'doctorpilot' ),
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'description_show' => 'yes' ),
			)
		);

		$this->add_control(
			'description_tag',
			array(
				'label'     => __( 'HTML tag', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => $this->tag_options(),
				'default'   => 'div',
				'condition' => array( 'description_show' => 'yes' ),
			)
		);

		$this->add_control(
			'description_order',
			array(
				'label'     => __( 'Order', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'default'   => 3,
				'min'       => 1,
				'max'       => 3,
				'condition' => array( 'description_show' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Layout + decorative line content controls.
	 *
	 * @return void
	 */
	private function content_layout_controls(): void {
		$this->start_controls_section( 'section_layout', array( 'label' => __( 'Layout', 'doctorpilot' ) ) );

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

		$this->add_control(
			'line_show',
			array(
				'label'        => __( 'Decorative line', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'separator'    => 'before',
			)
		);

		$this->add_control(
			'line_position',
			array(
				'label'     => __( 'Line position', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'bottom',
				'options'   => array(
					'top'    => __( 'Above content', 'doctorpilot' ),
					'bottom' => __( 'Below content', 'doctorpilot' ),
					'left'   => __( 'Left of content', 'doctorpilot' ),
					'right'  => __( 'Right of content', 'doctorpilot' ),
				),
				'condition' => array( 'line_show' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Wrapper style: background, overlay, padding, border, radius, box shadow,
	 * width, and transition — with normal / hover tabs.
	 *
	 * @return void
	 */
	private function style_wrapper_controls(): void {
		$this->start_controls_section(
			'section_wrapper_style',
			array(
				'label' => __( 'Box', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'box_width',
			array(
				'label'      => __( 'Max width', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'vw' ),
				'range'      => array(
					'px' => array( 'min' => 100, 'max' => 1200 ),
					'%'  => array( 'min' => 10, 'max' => 100 ),
				),
				'selectors'  => array( '{{WRAPPER}} .dp-ah' => 'max-width: {{SIZE}}{{UNIT}};' ),
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

		$this->add_control(
			'box_transition',
			array(
				'label'     => __( 'Transition duration (s)', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 3, 'step' => 0.1 ) ),
				'selectors' => array( '{{WRAPPER}} .dp-ah' => 'transition: all {{SIZE}}s ease;' ),
			)
		);

		$this->start_controls_tabs( 'box_tabs' );

		$this->start_controls_tab( 'box_tab_normal', array( 'label' => __( 'Normal', 'doctorpilot' ) ) );
		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'box_bg',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .dp-ah',
			)
		);
		$this->add_control(
			'box_overlay',
			array(
				'label'     => __( 'Overlay color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-ah::before' => 'background: {{VALUE}}; opacity: 1;' ),
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
		$this->end_controls_tab();

		$this->start_controls_tab( 'box_tab_hover', array( 'label' => __( 'Hover', 'doctorpilot' ) ) );
		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'box_bg_hover',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .dp-ah:hover',
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'box_border_hover',
				'selector' => '{{WRAPPER}} .dp-ah:hover',
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'box_shadow_hover',
				'selector' => '{{WRAPPER}} .dp-ah:hover',
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Register a per-element text style section (typography, color normal/hover,
	 * gradient text, text shadow, spacing, padding).
	 *
	 * @param string $id       Element id (heading|subheading|description).
	 * @param string $label    Section label.
	 * @param string $selector The element's CSS selector.
	 * @return void
	 */
	private function register_text_style( string $id, string $label, string $selector ): void {
		$this->start_controls_section(
			'section_style_' . $id,
			array(
				'label'     => $label,
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( $id . '_show' => 'yes' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => $id . '_typography',
				'selector' => $selector,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Text_Shadow::get_type(),
			array(
				'name'     => $id . '_text_shadow',
				'selector' => $selector,
			)
		);

		$this->start_controls_tabs( $id . '_color_tabs' );

		$this->start_controls_tab( $id . '_color_normal', array( 'label' => __( 'Normal', 'doctorpilot' ) ) );
		$this->add_control(
			$id . '_color',
			array(
				'label'     => __( 'Text color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $selector => 'color: {{VALUE}};' ),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( $id . '_color_hover', array( 'label' => __( 'Hover', 'doctorpilot' ) ) );
		$this->add_control(
			$id . '_color_h',
			array(
				'label'     => __( 'Text color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					$selector . ':hover'                 => 'color: {{VALUE}};',
					'{{WRAPPER}} .dp-ah:hover ' . $this->relative( $selector ) => 'color: {{VALUE}};',
				),
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_control(
			$id . '_gradient',
			array(
				'label'        => __( 'Gradient text', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'      => $id . '_gradient_bg',
				'types'     => array( 'gradient' ),
				'selector'  => $selector . '.is-gradient',
				'condition' => array( $id . '_gradient' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			$id . '_padding',
			array(
				'label'      => __( 'Padding', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array( $selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				'separator'  => 'before',
			)
		);

		$this->add_responsive_control(
			$id . '_spacing',
			array(
				'label'      => __( 'Spacing below', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
				'selectors'  => array( $selector => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Strip the "{{WRAPPER}} " prefix from a selector so it can be nested inside a
	 * hover on the wrapper.
	 *
	 * @param string $selector Full selector.
	 * @return string
	 */
	private function relative( string $selector ): string {
		return trim( str_replace( '{{WRAPPER}}', '', $selector ) );
	}

	/**
	 * Decorative line style controls.
	 *
	 * @return void
	 */
	private function style_line_controls(): void {
		$this->start_controls_section(
			'section_line_style',
			array(
				'label'     => __( 'Decorative line', 'doctorpilot' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'line_show' => 'yes' ),
			)
		);

		$this->add_control(
			'line_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#E6E9EE',
				'selectors' => array( '{{WRAPPER}} .dp-ah' => '--dp-ah-line-color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'line_thickness',
			array(
				'label'      => __( 'Thickness', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 1, 'max' => 20 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 2 ),
				'selectors'  => array( '{{WRAPPER}} .dp-ah' => '--dp-ah-line-thickness: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'line_length',
			array(
				'label'      => __( 'Length', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array( 'min' => 10, 'max' => 600 ),
					'%'  => array( 'min' => 0, 'max' => 100 ),
				),
				'default'    => array( 'unit' => '%', 'size' => 100 ),
				'selectors'  => array( '{{WRAPPER}} .dp-ah' => '--dp-ah-line-length: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'line_gap',
			array(
				'label'      => __( 'Gap', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .dp-ah__line--top'    => 'margin-bottom: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .dp-ah__line--bottom' => 'margin-top: {{SIZE}}{{UNIT}};',
				),
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
		$tags     = array_keys( $this->tag_options() );

		// Build the ordered set of visible text elements.
		$elements = array();
		foreach ( array( 'heading', 'subheading', 'description' ) as $key ) {
			if ( 'yes' !== ( $settings[ $key . '_show' ] ?? '' ) ) {
				continue;
			}
			$text = (string) ( $settings[ $key . '_text' ] ?? '' );
			if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
				continue;
			}
			$elements[] = array(
				'key'   => $key,
				'order' => (int) ( $settings[ $key . '_order' ] ?? 1 ),
			);
		}

		usort(
			$elements,
			static function ( array $a, array $b ): int {
				return $a['order'] <=> $b['order'];
			}
		);

		$line_show = 'yes' === ( $settings['line_show'] ?? '' );
		$line_pos  = (string) ( $settings['line_position'] ?? 'bottom' );
		$line_pos  = in_array( $line_pos, array( 'top', 'bottom', 'left', 'right' ), true ) ? $line_pos : 'bottom';

		echo '<div class="dp-ah">';

		if ( $line_show && in_array( $line_pos, array( 'top', 'left', 'right' ), true ) ) {
			printf( '<span class="dp-ah__line dp-ah__line--%s" aria-hidden="true"></span>', esc_attr( $line_pos ) );
		}

		foreach ( $elements as $element ) {
			$this->render_element( $element['key'], $settings, $tags );
		}

		if ( $line_show && 'bottom' === $line_pos ) {
			echo '<span class="dp-ah__line dp-ah__line--bottom" aria-hidden="true"></span>';
		}

		echo '</div>';
	}

	/**
	 * Render a single text element.
	 *
	 * @param string               $key      Element key.
	 * @param array<string, mixed>  $settings Widget settings.
	 * @param array<int, string>    $tags     Allowed tags.
	 * @return void
	 */
	private function render_element( string $key, array $settings, array $tags ): void {
		$tag = (string) ( $settings[ $key . '_tag' ] ?? 'div' );
		$tag = in_array( $tag, $tags, true ) ? $tag : 'div';

		$class = 'dp-ah__' . $key;
		if ( 'yes' === ( $settings[ $key . '_gradient' ] ?? '' ) ) {
			$class .= ' is-gradient';
		}

		$raw = (string) ( $settings[ $key . '_text' ] ?? '' );

		// All three are plain text; the description keeps its line breaks.
		$content = 'description' === $key ? nl2br( esc_html( $raw ) ) : esc_html( $raw );

		// Optional link on the main heading only.
		if ( 'heading' === $key && ! empty( $settings['heading_link']['url'] ) ) {
			$this->add_link_attributes( 'heading_link', $settings['heading_link'] );
			$this->add_render_attribute( 'heading_link', 'class', 'dp-ah__link' );
			$content = sprintf(
				'<a %1$s>%2$s</a>',
				$this->get_render_attribute_string( 'heading_link' ),
				$content
			);
		}

		printf(
			'<%1$s class="%2$s">%3$s</%1$s>',
			tag_escape( $tag ),
			esc_attr( $class ),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $content is esc_html / wp_kses_post above; link built from Elementor render attributes.
			$content
		);
	}
}
