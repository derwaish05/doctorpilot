<?php
/**
 * DoctorPilot Pro migration/redirect unit tests.
 *
 * Requires the WordPress PHPUnit harness with both DoctorPilot and DoctorPilot
 * Pro loaded. Set WP_TESTS_DIR before running phpunit.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

use BrainStudioz\DoctorPilot\Migration\MappingConfig;
use BrainStudioz\DoctorPilot\Migration\ReviewFlags;
use BrainStudioz\DoctorPilot\Redirects\RedirectValidator;

/**
 * @covers \BrainStudioz\DoctorPilot\Migration\MappingConfig
 * @covers \BrainStudioz\DoctorPilot\Migration\ReviewFlags
 * @covers \BrainStudioz\DoctorPilot\Redirects\RedirectValidator
 */
final class Test_DoctorPilot_Migration extends WP_UnitTestCase {

	public function test_mapping_requires_name_and_source_key(): void {
		$errors = MappingConfig::validate_mapping( array( 'Some Column' => 'credentials' ) );
		$this->assertNotEmpty( $errors );

		$errors = MappingConfig::validate_mapping(
			array( 'Name' => 'name', 'ID' => 'source_key' )
		);
		$this->assertSame( array(), $errors );
	}

	public function test_mapping_rejects_duplicate_targets(): void {
		$errors = MappingConfig::validate_mapping(
			array( 'A' => 'name', 'B' => 'name', 'ID' => 'source_key' )
		);
		$this->assertNotEmpty( $errors );
	}

	public function test_auto_map_matches_common_headers(): void {
		$mapping = MappingConfig::auto_map( array( 'Full Name', 'Old URL', 'Specialty' ) );

		$this->assertSame( 'name', $mapping['Full Name'] ?? '' );
		$this->assertSame( 'legacy_url', $mapping['Old URL'] ?? '' );
		$this->assertSame( 'specialty', $mapping['Specialty'] ?? '' );
	}

	public function test_review_flags_detect_wrong_doctor_and_staging(): void {
		$flags = ReviewFlags::inspect(
			array(
				'name'      => 'Ali A. Haydar',
				'biography' => 'Patients love Dr. Kanso. Visit https://amanhospital.tempurl.host/x',
			)
		);

		$joined = implode( ' ', $flags );
		$this->assertStringContainsString( 'wrong-doctor', $joined );
		$this->assertStringContainsString( 'Staging', $joined );
	}

	public function test_review_flags_detect_placeholder_counter(): void {
		$flags = ReviewFlags::inspect( array( 'name' => 'Jane Smith', 'intro' => '1+ years of care' ) );
		$this->assertNotEmpty( $flags );
	}

	public function test_redirect_validator_rejects_home_and_self(): void {
		$this->assertNotEmpty( RedirectValidator::validate( '/', 0 ) );
	}

	public function test_redirect_validator_flags_external_host(): void {
		$this->assertTrue( RedirectValidator::is_unsafe_destination_host( 'https://amanhospital.tempurl.host/doctor/x' ) );
		$this->assertFalse( RedirectValidator::is_unsafe_destination_host( '/find-a-doctor/dr-x/' ) );
	}
}
