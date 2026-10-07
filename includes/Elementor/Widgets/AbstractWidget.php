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
	 * Register the built-in heading style sections: one for the eyebrow, one for
	 * the heading.
	 *
	 * Two sections rather than one, because the two lines are styled
	 * independently in practice — an eyebrow is usually a small tinted label and
	 * the heading is display type — and a single section holding both was long
	 * enough that the second half sat below the fold of the panel.
	 *
	 * `eyebrow_color`, `eyebrow_typography`, `wh_title_color`,
	 * `wh_title_typography` and `wh_spacing` keep the IDs they have always had.
	 * Elementor stores settings flat, by control ID, so moving a control into a
	 * different section or tab preserves what an author already chose; renaming
	 * one would resolve it to the control default instead and silently restyle
	 * every page already built.
	 *
	 * Every control added here defaults to empty, so a widget nobody has touched
	 * emits exactly the CSS it emitted before.
	 *
	 * @return void
	 */
	protected function register_heading_style(): void {
		$this->register_wh_part_style(
			'section_eyebrow_style',
			__( 'Eyebrow', 'doctorpilot' ),
			'eyebrow',
			'{{WRAPPER}} .dp-wh__eyebrow',
			'eyebrow_color',
			''
		);

		$this->register_wh_part_style(
			'section_heading_style',
			__( 'Heading', 'doctorpilot' ),
			'wh_title',
			'{{WRAPPER}} .dp-wh__title',
			'wh_title_color',
			'#136888'
		);
	}

	/**
	 * One style section for one line of the built-in heading block.
	 *
	 * Both lines get an identical control set, so they are generated from one
	 * method rather than written out twice: two copies would be two places to
	 * forget a selector, and the eyebrow and the heading would drift apart
	 * control by control.
	 *
	 * @param string $section_id Section control ID.
	 * @param string $label      Section label.
	 * @param string $prefix     Control ID prefix. Load-bearing — it names saved settings.
	 * @param string $selector   Element selector, including `{{WRAPPER}}`.
	 * @param string $color_id   Existing colour control ID to reuse, so saved values survive.
	 * @param string $color      Default colour, or '' to inherit the stylesheet's.
	 * @return void
	 */
	private function register_wh_part_style( string $section_id, string $label, string $prefix, string $selector, string $color_id, string $color = '' ): void {
		$hover = $selector . ':hover';

		$this->start_controls_section(
			$section_id,
			array(
				'label'     => $label,
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'heading_show' => 'yes' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array( 'name' => $prefix . '_typography', 'selector' => $selector )
		);

		$this->add_responsive_control(
			$prefix . '_padding',
			array(
				'label'      => __( 'Padding', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array( $selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				'separator'  => 'before',
			)
		);

		$this->add_responsive_control(
			$prefix . '_margin',
			array(
				'label'      => __( 'Margin', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array( $selector => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			$prefix . '_radius',
			array(
				'label'      => __( 'Border radius', 'doctorpilot' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( $selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		// Both lines are block-level, so a background paints the full column width
		// — right for a banded heading, wrong for the tinted label an eyebrow
		// usually wants. Off by default: no widget carries a background yet, so
		// neither default changes an existing page, and full-width is what the
		// stylesheet does on its own.
		$this->add_control(
			$prefix . '_inline',
			array(
				'label'        => __( 'Fit background to text', 'doctorpilot' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'selectors'    => array( $selector => 'width: fit-content; max-width: 100%;' ),
				'description'  => __( 'Shrinks the line to its own text, so a background reads as a label rather than a full-width band.', 'doctorpilot' ),
			)
		);

		$this->add_control(
			$prefix . '_transition',
			array(
				'label'       => __( 'Transition duration (s)', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'range'       => array( 'px' => array( 'min' => 0, 'max' => 3, 'step' => 0.1 ) ),
				'selectors'   => array( $selector => 'transition: all {{SIZE}}s ease;' ),
				'description' => __( 'Leave this at zero and the hover state snaps instead of fading.', 'doctorpilot' ),
			)
		);

		$this->start_controls_tabs( $prefix . '_tabs' );

		$this->start_controls_tab( $prefix . '_tab_normal', array( 'label' => __( 'Normal', 'doctorpilot' ) ) );
		$this->add_control(
			$color_id,
			array(
				'label'     => __( 'Text color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => $color,
				'selectors' => array( $selector => 'color: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array( 'name' => $prefix . '_bg', 'types' => array( 'classic', 'gradient' ), 'selector' => $selector )
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array( 'name' => $prefix . '_shadow', 'selector' => $selector )
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
		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array( 'name' => $prefix . '_bg_hover', 'types' => array( 'classic', 'gradient' ), 'selector' => $hover )
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array( 'name' => $prefix . '_shadow_hover', 'selector' => $hover )
		);
		$this->end_controls_tab();

		$this->end_controls_tabs();

		// Kept in the Heading section, and kept under its original ID: it is the
		// gap below the eyebrow-and-heading block as a whole, not below this one
		// line, and it is already set on pages in the wild.
		if ( 'wh_title' === $prefix ) {
			$this->add_responsive_control(
				'wh_spacing',
				array(
					'label'       => __( 'Spacing below', 'doctorpilot' ),
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'size_units'  => array( 'px' ),
					'range'       => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
					'selectors'   => array( '{{WRAPPER}} .dp-wh' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
					'separator'   => 'before',
					'description' => __( 'The gap below the whole eyebrow-and-heading block, before the widget content.', 'doctorpilot' ),
				)
			);
		}

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
	 * Whether pixfort's icon library and its Elementor control are both present.
	 *
	 * Every pixfort control and every pixfort render path is registered behind
	 * this, so the widgets fall back to their Elementor icons — unchanged —
	 * whenever the theme's plugin is inactive.
	 *
	 * @return bool
	 */
	protected function pixfort_icons_enabled(): bool {
		return class_exists( '\\Elementor\\CustomControl\\PixfortIconSelector_Control' )
			&& class_exists( '\\PixfortCore' );
	}

	/**
	 * Add the "which icon library" dropdown that governs one icon control.
	 *
	 * Call it immediately before the Elementor icon control it belongs to. It
	 * only exists while pixfort's library does — with one library there is
	 * nothing to choose — and it defaults to the Elementor picker, so a page
	 * built before this control existed keeps the icon it already shows.
	 *
	 * @param string               $icon_id   ID of the Elementor icon control.
	 * @param string               $label     Label for the dropdown.
	 * @param array<string, mixed> $condition Condition to mirror from that control.
	 * @return void
	 */
	protected function register_icon_source( string $icon_id, string $label, array $condition = array() ): void {
		if ( ! $this->pixfort_icons_enabled() ) {
			return;
		}

		$args = array(
			'label'   => $label,
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'elementor',
			'options' => array(
				'elementor' => __( 'Elementor icon library', 'doctorpilot' ),
				'pixfort'   => __( 'pixfort icon', 'doctorpilot' ),
			),
		);

		if ( array() !== $condition ) {
			$args['condition'] = $condition;
		}

		$this->add_control( $icon_id . '_src', $args );
	}

	/**
	 * Condition for one half of an icon pair.
	 *
	 * Without pixfort there is no source dropdown, so the condition must not
	 * mention it — a condition on a control that was never registered hides the
	 * icon picker entirely.
	 *
	 * @param string               $icon_id ID of the Elementor icon control.
	 * @param string               $source  'elementor' or 'pixfort'.
	 * @param array<string, mixed> $extra   The control's own condition.
	 * @return array<string, mixed>
	 */
	protected function icon_condition( string $icon_id, string $source, array $extra = array() ): array {
		if ( ! $this->pixfort_icons_enabled() ) {
			return $extra;
		}

		return array_merge( $extra, array( $icon_id . '_src' => $source ) );
	}

	/**
	 * Add the pixfort half of an icon pair.
	 *
	 * Call it straight after the Elementor icon control it belongs to. It is a
	 * separate setting (`<icon>_pix`) shown only while the source dropdown is set
	 * to pixfort, so an icon already chosen in the Elementor picker is never
	 * overwritten and comes straight back when the dropdown is switched.
	 *
	 * @param string               $icon_id   ID of the Elementor icon control.
	 * @param array<string, mixed> $condition Condition to mirror from that control.
	 * @return void
	 */
	protected function register_pixfort_icon( string $icon_id, array $condition = array() ): void {
		if ( ! $this->pixfort_icons_enabled() ) {
			return;
		}

		$this->add_control(
			$icon_id . '_pix',
			array(
				'label'     => __( 'pixfort icon', 'doctorpilot' ),
				'type'      => \Elementor\CustomControl\PixfortIconSelector_Control::PixfortIconSelector,
				'default'   => '',
				'condition' => $this->icon_condition( $icon_id, 'pixfort', $condition ),
			)
		);
	}

	/**
	 * Whether a saved value is shaped like a pixfort icon name — an optional
	 * `Line/`, `Duotone/` or `Solid/` folder plus a plain slug. Anything else is
	 * ignored rather than handed to a routine that resolves it to a file path.
	 *
	 * @param string $name Saved control value.
	 * @return bool
	 */
	private function is_pixfort_icon_name( string $name ): bool {
		return 1 === preg_match( '#^(?:[A-Za-z]+/)?[A-Za-z0-9_-]+$#', $name );
	}

	/**
	 * Icon markup for an icon control: whichever library its source dropdown
	 * names, and an empty string when that library has no icon chosen.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @param string               $icon_id  ID of the Elementor icon control.
	 * @param string               $classes  Extra classes for the icon element.
	 * @param int                  $size     Pixel size for a pixfort SVG.
	 * @return string
	 */
	protected function icon_markup( array $settings, string $icon_id, string $classes = '', int $size = 24 ): string {
		// The dropdown decides. A page with no source saved resolves to the
		// Elementor picker, and so does one that asks for pixfort on a site where
		// the library has since been switched off.
		$source = 'pixfort' === (string) ( $settings[ $icon_id . '_src' ] ?? '' ) && $this->pixfort_icons_enabled();

		if ( $source ) {
			$pix = is_string( $settings[ $icon_id . '_pix' ] ?? null ) ? trim( (string) $settings[ $icon_id . '_pix' ] ) : '';
			if ( '' === $pix || ! $this->is_pixfort_icon_name( $pix ) ) {
				return '';
			}

			// pixfort builds a file path out of the name, so only render through a
			// library that is actually up, and only for a name shaped like one.
			$core = \PixfortCore::instance();
			if ( ! is_object( $core ) || ! isset( $core->icons ) || ! method_exists( $core->icons, 'getIcon' ) ) {
				return '';
			}

			return (string) $core->icons->getIcon( $pix, $size, $classes, 'aria-hidden="true"' );
		}

		$icon = $settings[ $icon_id ] ?? null;
		// Not a string comparison: the SVG library stores value as an array of
		// attachment data, and casting that to string warns on every render.
		if ( ! is_array( $icon ) || empty( $icon['value'] ) ) {
			return '';
		}

		$attributes = array( 'aria-hidden' => 'true' );
		if ( '' !== $classes ) {
			$attributes['class'] = $classes;
		}

		ob_start();
		\Elementor\Icons_Manager::render_icon( $icon, $attributes );

		return (string) ob_get_clean();
	}

	/**
	 * Icon markup for a list item: the author's chosen icon, or the built-in
	 * circle-check mark, so a list never depends on an icon library being loaded.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @param string               $key      Icon control ID.
	 * @return string
	 */
	protected function list_icon_markup( array $settings, string $key = 'list_icon' ): string {
		$markup = $this->icon_markup( $settings, $key );
		if ( '' !== $markup ) {
			return $markup;
		}

		return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">'
			. '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm4.7 7.7-5.4 5.4a1 1 0 0 1-1.4 0l-2.6-2.6a1 1 0 1 1 1.4-1.4l1.9 1.9 4.7-4.7a1 1 0 1 1 1.4 1.4Z"/>'
			. '</svg>';
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
	 * Resolve an optional link override from an Elementor URL control.
	 *
	 * A button that normally points at doctor data — the booking destination,
	 * the phone number — can be sent somewhere else by the editor. The URL goes
	 * through `esc_url_raw()`, so a `javascript:` or otherwise disallowed scheme
	 * resolves to no override rather than to a live link, while `tel:`,
	 * `mailto:`, in-page anchors and relative paths all pass. The control's
	 * "open in new window" and "nofollow" switches become the usual `target`
	 * and `rel` attributes.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @param string               $key      URL control ID.
	 * @return array{url: string, target: string, rel: string, attrs: string}
	 *         `url` is empty when the control is blank or unsafe. `attrs` is the
	 *         target/rel pair as a ready-to-print attribute string with a leading
	 *         space, or empty.
	 */
	protected function link_override( array $settings, string $key ): array {
		$none = array(
			'url'    => '',
			'target' => '',
			'rel'    => '',
			'attrs'  => '',
		);

		$raw = is_array( $settings[ $key ] ?? null ) ? $settings[ $key ] : array();
		$url = trim( (string) ( $raw['url'] ?? '' ) );

		if ( '' === $url ) {
			return $none;
		}

		$url = esc_url_raw( $url );

		if ( '' === $url ) {
			return $none;
		}

		$target = ! empty( $raw['is_external'] ) ? '_blank' : '';
		$rel    = array();

		if ( '' !== $target ) {
			$rel[] = 'noopener';
		}
		if ( ! empty( $raw['nofollow'] ) ) {
			$rel[] = 'nofollow';
		}

		$attrs = '';
		if ( '' !== $target ) {
			$attrs .= ' target="_blank"';
		}
		if ( array() !== $rel ) {
			$attrs .= ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"';
		}

		return array(
			'url'    => $url,
			'target' => $target,
			'rel'    => implode( ' ', $rel ),
			'attrs'  => $attrs,
		);
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
