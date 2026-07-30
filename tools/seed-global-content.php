<?php
/**
 * Demo seed: global content library records.
 *
 * Creates reusable Experience, Education, Procedure, Publication, and FAQ records
 * (the five DoctorPilot global content types) and, when the Dr. Ali A. Haydar
 * demo doctor exists, assigns them to that doctor so the hybrid global + local
 * rendering can be seen end to end.
 *
 * Run it either way:
 *
 *   # With WP-CLI, if available:
 *   wp eval-file wp-content/plugins/doctorpilot/tools/seed-global-content.php
 *
 *   # Or directly with PHP (no WP-CLI needed) from the site root:
 *   php wp-content/plugins/doctorpilot/tools/seed-global-content.php
 *
 * Idempotent: each record is matched by a stable seed key, so re-running updates
 * the same records instead of creating duplicates. Records are published; they
 * surface publicly only through a published doctor that references them.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

use BrainStudioz\DoctorPilot\Library\DoctorContentResolver;
use BrainStudioz\DoctorPilot\Library\LibraryPostTypes;
use BrainStudioz\DoctorPilot\Library\ReverseIndex;

// Bootstrap WordPress when run directly with PHP. Under `wp eval-file`, ABSPATH
// is already defined and this block is skipped.
if ( ! defined( 'ABSPATH' ) ) {
	$dp_dir    = __DIR__;
	$dp_loaded = false;
	for ( $dp_i = 0; $dp_i < 8; $dp_i++ ) {
		$dp_dir = dirname( $dp_dir );
		if ( is_readable( $dp_dir . '/wp-load.php' ) ) {
			require $dp_dir . '/wp-load.php';
			$dp_loaded = true;
			break;
		}
	}
	if ( ! $dp_loaded ) {
		fwrite( STDERR, "Could not locate wp-load.php. Run this from inside the WordPress install.\n" );
		exit( 1 );
	}
}

if ( ! class_exists( LibraryPostTypes::class ) || ! post_type_exists( LibraryPostTypes::EXPERIENCE ) ) {
	fwrite( STDERR, "DoctorPilot base plugin (with the global content library) is not active.\n" );
	exit( 1 );
}

const DP_SEED_KEY_META = '_doctorpilot_lib_seed_key';

/**
 * Create or update a library record, matched idempotently by its seed key.
 *
 * @param string               $cpt      Library CPT key.
 * @param string               $seed_key Stable identifier for idempotency.
 * @param string               $title    Post title (kept as typed).
 * @param string               $content  Post content (the type's content field).
 * @param array<string, mixed> $meta     Field key => value for `_doctorpilot_lib_*`.
 * @return int Post ID, or 0 on failure.
 */
function dp_seed_library_record( string $cpt, string $seed_key, string $title, string $content, array $meta ): int {
	$existing = get_posts(
		array(
			'post_type'        => $cpt,
			'post_status'      => 'any',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
			'meta_key'         => DP_SEED_KEY_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'       => $seed_key,        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);

	$postarr = array(
		'post_type'    => $cpt,
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_content' => $content,
	);

	if ( ! empty( $existing ) ) {
		$postarr['ID'] = (int) $existing[0];
		$post_id       = wp_update_post( $postarr, true );
	} else {
		$post_id = wp_insert_post( $postarr, true );
	}

	if ( is_wp_error( $post_id ) || 0 === (int) $post_id ) {
		return 0;
	}

	$post_id = (int) $post_id;
	update_post_meta( $post_id, DP_SEED_KEY_META, $seed_key );
	foreach ( $meta as $field_key => $value ) {
		update_post_meta( $post_id, LibraryPostTypes::META_PREFIX . $field_key, $value );
	}

	return $post_id;
}

// -----------------------------------------------------------------------------
// Demo records, grouped by short resolver type.
// -----------------------------------------------------------------------------

$dp_created = array(
	'exp'  => array(),
	'edu'  => array(),
	'proc' => array(),
	'pub'  => array(),
	'faq'  => array(),
	'loc'  => array(),
);

// Experience.
$dp_created['exp'][] = dp_seed_library_record(
	LibraryPostTypes::EXPERIENCE,
	'exp-cmo-aman',
	'Chief Medical Officer — Aman Hospital',
	'Clinical leadership across diagnostic and interventional radiology services.',
	array( 'primary' => 'Chief Medical Officer', 'secondary' => 'Aman Hospital', 'start_year' => '2018', 'end_year' => '', 'country' => 'Qatar' )
);
$dp_created['exp'][] = dp_seed_library_record(
	LibraryPostTypes::EXPERIENCE,
	'exp-consultant-ir-aman',
	'Consultant Interventional Radiologist — Aman Hospital',
	'Minimally invasive, image-guided treatment for vascular and oncologic conditions.',
	array( 'primary' => 'Consultant Interventional Radiologist', 'secondary' => 'Aman Hospital', 'start_year' => '2015', 'end_year' => '', 'country' => 'Qatar' )
);
$dp_created['exp'][] = dp_seed_library_record(
	LibraryPostTypes::EXPERIENCE,
	'exp-ir-fellow-stmarys',
	"Interventional Radiology Fellow — St Mary's Hospital",
	'Advanced fellowship in vascular and interventional radiology.',
	array( 'primary' => 'Interventional Radiology Fellow', 'secondary' => "St Mary's Hospital, Vascular Unit", 'start_year' => '2008', 'end_year' => '2010', 'country' => 'United Kingdom' )
);

// Education.
$dp_created['edu'][] = dp_seed_library_record(
	LibraryPostTypes::EDUCATION,
	'edu-md-aub',
	'Doctor of Medicine (MD) — American University of Beirut',
	'Doctor of Medicine, graduated with distinction.',
	array( 'primary' => 'Doctor of Medicine (MD)', 'secondary' => 'American University of Beirut', 'start_year' => '1998', 'end_year' => '2004', 'country' => 'Lebanon' )
);
$dp_created['edu'][] = dp_seed_library_record(
	LibraryPostTypes::EDUCATION,
	'edu-mrcp',
	'MRCP — Royal College of Physicians',
	'Membership of the Royal College of Physicians.',
	array( 'primary' => 'MRCP', 'secondary' => 'Royal College of Physicians', 'start_year' => '', 'end_year' => '2007', 'country' => 'United Kingdom' )
);
$dp_created['edu'][] = dp_seed_library_record(
	LibraryPostTypes::EDUCATION,
	'edu-frcr',
	'FRCR — Royal College of Radiologists',
	'Fellowship of the Royal College of Radiologists.',
	array( 'primary' => 'FRCR', 'secondary' => 'Royal College of Radiologists', 'start_year' => '', 'end_year' => '2011', 'country' => 'United Kingdom' )
);

// Procedures — the name is the post title; description is the content.
$dp_created['proc'][] = dp_seed_library_record(
	LibraryPostTypes::PROCEDURE,
	'proc-ufe',
	'Uterine Fibroid Embolization',
	'Non-surgical, image-guided treatment for symptomatic uterine fibroids.',
	array()
);
$dp_created['proc'][] = dp_seed_library_record(
	LibraryPostTypes::PROCEDURE,
	'proc-pae',
	'Prostate Artery Embolization',
	'Image-guided option for benign prostatic hyperplasia (BPH).',
	array()
);
$dp_created['proc'][] = dp_seed_library_record(
	LibraryPostTypes::PROCEDURE,
	'proc-varicocele',
	'Varicocele Embolization',
	'Minimally invasive procedure addressing a common cause of male infertility.',
	array()
);
$dp_created['proc'][] = dp_seed_library_record(
	LibraryPostTypes::PROCEDURE,
	'proc-chemoembolization',
	'Tumour Chemoembolization',
	'Targeted therapy delivering chemotherapy directly to liver and other organ tumours.',
	array()
);

// Publications — the title is the post title (demo titles; no fabricated DOIs).
$dp_created['pub'][] = dp_seed_library_record(
	LibraryPostTypes::PUBLICATION,
	'pub-ufe-cohort',
	'Outcomes of uterine fibroid embolization in a Middle Eastern cohort',
	'A retrospective review of symptom relief and re-intervention rates.',
	array( 'journal' => 'CardioVascular and Interventional Radiology', 'published_date' => '2021', 'url' => '', 'doi' => '' )
);
$dp_created['pub'][] = dp_seed_library_record(
	LibraryPostTypes::PUBLICATION,
	'pub-pae-review',
	'Prostate artery embolization: a systematic review',
	'Evidence synthesis of efficacy and safety across published series.',
	array( 'journal' => 'Journal of Vascular and Interventional Radiology', 'published_date' => '2020', 'url' => '', 'doi' => '' )
);
$dp_created['pub'][] = dp_seed_library_record(
	LibraryPostTypes::PUBLICATION,
	'pub-trauma-haemorrhage',
	'Emergency image-guided management of trauma haemorrhage',
	'Case series on catheter-based control of acute bleeding.',
	array( 'journal' => 'European Radiology', 'published_date' => '2019', 'url' => '', 'doi' => '' )
);

// FAQs — the question is the post title; the answer is the content.
$dp_created['faq'][] = dp_seed_library_record(
	LibraryPostTypes::FAQ,
	'faq-what-is-ir',
	'What is interventional radiology?',
	'Interventional radiology uses image guidance (X-ray, ultrasound, or CT) to perform minimally invasive procedures through tiny incisions, often avoiding open surgery and shortening recovery.',
	array()
);
$dp_created['faq'][] = dp_seed_library_record(
	LibraryPostTypes::FAQ,
	'faq-referral',
	'Do I need a referral to book a procedure?',
	'In most cases a referral from your physician is recommended, but you can contact the department directly to discuss your options.',
	array()
);
$dp_created['faq'][] = dp_seed_library_record(
	LibraryPostTypes::FAQ,
	'faq-anaesthesia',
	'Will I need general anaesthesia?',
	'Many interventional procedures are performed under local anaesthetic with light sedation; your care team will confirm the plan with you beforehand.',
	array()
);

// Locations — the name is the post title; opening hours are the content.
$dp_created['loc'][] = dp_seed_library_record(
	LibraryPostTypes::LOCATION,
	'loc-aman-main',
	'Aman Hospital — Main Campus',
	"Sun–Thu: 8:00 – 20:00\nSat: 9:00 – 14:00",
	array( 'area' => 'Al Rayyan Road', 'city' => 'Doha', 'phone' => '+974 4000 0000', 'map_url' => '' )
);
$dp_created['loc'][] = dp_seed_library_record(
	LibraryPostTypes::LOCATION,
	'loc-aman-clinic',
	'Aman Outpatient Clinic',
	'Sun–Thu: 9:00 – 17:00',
	array( 'area' => 'West Bay', 'city' => 'Doha', 'phone' => '+974 4000 0001', 'map_url' => '' )
);

// -----------------------------------------------------------------------------
// Assign the new records to the demo doctor, if present.
// -----------------------------------------------------------------------------

$dp_doctor = get_posts(
	array(
		'post_type'        => 'doctorpilot_doctor',
		'post_status'      => 'any',
		'posts_per_page'   => 1,
		'fields'           => 'ids',
		'no_found_rows'    => true,
		'suppress_filters' => false,
		'meta_key'         => '_doctorpilot_external_source_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'       => 'aman-ali-a-haydar',               // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	)
);

$dp_assigned = 0;
if ( ! empty( $dp_doctor ) ) {
	$doctor_id = (int) $dp_doctor[0];

	foreach ( $dp_created as $type => $ids ) {
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( array() === $ids ) {
			continue;
		}

		$key     = DoctorContentResolver::ids_key( $type );
		$current = get_post_meta( $doctor_id, $key, true );
		$current = is_array( $current ) ? array_map( 'intval', $current ) : array();

		$merged = array_values( array_unique( array_merge( $current, $ids ) ) );
		update_post_meta( $doctor_id, $key, $merged );

		$added = array_values( array_diff( $merged, $current ) );
		if ( array() !== $added ) {
			ReverseIndex::apply_diff( $doctor_id, $added, array() );
			$dp_assigned += count( $added );
		}
	}
}

// -----------------------------------------------------------------------------
// Report.
// -----------------------------------------------------------------------------

$dp_total = 0;
foreach ( $dp_created as $ids ) {
	$dp_total += count( array_filter( $ids ) );
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::success(
		sprintf(
			'Seeded %d global content records (exp %d, edu %d, proc %d, pub %d, faq %d, loc %d). Assigned %d to the demo doctor.',
			$dp_total,
			count( array_filter( $dp_created['exp'] ) ),
			count( array_filter( $dp_created['edu'] ) ),
			count( array_filter( $dp_created['proc'] ) ),
			count( array_filter( $dp_created['pub'] ) ),
			count( array_filter( $dp_created['faq'] ) ),
			count( array_filter( $dp_created['loc'] ) ),
			$dp_assigned
		)
	);
} else {
	fwrite( STDOUT, "Seeded {$dp_total} global content records; assigned {$dp_assigned} to the demo doctor.\n" );
}
