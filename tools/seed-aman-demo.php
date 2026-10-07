<?php
/**
 * Demo seed: Dr. Ali A. Haydar (from the live Aman Hospital profile).
 *
 * Creates one structured doctor as a DRAFT with review status "needs review".
 * Nothing is published — the live source page mixes in another person's
 * testimonial content, so an editor must review before publishing (ADR-0007).
 *
 * Run in a WordPress environment with the DoctorPilot base plugin active:
 *
 *   wp eval-file wp-content/plugins/doctorpilot/tools/seed-aman-demo.php
 *
 * Idempotent: re-running updates the same draft (matched by source key).
 *
 * Source: https://amanhospital.org/doctor/ali-a-haydar/ (retrieved for demo).
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via WP-CLI: wp eval-file <path>\n" );
	exit( 1 );
}

if ( ! post_type_exists( 'doctorpilot_doctor' ) ) {
	fwrite( STDERR, "DoctorPilot base plugin is not active.\n" );
	exit( 1 );
}

$dp_prefix     = '_doctorpilot_';
$dp_source_key = 'aman-ali-a-haydar';

/**
 * Find or create a term and return its ID.
 *
 * @param string $name     Term name.
 * @param string $taxonomy Taxonomy.
 * @return int
 */
function dp_seed_term( string $name, string $taxonomy ): int {
	$existing = get_term_by( 'name', $name, $taxonomy );
	if ( $existing instanceof WP_Term ) {
		return (int) $existing->term_id;
	}
	$created = wp_insert_term( $name, $taxonomy );

	return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
}

// Locate an existing seed draft (idempotency).
$dp_existing = get_posts(
	array(
		'post_type'      => 'doctorpilot_doctor',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => $dp_prefix . 'external_source_id',
		'meta_value'     => $dp_source_key,
	)
);

$dp_bio = "Dr. Ali A. Haydar, MD, MRCP, FRCR, EMBA, is widely recognized as one of the most accomplished interventional radiologists in the Middle East. Serving as Chief Medical Officer and Chair of Diagnostic & Interventional Radiology Services at Aman Hospital, he combines more than 15 years of advanced clinical experience with a patient-centred approach. Fluent in Arabic, English, and French, Dr. Haydar specialises in minimally invasive, image-guided procedures for vascular, oncologic, and emergency conditions.";

$dp_postarr = array(
	'post_type'    => 'doctorpilot_doctor',
	'post_status'  => 'draft',
	'post_name'    => 'dr-ali-a-haydar',
	'post_title'   => 'Ali A. Haydar',
	'post_excerpt' => 'Interventional Radiology Consultant and Chief Medical Officer at Aman Hospital, specialising in minimally invasive image-guided treatment.',
	'post_content' => $dp_bio,
);

if ( array() !== $dp_existing ) {
	$dp_postarr['ID'] = (int) $dp_existing[0];
	$dp_id            = (int) wp_update_post( $dp_postarr, true );
} else {
	$dp_id = (int) wp_insert_post( $dp_postarr, true );
}

if ( is_wp_error( $dp_id ) || 0 === $dp_id ) {
	fwrite( STDERR, "Failed to create the doctor.\n" );
	exit( 1 );
}

// Taxonomy relationships.
$dp_department = dp_seed_term( 'Diagnostic & Interventional Radiology', 'doctorpilot_department' );
$dp_specialty  = dp_seed_term( 'Interventional Radiology', 'doctorpilot_specialty' );
wp_set_object_terms( $dp_id, array( $dp_department ), 'doctorpilot_department' );
wp_set_object_terms( $dp_id, array( $dp_specialty ), 'doctorpilot_specialty' );

$dp_languages = array();
foreach ( array( 'Arabic', 'English', 'French' ) as $dp_lang ) {
	$dp_languages[] = dp_seed_term( $dp_lang, 'doctorpilot_language' );
}
wp_set_object_terms( $dp_id, $dp_languages, 'doctorpilot_language' );

$dp_symptoms = array();
foreach ( array( 'Varicose Veins', 'Uterine Fibroids', 'Internal Bleeding' ) as $dp_symptom ) {
	$dp_symptoms[] = dp_seed_term( $dp_symptom, 'doctorpilot_symptom' );
}
wp_set_object_terms( $dp_id, $dp_symptoms, 'doctorpilot_symptom' );

$dp_treatments = array();
foreach (
	array(
		'Uterine Fibroid Embolization',
		'Varicocele Embolization',
		'Prostate Artery Embolization',
		'Tumor Embolization',
		'Chemoembolization',
		'Varicose Veins Treatment',
		'Image-guided Biopsy',
		'Angiography',
	) as $dp_treatment
) {
	$dp_treatments[] = dp_seed_term( $dp_treatment, 'doctorpilot_treatment' );
}
wp_set_object_terms( $dp_id, $dp_treatments, 'doctorpilot_treatment' );

// Scalar meta.
update_post_meta( $dp_id, $dp_prefix . 'external_source_id', $dp_source_key );
update_post_meta( $dp_id, $dp_prefix . 'review_status', 'needs_review' );
update_post_meta( $dp_id, $dp_prefix . 'prefix', 'dr' );
update_post_meta( $dp_id, $dp_prefix . 'gender', 'male' );
update_post_meta( $dp_id, $dp_prefix . 'years_experience', 15 );
update_post_meta( $dp_id, $dp_prefix . 'legacy_url', 'https://amanhospital.org/doctor/ali-a-haydar/' );
update_post_meta( $dp_id, $dp_prefix . 'seo_canonical_url', home_url( '/find-a-doctor/dr-ali-a-haydar/' ) );
update_post_meta( $dp_id, $dp_prefix . 'booking_method', 'external_url' );
update_post_meta( $dp_id, $dp_prefix . 'booking_external_url', 'https://amanhospital.org/book-an-appointment/' );
update_post_meta( $dp_id, $dp_prefix . 'booking_label', 'Book Consultation' );

// Leadership positions (repeater).
update_post_meta(
	$dp_id,
	$dp_prefix . 'leadership_positions',
	array(
		array( 'title' => 'Chief Medical Officer', 'organization_or_scope' => 'Aman Hospital', 'start_year' => '', 'end_year' => '' ),
		array( 'title' => 'Chair of Diagnostic & Interventional Radiology Services', 'organization_or_scope' => 'Aman Hospital', 'start_year' => '', 'end_year' => '' ),
	)
);

// Procedures (repeater).
update_post_meta(
	$dp_id,
	$dp_prefix . 'procedures',
	array(
		array( 'name' => 'Uterine Fibroid Embolization', 'description' => 'Non-surgical, image-guided treatment for symptomatic uterine fibroids.' ),
		array( 'name' => 'Varicocele Embolization', 'description' => 'Minimally invasive procedure addressing a common cause of male infertility.' ),
		array( 'name' => 'Prostate Artery Embolization', 'description' => 'Image-guided option for benign prostatic hyperplasia (BPH).' ),
		array( 'name' => 'Tumor Embolization / Chemoembolization', 'description' => 'Targeted therapy for liver and other organ cancers.' ),
		array( 'name' => 'Vascular Intervention', 'description' => 'Catheter-based treatment for trauma, internal bleeding, and peripheral artery disease.' ),
	)
);

// Clinical interests.
update_post_meta(
	$dp_id,
	$dp_prefix . 'clinical_interests',
	array( 'Vascular interventions', 'Interventional oncology', 'Emergency image-guided care', 'Women\'s health interventions' )
);

// Academic highlights (repeater) — shown in the sidebar Academic Highlights widget.
update_post_meta(
	$dp_id,
	$dp_prefix . 'academic_highlights',
	array(
		array( 'value' => '90+', 'label' => 'Peer-Reviewed Publications' ),
		array( 'value' => '20+', 'label' => 'Conference Posters & Presentations' ),
		array( 'value' => '12+', 'label' => 'Prizes & Awards' ),
		array( 'value' => '25+', 'label' => 'Professional Courses' ),
	)
);

// Education (doctor-specific local repeater; global records may also be assigned
// via _doctorpilot_edu_ids from the editor's shared-records selector).
update_post_meta(
	$dp_id,
	$dp_prefix . 'edu_local',
	array(
		array( 'primary' => 'Doctor of Medicine (MD), with distinction', 'secondary' => 'American University of Beirut', 'start_year' => '', 'end_year' => '', 'country' => 'Lebanon', 'description' => '' ),
		array( 'primary' => 'Interventional Radiology Fellowship', 'secondary' => "St Mary's Hospital, Vascular Unit", 'start_year' => '', 'end_year' => '', 'country' => 'United Kingdom', 'description' => '' ),
	)
);

// Experience (doctor-specific local repeater).
update_post_meta(
	$dp_id,
	$dp_prefix . 'exp_local',
	array(
		array( 'primary' => 'Consultant Interventional Radiologist', 'secondary' => 'Aman Hospital', 'start_year' => '', 'end_year' => '', 'country' => 'Qatar', 'description' => '' ),
	)
);

// Certifications & memberships (repeater).
update_post_meta(
	$dp_id,
	$dp_prefix . 'certifications',
	array(
		array( 'name' => 'Fellow of the Royal College of Radiologists (FRCR)', 'organization' => 'Royal College of Radiologists', 'issued_date' => '', 'expiry_date' => '', 'credential_url' => '' ),
		array( 'name' => 'Member of the Royal College of Physicians (MRCP)', 'organization' => 'Royal College of Physicians', 'issued_date' => '', 'expiry_date' => '', 'credential_url' => '' ),
		array( 'name' => 'Fellow, Pan Arab Interventional Radiology Society', 'organization' => 'Pan Arab Interventional Radiology Society', 'issued_date' => '', 'expiry_date' => '', 'credential_url' => '' ),
		array( 'name' => 'Member, Cardiovascular and Interventional Radiological Society of Europe (CIRSE)', 'organization' => 'CIRSE', 'issued_date' => '', 'expiry_date' => '', 'credential_url' => '' ),
	)
);

// FAQs (repeater) — derived from the profile; verify before publishing.
update_post_meta(
	$dp_id,
	$dp_prefix . 'faqs',
	array(
		array( 'question' => 'What conditions does Dr. Haydar treat?', 'answer' => 'Vascular conditions, cancers requiring targeted therapy, uterine fibroids, varicose veins, and emergency bleeding — using minimally invasive, image-guided techniques.' ),
		array( 'question' => 'Which languages does Dr. Haydar speak?', 'answer' => 'Arabic, English, and French.' ),
	)
);

// Optionally sideload the profile photo as the featured image.
if ( ! has_post_thumbnail( $dp_id ) && function_exists( 'media_sideload_image' ) ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$dp_image = 'https://amanhospital.org/wp-content/uploads/2019/02/DR-ALI-HAYDAR-1-970x1024-1-1.webp';
	$dp_att   = media_sideload_image( $dp_image, $dp_id, 'Dr. Ali A. Haydar', 'id' );
	if ( ! is_wp_error( $dp_att ) ) {
		set_post_thumbnail( $dp_id, (int) $dp_att );
	}
}

// Rebuild the derived search index.
if ( class_exists( '\\BrainStudioz\\DoctorPilot\\Application\\Search\\SearchIndexBuilder' ) ) {
	\BrainStudioz\DoctorPilot\Application\Search\SearchIndexBuilder::rebuild( $dp_id );
}

echo "Seeded Dr. Ali A. Haydar as DRAFT (needs review), post ID {$dp_id}.\n";
echo "Review and publish under DoctorPilot > Doctors. A redirect from /doctor/ali-a-haydar/ can be added in DoctorPilot Pro > Migration > Redirects.\n";
