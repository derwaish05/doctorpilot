<?php
/**
 * Monotonic cache generation counter and invalidation hooks.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Infrastructure\Cache;

use BrainStudioz\DoctorPilot\Admin\Settings;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;
use BrainStudioz\DoctorPilot\Taxonomies\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A single monotonic integer that participates in every public cache key.
 *
 * Incrementing it invalidates all cached search responses at once without
 * enumerating cache entries. It is bumped whenever a doctor, a relevant term,
 * or the plugin settings change.
 */
final class CacheGeneration {

	/**
	 * Option key for the generation counter.
	 *
	 * @var string
	 */
	public const OPTION = 'bstdz_doctorpilot_cache_generation';

	/**
	 * Register invalidation hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'save_post_' . DoctorPostType::POST_TYPE, array( $this, 'bump' ) );
		add_action( 'deleted_post', array( $this, 'bump' ) );
		add_action( 'transition_post_status', array( $this, 'bump' ) );
		add_action( 'update_option_' . Settings::OPTION_KEY, array( $this, 'bump' ) );

		foreach ( Taxonomies::all() as $taxonomy ) {
			add_action( 'created_' . $taxonomy, array( $this, 'bump' ) );
			add_action( 'edited_' . $taxonomy, array( $this, 'bump' ) );
			add_action( 'delete_' . $taxonomy, array( $this, 'bump' ) );
		}
	}

	/**
	 * Current generation value (at least 1).
	 *
	 * @return int
	 */
	public static function current(): int {
		return max( 1, (int) get_option( self::OPTION, 1 ) );
	}

	/**
	 * Increment the generation counter.
	 *
	 * @return void
	 */
	public function bump(): void {
		update_option( self::OPTION, self::current() + 1, true );
	}
}
