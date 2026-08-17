<?php
/**
 * Pro Insurance taxonomy.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Directory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Insurance Providers taxonomy on the doctor post type.
 *
 * Registered here rather than alongside the other seven in `Taxonomies` because
 * it arrived with the merge of the Pro add-on and its slug, labels and non-public
 * archive behaviour are already relied on by installed sites. Term management
 * reuses the shared term-management capability.
 */
final class InsuranceTaxonomy {

	public const TAXONOMY = 'doctorpilot_insurance';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ), 8 );
	}

	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		register_taxonomy(
			self::TAXONOMY,
			array( 'doctorpilot_doctor' ),
			array(
				'labels'            => array(
					'name'          => __( 'Insurance Providers', 'doctorpilot' ),
					'singular_name' => __( 'Insurance Provider', 'doctorpilot' ),
					'menu_name'     => __( 'Insurance', 'doctorpilot' ),
				),
				'public'            => false,
				'publicly_queryable' => false,
				// Hierarchical (checkbox UI) like Department, not tag-style.
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_in_rest'      => false,
				'show_admin_column' => false,
				'query_var'         => false,
				'rewrite'           => false,
				'capabilities'      => array(
					'manage_terms' => 'manage_doctorpilot_terms',
					'edit_terms'   => 'manage_doctorpilot_terms',
					'delete_terms' => 'manage_doctorpilot_terms',
					'assign_terms' => 'edit_doctorpilot_doctors',
				),
			)
		);
	}
}
