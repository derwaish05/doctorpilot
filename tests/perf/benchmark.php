<?php
/**
 * Search performance benchmark.
 *
 * Runs a set of representative queries against the current data set and reports
 * p50/p95 latency and the database query count per request. Intended to be run
 * after generate-fixtures.php in a WordPress environment:
 *
 *   wp eval-file wp-content/plugins/doctorpilot/tests/perf/benchmark.php
 *
 * Target (spec §20 / NFR): REST p95 <= 500 ms for 1,000 published doctors,
 * excluding network. This script measures the service layer only.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this via WP-CLI: wp eval-file <path>\n" );
	exit( 1 );
}

use BrainStudioz\DoctorPilot\Application\Search\SearchService;
use BrainStudioz\DoctorPilot\Domain\Search\SearchRequestFactory;

$dp_queries = array(
	array( 'q' => 'cardiology' ),
	array( 'q' => 'chest pain' ),
	array( 'q' => 'test doctor 0042' ),
	array( 'specialty' => 'interventional-cardiology' ),
	array( 'department' => 'radiology', 'sort' => 'experience' ),
	array( 'symptom' => 'headache', 'language' => 'arabic' ),
	array( 'q' => '', 'sort' => 'name', 'page' => 3 ),
);

$dp_service = new SearchService();
$dp_times   = array();
$dp_queries_used = array();

foreach ( $dp_queries as $dp_raw ) {
	$request = SearchRequestFactory::from_array( $dp_raw, 12 );

	$before_queries = (int) get_num_queries();
	$start          = microtime( true );

	$dp_service->search( $request );

	$dp_times[]        = ( microtime( true ) - $start ) * 1000;
	$dp_queries_used[] = (int) get_num_queries() - $before_queries;
}

sort( $dp_times );
$dp_count = count( $dp_times );
$dp_p50   = $dp_times[ (int) floor( $dp_count * 0.50 ) ];
$dp_p95   = $dp_times[ min( $dp_count - 1, (int) floor( $dp_count * 0.95 ) ) ];

printf( "Environment: PHP %s, WP %s\n", PHP_VERSION, get_bloginfo( 'version' ) );
printf( "Queries run: %d\n", $dp_count );
printf( "Latency p50: %.1f ms\n", $dp_p50 );
printf( "Latency p95: %.1f ms\n", $dp_p95 );
printf( "Avg DB queries/request: %.1f\n", array_sum( $dp_queries_used ) / max( 1, $dp_count ) );
printf( "Target: p95 <= 500 ms — %s\n", $dp_p95 <= 500 ? 'PASS' : 'REVIEW' );
