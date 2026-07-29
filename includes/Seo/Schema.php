<?php
/**
 * Structured data (JSON-LD) builder.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds Physician JSON-LD from an already public, reviewed view model.
 *
 * Only reviewed public fields are included. The builder never invents
 * credentials, availability, or relationships.
 */
final class Schema {

	/**
	 * Build a Physician schema graph for a doctor.
	 *
	 * @param array<string, mixed> $data      Public view model from DoctorView::full().
	 * @param string               $canonical Canonical URL.
	 * @return array<string, mixed>
	 */
	public static function physician( array $data, string $canonical ): array {
		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Physician',
			'name'     => (string) $data['name'],
			'url'      => $canonical,
		);

		if ( ! empty( $data['photo']['url'] ) ) {
			$schema['image'] = (string) $data['photo']['url'];
		}

		$description = wp_strip_all_tags( (string) ( $data['intro'] ?? '' ) );
		if ( '' !== $description ) {
			$schema['description'] = $description;
		}

		if ( ! empty( $data['primary_specialty']['name'] ) ) {
			$schema['medicalSpecialty'] = (string) $data['primary_specialty']['name'];
		}

		$languages = array();
		foreach ( (array) ( $data['languages'] ?? array() ) as $language ) {
			if ( isset( $language['name'] ) ) {
				$languages[] = (string) $language['name'];
			}
		}
		if ( array() !== $languages ) {
			$schema['knowsLanguage'] = $languages;
		}

		// Affiliation with the hospital organization, if a location is present.
		$locations = (array) ( $data['locations'] ?? array() );
		if ( array() !== $locations && ! empty( $locations[0]['name'] ) ) {
			$schema['memberOf'] = array(
				'@type' => 'MedicalOrganization',
				'name'  => (string) $locations[0]['name'],
			);

			if ( ! empty( $locations[0]['phone'] ) ) {
				$schema['telephone'] = (string) $locations[0]['phone'];
			}
		}

		/**
		 * Filter the Physician schema before output.
		 *
		 * @param array<string, mixed> $schema Schema graph.
		 * @param array<string, mixed> $data   Doctor view model.
		 */
		return (array) apply_filters( 'doctorpilot/seo/physician_schema', $schema, $data );
	}
}
