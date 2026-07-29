<?php
/**
 * Doctor post type, taxonomies, and public view model tests.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

use BrainStudioz\DoctorPilot\Application\Doctors\DoctorView;
use BrainStudioz\DoctorPilot\Domain\Doctor\PrimaryTermValidator;
use BrainStudioz\DoctorPilot\Fields\FieldRegistry;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;
use BrainStudioz\DoctorPilot\Taxonomies\Taxonomies;

/**
 * @covers \BrainStudioz\DoctorPilot\Application\Doctors\DoctorView
 * @covers \BrainStudioz\DoctorPilot\Domain\Doctor\PrimaryTermValidator
 */
final class Test_DoctorPilot_DoctorView extends WP_UnitTestCase {

	public function test_post_type_and_taxonomies_are_registered(): void {
		$this->assertTrue( post_type_exists( DoctorPostType::POST_TYPE ) );
		foreach ( Taxonomies::all() as $taxonomy ) {
			$this->assertTrue( taxonomy_exists( $taxonomy ), "Missing taxonomy {$taxonomy}" );
		}
	}

	public function test_symptom_condition_treatment_are_separate(): void {
		$this->assertTrue( taxonomy_exists( Taxonomies::SYMPTOM ) );
		$this->assertTrue( taxonomy_exists( Taxonomies::CONDITION ) );
		$this->assertTrue( taxonomy_exists( Taxonomies::TREATMENT ) );
		$this->assertNotSame( Taxonomies::SYMPTOM, Taxonomies::CONDITION );
	}

	public function test_draft_doctor_is_not_public(): void {
		$id   = self::factory()->post->create(
			array(
				'post_type'   => DoctorPostType::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => 'Dr. Draft',
			)
		);
		$view = new DoctorView();

		$this->assertFalse( $view->is_public( $id ) );
		$this->assertNull( $view->card( $id ) );
	}

	public function test_published_doctor_card_excludes_private_fields(): void {
		$id = self::factory()->post->create(
			array(
				'post_type'   => DoctorPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Ali A. Haydar',
			)
		);
		update_post_meta( $id, FieldRegistry::META_PREFIX . 'credentials', 'MD, FRCR' );
		update_post_meta( $id, FieldRegistry::META_PREFIX . 'license_number', 'SECRET-123' );
		update_post_meta( $id, FieldRegistry::META_PREFIX . 'prefix', 'dr' );

		$view = new DoctorView();
		$card = $view->card( $id );

		$this->assertIsArray( $card );
		$this->assertSame( 'Dr. Ali A. Haydar', $card['name'] );
		$this->assertSame( 'MD, FRCR', $card['credentials'] );
		$encoded = wp_json_encode( $card );
		$this->assertStringNotContainsString( 'SECRET-123', (string) $encoded );
	}

	public function test_primary_term_reconciles_when_not_assigned(): void {
		$id   = self::factory()->post->create( array( 'post_type' => DoctorPostType::POST_TYPE ) );
		$term = self::factory()->term->create( array( 'taxonomy' => Taxonomies::DEPARTMENT ) );

		// Set a primary department that is NOT assigned to the doctor.
		update_post_meta( $id, FieldRegistry::META_PREFIX . 'primary_department_id', $term );
		PrimaryTermValidator::reconcile( $id );

		$this->assertSame( '', (string) get_post_meta( $id, FieldRegistry::META_PREFIX . 'primary_department_id', true ) );

		// Now assign the term; the primary should be accepted as valid.
		wp_set_object_terms( $id, array( (int) $term ), Taxonomies::DEPARTMENT );
		update_post_meta( $id, FieldRegistry::META_PREFIX . 'primary_department_id', $term );
		PrimaryTermValidator::reconcile( $id );

		$this->assertSame( (int) $term, (int) get_post_meta( $id, FieldRegistry::META_PREFIX . 'primary_department_id', true ) );
	}
}
