<?php
/**
 * Premium doctor-card design controls.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor\Widgets\Concerns;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the card design, image, badge, metadata and button style controls.
 *
 * Every control writes a CSS custom property rather than a concrete declaration
 * on a component selector. A single token drives every component that reads it,
 * so a radius set once applies to the card, its skeleton and its overlay
 * together, and an unstyled widget renders from the stylesheet's own defaults,
 * which is what lets a freshly dropped widget look finished.
 *
 * **The target is `{{WRAPPER}} .dp-dir`, not `{{WRAPPER}}`, and that matters.**
 * `directory-premium.css` declares the token defaults on `.dp-dir` and the
 * presets on `.dp-dir--preset-*` — both of which land on the directory root
 * element itself. A custom property declared on an element always beats one
 * inherited from an ancestor, whatever the ancestor's specificity, so a control
 * written to the Elementor wrapper is silently ignored: the value reaches
 * `.dp-dir`, which then overwrites it for every descendant that reads it.
 * Writing to `{{WRAPPER}} .dp-dir` puts the control on the same element as the
 * defaults, at a higher specificity than either the default or a preset, so an
 * explicit choice wins over both. Every widget using this trait renders a
 * `.dp-dir` root — `templates/directory.php` and `templates/carousel-doctor.php`
 * in both of its display modes — and a widget that stopped doing so would lose
 * the stylesheet's defaults as well, so the two cannot drift apart silently.
 *
 * Two configurations are exempt, and knowingly so. The overlay preset's
 * `.dp-card--no-media` and `.dp-card--row` blocks re-declare their tokens on the
 * card, which is a descendant of `.dp-dir`, so the same rule that used to defeat
 * this whole trait now protects a legibility guard: white type never lands on a
 * card with no photo behind it. The cost is that roughly ten text and
 * secondary-button colour controls are inert under Image overlay when the photo
 * is switched off or the widget is an image-card list, with nothing in the panel
 * to say so beyond the preset's own description. Fixing that properly means
 * giving the guard its own fallback token instead of re-declaring the token the
 * control owns; it is not worth doing blind.
 *
 * All controls are additive: no pre-existing setting key is reused or
 * repurposed, so Elementor's stored settings for existing instances survive and
 * nothing breaks on upgrade. Appearance, however, does change — `card_preset`
 * defaults to `modern`, and Elementor supplies a control default to instances
 * saved before the control existed. That is the intended upgrade. Selecting
 * `classic` restores the previous card exactly.
 */
trait CardDesignControls {

	/**
	 * Register every premium card style section.
	 *
	 * @return void
	 */
	protected function register_card_design_controls(): void {
		$this->register_card_preset_section();
		$this->register_card_box_style();
		$this->register_card_image_style();
		$this->register_card_typography_style();
		$this->register_card_badge_style();
		$this->register_card_button_style();
	}

	/**
	 * Card design preset and layout metrics. Content tab — this changes
	 * structure, not only paint.
	 *
	 * @return void
	 */
	private function register_card_preset_section(): void {
		$this->start_controls_section(
			'section_card_design',
			array( 'label' => __( 'Card design', 'doctorpilot' ) )
		);

		$this->add_control(
			'card_preset',
			array(
				'label'       => __( 'Design preset', 'doctorpilot' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'modern',
				'options'     => array(
					'classic'  => __( 'Classic', 'doctorpilot' ),
					'modern'   => __( 'Modern', 'doctorpilot' ),
					'elevated' => __( 'Elevated', 'doctorpilot' ),
					'minimal'  => __( 'Minimal', 'doctorpilot' ),
					'overlay'  => __( 'Image overlay', 'doctorpilot' ),
					'compact'  => __( 'Compact', 'doctorpilot' ),
				),
				'description' => __( 'A starting point. Any style control you set below overrides the preset. Choose Classic to restore the pre-upgrade card appearance. Image overlay is the one exception: on a card with no photo, and in an image-card list, it forces its own dark text colours so white type never lands on a white card — your text colour controls are ignored in those two cases.', 'doctorpilot' ),
			)
		);

		$this->add_responsive_control(
			'card_col_gap',
			array(
				'label'      => __( 'Column gap', 'doctorpilot' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .dp-dir' => '--dpc-col-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'card_row_gap',
			array(
				'label'      => __( 'Row gap', 'doctorpilot' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .dp-dir' => '--dpc-row-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'card_linked',
			array(
				'label'       => __( 'Make the whole card clickable', 'doctorpilot' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => '',
				'description' => __( 'Adds a cover link to the profile. The name, buttons and badges stay separately focusable, so keyboard users keep every stop.', 'doctorpilot' ),
			)
		);

		$this->add_control(
			'list_style',
			array(
				'label'       => __( 'List style', 'doctorpilot' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'rows',
				'options'     => array(
					'rows'  => __( 'Compact rows', 'doctorpilot' ),
					'cards' => __( 'Image cards', 'doctorpilot' ),
				),
				'condition'   => array( 'layout' => 'list' ),
				'description' => __( 'Compact rows is the original name-and-specialty list. Image cards uses the full doctor card laid out horizontally.', 'doctorpilot' ),
			)
		);

		$this->add_control(
			'list_media_position',
			array(
				'label'     => __( 'List image position', 'doctorpilot' ),
				'type'      => Controls_Manager::CHOOSE,
				'default'   => 'left',
				'options'   => array(
					'left'  => array(
						'title' => __( 'Left', 'doctorpilot' ),
						'icon'  => 'eicon-h-align-left',
					),
					'top'   => array(
						'title' => __( 'Top', 'doctorpilot' ),
						'icon'  => 'eicon-v-align-top',
					),
					'right' => array(
						'title' => __( 'Right', 'doctorpilot' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'condition' => array(
					'layout'     => 'list',
					'list_style' => 'cards',
				),
			)
		);

		$this->add_responsive_control(
			'list_media_width',
			array(
				'label'      => __( 'List image width', 'doctorpilot' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array( 'min' => 80, 'max' => 460 ),
					'%'  => array( 'min' => 10, 'max' => 60 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .dp-dir' => '--dpc-list-media-w: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'layout'     => 'list',
					'list_style' => 'cards',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Card box: surface, border, radius, shadow, padding, hover motion.
	 *
	 * @return void
	 */
	private function register_card_box_style(): void {
		$this->start_controls_section(
			'section_card_box_style',
			array(
				'label' => __( 'Card box', 'doctorpilot' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'card_bg',
			array(
				'label'     => __( 'Background', 'doctorpilot' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-dir' => '--dpc-bg: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'card_border_color',
			array(
				'label'     => __( 'Border color', 'doctorpilot' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-dir' => '--dpc-border-color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'card_border_width',
			array(
				'label'      => __( 'Border width', 'doctorpilot' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 8 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-dir' => '--dpc-border-width: {{SIZE}}px;' ),
			)
		);

		$this->add_responsive_control(
			'card_radius',
			array(
				'label'      => __( 'Border radius', 'doctorpilot' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 48 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-dir' => '--dpc-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => __( 'Content padding', 'doctorpilot' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array(
					'{{WRAPPER}} .dp-dir' => '--dpc-pad: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'card_content_gap',
			array(
				'label'      => __( 'Gap between content rows', 'doctorpilot' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 32 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-dir' => '--dpc-gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'card_actions_rule',
			array(
				'label'       => __( 'Divider above buttons', 'doctorpilot' ),
				'type'        => Controls_Manager::COLOR,
				'selectors'   => array( '{{WRAPPER}} .dp-dir' => '--dpc-actions-rule: {{VALUE}};' ),
				'description' => __( 'Leave empty for no divider.', 'doctorpilot' ),
			)
		);

		$this->start_controls_tabs( 'card_box_tabs' );

		$this->start_controls_tab(
			'card_box_normal',
			array( 'label' => __( 'Normal', 'doctorpilot' ) )
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'card_shadow',
				// `.dp-dir` is included so this out-specifies the stylesheet's
				// own `.dp-dir .dp-card { box-shadow: var(--dpc-shadow) }`.
				// Without it the two tie and source order decides, which is
				// not something a control should depend on.
				'selector' => '{{WRAPPER}} .dp-dir .dp-card',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'card_box_hover',
			array( 'label' => __( 'Hover', 'doctorpilot' ) )
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'card_shadow_hover',
				'selector' => '{{WRAPPER}} .dp-dir .dp-card:hover, {{WRAPPER}} .dp-dir .dp-card:focus-within',
			)
		);

		$this->add_control(
			'card_hover_lift',
			array(
				'label'      => __( 'Lift', 'doctorpilot' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => -24, 'max' => 0 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-dir' => '--dpc-hover-lift: {{SIZE}}px;' ),
			)
		);

		$this->add_control(
			'card_hover_scale',
			array(
				'label'     => __( 'Scale', 'doctorpilot' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 1, 'max' => 1.08, 'step' => 0.01 ) ),
				'selectors' => array( '{{WRAPPER}} .dp-dir' => '--dpc-hover-scale: {{SIZE}};' ),
			)
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_control(
			'card_transition',
			array(
				'label'       => __( 'Transition duration (ms)', 'doctorpilot' ),
				'type'        => Controls_Manager::SLIDER,
				'separator'   => 'before',
				'range'       => array( 'px' => array( 'min' => 0, 'max' => 800, 'step' => 10 ) ),
				'selectors'   => array( '{{WRAPPER}} .dp-dir' => '--dpc-transition: {{SIZE}}ms;' ),
				'description' => __( 'Visitors who ask their system for reduced motion always get an instant transition, whatever is set here.', 'doctorpilot' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Doctor image: ratio, fit, position, radius, overlay, zoom.
	 *
	 * @return void
	 */
	private function register_card_image_style(): void {
		$this->start_controls_section(
			'section_card_image_style',
			array(
				'label' => __( 'Doctor image', 'doctorpilot' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'image_ratio',
			array(
				'label'       => __( 'Aspect ratio', 'doctorpilot' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => '',
				'options'     => array(
					''        => __( 'From preset', 'doctorpilot' ),
					'1 / 1'   => __( 'Square (1:1)', 'doctorpilot' ),
					'4 / 5'   => __( 'Portrait (4:5)', 'doctorpilot' ),
					'3 / 4'   => __( 'Portrait (3:4)', 'doctorpilot' ),
					'4 / 3'   => __( 'Landscape (4:3)', 'doctorpilot' ),
					'16 / 10' => __( 'Landscape (16:10)', 'doctorpilot' ),
					'16 / 9'  => __( 'Wide (16:9)', 'doctorpilot' ),
				),
				'selectors'   => array( '{{WRAPPER}} .dp-dir' => '--dpc-media-ratio: {{VALUE}};' ),
				'description' => __( 'The image box always reserves its height, so a missing or slow photo never shifts the layout.', 'doctorpilot' ),
			)
		);

		$this->add_control(
			'image_fit',
			array(
				'label'     => __( 'Object fit', 'doctorpilot' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'cover',
				'options'   => array(
					'cover'      => __( 'Cover', 'doctorpilot' ),
					'contain'    => __( 'Contain', 'doctorpilot' ),
					'fill'       => __( 'Fill', 'doctorpilot' ),
					'scale-down' => __( 'Scale down', 'doctorpilot' ),
				),
				'selectors' => array( '{{WRAPPER}} .dp-dir' => '--dpc-media-fit: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'image_position',
			array(
				'label'       => __( 'Object position', 'doctorpilot' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'center top',
				'options'     => array(
					'center top'    => __( 'Top', 'doctorpilot' ),
					'center center' => __( 'Center', 'doctorpilot' ),
					'center bottom' => __( 'Bottom', 'doctorpilot' ),
					'left center'   => __( 'Left', 'doctorpilot' ),
					'right center'  => __( 'Right', 'doctorpilot' ),
				),
				'selectors'   => array( '{{WRAPPER}} .dp-dir' => '--dpc-media-pos: {{VALUE}};' ),
				'description' => __( 'Top usually frames a portrait head better than centre.', 'doctorpilot' ),
			)
		);

		$this->add_responsive_control(
			'image_radius',
			array(
				'label'      => __( 'Image radius', 'doctorpilot' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 48 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-dir' => '--dpc-media-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'image_placeholder_bg',
			array(
				'label'       => __( 'Placeholder background', 'doctorpilot' ),
				'type'        => Controls_Manager::COLOR,
				'selectors'   => array( '{{WRAPPER}} .dp-dir' => '--dpc-media-bg: {{VALUE}};' ),
				'description' => __( 'Shown behind the initials when a doctor has no photo.', 'doctorpilot' ),
			)
		);

		$this->add_control(
			'image_overlay',
			array(
				'label'     => __( 'Overlay', 'doctorpilot' ),
				'type'      => Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array( '{{WRAPPER}} .dp-dir' => '--dpc-overlay: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'image_overlay_hover',
			array(
				'label'     => __( 'Overlay on hover', 'doctorpilot' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-dir' => '--dpc-overlay-hover: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'image_zoom',
			array(
				'label'     => __( 'Hover zoom', 'doctorpilot' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 1, 'max' => 1.25, 'step' => 0.01 ) ),
				'selectors' => array( '{{WRAPPER}} .dp-dir' => '--dpc-media-zoom: {{SIZE}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Per-element typography and colour.
	 *
	 * @return void
	 */
	private function register_card_typography_style(): void {
		$this->start_controls_section(
			'section_card_type_style',
			array(
				'label' => __( 'Card text', 'doctorpilot' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$elements = array(
			'cname'    => array(
				'label'    => __( 'Doctor name', 'doctorpilot' ),
				'selector' => '{{WRAPPER}} .dp-dir .dp-card__name',
				'token'    => '--dpc-name-color',
				'hover'    => '--dpc-name-hover',
			),
			'headline' => array(
				'label'    => __( 'Headline / job title', 'doctorpilot' ),
				'selector' => '{{WRAPPER}} .dp-dir .dp-card__headline',
				'token'    => '--dpc-headline-color',
			),
			'spec'     => array(
				'label'    => __( 'Specialty / department', 'doctorpilot' ),
				'selector' => '{{WRAPPER}} .dp-dir .dp-card__specialty',
				'token'    => '--dpc-spec-color',
			),
			'meta'     => array(
				'label'    => __( 'Metadata', 'doctorpilot' ),
				'selector' => '{{WRAPPER}} .dp-dir .dp-card__meta',
				'token'    => '--dpc-meta-color',
			),
		);

		foreach ( $elements as $key => $element ) {
			$this->add_control(
				$key . '_heading',
				array(
					'label'     => $element['label'],
					'type'      => Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);

			$this->add_control(
				$key . '_color',
				array(
					'label'     => __( 'Color', 'doctorpilot' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .dp-dir' => $element['token'] . ': {{VALUE}};' ),
				)
			);

			if ( isset( $element['hover'] ) ) {
				$this->add_control(
					$key . '_color_hover',
					array(
						'label'     => __( 'Hover color', 'doctorpilot' ),
						'type'      => Controls_Manager::COLOR,
						'selectors' => array( '{{WRAPPER}} .dp-dir' => $element['hover'] . ': {{VALUE}};' ),
					)
				);
			}

			$this->add_group_control(
				Group_Control_Typography::get_type(),
				array(
					'name'     => $key . '_typography',
					'selector' => $element['selector'],
				)
			);
		}

		$this->add_control(
			'meta_icon_color',
			array(
				'label'     => __( 'Metadata icon color', 'doctorpilot' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dp-dir' => '--dpc-meta-icon: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'meta_icons_show',
			array(
				'label'   => __( 'Show metadata icons', 'doctorpilot' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'bio_clamp',
			array(
				'label'       => __( 'Clamp long text to lines', 'doctorpilot' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 8,
				'default'     => 0,
				'separator'   => 'before',
				'selectors'   => array( '{{WRAPPER}} .dp-dir' => '--dp-clamp: {{VALUE}};' ),
				'description' => __( 'Applies to the headline. Zero leaves text unclamped.', 'doctorpilot' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Badges: featured and international.
	 *
	 * @return void
	 */
	private function register_card_badge_style(): void {
		$this->start_controls_section(
			'section_card_badge_style',
			array(
				'label' => __( 'Badges', 'doctorpilot' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$badges = array(
			'badge_featured' => array(
				'label' => __( 'Featured', 'doctorpilot' ),
				'bg'    => '--dpb-featured-bg',
				'color' => '--dpb-featured-color',
			),
			'badge_intl'     => array(
				'label' => __( 'International', 'doctorpilot' ),
				'bg'    => '--dpb-intl-bg',
				'color' => '--dpb-intl-color',
			),
		);

		foreach ( $badges as $key => $badge ) {
			$this->add_control(
				$key . '_heading',
				array(
					'label'     => $badge['label'],
					'type'      => Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);

			$this->add_control(
				$key . '_bg',
				array(
					'label'     => __( 'Background', 'doctorpilot' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .dp-dir' => $badge['bg'] . ': {{VALUE}};' ),
				)
			);

			$this->add_control(
				$key . '_color',
				array(
					'label'     => __( 'Text color', 'doctorpilot' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .dp-dir' => $badge['color'] . ': {{VALUE}};' ),
				)
			);
		}

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'badge_typography',
				'selector'  => '{{WRAPPER}} .dp-dir .dp-badge',
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'badge_radius',
			array(
				'label'      => __( 'Radius', 'doctorpilot' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 999 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-dir' => '--dpb-radius: {{SIZE}}px;' ),
			)
		);

		$this->add_responsive_control(
			'badge_padding',
			array(
				'label'      => __( 'Padding', 'doctorpilot' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .dp-dir' => '--dpb-pad: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'badge_gap',
			array(
				'label'      => __( 'Gap between badges', 'doctorpilot' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 24 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-dir' => '--dpb-gap: {{SIZE}}px;' ),
			)
		);

		$this->add_control(
			'badge_offset_x',
			array(
				'label'      => __( 'Horizontal offset', 'doctorpilot' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 48 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-dir' => '--dpb-offset-x: {{SIZE}}px;' ),
			)
		);

		$this->add_control(
			'badge_offset_y',
			array(
				'label'      => __( 'Vertical offset', 'doctorpilot' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 48 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-dir' => '--dpb-offset-y: {{SIZE}}px;' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Independent primary and secondary button styling.
	 *
	 * @return void
	 */
	private function register_card_button_style(): void {
		$this->start_controls_section(
			'section_card_button_style',
			array(
				'label' => __( 'Card buttons', 'doctorpilot' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'btn_typography',
				'selector' => '{{WRAPPER}} .dp-dir .dp-card__actions .dp-btn',
			)
		);

		$this->add_responsive_control(
			'btn_padding',
			array(
				'label'      => __( 'Padding', 'doctorpilot' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .dp-dir' => '--dpbtn-pad: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'btn_radius',
			array(
				'label'      => __( 'Radius', 'doctorpilot' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 999 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-dir' => '--dpbtn-radius: {{SIZE}}px;' ),
			)
		);

		$this->add_control(
			'btn_gap',
			array(
				'label'      => __( 'Gap between buttons', 'doctorpilot' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 32 ) ),
				'selectors'  => array( '{{WRAPPER}} .dp-dir' => '--dpbtn-gap: {{SIZE}}px;' ),
			)
		);

		$this->add_responsive_control(
			'btn_stack',
			array(
				'label'        => __( 'Stack buttons', 'doctorpilot' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '',
				'mobile_default' => 'yes',
				'selectors'    => array(
					'{{WRAPPER}} .dp-dir .dp-card__actions' => 'flex-direction: column;',
					'{{WRAPPER}} .dp-dir .dp-card__actions .dp-btn' => 'width: 100%;',
				),
				'description'  => __( 'Full-width stacked buttons are the mobile default.', 'doctorpilot' ),
			)
		);

		$buttons = array(
			'btn_primary'   => array(
				'label'      => __( 'Book appointment', 'doctorpilot' ),
				'bg'         => '--dpbtn-primary-bg',
				'color'      => '--dpbtn-primary-color',
				'border'     => '--dpbtn-primary-border',
				'bg_hover'   => '--dpbtn-primary-bg-hover',
				'color_hover' => '--dpbtn-primary-color-hover',
			),
			'btn_secondary' => array(
				'label'      => __( 'View profile', 'doctorpilot' ),
				'bg'         => '--dpbtn-secondary-bg',
				'color'      => '--dpbtn-secondary-color',
				'border'     => '--dpbtn-secondary-border',
				'bg_hover'   => '--dpbtn-secondary-bg-hover',
				'color_hover' => '--dpbtn-secondary-color-hover',
			),
		);

		foreach ( $buttons as $key => $button ) {
			$this->add_control(
				$key . '_heading',
				array(
					'label'     => $button['label'],
					'type'      => Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);

			$this->start_controls_tabs( $key . '_tabs' );

			$this->start_controls_tab(
				$key . '_normal',
				array( 'label' => __( 'Normal', 'doctorpilot' ) )
			);

			$this->add_control(
				$key . '_bg',
				array(
					'label'     => __( 'Background', 'doctorpilot' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .dp-dir' => $button['bg'] . ': {{VALUE}};' ),
				)
			);

			$this->add_control(
				$key . '_color',
				array(
					'label'     => __( 'Text color', 'doctorpilot' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .dp-dir' => $button['color'] . ': {{VALUE}};' ),
				)
			);

			$this->add_control(
				$key . '_border',
				array(
					'label'     => __( 'Border color', 'doctorpilot' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .dp-dir' => $button['border'] . ': {{VALUE}};' ),
				)
			);

			$this->end_controls_tab();

			$this->start_controls_tab(
				$key . '_hover',
				array( 'label' => __( 'Hover', 'doctorpilot' ) )
			);

			$this->add_control(
				$key . '_bg_hover',
				array(
					'label'     => __( 'Background', 'doctorpilot' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .dp-dir' => $button['bg_hover'] . ': {{VALUE}};' ),
				)
			);

			$this->add_control(
				$key . '_color_hover',
				array(
					'label'     => __( 'Text color', 'doctorpilot' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .dp-dir' => $button['color_hover'] . ': {{VALUE}};' ),
				)
			);

			$this->end_controls_tab();
			$this->end_controls_tabs();
		}

		$this->end_controls_section();
	}
}
