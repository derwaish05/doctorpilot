<?php
/**
 * Single-doctor page header Elementor widget (dynamic data).
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
 * Renders the hero header for a single doctor from canonical data: breadcrumb,
 * name, role, key facts, credential chips, photo with a specialty badge, and the
 * booking / call actions. The doctor is the selected one or the current queried
 * doctor, so a single design serves every doctor on a single-doctor template.
 */
final class PageHeader extends AbstractWidget {

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
		return 'doctorpilot-page-header';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Page header with dynamic data', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-single-post';
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array( 'label' => __( 'Header', 'doctorpilot' ) )
		);

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
			'placeholder_image',
			array(
				'label'       => __( 'Placeholder image', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::MEDIA,
				'default'     => array( 'url' => \Elementor\Utils::get_placeholder_image_src() ),
				'description' => __( 'Shown when the doctor has no photo.', 'doctorpilot' ),
			)
		);

		$this->add_control(
			'heading_photo_badge',
			array(
				'label'     => __( 'Photo badge', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'badge_international_text',
			array(
				'label'       => __( 'International badge text', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'International doctor', 'doctorpilot' ),
				'description' => __( 'Shown on the photo badge when the doctor is flagged as international; otherwise the primary specialty is shown.', 'doctorpilot' ),
			)
		);

		$this->add_control(
			'show_breadcrumb',
			array(
				'label'        => __( 'Show breadcrumb', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'doctorpilot' ),
				'label_off'    => __( 'Hide', 'doctorpilot' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'home_label',
			array(
				'label'     => __( 'Breadcrumb: home label', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Home', 'doctorpilot' ),
				'condition' => array( 'show_breadcrumb' => 'yes' ),
			)
		);

		$this->add_control(
			'doctors_label',
			array(
				'label'     => __( 'Breadcrumb: directory label', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Our Doctors', 'doctorpilot' ),
				'condition' => array( 'show_breadcrumb' => 'yes' ),
			)
		);

		$this->add_control(
			'availability',
			array(
				'label'       => __( 'Availability text', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'Sunday – Thursday', 'doctorpilot' ),
				'description' => __( 'Optional. Overrides the Availability row; if left blank, the doctor\'s location office hours are shown.', 'doctorpilot' ),
			)
		);

		$this->add_control(
			'heading_buttons',
			array(
				'label'     => __( 'Buttons', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'book_text',
			array(
				'label'       => __( 'Book button text', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'Book an Appointment', 'doctorpilot' ),
				'description' => __( 'Leave blank to use the doctor\'s booking label.', 'doctorpilot' ),
			)
		);

		$this->add_control(
			'book_icon',
			array(
				'label'   => __( 'Book button icon', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::ICONS,
				'default' => array( 'value' => 'far fa-calendar-check', 'library' => 'fa-regular' ),
			)
		);

		$this->add_control(
			'call_text',
			array(
				'label'   => __( 'Call button text', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Call Now', 'doctorpilot' ),
			)
		);

		$this->add_control(
			'call_icon',
			array(
				'label'   => __( 'Call button icon', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::ICONS,
				'default' => array( 'value' => 'fas fa-phone', 'library' => 'fa-solid' ),
			)
		);

		$this->add_control(
			'heading_certs',
			array(
				'label'     => __( 'Certifications', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'show_certs',
			array(
				'label'        => __( 'Show credential chips', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'doctorpilot' ),
				'label_off'    => __( 'Hide', 'doctorpilot' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'cert_icon',
			array(
				'label'     => __( 'Certification icon', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::ICONS,
				'default'   => array( 'value' => 'fas fa-award', 'library' => 'fa-solid' ),
				'condition' => array( 'show_certs' => 'yes' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_background',
			array(
				'label' => __( 'Background', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		// Full Elementor background control: Classic (color + image with position,
		// repeat, size, attachment), Gradient, and — when Elementor Pro is active —
		// Video and Slideshow. Defaults to the built-in teal gradient.
		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'           => 'hero_bg',
				'types'          => array( 'classic', 'gradient', 'video', 'slideshow' ),
				'selector'       => '{{WRAPPER}} .dp-hero',
				'fields_options' => array(
					'background'     => array( 'default' => 'gradient' ),
					'color'          => array( 'default' => '#136888' ),
					'color_b'        => array( 'default' => '#0E4E63' ),
					'gradient_angle' => array( 'default' => array( 'unit' => 'deg', 'size' => 120 ) ),
				),
			)
		);

		$this->add_control(
			'overlay',
			array(
				'label'     => __( 'Overlay color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-hero::before' => 'background: {{VALUE}}; opacity: 1;' ),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'accent',
			array(
				'label'     => __( 'Accent (buttons & badge)', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-hero' => '--dp-accent: {{VALUE}};' ),
				'separator' => 'before',
			)
		);

		$this->register_heading_controls();

		$this->end_controls_section();

		$this->register_heading_style();

		$this->register_breadcrumb_controls();
		$this->register_typography_controls();
		$this->register_image_controls();
		$this->register_badge_controls();
		$this->register_button_controls();
		$this->register_certs_controls();
	}

	/**
	 * Certification chip style controls — icon color/size plus chip text color,
	 * background, spacing, and radius. The icon selectors set `fill`/`stroke` to
	 * `currentColor` so the color applies to both the built-in inline SVG and any
	 * Font Awesome icon chosen in the content tab.
	 *
	 * @return void
	 */
	private function register_certs_controls(): void {
		$icon = '{{WRAPPER}} .dp-hero__chip .dp-hero__ico, {{WRAPPER}} .dp-hero__chip i, {{WRAPPER}} .dp-hero__chip svg';

		$this->start_controls_section(
			'section_certs',
			array(
				'label'     => __( 'Certifications', 'doctorpilot' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_certs' => 'yes' ),
			)
		);

		$this->add_control(
			'cert_icon_color',
			array(
				'label'     => __( 'Icon color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#136888',
				'selectors' => array(
					// Line SVG (built-in award) + FA webfont follow `color`; the
					// award icon strokes with currentColor, so no fill is forced.
					'{{WRAPPER}} .dp-hero__chip .dp-hero__ico, {{WRAPPER}} .dp-hero__chip i' => 'color: {{VALUE}};',
					// Font Awesome inline SVG needs an explicit fill.
					'{{WRAPPER}} .dp-hero__chip svg.e-font-icon-svg' => 'color: {{VALUE}}; fill: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'cert_icon_size',
			array(
				'label'      => __( 'Icon size', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 32 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 16 ),
				'selectors'  => array( $icon => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; font-size: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'cert_text_color',
			array(
				'label'     => __( 'Text color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-hero__chip' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'cert_bg',
			array(
				'label'     => __( 'Chip background', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-hero__chip' => 'background: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => 'cert_typography', 'selector' => '{{WRAPPER}} .dp-hero__chip' )
		);

		$this->add_responsive_control(
			'cert_gap',
			array(
				'label'      => __( 'Gap between chips', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-hero__tags' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'cert_padding',
			array(
				'label'      => __( 'Chip padding', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .dp-hero__chip' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'cert_radius',
			array(
				'label'      => __( 'Chip radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .dp-hero__chip' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Button style controls: shared typography/spacing plus per-button (Book &
	 * Call) color, background, border, and box-shadow with Normal / Hover tabs.
	 *
	 * @return void
	 */
	private function register_button_controls(): void {
		$this->start_controls_section(
			'section_buttons',
			array(
				'label' => __( 'Buttons', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'selector' => '{{WRAPPER}} .dp-hero__btn',
			)
		);

		$this->add_responsive_control(
			'button_icon_size',
			array(
				'label'      => __( 'Icon size', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 40 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 20 ),
				'selectors'  => array( '{{WRAPPER}} .dp-hero__btn .dp-hero__ico, {{WRAPPER}} .dp-hero__btn i, {{WRAPPER}} .dp-hero__btn svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; font-size: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'button_icon_gap',
			array(
				'label'      => __( 'Icon spacing', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-hero__btn' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'button_padding',
			array(
				'label'      => __( 'Padding', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .dp-hero__btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'button_radius',
			array(
				'label'      => __( 'Border radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .dp-hero__btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->register_button_variant_controls( 'primary', __( 'Primary button (Book)', 'doctorpilot' ), '.dp-hero__btn--primary' );
		$this->register_button_variant_controls( 'secondary', __( 'Secondary button (Call)', 'doctorpilot' ), '.dp-hero__btn--ghost' );

		$this->end_controls_section();
	}

	/**
	 * Register color/background/border/shadow controls (Normal + Hover) for one
	 * button variant.
	 *
	 * @param string $prefix   Control-name prefix.
	 * @param string $heading  Section heading.
	 * @param string $selector The variant's class selector (relative to .dp-hero).
	 * @return void
	 */
	private function register_button_variant_controls( string $prefix, string $heading, string $selector ): void {
		$base  = '{{WRAPPER}} ' . $selector;
		$hover = '{{WRAPPER}} ' . $selector . ':hover';

		// Icon targets: inline SVG (uses currentColor), Elementor <i>, and <svg>.
		$icon       = $base . ' .dp-hero__ico, ' . $base . ' i, ' . $base . ' svg';
		$icon_hover = $hover . ' .dp-hero__ico, ' . $hover . ' i, ' . $hover . ' svg';

		$this->add_control(
			$prefix . '_heading',
			array(
				'label'     => $heading,
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
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
				'default'   => '#136888',
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
			array(
				'name'     => $prefix . '_border',
				'selector' => $base,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => $prefix . '_shadow',
				'selector' => $base,
			)
		);
	}

	/**
	 * Breadcrumb style controls: typography, link / hover / current / separator
	 * colors, and spacing below the trail.
	 *
	 * @return void
	 */
	private function register_breadcrumb_controls(): void {
		$this->start_controls_section(
			'section_breadcrumb',
			array(
				'label'     => __( 'Breadcrumb', 'doctorpilot' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_breadcrumb' => 'yes' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'breadcrumb_typography',
				'selector' => '{{WRAPPER}} .dp-hero__crumbs',
			)
		);

		$this->add_control(
			'breadcrumb_link_color',
			array(
				'label'     => __( 'Link color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-hero__crumbs a' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'breadcrumb_link_hover_color',
			array(
				'label'     => __( 'Link hover color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-hero__crumbs a:hover' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'breadcrumb_current_color',
			array(
				'label'     => __( 'Current item color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-hero__current' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'breadcrumb_sep_color',
			array(
				'label'     => __( 'Separator color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-hero__sep' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'breadcrumb_align',
			array(
				'label'     => __( 'Alignment', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array( 'title' => __( 'Left', 'doctorpilot' ), 'icon' => 'eicon-text-align-left' ),
					'center' => array( 'title' => __( 'Center', 'doctorpilot' ), 'icon' => 'eicon-text-align-center' ),
					'right'  => array( 'title' => __( 'Right', 'doctorpilot' ), 'icon' => 'eicon-text-align-right' ),
				),
				'selectors' => array( '{{WRAPPER}} .dp-hero__crumbs' => 'text-align: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'breadcrumb_spacing',
			array(
				'label'      => __( 'Spacing below', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-hero__crumbs' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Typography (and color) controls for the hero text: name, role, and the meta
	 * row labels and values.
	 *
	 * @return void
	 */
	private function register_typography_controls(): void {
		$this->start_controls_section(
			'section_typography',
			array(
				'label' => __( 'Typography', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'heading_name',
			array(
				'label' => __( 'Name', 'doctorpilot' ),
				'type'  => \Elementor\Controls_Manager::HEADING,
			)
		);

		$this->add_control(
			'name_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-hero__name' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'name_typography',
				'selector' => '{{WRAPPER}} .dp-hero__name',
			)
		);

		$this->add_control(
			'heading_role',
			array(
				'label'     => __( 'Role / subtitle', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'role_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-hero__role' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'role_typography',
				'selector' => '{{WRAPPER}} .dp-hero__role',
			)
		);

		$this->add_control(
			'heading_meta_label',
			array(
				'label'     => __( 'Meta label', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'meta_label_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-hero__k' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'meta_label_typography',
				'selector' => '{{WRAPPER}} .dp-hero__k',
			)
		);

		$this->add_control(
			'heading_meta_value',
			array(
				'label'     => __( 'Meta value', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'meta_value_color',
			array(
				'label'     => __( 'Color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-hero__v' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'meta_value_typography',
				'selector' => '{{WRAPPER}} .dp-hero__v',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Image (doctor photo) style controls — the full Elementor image treatment:
	 * size, radius, border, shadow, CSS filters (normal + hover), opacity, and a
	 * hover animation.
	 *
	 * @return void
	 */
	private function register_image_controls(): void {
		$this->start_controls_section(
			'section_image',
			array(
				'label' => __( 'Image', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'image_width',
			array(
				'label'      => __( 'Width', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array( 'min' => 120, 'max' => 640 ),
					'%'  => array( 'min' => 20, 'max' => 100 ),
				),
				'selectors'  => array( '{{WRAPPER}} .dp-hero__photo' => 'width: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'image_ratio',
			array(
				'label'      => __( 'Aspect ratio (H)', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 200, 'max' => 900 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-hero__photo img, {{WRAPPER}} .dp-hero__placeholder' => 'aspect-ratio: auto; height: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'image_object_fit',
			array(
				'label'     => __( 'Object fit', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => '',
				'options'   => array(
					''        => __( 'Default', 'doctorpilot' ),
					'cover'   => __( 'Cover', 'doctorpilot' ),
					'contain' => __( 'Contain', 'doctorpilot' ),
					'fill'    => __( 'Fill', 'doctorpilot' ),
				),
				'selectors' => array( '{{WRAPPER}} .dp-hero__photo img' => 'object-fit: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'image_radius',
			array(
				'label'      => __( 'Border radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .dp-hero__photo' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'image_border',
				'selector' => '{{WRAPPER}} .dp-hero__photo',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'image_shadow',
				'selector' => '{{WRAPPER}} .dp-hero__photo',
			)
		);

		$this->add_control(
			'image_hover_animation',
			array(
				'label' => __( 'Hover animation', 'doctorpilot' ),
				'type'  => \Elementor\Controls_Manager::HOVER_ANIMATION,
			)
		);

		$this->start_controls_tabs( 'image_effects_tabs' );

		$this->start_controls_tab( 'image_effects_normal', array( 'label' => __( 'Normal', 'doctorpilot' ) ) );

		$this->add_control(
			'image_opacity',
			array(
				'label'     => __( 'Opacity', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.01 ) ),
				'selectors' => array( '{{WRAPPER}} .dp-hero__photo img' => 'opacity: {{SIZE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Css_Filter::get_type(),
			array(
				'name'     => 'image_filters',
				'selector' => '{{WRAPPER}} .dp-hero__photo img',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'image_effects_hover', array( 'label' => __( 'Hover', 'doctorpilot' ) ) );

		$this->add_control(
			'image_opacity_hover',
			array(
				'label'     => __( 'Opacity', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.01 ) ),
				'selectors' => array( '{{WRAPPER}} .dp-hero__photo:hover img' => 'opacity: {{SIZE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Css_Filter::get_type(),
			array(
				'name'     => 'image_filters_hover',
				'selector' => '{{WRAPPER}} .dp-hero__photo:hover img',
			)
		);

		$this->add_control(
			'image_transition',
			array(
				'label'     => __( 'Transition duration (s)', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 3, 'step' => 0.1 ) ),
				'selectors' => array( '{{WRAPPER}} .dp-hero__photo img' => 'transition-duration: {{SIZE}}s;' ),
			)
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Badge style controls — typography, colors, spacing, radius, and border for
	 * the photo badge (specialty / "International doctor").
	 *
	 * @return void
	 */
	private function register_badge_controls(): void {
		$this->start_controls_section(
			'section_badge',
			array(
				'label' => __( 'Photo badge', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'badge_position',
			array(
				'label'   => __( 'Position', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'bottom-left',
				'options' => array(
					'top-left'     => __( 'Top left', 'doctorpilot' ),
					'top-right'    => __( 'Top right', 'doctorpilot' ),
					'bottom-left'  => __( 'Bottom left', 'doctorpilot' ),
					'bottom-right' => __( 'Bottom right', 'doctorpilot' ),
				),
			)
		);

		$this->add_responsive_control(
			'badge_offset',
			array(
				'label'      => __( 'Edge offset', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-hero__badge' => '--dp-badge-offset: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'badge_color',
			array(
				'label'     => __( 'Text color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-hero__badge' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'badge_bg',
			array(
				'label'     => __( 'Background color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-hero__badge' => 'background: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'badge_typography',
				'selector' => '{{WRAPPER}} .dp-hero__badge',
			)
		);

		$this->add_responsive_control(
			'badge_padding',
			array(
				'label'      => __( 'Padding', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .dp-hero__badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'badge_radius',
			array(
				'label'      => __( 'Border radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .dp-hero__badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'badge_border',
				'selector' => '{{WRAPPER}} .dp-hero__badge',
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

		$view = $this->prepare( $data, $settings );

		// Above the hero, not inside it. The hero's own title is the doctor's
		// name; this is the optional section label a page might put over it.
		$this->render_heading( $settings );

		echo '<section class="dp-hero">';
		echo '<div class="dp-hero__inner">';

		// Breadcrumb.
		if ( 'yes' === (string) ( $settings['show_breadcrumb'] ?? 'yes' ) ) {
			echo '<nav class="dp-hero__crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'doctorpilot' ) . '">';
			printf( '<a href="%1$s">%2$s</a>', esc_url( home_url( '/' ) ), esc_html( $view['home_label'] ) );
			if ( '' !== $view['archive'] ) {
				echo ' <span class="dp-hero__sep">&rsaquo;</span> ';
				printf( '<a href="%1$s">%2$s</a>', esc_url( $view['archive'] ), esc_html( $view['doctors_label'] ) );
			}
			echo ' <span class="dp-hero__sep">&rsaquo;</span> ';
			echo '<span class="dp-hero__current">' . esc_html( $view['name'] ) . '</span>';
			echo '</nav>';
		}

		echo '<div class="dp-hero__grid">';

		// Media column.
		echo '<div class="dp-hero__media">';
		echo '<div class="dp-hero__photo">';
		$photo_url = '' !== $view['photo_url'] ? $view['photo_url'] : (string) ( $settings['placeholder_image']['url'] ?? '' );
		if ( '' !== $photo_url ) {
			$img_class = 'dp-hero__img';
			$anim      = (string) ( $settings['image_hover_animation'] ?? '' );
			if ( '' !== $anim ) {
				$img_class .= ' elementor-animation-' . sanitize_html_class( $anim );
			}
			printf(
				'<img class="%1$s" src="%2$s" alt="%3$s" loading="lazy" />',
				esc_attr( $img_class ),
				esc_url( $photo_url ),
				esc_attr( $view['photo_alt'] )
			);
		} else {
			echo '<span class="dp-hero__placeholder" aria-hidden="true"></span>';
		}
		// The photo badge shows the international text when the flag is set,
		// otherwise the primary specialty.
		if ( $view['international'] ) {
			$badge = '' !== (string) ( $settings['badge_international_text'] ?? '' )
				? (string) $settings['badge_international_text']
				: __( 'International doctor', 'doctorpilot' );
		} else {
			$badge = $view['specialty'];
		}
		if ( '' !== $badge ) {
			$allowed_pos = array( 'top-left', 'top-right', 'bottom-left', 'bottom-right' );
			$badge_pos   = (string) ( $settings['badge_position'] ?? 'bottom-left' );
			$badge_pos   = in_array( $badge_pos, $allowed_pos, true ) ? $badge_pos : 'bottom-left';
			printf(
				'<span class="dp-hero__badge dp-hero__badge--%1$s">%2$s</span>',
				esc_attr( $badge_pos ),
				esc_html( $badge )
			);
		}
		echo '</div>';
		echo '</div>';

		// Body column.
		echo '<div class="dp-hero__body">';
		echo '<h1 class="dp-hero__name">' . esc_html( $view['name'] ) . '</h1>';
		if ( '' !== $view['role'] ) {
			echo '<p class="dp-hero__role">' . esc_html( $view['role'] ) . '</p>';
		}

		echo '<div class="dp-hero__meta">';
		$this->meta_row( __( 'Primary Specialty', 'doctorpilot' ), $view['specialty'] );
		$this->meta_row( __( 'Department', 'doctorpilot' ), $view['department'] );
		$this->meta_row( __( 'Languages', 'doctorpilot' ), $view['languages'] );
		$this->meta_row( __( 'Availability', 'doctorpilot' ), $view['availability'] );
		$this->meta_row( __( 'Email', 'doctorpilot' ), $view['email'] );
		$this->meta_row( __( 'Phone', 'doctorpilot' ), $view['phone'] );
		echo '</div>';

		if ( 'yes' === (string) ( $settings['show_certs'] ?? 'yes' ) && array() !== $view['certs'] ) {
			echo '<ul class="dp-hero__tags">';
			foreach ( $view['certs'] as $cert ) {
				echo '<li class="dp-hero__chip">';
				$this->render_cert_icon( $settings['cert_icon'] ?? array() );
				echo '<span>' . esc_html( $cert ) . '</span>';
				echo '</li>';
			}
			echo '</ul>';
		}

		if ( '' !== $view['book_url'] || '' !== $view['tel'] ) {
			echo '<div class="dp-hero__actions">';
			if ( '' !== $view['book_url'] ) {
				$book_label = '' !== (string) ( $settings['book_text'] ?? '' ) ? (string) $settings['book_text'] : $view['book_label'];
				printf(
					'<a class="dp-hero__btn dp-hero__btn--primary" href="%1$s"%2$s>',
					esc_url( $view['book_url'] ),
					$view['book_target'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant, safe attribute string.
				);
				$this->render_button_icon( $settings['book_icon'] ?? array(), 'icon_calendar' );
				echo '<span>' . esc_html( $book_label ) . '</span></a>';
			}
			if ( '' !== $view['tel'] ) {
				$call_label = '' !== (string) ( $settings['call_text'] ?? '' ) ? (string) $settings['call_text'] : __( 'Call Now', 'doctorpilot' );
				printf( '<a class="dp-hero__btn dp-hero__btn--ghost" href="tel:%1$s">', esc_attr( $view['tel'] ) );
				$this->render_button_icon( $settings['call_icon'] ?? array(), 'icon_phone' );
				echo '<span>' . esc_html( $call_label ) . '</span></a>';
			}
			echo '</div>';
		}

		echo '</div>'; // body.
		echo '</div>'; // grid.
		echo '</div>'; // inner.
		echo '</section>';
	}

	/**
	 * Reduce the view model + settings to the flat values the header renders.
	 *
	 * @param array<string, mixed> $data     Doctor view model.
	 * @param array<string, mixed> $settings Widget settings.
	 * @return array<string, mixed>
	 */
	private function prepare( array $data, array $settings ): array {
		$specialty  = isset( $data['primary_specialty']['name'] ) ? (string) $data['primary_specialty']['name'] : '';
		$department = isset( $data['primary_department']['name'] ) ? (string) $data['primary_department']['name'] : '';

		$languages = array();
		foreach ( (array) ( $data['languages'] ?? array() ) as $term ) {
			if ( isset( $term['name'] ) ) {
				$languages[] = (string) $term['name'];
			}
		}

		// Role: first leadership entry (string for line input, array for legacy
		// rows), falling back to the primary specialty.
		$role = '';
		if ( ! empty( $data['leadership'] ) && is_array( $data['leadership'] ) ) {
			$first = reset( $data['leadership'] );
			if ( is_string( $first ) ) {
				$role = $first;
			} elseif ( is_array( $first ) ) {
				$role = (string) ( $first['title'] ?? $first['name'] ?? '' );
			}
		}
		if ( '' === $role ) {
			$role = $specialty;
		}

		// Email + phone + office hours: the first location that provides each.
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

		// Availability: the widget's text override, else the location office hours.
		$availability = (string) ( $settings['availability'] ?? '' );
		if ( '' === $availability ) {
			$availability = $hours;
		}

		$certs = array();
		foreach ( (array) ( $data['certifications'] ?? array() ) as $cert ) {
			$name = (string) ( $cert['name'] ?? '' );
			if ( '' !== $name ) {
				$certs[] = $name;
			}
		}

		$booking     = is_array( $data['booking'] ?? null ) ? $data['booking'] : array();
		$book_url    = (string) ( $booking['url'] ?? '' );
		$book_label  = '' !== (string) ( $booking['label'] ?? '' ) ? (string) $booking['label'] : __( 'Book an Appointment', 'doctorpilot' );
		$book_target = '_blank' === ( $booking['target'] ?? '' ) ? ' target="_blank" rel="noopener"' : '';

		// A tel: link — prefer a phone, else a phone-type booking destination.
		$tel_source = '' !== $phone ? $phone : ( 0 === strpos( $book_url, 'tel:' ) ? substr( $book_url, 4 ) : '' );
		$tel        = preg_replace( '/[^0-9+]/', '', $tel_source );

		$photo     = is_array( $data['photo'] ?? null ) ? $data['photo'] : array();
		$photo_url = (string) ( $photo['url'] ?? '' );

		return array(
			'name'          => (string) ( $data['name'] ?? '' ),
			'role'          => $role,
			'specialty'     => $specialty,
			'department'    => $department,
			'languages'     => implode( ', ', $languages ),
			'availability'  => $availability,
			'email'         => $email,
			'phone'         => $phone,
			'tel'           => (string) $tel,
			'certs'         => $certs,
			'international'  => ! empty( $data['is_international'] ),
			'book_url'      => $book_url,
			'book_label'    => $book_label,
			'book_target'   => $book_target,
			'photo_url'     => $photo_url,
			'photo_alt'     => '' !== (string) ( $photo['alt'] ?? '' ) ? (string) $photo['alt'] : (string) ( $data['name'] ?? '' ),
			'archive'       => (string) get_post_type_archive_link( DoctorPostType::POST_TYPE ),
			'home_label'    => '' !== (string) ( $settings['home_label'] ?? '' ) ? (string) $settings['home_label'] : __( 'Home', 'doctorpilot' ),
			'doctors_label' => '' !== (string) ( $settings['doctors_label'] ?? '' ) ? (string) $settings['doctors_label'] : __( 'Our Doctors', 'doctorpilot' ),
		);
	}

	/**
	 * Render one label/value meta row when the value is non-empty.
	 *
	 * @param string $label Row label.
	 * @param string $value Row value.
	 * @return void
	 */
	private function meta_row( string $label, string $value ): void {
		if ( '' === $value ) {
			return;
		}
		echo '<div class="dp-hero__row">';
		echo '<span class="dp-hero__k">' . esc_html( $label ) . '</span>';
		echo '<span class="dp-hero__v">: ' . esc_html( $value ) . '</span>';
		echo '</div>';
	}

	/**
	 * Render a certification chip icon: the editor-chosen Elementor icon when set,
	 * otherwise the built-in inline award SVG.
	 *
	 * @param mixed $icon The ICONS control value.
	 * @return void
	 */
	private function render_cert_icon( $icon ): void {
		if ( is_array( $icon ) && '' !== (string) ( $icon['value'] ?? '' ) ) {
			\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true', 'class' => 'dp-hero__ico' ) );
			return;
		}

		$this->icon_award();
	}

	/**
	 * Award icon for credential chips.
	 *
	 * @return void
	 */
	private function icon_award(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static inline SVG.
		echo '<svg class="dp-hero__ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="6"/><path d="M15.5 12.9 17 22l-5-3-5 3 1.5-9.1"/></svg>';
	}

	/**
	 * Render a button icon: the editor-chosen Elementor icon when set, otherwise
	 * the built-in inline SVG fallback.
	 *
	 * @param mixed  $icon     The ICONS control value.
	 * @param string $fallback Name of the fallback icon method.
	 * @return void
	 */
	private function render_button_icon( $icon, string $fallback ): void {
		if ( is_array( $icon ) && '' !== (string) ( $icon['value'] ?? '' ) ) {
			\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true', 'class' => 'dp-hero__ico' ) );
			return;
		}

		$this->{$fallback}();
	}

	/**
	 * Calendar icon for the booking button.
	 *
	 * @return void
	 */
	private function icon_calendar(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static inline SVG.
		echo '<svg class="dp-hero__ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
	}

	/**
	 * Phone icon for the call button.
	 *
	 * @return void
	 */
	private function icon_phone(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static inline SVG.
		echo '<svg class="dp-hero__ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>';
	}
}
