<?php
/**
 * Field registry and sanitizer tests.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

use BrainStudioz\DoctorPilot\Fields\FieldRegistry;
use BrainStudioz\DoctorPilot\Fields\Sanitizer;

/**
 * @covers \BrainStudioz\DoctorPilot\Fields\FieldRegistry
 * @covers \BrainStudioz\DoctorPilot\Fields\Sanitizer
 */
final class Test_DoctorPilot_FieldRegistry extends WP_UnitTestCase {

	public function test_every_field_has_required_keys(): void {
		foreach ( FieldRegistry::all() as $key => $field ) {
			$this->assertNotEmpty( $key );
			foreach ( array( 'group', 'storage', 'type', 'input', 'sanitize', 'public', 'private', 'tier' ) as $required ) {
				$this->assertArrayHasKey( $required, $field, "Field {$key} missing {$required}" );
			}
		}
	}

	public function test_private_fields_are_never_public(): void {
		foreach ( FieldRegistry::all() as $key => $field ) {
			if ( ! empty( $field['private'] ) ) {
				$this->assertNotContains( $key, FieldRegistry::public_keys(), "Private field {$key} leaked into public keys" );
			}
		}
	}

	public function test_license_number_is_private(): void {
		$field = FieldRegistry::get( '_doctorpilot_license_number' );
		$this->assertNotNull( $field );
		$this->assertTrue( (bool) $field['private'] );
	}

	public function test_nonneg_int_sanitizer(): void {
		$this->assertSame( 0, Sanitizer::scalar( 'nonneg_int', -12 ) );
		$this->assertSame( 15, Sanitizer::scalar( 'nonneg_int', '15' ) );
	}

	public function test_url_sanitizer_rejects_javascript_scheme(): void {
		$this->assertSame( '', Sanitizer::scalar( 'url', 'javascript:alert(1)' ) );
		$this->assertSame( 'https://example.org/x', Sanitizer::scalar( 'url', 'https://example.org/x' ) );
	}

	public function test_repeater_drops_empty_rows_and_unknown_keys(): void {
		$field = FieldRegistry::get( '_doctorpilot_faqs' );
		$this->assertNotNull( $field );

		$clean = Sanitizer::apply(
			$field,
			array(
				array( 'question' => 'Q1', 'answer' => 'A1', 'evil' => '<script>' ),
				array( 'question' => '', 'answer' => '' ),
			)
		);

		$this->assertCount( 1, $clean );
		$this->assertArrayNotHasKey( 'evil', $clean[0] );
		$this->assertSame( 'Q1', $clean[0]['question'] );
	}
}
