<?php
/**
 * Reviewed demo seed for Aman Hospital — Dr. Ali A. Haydar.
 *
 * Creates a single structured doctor as a DRAFT marked "needs review", using
 * only facts verified from the live profile at
 * https://amanhospital.org/doctor/ali-a-haydar/ (fetched 2026-07-28).
 *
 * Deliberately EXCLUDED per the migration acceptance criteria:
 *   - the incorrect "Dr. Kanso" testimonials present on the legacy page;
 *   - placeholder counters such as "1+";
 *   - any staging (tempurl.host) URLs.
 *
 * Run in a WordPress environment with DoctorPilot active:
 *
 *   wp eval-file wp-content/plugins/doctorpilot/samples/seed-aman.php
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via WP-CLI: wp eval-file <path>\n" );
	exit( 1 );
}

$dp_meta = '_doctorpilot_';
$dp_key  = 'ali-a-haydar';

/**
 * Find or create a term, returning its ID.
 *
 * @param string $name     Term name.
 * @param string $taxonomy Taxonomy.
 * @return int
 */
$dp_term = static function ( string $name, string $taxonomy ): int {
	$existing = get_term_by( 'name', $name, $taxonomy );
	if ( $existing instanceof WP_Term ) {
		return (int) $existing->term_id;
	}
	$created = wp_insert_term( $name, $taxonomy );

	return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
};

// Idempotency: reuse an existing seeded doctor if present.
$dp_existing = get_posts(
	array(
		'post_type'      => 'doctorpilot_doctor',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => $dp_meta . 'external_source_id',
		'meta_value'     => $dp_key,
	)
);

$dp_postarr = array(
	'post_type'    => 'doctorpilot_doctor',
	'post_status'  => 'draft',
	'post_name'    => 'dr-ali-a-haydar',
	'post_title'   => 'Dr. Ali A. Haydar',
	'post_excerpt' => 'Chief Medical Officer and Chair of Diagnostic & Interventional Radiology at Aman Hospital, Doha.',
	'post_content' => 'Dr. Ali A. Haydar is a board-certified interventional radiology consultant based in Doha, Qatar. '
		. 'He serves as Chief Medical Officer and Chair of Diagnostic & Interventional Radiology at Aman Hospital, '
		. 'and specializes in minimally invasive, image-guided procedures for diagnosis and treatment. '
		. 'He consults in Arabic, English, and French.',
);

if ( ! empty( $dp_existing ) ) {
	$dp_postarr['ID'] = (int) $dp_existing[0];
	$dp_id            = (int) wp_update_post( $dp_postarr, true );
} else {
	$dp_id = (int) wp_insert_post( $dp_postarr, true );
}

if ( is_wp_error( $dp_id ) || 0 === $dp_id ) {
	fwrite( STDERR, "Failed to create the doctor.\n" );
	exit( 1 );
}

// Taxonomies.
wp_set_object_terms( $dp_id, array( $dp_term( 'Radiology', 'doctorpilot_department' ) ), 'doctorpilot_department' );
$dp_specialty = $dp_term( 'Interventional Radiology', 'doctorpilot_specialty' );
wp_set_object_terms( $dp_id, array( $dp_specialty ), 'doctorpilot_specialty' );
wp_set_object_terms(
	$dp_id,
	array(
		$dp_term( 'Arabic', 'doctorpilot_language' ),
		$dp_term( 'English', 'doctorpilot_language' ),
		$dp_term( 'French', 'doctorpilot_language' ),
	),
	'doctorpilot_language'
);

// Scalar meta (verified facts only).
update_post_meta( $dp_id, $dp_meta . 'external_source_id', $dp_key );
update_post_meta( $dp_id, $dp_meta . 'review_status', 'needs_review' );
update_post_meta( $dp_id, $dp_meta . 'credentials', 'MD, MRCP, FRCR, EMBA' );
update_post_meta( $dp_id, $dp_meta . 'gender', 'male' );
update_post_meta( $dp_id, $dp_meta . 'years_experience', 15 );
update_post_meta( $dp_id, $dp_meta . 'is_featured', 1 );
update_post_meta( $dp_id, $dp_meta . 'accepting_new_patients', 1 );
update_post_meta( $dp_id, $dp_meta . 'primary_specialty_id', $dp_specialty );
update_post_meta( $dp_id, $dp_meta . 'legacy_url', '/doctor/ali-a-haydar/' );
update_post_meta( $dp_id, $dp_meta . 'seo_canonical_url', 'https://amanhospital.org/find-a-doctor/dr-ali-a-haydar/' );
update_post_meta( $dp_id, $dp_meta . 'seo_description', 'Dr. Ali A. Haydar, board-certified interventional radiology consultant in Doha, Qatar. Expert in minimally invasive image-guided procedures for diagnosis and treatment.' );

// Booking: enquiry (no guessed external URL).
update_post_meta( $dp_id, $dp_meta . 'booking_method', 'enquiry' );
update_post_meta( $dp_id, $dp_meta . 'booking_label', 'Request an appointment' );

// Leadership positions (verified).
update_post_meta(
	$dp_id,
	$dp_meta . 'leadership_positions',
	array(
		array( 'title' => 'Chief Medical Officer', 'organization_or_scope' => 'Aman Hospital', 'start_year' => 0, 'end_year' => 0 ),
		array( 'title' => 'Chair of Diagnostic & Interventional Radiology', 'organization_or_scope' => 'Aman Hospital', 'start_year' => 0, 'end_year' => 0 ),
	)
);

// A single location record (verified: Aman Hospital, Doha, Qatar).
$dp_location = $dp_term( 'Aman Hospital', 'doctorpilot_location' );
update_post_meta(
	$dp_id,
	$dp_meta . 'locations',
	array(
		array(
			'location_term_id'   => $dp_location,
			'department_term_id' => 0,
			'phone'              => '',
			'public_email'       => '',
			'email_is_public'    => 0,
			'room_floor'         => 'Doha, Qatar',
			'consultation_type'  => 'in_person',
			'office_hours_text'  => '',
		),
	)
);

// FAQs kept to safe, verified, non-diagnostic information only.
update_post_meta(
	$dp_id,
	$dp_meta . 'faqs',
	array(
		array(
			'question' => 'What languages does Dr. Haydar speak?',
			'answer'   => 'Dr. Haydar consults in Arabic, English, and French.',
		),
		array(
			'question' => 'What is interventional radiology?',
			'answer'   => 'Interventional radiology uses minimally invasive, image-guided techniques to diagnose and treat conditions. This information is general and is not a diagnosis.',
		),
	)
);

// Rebuild the derived search index if the base builder is available.
if ( class_exists( '\\BrainStudioz\\DoctorPilot\\Application\\Search\\SearchIndexBuilder' ) ) {
	\BrainStudioz\DoctorPilot\Application\Search\SearchIndexBuilder::rebuild( $dp_id );
}

// Optional: sideload the public profile photo as the featured image.
if ( ! has_post_thumbnail( $dp_id ) && function_exists( 'media_sideload_image' ) === false ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
}
if ( ! has_post_thumbnail( $dp_id ) && function_exists( 'media_sideload_image' ) ) {
	$dp_photo = media_sideload_image(
		'https://amanhospital.org/wp-content/uploads/2019/02/DR-ALI-HAYDAR-1-970x1024-1-1.webp',
		$dp_id,
		'Dr. Ali A. Haydar',
		'id'
	);
	if ( ! is_wp_error( $dp_photo ) ) {
		set_post_thumbnail( $dp_id, (int) $dp_photo );
	}
}

// Register the legacy → new redirect (Pro). Resolves only once published.
if ( class_exists( '\\BrainStudioz\\DoctorPilot\\Redirects\\RedirectRepository' ) ) {
	$dp_dest = wp_parse_url( (string) get_permalink( $dp_id ), PHP_URL_PATH );
	$dp_dest = is_string( $dp_dest ) ? user_trailingslashit( '/' . ltrim( $dp_dest, '/' ) ) : '';
	( new \BrainStudioz\DoctorPilot\Redirects\RedirectRepository() )->save( '/doctor/ali-a-haydar/', $dp_id, $dp_dest, true );
}

echo "Seeded Dr. Ali A. Haydar as draft (needs review), post ID {$dp_id}.\n";
echo "Review and publish from Doctors → Dr. Ali A. Haydar before go-live.\n";
