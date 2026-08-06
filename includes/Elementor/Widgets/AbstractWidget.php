<?php
/**
 * Base class for DoctorPilot Elementor widgets.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor\Widgets;

use BrainStudioz\DoctorPilot\Elementor\Integration;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared behaviour for DoctorPilot widgets.
 *
 * Every widget belongs to the DoctorPilot category, depends on the shared
 * frontend stylesheet (loaded only where a widget is present), and renders
 * through the same view/search/booking services as native output — never a
 * parallel query engine and never raw meta.
 */
abstract class AbstractWidget extends Widget_Base {

	/**
	 * Widget category.
	 *
	 * @return array<int, string>
	 */
	public function get_categories(): array {
		return array( 'doctorpilot' );
	}

	/**
	 * Style dependencies.
	 *
	 * @return array<int, string>
	 */
	public function get_style_depends(): array {
		return array( Integration::STYLE_HANDLE );
	}

	/**
	 * Default keywords for discovery in the editor.
	 *
	 * @return array<int, string>
	 */
	public function get_keywords(): array {
		return array( 'doctor', 'doctorpilot', 'directory', 'medical', 'hospital' );
	}

	/**
	 * Allowed tags for a widget's built-in heading.
	 *
	 * @return array<string, string>
	 */
	private function heading_tags(): array {
		return array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'div' );
	}

	/**
	 * Register the optional built-in heading content controls. Call inside an
	 * open content section. The heading renders only when the widget has data, so
	 * the section (eyebrow + heading) disappears for doctors without content.
	 *
	 * @return void
	 */
	protected function register_heading_controls(): void {
		$this->add_control(
			'heading_show',
			array(
				'label'        => __( 'Show heading', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
			)
		);
		$this->add_control(
			'eyebrow_text',
			array(
				'label'     => __( 'Eyebrow', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'heading_show' => 'yes' ),
			)
		);
		$this->add_control(
			'heading_text',
			array(
				'label'     => __( 'Heading', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'heading_show' => 'yes' ),
			)
		);
		$this->add_control(
			'heading_tag',
			array(
				'label'     => __( 'Heading tag', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => $this->heading_tags(),
				'default'   => 'h2',
				'condition' => array( 'heading_show' => 'yes' ),
			)
		);
	}

	/**
	 * Register the built-in heading style section.
	 *
	 * @return void
	 */
	protected function register_heading_style(): void {
		$this->start_controls_section(
			'section_heading_style',
			array(
				'label'     => __( 'Heading', 'doctorpilot' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'heading_show' => 'yes' ),
			)
		);

		$this->add_control( 'eyebrow_heading', array( 'label' => __( 'Eyebrow', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING ) );
		$this->add_control(
			'eyebrow_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-wh__eyebrow' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'eyebrow_typography', 'selector' => '{{WRAPPER}} .dp-wh__eyebrow' )
		);

		$this->add_control( 'wh_title_heading', array( 'label' => __( 'Heading', 'doctorpilot' ), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_control(
			'wh_title_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-wh__title' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'wh_title_typography', 'selector' => '{{WRAPPER}} .dp-wh__title' )
		);

		$this->add_responsive_control(
			'wh_spacing',
			array(
				'label'      => __( 'Spacing below', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-wh' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
				'separator'  => 'before',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Output the built-in heading (eyebrow + heading) when enabled and non-empty.
	 * Call from render only after confirming the widget has data.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @return void
	 */
	protected function render_heading( array $settings ): void {
		if ( 'yes' !== ( $settings['heading_show'] ?? '' ) ) {
			return;
		}

		$eyebrow = trim( (string) ( $settings['eyebrow_text'] ?? '' ) );
		$heading = trim( (string) ( $settings['heading_text'] ?? '' ) );
		if ( '' === $eyebrow && '' === $heading ) {
			return;
		}

		$tag = (string) ( $settings['heading_tag'] ?? 'h2' );
		$tag = array_key_exists( $tag, $this->heading_tags() ) ? $tag : 'h2';

		echo '<div class="dp-wh">';
		if ( '' !== $eyebrow ) {
			echo '<div class="dp-wh__eyebrow">' . esc_html( $eyebrow ) . '</div>';
		}
		if ( '' !== $heading ) {
			printf( '<%1$s class="dp-wh__title">%2$s</%1$s>', tag_escape( $tag ), esc_html( $heading ) );
		}
		echo '</div>';
	}

	/**
	 * Append a full "icon box" control set to the current open style section:
	 * padding, border radius, transition, and Background / Border / Box-shadow
	 * groups with Normal + Hover tabs. Lets a plain glyph become a styled box.
	 *
	 * @param string $selector Icon element selector (with {{WRAPPER}}).
	 * @param string $hover    Hover-state selector (with {{WRAPPER}}).
	 * @return void
	 */
	protected function register_icon_box_controls( string $selector, string $hover ): void {
		$this->add_responsive_control(
			'iconbox_padding',
			array(
				'label'      => __( 'Padding', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( $selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				'separator'  => 'before',
			)
		);

		$this->add_responsive_control(
			'iconbox_radius',
			array(
				'label'      => __( 'Border radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( $selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'iconbox_transition',
			array(
				'label'     => __( 'Transition duration (s)', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 3, 'step' => 0.1 ) ),
				'selectors' => array( $selector => 'transition: all {{SIZE}}s ease;' ),
			)
		);

		$this->start_controls_tabs( 'iconbox_tabs' );

		$this->start_controls_tab( 'iconbox_tab_normal', array( 'label' => __( 'Normal', 'doctorpilot' ) ) );
		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array( 'name' => 'iconbox_bg', 'types' => array( 'classic', 'gradient' ), 'selector' => $selector )
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array( 'name' => 'iconbox_border', 'selector' => $selector )
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array( 'name' => 'iconbox_shadow', 'selector' => $selector )
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'iconbox_tab_hover', array( 'label' => __( 'Hover', 'doctorpilot' ) ) );
		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array( 'name' => 'iconbox_bg_hover', 'types' => array( 'classic', 'gradient' ), 'selector' => $hover )
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array( 'name' => 'iconbox_border_hover', 'selector' => $hover )
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array( 'name' => 'iconbox_shadow_hover', 'selector' => $hover )
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();
	}

	/**
	 * Resolve the doctor to render.
	 *
	 * The current queried doctor (a single-doctor page) always wins, so a single
	 * template shows the correct doctor on every page. The widget's Doctor ID is
	 * only a fallback — for previewing in the editor or placing the widget on a
	 * page that is not a doctor.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @return int Doctor post ID, or 0 when none.
	 */
	protected function resolve_doctor_id( array $settings ): int {
		$queried = get_queried_object();
		if ( $queried instanceof \WP_Post && DoctorPostType::POST_TYPE === $queried->post_type ) {
			return (int) $queried->ID;
		}

		return isset( $settings['doctor_id'] ) ? max( 0, (int) $settings['doctor_id'] ) : 0;
	}

	/**
	 * Add a responsive columns control to the current controls section.
	 *
	 * @param int                $default   Default column count.
	 * @param array<int, string> $selectors Extra grid selectors (with {{WRAPPER}}) the
	 *                                      column count should drive, beyond the card grid.
	 * @return void
	 */
	protected function add_columns_control( int $default = 3, array $selectors = array() ): void {
		$targets = array_merge( array( '{{WRAPPER}} .dp-directory' ), $selectors );
		$rules   = array();

		foreach ( $targets as $target ) {
			$rules[ $target ] = 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));';
		}

		$this->add_responsive_control(
			'columns',
			array(
				'label'          => __( 'Columns', 'doctorpilot' ),
				'type'           => \Elementor\Controls_Manager::SELECT,
				'default'        => (string) $default,
				'tablet_default' => '2',
				'mobile_default' => '1',
				'options'        => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
				'selectors'      => $rules,
			)
		);
	}

	/**
	 * Add card name typography/color style controls bound to scoped selectors.
	 *
	 * @return void
	 */
	protected function add_card_style_controls(): void {
		$this->start_controls_section(
			'section_card_style',
			array(
				'label' => __( 'Card', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'name_color',
			array(
				'label'     => __( 'Name color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .dp-card__name a' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'name_typography',
				'selector' => '{{WRAPPER}} .dp-card__name',
			)
		);

		$this->add_responsive_control(
			'card_gap',
			array(
				'label'      => __( 'Gap', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .dp-directory' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}
}
