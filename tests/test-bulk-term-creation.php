<?php
/**
 * Term creation from the editor's Bulk edit (ADR-0025).
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

use BrainStudioz\DoctorPilot\Application\Doctors\SaveDoctor;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;
use BrainStudioz\DoctorPilot\Support\Capabilities;
use BrainStudioz\DoctorPilot\Taxonomies\Taxonomies;

/**
 * @covers \BrainStudioz\DoctorPilot\Application\Doctors\SaveDoctor
 */
final class Test_DoctorPilot_Bulk_Term_Creation extends WP_UnitTestCase {

	/**
	 * Submit a doctor save as the given user with the given new term names.
	 *
	 * @param int           $user_id User ID.
	 * @param int           $post_id Doctor ID.
	 * @param array<string> $names   Names for `new_terms_*`.
	 * @return void
	 */
	private function save_as( int $user_id, int $post_id, array $names ): void {
		wp_set_current_user( $user_id );

		$key = 'terms_' . Taxonomies::DEPARTMENT;

		$_POST = array(
			SaveDoctor::NONCE_NAME => wp_create_nonce( SaveDoctor::NONCE_ACTION ),
			'doctorpilot'          => array(
				$key          => array(),
				'new_' . $key => $names,
			),
		);

		( new SaveDoctor() )->handle( $post_id, get_post( $post_id ) );
	}

	private function admin(): int {
		Capabilities::grant_default_roles();

		return self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

	private function doctor(): int {
		return self::factory()->post->create( array( 'post_type' => DoctorPostType::POST_TYPE ) );
	}

	/**
	 * @return array<int, string>
	 */
	private function assigned_names( int $post_id ): array {
		return wp_get_object_terms( $post_id, Taxonomies::DEPARTMENT, array( 'fields' => 'names' ) );
	}

	public function test_unmatched_names_are_created_and_assigned(): void {
		$post_id = $this->doctor();

		$this->save_as( $this->admin(), $post_id, array( 'Neonatology', 'Pain Medicine' ) );

		$this->assertEqualsCanonicalizing( array( 'Neonatology', 'Pain Medicine' ), $this->assigned_names( $post_id ) );
		$this->assertNotFalse( get_term_by( 'name', 'Neonatology', Taxonomies::DEPARTMENT ) );
	}

	public function test_existing_name_is_reused_not_duplicated(): void {
		$existing = self::factory()->term->create( array( 'taxonomy' => Taxonomies::DEPARTMENT, 'name' => 'Cardiology' ) );
		$post_id  = $this->doctor();

		$this->save_as( $this->admin(), $post_id, array( ' Cardiology ', 'cardiology' ) );

		$this->assertSame( array( 'Cardiology' ), $this->assigned_names( $post_id ) );
		$this->assertSame(
			1,
			count( get_terms( array( 'taxonomy' => Taxonomies::DEPARTMENT, 'hide_empty' => false, 'name' => 'Cardiology' ) ) )
		);
		$this->assertSame( array( $existing ), wp_get_object_terms( $post_id, Taxonomies::DEPARTMENT, array( 'fields' => 'ids' ) ) );
	}

	public function test_saving_twice_is_idempotent(): void {
		$post_id = $this->doctor();
		$admin   = $this->admin();

		$this->save_as( $admin, $post_id, array( 'Foo & Bar' ) );
		$this->save_as( $admin, $post_id, array( 'Foo & Bar' ) );

		$this->assertCount( 1, get_terms( array( 'taxonomy' => Taxonomies::DEPARTMENT, 'hide_empty' => false ) ) );
	}

	public function test_names_with_commas_stay_one_term(): void {
		$post_id = $this->doctor();

		$this->save_as( $this->admin(), $post_id, array( 'ENT, Head & Neck' ) );

		$this->assertCount( 1, $this->assigned_names( $post_id ) );
	}

	public function test_overlong_empty_and_non_string_names_are_dropped(): void {
		$post_id = $this->doctor();

		$this->save_as( $this->admin(), $post_id, array( str_repeat( 'x', 201 ), '', '   ', 'Valid' ) );

		$this->assertSame( array( 'Valid' ), $this->assigned_names( $post_id ) );
	}

	public function test_user_without_manage_terms_cannot_create_terms(): void {
		$post_id = $this->doctor();
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$user    = new WP_User( $user_id );
		foreach ( array( 'edit_doctorpilot_doctors', 'edit_others_doctorpilot_doctors', 'edit_published_doctorpilot_doctors', 'edit_private_doctorpilot_doctors' ) as $cap ) {
			$user->add_cap( $cap );
		}

		$this->assertFalse( user_can( $user_id, Capabilities::MANAGE_TERMS ) );

		$this->save_as( $user_id, $post_id, array( 'Should Not Exist' ) );

		$this->assertFalse( get_term_by( 'name', 'Should Not Exist', Taxonomies::DEPARTMENT ) );
		$this->assertSame( array(), $this->assigned_names( $post_id ) );
	}
}
