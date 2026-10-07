<?php
/**
 * Regression tests for the removal of the Credentials field (ADR-0024).
 *
 * Requires the WordPress PHPUnit harness with DoctorPilot loaded. Set
 * WP_TESTS_DIR before running phpunit.
 *
 * These cover the destructive half of the change, which is the half that cannot
 * be undone by reverting a commit: migration 5 must delete every stored row,
 * must queue the affected doctors for reindexing rather than reindexing them
 * inline, and must be safe to run twice.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

use BrainStudioz\DoctorPilot\Application\Doctors\DoctorView;
use BrainStudioz\DoctorPilot\Application\Search\SearchIndexBuilder;
use BrainStudioz\DoctorPilot\Fields\FieldRegistry;
use BrainStudioz\DoctorPilot\Infrastructure\Cache\CacheGeneration;
use BrainStudioz\DoctorPilot\Infrastructure\Migrations\CredentialsReindexQueue;
use BrainStudioz\DoctorPilot\Infrastructure\Migrations\Migrator;
use BrainStudioz\DoctorPilot\Migration\MappingConfig;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;

/**
 * @covers \BrainStudioz\DoctorPilot\Infrastructure\Migrations\Migrator
 * @covers \BrainStudioz\DoctorPilot\Infrastructure\Migrations\CredentialsReindexQueue
 */
final class Test_DoctorPilot_Credentials_Removal extends WP_UnitTestCase {

	private const META_KEY = '_doctorpilot_credentials';

	/**
	 * A published doctor carrying a legacy credentials row.
	 *
	 * @param string $credentials Stored value.
	 * @return int Doctor post ID.
	 */
	private function legacy_doctor( string $credentials ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => DoctorPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Ali A. Haydar',
			)
		);

		update_post_meta( $id, self::META_KEY, $credentials );
		SearchIndexBuilder::rebuild( $id );

		return $id;
	}

	/**
	 * Put the site back on schema 4 so `run()` applies migration 5.
	 *
	 * @return void
	 */
	private function rewind_schema(): void {
		update_option( 'bstdz_doctorpilot_schema_version', 4, true );
	}

	public function test_field_is_gone_from_the_registry_and_the_card(): void {
		$all = FieldRegistry::all();
		$this->assertArrayNotHasKey( FieldRegistry::META_PREFIX . 'credentials', $all );

		$id   = $this->legacy_doctor( 'MD, FRCR' );
		$card = ( new DoctorView() )->card( $id );

		$this->assertIsArray( $card );
		$this->assertArrayNotHasKey( 'credentials', $card );
	}

	public function test_headline_is_the_only_field_labelled_headline(): void {
		$labels = array();
		foreach ( FieldRegistry::all() as $key => $field ) {
			if ( 'Headline' === (string) ( $field['label'] ?? '' ) ) {
				$labels[] = $key;
			}
		}

		$this->assertSame( array( 'post_excerpt' ), $labels );
	}

	public function test_credentials_is_no_longer_an_import_target(): void {
		$this->assertNotEmpty(
			MappingConfig::validate_mapping(
				array( 'Name' => 'name', 'ID' => 'source_key', 'Quals' => 'credentials' )
			)
		);
	}

	public function test_migration_deletes_every_row_and_queues_a_reindex(): void {
		$with    = $this->legacy_doctor( 'MD, MRCP, FRCR' );
		$without = self::factory()->post->create(
			array( 'post_type' => DoctorPostType::POST_TYPE, 'post_status' => 'publish' )
		);

		// The credentials text is in the derived search document before the run.
		$this->assertStringContainsString(
			'frcr',
			(string) get_post_meta( $with, SearchIndexBuilder::DOCUMENT, true )
		);

		delete_option( CredentialsReindexQueue::OPTION );
		$generation = CacheGeneration::current();

		$this->rewind_schema();
		( new Migrator() )->run();

		$this->assertSame( '', (string) get_post_meta( $with, self::META_KEY, true ) );
		$this->assertSame(
			(int) DOCTORPILOT_SCHEMA_VERSION,
			(int) get_option( 'bstdz_doctorpilot_schema_version' )
		);
		$this->assertGreaterThan( $generation, CacheGeneration::current() );

		// Reindexing is deferred, not inline: at `init` priority 1 the doctor
		// taxonomies are not registered yet, so rebuilding there would blank
		// every affected doctor's terms out of the index.
		$queued = get_option( CredentialsReindexQueue::OPTION, array() );
		$this->assertContains( $with, array_map( 'intval', (array) $queued ) );
		$this->assertNotContains( $without, array_map( 'intval', (array) $queued ) );
	}

	public function test_draining_the_queue_clears_the_credentials_from_the_search_document(): void {
		$id = $this->legacy_doctor( 'MD, MRCP, FRCR' );

		$this->rewind_schema();
		( new Migrator() )->run();

		( new CredentialsReindexQueue() )->drain();

		$this->assertStringNotContainsString(
			'frcr',
			(string) get_post_meta( $id, SearchIndexBuilder::DOCUMENT, true )
		);

		$index = get_post_meta( $id, SearchIndexBuilder::INDEX, true );
		$this->assertIsArray( $index );
		$this->assertNotContains( 'md mrcp frcr', (array) ( $index['keywords'] ?? array() ) );

		// The queue removes itself once empty, so nothing is left to load.
		$this->assertFalse( get_option( CredentialsReindexQueue::OPTION, false ) );
	}

	public function test_migration_is_safe_to_run_twice(): void {
		$this->legacy_doctor( 'MD, FRCR' );

		$this->rewind_schema();
		( new Migrator() )->run();
		( new CredentialsReindexQueue() )->drain();

		$generation = CacheGeneration::current();

		// A database restore can roll the version back with the rows already
		// gone. The second pass must find nothing and queue nothing.
		$this->rewind_schema();
		( new Migrator() )->run();

		$this->assertFalse( get_option( CredentialsReindexQueue::OPTION, false ) );
		$this->assertSame(
			(int) DOCTORPILOT_SCHEMA_VERSION,
			(int) get_option( 'bstdz_doctorpilot_schema_version' )
		);
		// The bump is unconditional by design; it must never go backwards.
		$this->assertGreaterThanOrEqual( $generation, CacheGeneration::current() );
	}
}
