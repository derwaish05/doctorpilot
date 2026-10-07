<?php
/**
 * Doctor archive template — the plugin's own search page.
 *
 * Renders the same search header, browse tabs, A–Z bar and filtered grid the
 * Doctor Grid / List widget renders, through the same assembly, so a link like
 * `/doctors/?department=cardiology` from anywhere on the site lands on real
 * results. No Elementor required: this is the native path.
 *
 * A site that would rather design its own search page builds one and sets it as
 * the Search page in DoctorPilot → Settings; that page then wins for every link
 * the index widget writes, and this template keeps working as the archive.
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

echo '<main id="primary" class="dp-main dp-archive">';

$dp_title = post_type_archive_title( '', false );
$dp_intro = '';
if ( \BrainStudioz\DoctorPilot\Frontend\Templates::is_doctor_taxonomy() ) {
	$dp_term = get_queried_object();
	if ( $dp_term instanceof WP_Term ) {
		$dp_title = $dp_term->name;
		$dp_intro = wp_strip_all_tags( (string) $dp_term->description );
	}
}

/*
 * The title band is deliberately tall. Themes whose header floats over the page
 * (the header box has no height of its own, and every page template is expected
 * to open with a hero that clears it) would otherwise slide the search bar under
 * the logo. A band reads as a page header on themes that do not need it, and
 * rescues the ones that do.
 */
echo '<header class="dp-archive__band"><div class="dp-archive__inner">';
echo '<h1 class="dp-archive__title">';
echo esc_html( '' !== (string) $dp_title ? (string) $dp_title : __( 'Find a Doctor', 'doctorpilot' ) );
echo '</h1>';
if ( '' !== $dp_intro ) {
	echo '<p class="dp-archive__intro">' . esc_html( $dp_intro ) . '</p>';
}
echo '</div></header>';

echo '<div class="dp-archive__inner dp-archive__body">';

/*
 * A taxonomy archive already carries its term in the main query, and the
 * directory reads its filters from the query string — so the term is passed
 * across as the matching search parameter and the two agree.
 */
$dp_args = array(
	'uid'     => 'dp-dir-archive',
	'prefill' => \BrainStudioz\DoctorPilot\Frontend\DirectoryRenderer::archive_prefill(),
);

\BrainStudioz\DoctorPilot\Frontend\DirectoryRenderer::render( $dp_args );

echo '</div>';

echo '</main>';

get_footer();
