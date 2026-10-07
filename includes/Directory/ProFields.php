<?php
/**
 * Pro field registry extensions.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Directory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the advanced field set to the canonical field registry via the
 * registry hook, rather than by editing the registry, so the extension point
 * stays exercised by the plugin's own code.
 *
 * Registering through `doctorpilot/fields/register` means the editor UI, meta
 * registration, sanitization, and public serializer all handle the new fields
 * with no duplicate schema. Fields disappear cleanly if Pro is deactivated.
 */
final class ProFields {

	private const META_PREFIX = '_doctorpilot_';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'doctorpilot/fields/register', array( $this, 'add_fields' ) );
	}

	/**
	 * Add the insurance assignment field.
	 *
	 * @param array<string, array<string, mixed>> $fields Existing registry.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_fields( array $fields ): array {
		// Insurance taxonomy assignment on the doctor.
		$fields[ 'terms_' . InsuranceTaxonomy::TAXONOMY ] = array(
			'key'         => 'terms_' . InsuranceTaxonomy::TAXONOMY,
			'group'       => 'booking',
			'storage'     => 'term',
			'taxonomy'    => InsuranceTaxonomy::TAXONOMY,
			'type'        => 'array',
			'input'       => 'terms',
			'default'     => '',
			'sanitize'    => 'term_ids',
			'public'      => true,
			'private'     => false,
			'tier'        => 'pro',
			'repeater'    => array(),
			'options'     => array(),
			'searchable'  => true,
			'elementor'   => true,
			'label'       => __( 'Accepted insurance', 'doctorpilot' ),
			'description' => __( 'Insurance providers this doctor accepts (Pro).', 'doctorpilot' ),
		);

		return $fields;
	}
}
