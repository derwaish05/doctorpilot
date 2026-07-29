<?php
/**
 * Doctor archive template.
 *
 * Override by copying to your theme as doctorpilot/archive-doctor.php.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$dp_view = new \BrainStudioz\DoctorPilot\Application\Doctors\DoctorView();

echo '<main id="primary" class="dp-main">';

echo '<header class="dp-archive__header"><h1>';
echo esc_html( post_type_archive_title( '', false ) ? post_type_archive_title( '', false ) : __( 'Find a Doctor', 'doctorpilot' ) );
echo '</h1></header>';

if ( have_posts() ) {
	echo '<div class="dp-directory dp-cols-3">';
	while ( have_posts() ) {
		the_post();
		$card = $dp_view->card( (int) get_the_ID() );
		if ( is_array( $card ) ) {
			include \BrainStudioz\DoctorPilot\Frontend\Templates::locate( 'card-doctor.php' );
		}
	}
	echo '</div>';

	the_posts_pagination();
} else {
	echo '<p class="dp-empty">' . esc_html__( 'No doctors found.', 'doctorpilot' ) . '</p>';
}

echo '</main>';

get_footer();
