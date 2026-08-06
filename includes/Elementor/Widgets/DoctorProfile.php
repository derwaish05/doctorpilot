<?php
/**
 * Doctor Profile Elementor widget.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor\Widgets;

use BrainStudioz\DoctorPilot\Application\Doctors\DoctorView;
use BrainStudioz\DoctorPilot\Frontend\Templates;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a full doctor profile from canonical data.
 *
 * The doctor is either an explicitly selected published doctor or the current
 * queried doctor (for Theme Builder single templates). Content comes from the
 * shared view model, so editing a doctor in WordPress updates every widget.
 */
final class DoctorProfile extends AbstractWidget {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'doctorpilot-profile';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Doctor Profile', 'doctorpilot' );
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
			array( 'label' => __( 'Profile', 'doctorpilot' ) )
		);

		$this->add_control(
			'doctor_id',
			array(
				'label'       => __( 'Doctor ID', 'doctorpilot' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'description' => __( 'Leave empty to use the current doctor (for single-doctor templates).', 'doctorpilot' ),
				'min'         => 0,
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'Headings', 'doctorpilot' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'heading_color',
			array(
				'label'     => __( 'Section heading color', 'doctorpilot' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .dp-profile__section h2' => 'color: {{VALUE}};',
				),
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

		include Templates::locate( 'profile-content.php' );
	}
}
