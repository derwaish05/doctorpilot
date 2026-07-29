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

while ( have_posts() ) {
	the_post();
	$data = $dp_view->full( (int) get_the_ID() );

	echo '<main id="primary" class="dp-main">';
	if ( is_array( $data ) ) {
		include \BrainStudioz\DoctorPilot\Frontend\Templates::locate( 'profile-content.php' );
	} else {
		echo '<p>' . esc_html__( 'This profile is not available.', 'doctorpilot' ) . '</p>';
	}
	echo '</main>';
}

get_footer();
