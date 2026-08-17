<?php
/**
 * Department Grid Elementor widget.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor\Widgets;

use BrainStudioz\DoctorPilot\Taxonomies\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays departments as linked cards for browse-by-department discovery.
 */
final class DepartmentGrid extends AbstractWidget {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'doctorpilot-department-grid';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Department Grid', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-gallery-grid';
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'section_query',
			array( 'label' => __( 'Departments', 'doctorpilot' ) )
		);

		$this->add_columns_control( 4 );

		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Maximum departments', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 100,
				'default' => 12,
			)
		);

		$this->add_control(
			'hide_empty',
			array(
				'label'   => __( 'Hide empty departments', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->register_heading_controls();

		$this->end_controls_section();

		$this->register_heading_style();
	}

	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();

		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomies::DEPARTMENT,
				'hide_empty' => ! empty( $settings['hide_empty'] ) && 'yes' === $settings['hide_empty'],
				'number'     => isset( $settings['limit'] ) ? max( 1, min( 100, (int) $settings['limit'] ) ) : 12,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || array() === $terms ) {
			echo '<p class="dp-empty">' . esc_html__( 'No departments found.', 'doctorpilot' ) . '</p>';
			return;
		}

		$columns = isset( $settings['columns'] ) ? max( 1, min( 4, (int) $settings['columns'] ) ) : 4;

		// After the empty-state return, so a heading never sits over nothing.
		$this->render_heading( $settings );

		echo '<div class="dp-directory dp-cols-' . esc_attr( (string) $columns ) . ' dp-departments">';
		foreach ( $terms as $term ) {
			$link = get_term_link( $term );
			$url  = is_wp_error( $link ) ? '#' : $link;
			echo '<a class="dp-dept-card" href="' . esc_url( $url ) . '">';
			echo '<span class="dp-dept-card__name">' . esc_html( $term->name ) . '</span>';
			echo '<span class="dp-dept-card__count">';
			printf(
				/* translators: %d: number of doctors in the department. */
				esc_html( _n( '%d doctor', '%d doctors', (int) $term->count, 'doctorpilot' ) ),
				(int) $term->count
			);
			echo '</span></a>';
		}
		echo '</div>';
	}
}
