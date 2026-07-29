<?php
/**
 * Doctor Search Elementor widget.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor\Widgets;

use BrainStudioz\DoctorPilot\Frontend\SearchRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the interactive directory search, reusing the shared SearchRenderer.
 *
 * The widget owns presentation only; querying, ranking, URL state, and the
 * accessible no-JS fallback all come from the same services as the
 * `[doctorpilot_search]` shortcode.
 */
final class DoctorSearch extends AbstractWidget {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'doctorpilot-search';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Doctor Search', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-search';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_script_depends(): array {
		return array( 'doctorpilot-search' );
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array( 'label' => __( 'Search', 'doctorpilot' ) )
		);

		$this->add_control(
			'help',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Displays the accessible directory search with filters and no-reload results.', 'doctorpilot' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->end_controls_section();

		$this->add_card_style_controls();
	}

	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	protected function render(): void {
		// Output is fully escaped inside the renderer/templates.
		echo ( new SearchRenderer() )->render( array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
