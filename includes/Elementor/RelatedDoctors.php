<?php
/**
 * Related Doctors Pro widget.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor;

use BrainStudioz\DoctorPilot\Application\Doctors\DoctorView;
use BrainStudioz\DoctorPilot\Elementor\Widgets\AbstractWidget;
use BrainStudioz\DoctorPilot\Elementor\Widgets\Concerns\CardElementControls;
use BrainStudioz\DoctorPilot\Frontend\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows other published doctors sharing the current doctor's primary specialty
 * or department.
 *
 * Extends the abstract widget so it inherits the DoctorPilot category, the shared
 * stylesheet dependency, and renders through the same view model and card
 * template as the rest of the directory.
 */
final class RelatedDoctors extends AbstractWidget {

	use CardElementControls;

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'doctorpilot-related-doctors';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Related Doctors (Pro)', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-person';
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array( 'label' => __( 'Related Doctors', 'doctorpilot' ) )
		);

		$this->add_control(
			'count',
			array(
				'label'   => __( 'How many', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 12,
				'default' => 3,
			)
		);

		$this->add_columns_control( 3 );

		$this->register_heading_controls();

		$this->end_controls_section();

		$this->register_heading_style();

		$this->register_card_elements_section();
	}

	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	protected function render(): void {
		$queried = get_queried_object();
		if ( ! ( $queried instanceof \WP_Post ) || 'doctorpilot_doctor' !== $queried->post_type ) {
			$this->editor_notice( __( 'Related Doctors appears on a single-doctor page. Preview it on a published doctor’s URL.', 'doctorpilot' ) );
			return;
		}

		$settings = $this->get_settings_for_display();
		$count    = isset( $settings['count'] ) ? max( 1, min( 12, (int) $settings['count'] ) ) : 3;
		$columns  = isset( $settings['columns'] ) ? max( 1, min( 4, (int) $settings['columns'] ) ) : 3;

		$specialties = wp_get_object_terms( $queried->ID, 'doctorpilot_specialty', array( 'fields' => 'ids' ) );
		$departments = wp_get_object_terms( $queried->ID, 'doctorpilot_department', array( 'fields' => 'ids' ) );

		$tax_query = array( 'relation' => 'OR' );
		if ( ! is_wp_error( $specialties ) && array() !== $specialties ) {
			$tax_query[] = array( 'taxonomy' => 'doctorpilot_specialty', 'field' => 'term_id', 'terms' => $specialties );
		}
		if ( ! is_wp_error( $departments ) && array() !== $departments ) {
			$tax_query[] = array( 'taxonomy' => 'doctorpilot_department', 'field' => 'term_id', 'terms' => $departments );
		}

		if ( 1 === count( $tax_query ) ) {
			$this->editor_notice( __( 'This doctor has no primary specialty or department assigned, so there is nothing to relate on.', 'doctorpilot' ) );
			return;
		}

		$related = get_posts(
			array(
				'post_type'      => 'doctorpilot_doctor',
				'post_status'    => 'publish',
				'posts_per_page' => $count,
				'post__not_in'   => array( $queried->ID ),
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);

		if ( array() === $related ) {
			$this->editor_notice( __( 'No other published doctors share this doctor’s specialty or department yet. Publish more doctors to see them here.', 'doctorpilot' ) );
			return;
		}

		$view = new DoctorView();

		// The card template reads its presentation flags from `$data`; only the
		// element switchers are set here, so every other flag falls back to the
		// template's own default and the card renders as it always has.
		$data = array( 'parts' => $this->card_parts( $settings ) );

		// After the three early returns, so a "Related Doctors" heading is never
		// left standing over an empty result.
		$this->render_heading( $settings );

		echo '<div class="dp-related">';
		echo '<div class="dp-directory dp-cols-' . esc_attr( (string) $columns ) . '">';
		foreach ( $related as $id ) {
			$card = $view->card( (int) $id );
			if ( null !== $card ) {
				include Templates::locate( 'card-doctor.php' );
			}
		}
		echo '</div></div>';
	}

	/**
	 * Show an explanatory notice in the Elementor editor only, so an empty result
	 * does not look broken. Nothing is output on the live front end.
	 *
	 * @param string $message Notice text.
	 * @return void
	 */
	private function editor_notice( string $message ): void {
		if ( \Elementor\Plugin::instance()->editor->is_edit_mode() ) {
			echo '<p class="dp-related__notice">' . esc_html( $message ) . '</p>';
		}
	}
}
