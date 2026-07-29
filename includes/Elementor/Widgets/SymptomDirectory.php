<?php
/**
 * Symptom Directory (A–Z) Elementor widget.
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
 * Renders an A–Z directory of symptoms flagged for listing.
 *
 * Only symptoms marked "Include in the A–Z symptom directory" appear. A
 * discovery-not-diagnosis note is always shown.
 */
final class SymptomDirectory extends AbstractWidget {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'doctorpilot-symptom-directory';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Symptom Directory', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-editor-list-ul';
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array( 'label' => __( 'Symptoms', 'doctorpilot' ) )
		);

		$this->add_control(
			'only_listed',
			array(
				'label'   => __( 'Only A–Z listed symptoms', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
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
		$settings    = $this->get_settings_for_display();
		$only_listed = ! isset( $settings['only_listed'] ) || 'yes' === $settings['only_listed'];

		$args = array(
			'taxonomy'   => Taxonomies::SYMPTOM,
			'hide_empty' => true,
			'number'     => 500,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);

		if ( $only_listed ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_doctorpilot_az_listed',
					'value' => '1',
				),
			);
		}

		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) || array() === $terms ) {
			echo '<p class="dp-empty">' . esc_html__( 'No symptoms found.', 'doctorpilot' ) . '</p>';
			return;
		}

		// Group terms by first letter.
		$groups = array();
		foreach ( $terms as $term ) {
			$letter = strtoupper( substr( remove_accents( $term->name ), 0, 1 ) );
			if ( ! preg_match( '/[A-Z]/', $letter ) ) {
				$letter = '#';
			}
			$groups[ $letter ][] = $term;
		}
		ksort( $groups );

		echo '<div class="dp-symptom-directory">';
		foreach ( $groups as $letter => $items ) {
			echo '<section class="dp-az-group">';
			echo '<h3 class="dp-az-letter">' . esc_html( $letter ) . '</h3><ul>';
			foreach ( $items as $term ) {
				$link = get_term_link( $term );
				$url  = is_wp_error( $link ) ? '#' : $link;
				echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a></li>';
			}
			echo '</ul></section>';
		}
		echo '</div>';

		echo '<p class="dp-disclaimer">' . esc_html__( 'Symptom search helps you find a doctor and does not provide a diagnosis.', 'doctorpilot' ) . '</p>';
	}
}
