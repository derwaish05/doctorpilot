<?php
/**
 * PHPUnit bootstrap for the WordPress test harness.
 *
 * Requires the WordPress PHPUnit test library. Set WP_TESTS_DIR (or
 * WP_PHPUNIT__DIR) to its location before running `phpunit`.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

$dp_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( false === $dp_tests_dir || '' === $dp_tests_dir ) {
	$dp_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

$dp_functions = $dp_tests_dir . '/includes/functions.php';

if ( ! is_readable( $dp_functions ) ) {
	fwrite(
		STDERR,
		"Could not find the WordPress test suite at {$dp_tests_dir}.\n" .
		"Install it with bin/install-wp-tests.sh or set WP_TESTS_DIR.\n"
	);
	exit( 1 );
}

require_once $dp_functions;

/**
 * Load the plugin under test before WordPress finishes bootstrapping.
 *
 * @return void
 */
function bstdz_doctorpilot_manually_load_plugin(): void {
	require dirname( __DIR__ ) . '/doctorpilot.php';
}

tests_add_filter( 'muplugins_loaded', 'bstdz_doctorpilot_manually_load_plugin' );

require $dp_tests_dir . '/includes/bootstrap.php';
