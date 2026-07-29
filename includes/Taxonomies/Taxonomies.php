<?php
/**
 * Directory taxonomies.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Taxonomies;

use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;
use BrainStudioz\DoctorPilot\Support\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the prefixed Free taxonomies for the doctor post type.
 *
 * Symptom, Condition, and Treatment are intentionally separate: a symptom is
 * what a patient experiences, a condition is a diagnosis, and a treatment is an
 * intervention (ADR-0003). Insurance is a Pro taxonomy and is not registered by
 * Free. Term management requires the `manage_doctorpilot_terms` capability.
 */
final class Taxonomies {

	public const DEPARTMENT = 'doctorpilot_department';
	public const SPECIALTY  = 'doctorpilot_specialty';
	public const SYMPTOM    = 'doctorpilot_symptom';
	public const CONDITION  = 'doctorpilot_condition';
	public const TREATMENT  = 'doctorpilot_treatment';
	public const LANGUAGE   = 'doctorpilot_language';
	public const LOCATION   = 'doctorpilot_location';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_taxonomies' ), 6 );
	}

	/**
	 * Register every Free taxonomy.
	 *
	 * @return void
	 */
	public function register_taxonomies(): void {
		$this->register_one( self::DEPARTMENT, __( 'Department', 'doctorpilot' ), __( 'Departments', 'doctorpilot' ), true, 'doctor-department' );
		$this->register_one( self::SPECIALTY, __( 'Specialty', 'doctorpilot' ), __( 'Specialties', 'doctorpilot' ), true, 'doctor-specialty' );
		// Symptom, Condition, and Treatment are hierarchical (category-style,
		// checkbox UI) like Department — not tag-style — so terms are managed as a
		// curated, structured vocabulary rather than free-text tags.
		$this->register_one( self::SYMPTOM, __( 'Symptom', 'doctorpilot' ), __( 'Symptoms', 'doctorpilot' ), true, 'symptom' );
		$this->register_one( self::CONDITION, __( 'Condition', 'doctorpilot' ), __( 'Conditions', 'doctorpilot' ), true, 'condition' );
		$this->register_one( self::TREATMENT, __( 'Treatment', 'doctorpilot' ), __( 'Treatments', 'doctorpilot' ), true, 'treatment' );
		// Language and Location are hierarchical (checkbox UI) like Department;
		// their public archives are disabled by default to avoid thin pages.
		$this->register_one( self::LANGUAGE, __( 'Language', 'doctorpilot' ), __( 'Languages', 'doctorpilot' ), true, false );
		$this->register_one( self::LOCATION, __( 'Location', 'doctorpilot' ), __( 'Locations', 'doctorpilot' ), true, false );
	}

	/**
	 * All registered taxonomy keys.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array(
			self::DEPARTMENT,
			self::SPECIALTY,
			self::SYMPTOM,
			self::CONDITION,
			self::TREATMENT,
			self::LANGUAGE,
			self::LOCATION,
		);
	}

	/**
	 * Register a single taxonomy with shared, safe defaults.
	 *
	 * @param string       $key          Taxonomy key.
	 * @param string       $singular     Singular label.
	 * @param string       $plural       Plural label.
	 * @param bool         $hierarchical Whether the taxonomy is hierarchical.
	 * @param string|false $rewrite_slug Public rewrite slug, or false to disable archives.
	 * @return void
	 */
	private function register_one( string $key, string $singular, string $plural, bool $hierarchical, $rewrite_slug ): void {
		$labels = array(
			'name'          => $plural,
			'singular_name' => $singular,
			'search_items'  => sprintf( /* translators: %s: taxonomy plural name. */ __( 'Search %s', 'doctorpilot' ), $plural ),
			'all_items'     => sprintf( /* translators: %s: taxonomy plural name. */ __( 'All %s', 'doctorpilot' ), $plural ),
			'edit_item'     => sprintf( /* translators: %s: taxonomy singular name. */ __( 'Edit %s', 'doctorpilot' ), $singular ),
			'add_new_item'  => sprintf( /* translators: %s: taxonomy singular name. */ __( 'Add New %s', 'doctorpilot' ), $singular ),
			'menu_name'     => $plural,
		);

		$capabilities = array(
			'manage_terms' => Capabilities::MANAGE_TERMS,
			'edit_terms'   => Capabilities::MANAGE_TERMS,
			'delete_terms' => Capabilities::MANAGE_TERMS,
			'assign_terms' => 'edit_doctorpilot_doctors',
		);

		register_taxonomy(
			$key,
			array( DoctorPostType::POST_TYPE ),
			array(
				'labels'            => $labels,
				'public'            => false !== $rewrite_slug,
				'publicly_queryable' => false !== $rewrite_slug,
				'hierarchical'      => $hierarchical,
				'show_ui'           => true,
				'show_admin_column' => in_array( $key, array( self::DEPARTMENT, self::SPECIALTY ), true ),
				'show_in_rest'      => false,
				'show_in_nav_menus' => false,
				'query_var'         => false !== $rewrite_slug,
				'rewrite'           => false !== $rewrite_slug
					? array(
						'slug'       => $rewrite_slug,
						'with_front' => false,
					)
					: false,
				'capabilities'      => $capabilities,
			)
		);
	}
}
