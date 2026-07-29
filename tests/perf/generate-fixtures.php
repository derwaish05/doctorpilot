<?php
/**
 * Synthetic performance fixture generator.
 *
 * Creates N published doctors with departments, specialties, symptoms, and
 * languages, then rebuilds each search index. Uses only synthetic, reviewed
 * data — no real names, credentials, or medical relationships.
 *
 * Run inside a WordPress environment, e.g.:
 *
 *   wp eval-file wp-content/plugins/doctorpilot/tests/perf/generate-fixtures.php 1000
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this via WP-CLI: wp eval-file <path> [count]\n" );
	exit( 1 );
}

use BrainStudioz\DoctorPilot\Application\Search\SearchIndexBuilder;
use BrainStudioz\DoctorPilot\Fields\FieldRegistry;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;
use BrainStudioz\DoctorPilot\Taxonomies\Taxonomies;

$dp_count = 1000;
if ( isset( $args ) && is_array( $args ) && isset( $args[0] ) ) {
	$dp_count = max( 1, (int) $args[0] );
}

$dp_departments = array( 'Cardiology', 'Radiology', 'Neurology', 'Oncology', 'Pediatrics', 'Orthopedics', 'Dermatology', 'Urology' );
$dp_specialties = array( 'Interventional Cardiology', 'Interventional Radiology', 'Clinical Neurology', 'Medical Oncology', 'Neonatology', 'Joint Replacement', 'Cosmetic Dermatology', 'Endourology' );
$dp_symptoms    = array( 'Chest Pain', 'Headache', 'Back Pain', 'Skin Rash', 'Shortness of Breath', 'Joint Pain' );
$dp_languages   = array( 'English', 'Arabic', 'French', 'Spanish' );

/**
 * Ensure a term exists and return its ID.
 *
 * @param string $name     Term name.
 * @param string $taxonomy Taxonomy key.
 * @return int
 */
function dp_perf_term( string $name, string $taxonomy ): int {
	$existing = get_term_by( 'name', $name, $taxonomy );
	if ( $existing instanceof WP_Term ) {
		return (int) $existing->term_id;
	}
	$created = wp_insert_term( $name, $taxonomy );

	return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
}

$dp_start = microtime( true );

for ( $i = 1; $i <= $dp_count; $i++ ) {
	$dept       = $dp_departments[ $i % count( $dp_departments ) ];
	$specialty  = $dp_specialties[ $i % count( $dp_specialties ) ];
	$symptom    = $dp_symptoms[ $i % count( $dp_symptoms ) ];
	$language   = $dp_languages[ $i % count( $dp_languages ) ];

	$id = wp_insert_post(
		array(
			'post_type'   => DoctorPostType::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => sprintf( 'Dr. Test Doctor %04d', $i ),
			'post_excerpt' => 'Synthetic profile for performance testing.',
		)
	);

	if ( is_wp_error( $id ) || 0 === $id ) {
		continue;
	}

	wp_set_object_terms( $id, array( dp_perf_term( $dept, Taxonomies::DEPARTMENT ) ), Taxonomies::DEPARTMENT );
	wp_set_object_terms( $id, array( dp_perf_term( $specialty, Taxonomies::SPECIALTY ) ), Taxonomies::SPECIALTY );
	wp_set_object_terms( $id, array( dp_perf_term( $symptom, Taxonomies::SYMPTOM ) ), Taxonomies::SYMPTOM );
	wp_set_object_terms( $id, array( dp_perf_term( $language, Taxonomies::LANGUAGE ) ), Taxonomies::LANGUAGE );

	update_post_meta( $id, FieldRegistry::META_PREFIX . 'years_experience', ( $i % 30 ) + 1 );
	update_post_meta( $id, FieldRegistry::META_PREFIX . 'is_featured', 0 === $i % 25 ? 1 : 0 );

	SearchIndexBuilder::rebuild( $id );
}

$dp_seconds = round( microtime( true ) - $dp_start, 2 );
echo "Created {$dp_count} synthetic doctors in {$dp_seconds}s\n";
