<?php
/**
 * Single doctor template.
 *
 * Override by copying to your theme as doctorpilot/single-doctor.php.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$dp_view = new \BrainStudioz\DoctorPilot\Application\Doctors\DoctorView();

// An admin may choose an Elementor template/page to render as the doctor layout.
// The Doctor Profile widget inside it reads the queried doctor, so one design
// serves every doctor — no Elementor Pro / Theme Builder required.
$dp_template_id   = (int) \BrainStudioz\DoctorPilot\Admin\Settings::single_doctor_template();
$dp_use_elementor = $dp_template_id > 0 && did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' );

while ( have_posts() ) {
	the_post();

	echo '<main id="primary" class="dp-main">';

	$dp_rendered = false;
	if ( $dp_use_elementor ) {
		$dp_html = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $dp_template_id, true );
		if ( '' !== trim( (string) $dp_html ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor-rendered markup.
			echo $dp_html;
			$dp_rendered = true;
		}
	}

	if ( ! $dp_rendered ) {
		$data = $dp_view->full( (int) get_the_ID() );
		if ( is_array( $data ) ) {
			include \BrainStudioz\DoctorPilot\Frontend\Templates::locate( 'profile-content.php' );
		} else {
			echo '<p>' . esc_html__( 'This profile is not available.', 'doctorpilot' ) . '</p>';
		}
	}

	echo '</main>';
}

get_footer();
