<?php
/**
 * Canonical doctor field registry.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Fields;

use BrainStudioz\DoctorPilot\Taxonomies\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The single authority for every doctor field.
 *
 * Admin fields, meta registration, sanitization, validation, public-serializer
 * decisions, migration mapping, search derivation, and Elementor tags all read
 * from here. There is intentionally no second schema. A field definition uses:
 *
 * - key        Storage key (meta key with the `_doctorpilot_` prefix, or a
 *              pseudo key like `post_title` for native columns).
 * - group      Editor group / tab.
 * - storage    post_title|post_content|post_excerpt|thumbnail|meta|term|derived.
 * - taxonomy   For term storage, the taxonomy key.
 * - type       string|int|bool|array|html.
 * - input      Editor control hint.
 * - sanitize   Sanitizer token (see Sanitizer::apply()).
 * - public     Whether the value may appear in a public serializer.
 * - private    Sensitive; never public even if requested.
 * - tier       free|pro.
 * - repeater   Item field schema for repeaters.
 * - searchable Whether it feeds the derived search document.
 * - elementor  Whether it is exposed as an Elementor dynamic tag.
 */
final class FieldRegistry {

	/**
	 * Meta key prefix for all private/structured doctor meta.
	 *
	 * @var string
	 */
	public const META_PREFIX = '_doctorpilot_';

	/**
	 * Cached definitions.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static ?array $cache = null;

	/**
	 * Retrieve every field definition keyed by storage key.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$fields = array_merge(
			self::profile_fields(),
			self::professional_fields(),
			self::clinical_fields(),
			self::history_fields(),
			self::location_fields(),
			self::booking_fields(),
			self::seo_fields(),
			self::faq_fields(),
			self::derived_fields()
		);

		$keyed = array();
		foreach ( $fields as $field ) {
			$keyed[ $field['key'] ] = $field;
		}

		/**
		 * Filter the DoctorPilot field registry.
		 *
		 * Extensions may add fields but must not expose private fields publicly,
		 * bypass validation, or change publication authority.
		 *
		 * @param array<string, array<string, mixed>> $keyed Field definitions.
		 */
		self::$cache = (array) apply_filters( 'doctorpilot/fields/register', $keyed );

		return self::$cache;
	}

	/**
	 * Reset the cached registry (used by tests).
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		self::$cache = null;
	}

	/**
	 * Get a single field definition.
	 *
	 * @param string $key Field key.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $key ): ?array {
		$all = self::all();

		return $all[ $key ] ?? null;
	}

	/**
	 * Fields for a given editor group, preserving definition order.
	 *
	 * @param string $group Group key.
	 * @return array<int, array<string, mixed>>
	 */
	public static function by_group( string $group ): array {
		return array_values(
			array_filter(
				self::all(),
				static function ( array $field ) use ( $group ): bool {
					return isset( $field['group'] ) && $group === $field['group'];
				}
			)
		);
	}

	/**
	 * All meta-stored field definitions.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function meta_fields(): array {
		return array_values(
			array_filter(
				self::all(),
				static function ( array $field ): bool {
					return 'meta' === $field['storage'];
				}
			)
		);
	}

	/**
	 * All term-stored field definitions.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function term_fields(): array {
		return array_values(
			array_filter(
				self::all(),
				static function ( array $field ): bool {
					return 'term' === $field['storage'];
				}
			)
		);
	}

	/**
	 * Keys allowed in public serializers.
	 *
	 * @return array<int, string>
	 */
	public static function public_keys(): array {
		$keys = array();
		foreach ( self::all() as $key => $field ) {
			if ( ! empty( $field['public'] ) && empty( $field['private'] ) ) {
				$keys[] = $key;
			}
		}

		return $keys;
	}

	/**
	 * Editor groups in display order.
	 *
	 * @return array<string, string>
	 */
	public static function groups(): array {
		return array(
			'profile'        => __( 'Profile', 'doctorpilot' ),
			'professional'   => __( 'Professional Information', 'doctorpilot' ),
			'clinical'       => __( 'Clinical Services', 'doctorpilot' ),
			'history'        => __( 'Experience & Education', 'doctorpilot' ),
			'certifications' => __( 'Certifications & Publications', 'doctorpilot' ),
			'location'       => __( 'Location & Contact', 'doctorpilot' ),
			'booking'        => __( 'Booking', 'doctorpilot' ),
			'seo'            => __( 'SEO', 'doctorpilot' ),
			'faqs'           => __( 'FAQs', 'doctorpilot' ),
		);
	}

	/**
	 * Base defaults merged into every field definition.
	 *
	 * @param array<string, mixed> $field Partial definition.
	 * @return array<string, mixed>
	 */
	private static function def( array $field ): array {
		return array_merge(
			array(
				'group'     => 'profile',
				'storage'   => 'meta',
				'taxonomy'  => '',
				'type'      => 'string',
				'input'     => 'text',
				'default'   => '',
				'sanitize'  => 'text',
				'public'    => false,
				'private'   => false,
				'tier'      => 'free',
				'repeater'  => array(),
				'options'   => array(),
				'searchable' => false,
				'elementor' => false,
				'label'     => '',
				'description' => '',
			),
			$field
		);
	}

	/**
	 * Profile group fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function profile_fields(): array {
		return array(
			self::def(
				array(
					'key'       => 'post_title',
					'group'     => 'profile',
					'storage'   => 'post_title',
					'input'     => 'text',
					'sanitize'  => 'text',
					'public'    => true,
					'searchable' => true,
					'elementor' => true,
					'label'     => __( 'Full name', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'       => self::META_PREFIX . 'prefix',
					'group'     => 'profile',
					'input'     => 'select',
					'sanitize'  => 'key',
					'public'    => true,
					'elementor' => true,
					'options'   => array(
						''      => __( '(none)', 'doctorpilot' ),
						'dr'    => __( 'Dr.', 'doctorpilot' ),
						'prof'  => __( 'Prof.', 'doctorpilot' ),
					),
					'label'     => __( 'Prefix', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'        => self::META_PREFIX . 'first_name',
					'group'      => 'profile',
					'sanitize'   => 'text',
					'searchable' => true,
					'elementor'  => true,
					'label'      => __( 'First name', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'        => self::META_PREFIX . 'last_name',
					'group'      => 'profile',
					'sanitize'   => 'text',
					'searchable' => true,
					'elementor'  => true,
					'label'      => __( 'Last name', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'       => self::META_PREFIX . 'credentials',
					'group'     => 'profile',
					'sanitize'  => 'text',
					'public'    => true,
					'searchable' => true,
					'elementor' => true,
					'label'     => __( 'Credentials', 'doctorpilot' ),
					'description' => __( 'e.g. MD, MRCP, FRCR', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'       => self::META_PREFIX . 'gender',
					'group'     => 'profile',
					'input'     => 'select',
					'sanitize'  => 'key',
					'public'    => true,
					'elementor' => true,
					'options'   => array(
						''       => __( '(unspecified)', 'doctorpilot' ),
						'female' => __( 'Female', 'doctorpilot' ),
						'male'   => __( 'Male', 'doctorpilot' ),
						'other'  => __( 'Other', 'doctorpilot' ),
					),
					'label'     => __( 'Gender', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => 'post_excerpt',
					'group'    => 'profile',
					'storage'  => 'post_excerpt',
					'input'    => 'textarea',
					'sanitize' => 'textarea',
					'public'   => true,
					'searchable' => true,
					'elementor' => true,
					'label'    => __( 'Short introduction', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => 'post_content',
					'group'    => 'profile',
					'storage'  => 'post_content',
					'type'     => 'html',
					'input'    => 'wysiwyg',
					'sanitize' => 'html',
					'public'   => true,
					'searchable' => true,
					'elementor' => true,
					'label'    => __( 'Biography', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => '_thumbnail_id',
					'group'    => 'profile',
					'storage'  => 'thumbnail',
					'type'     => 'int',
					'input'    => 'media',
					'sanitize' => 'int',
					'public'   => true,
					'elementor' => true,
					'label'    => __( 'Photograph', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'       => self::META_PREFIX . 'is_featured',
					'group'     => 'profile',
					'type'      => 'bool',
					'input'     => 'checkbox',
					'sanitize'  => 'bool',
					'public'    => true,
					'elementor' => true,
					'label'     => __( 'Featured doctor', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'       => self::META_PREFIX . 'accepting_new_patients',
					'group'     => 'profile',
					'type'      => 'bool',
					'input'     => 'checkbox',
					'sanitize'  => 'bool',
					'public'    => true,
					'elementor' => true,
					'label'     => __( 'Accepting new patients', 'doctorpilot' ),
				)
			),
		);
	}

	/**
	 * Professional group fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function professional_fields(): array {
		return array(
			self::def(
				array(
					'key'      => 'terms_' . Taxonomies::DEPARTMENT,
					'group'    => 'professional',
					'storage'  => 'term',
					'taxonomy' => Taxonomies::DEPARTMENT,
					'type'     => 'array',
					'input'    => 'terms',
					'sanitize' => 'term_ids',
					'public'   => true,
					'searchable' => true,
					'elementor' => true,
					'label'    => __( 'Departments', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => 'terms_' . Taxonomies::SPECIALTY,
					'group'    => 'professional',
					'storage'  => 'term',
					'taxonomy' => Taxonomies::SPECIALTY,
					'type'     => 'array',
					'input'    => 'terms',
					'sanitize' => 'term_ids',
					'public'   => true,
					'searchable' => true,
					'elementor' => true,
					'label'    => __( 'Specialties', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => 'terms_' . Taxonomies::LANGUAGE,
					'group'    => 'professional',
					'storage'  => 'term',
					'taxonomy' => Taxonomies::LANGUAGE,
					'type'     => 'array',
					'input'    => 'terms',
					'sanitize' => 'term_ids',
					'public'   => true,
					'elementor' => true,
					'label'    => __( 'Languages spoken', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'       => self::META_PREFIX . 'primary_department_id',
					'group'     => 'professional',
					'type'      => 'int',
					'input'     => 'select',
					'sanitize'  => 'int',
					'public'    => true,
					'elementor' => true,
					'label'     => __( 'Primary department', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'       => self::META_PREFIX . 'primary_specialty_id',
					'group'     => 'professional',
					'type'      => 'int',
					'input'     => 'select',
					'sanitize'  => 'int',
					'public'    => true,
					'elementor' => true,
					'label'     => __( 'Primary specialty', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'       => self::META_PREFIX . 'years_experience',
					'group'     => 'professional',
					'type'      => 'int',
					'input'     => 'number',
					'sanitize'  => 'nonneg_int',
					'public'    => true,
					'searchable' => true,
					'elementor' => true,
					'label'     => __( 'Years of experience', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => self::META_PREFIX . 'manual_order',
					'group'    => 'professional',
					'type'     => 'int',
					'input'    => 'number',
					'sanitize' => 'nonneg_int',
					'label'    => __( 'Manual order', 'doctorpilot' ),
					'description' => __( 'Lower numbers appear first when sorting is manual.', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => self::META_PREFIX . 'license_number',
					'group'    => 'professional',
					'sanitize' => 'text',
					'private'  => true,
					'label'    => __( 'License number', 'doctorpilot' ),
					'description' => __( 'Private. Never shown publicly.', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => self::META_PREFIX . 'license_authority',
					'group'    => 'professional',
					'sanitize' => 'text',
					'private'  => true,
					'label'    => __( 'License authority', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'         => self::META_PREFIX . 'leadership_positions',
					'group'       => 'professional',
					'type'        => 'array',
					'input'       => 'lines',
					'sanitize'    => 'lines',
					'public'      => true,
					'elementor'   => true,
					'label'       => __( 'Leadership positions', 'doctorpilot' ),
					'description' => __( 'One per line — e.g. Head of Obstetrics, Al Barsha Hospital.', 'doctorpilot' ),
				)
			),
		);
	}

	/**
	 * Clinical services group fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function clinical_fields(): array {
		return array(
			self::def(
				array(
					'key'      => 'terms_' . Taxonomies::SYMPTOM,
					'group'    => 'clinical',
					'storage'  => 'term',
					'taxonomy' => Taxonomies::SYMPTOM,
					'type'     => 'array',
					'input'    => 'terms',
					'sanitize' => 'term_ids',
					'public'   => true,
					'searchable' => true,
					'elementor' => true,
					'label'    => __( 'Symptoms treated', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => 'terms_' . Taxonomies::CONDITION,
					'group'    => 'clinical',
					'storage'  => 'term',
					'taxonomy' => Taxonomies::CONDITION,
					'type'     => 'array',
					'input'    => 'terms',
					'sanitize' => 'term_ids',
					'public'   => true,
					'searchable' => true,
					'elementor' => true,
					'label'    => __( 'Conditions treated', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => 'terms_' . Taxonomies::TREATMENT,
					'group'    => 'clinical',
					'storage'  => 'term',
					'taxonomy' => Taxonomies::TREATMENT,
					'type'     => 'array',
					'input'    => 'terms',
					'sanitize' => 'term_ids',
					'public'   => true,
					'searchable' => true,
					'elementor' => true,
					'label'    => __( 'Treatments', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'       => self::META_PREFIX . 'procedures',
					'group'     => 'clinical',
					'type'      => 'array',
					'input'     => 'repeater',
					'sanitize'  => 'repeater',
					'public'    => true,
					'searchable' => true,
					'elementor' => true,
					'label'     => __( 'Procedures', 'doctorpilot' ),
					'repeater'  => array(
						'name'        => array( 'label' => __( 'Name', 'doctorpilot' ), 'sanitize' => 'text' ),
						'description' => array( 'label' => __( 'Description', 'doctorpilot' ), 'sanitize' => 'textarea' ),
					),
				)
			),
			self::def(
				array(
					'key'       => self::META_PREFIX . 'clinical_interests',
					'group'     => 'clinical',
					'type'      => 'array',
					'input'     => 'lines',
					'sanitize'  => 'lines',
					'public'    => true,
					'searchable' => true,
					'elementor' => true,
					'label'     => __( 'Clinical interests', 'doctorpilot' ),
					'description' => __( 'One per line.', 'doctorpilot' ),
				)
			),
		);
	}

	/**
	 * Experience and education group fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function history_fields(): array {
		return array(
			self::def(
				array(
					'key'       => self::META_PREFIX . 'education_experience',
					'group'     => 'history',
					'type'      => 'array',
					'input'     => 'repeater',
					'sanitize'  => 'repeater',
					'public'    => true,
					'elementor' => true,
					'label'     => __( 'Experience & education', 'doctorpilot' ),
					'repeater'  => array(
						'type'                     => array( 'label' => __( 'Type', 'doctorpilot' ), 'sanitize' => 'key' ),
						'institution'              => array( 'label' => __( 'Institution', 'doctorpilot' ), 'sanitize' => 'text' ),
						'qualification_or_position' => array( 'label' => __( 'Qualification / position', 'doctorpilot' ), 'sanitize' => 'text' ),
						'start_year'               => array( 'label' => __( 'Start year', 'doctorpilot' ), 'sanitize' => 'year' ),
						'end_year'                 => array( 'label' => __( 'End year', 'doctorpilot' ), 'sanitize' => 'year' ),
						'country'                  => array( 'label' => __( 'Country', 'doctorpilot' ), 'sanitize' => 'text' ),
						'description'              => array( 'label' => __( 'Description', 'doctorpilot' ), 'sanitize' => 'textarea' ),
					),
				)
			),
			self::def(
				array(
					'key'       => self::META_PREFIX . 'certifications',
					'group'     => 'certifications',
					'type'      => 'array',
					'input'     => 'repeater',
					'sanitize'  => 'repeater',
					'public'    => true,
					'elementor' => true,
					'label'     => __( 'Certifications & memberships', 'doctorpilot' ),
					'repeater'  => array(
						'name'           => array( 'label' => __( 'Name', 'doctorpilot' ), 'sanitize' => 'text' ),
						'organization'   => array( 'label' => __( 'Organization', 'doctorpilot' ), 'sanitize' => 'text' ),
						'issued_date'    => array( 'label' => __( 'Issued date', 'doctorpilot' ), 'sanitize' => 'text' ),
						'expiry_date'    => array( 'label' => __( 'Expiry date', 'doctorpilot' ), 'sanitize' => 'text' ),
						'credential_url' => array( 'label' => __( 'Credential URL', 'doctorpilot' ), 'sanitize' => 'url' ),
					),
				)
			),
			self::def(
				array(
					'key'       => self::META_PREFIX . 'publications',
					'group'     => 'certifications',
					'type'      => 'array',
					'input'     => 'repeater',
					'sanitize'  => 'repeater',
					'public'    => true,
					'elementor' => true,
					'label'     => __( 'Publications', 'doctorpilot' ),
					'repeater'  => array(
						'title'          => array( 'label' => __( 'Title', 'doctorpilot' ), 'sanitize' => 'text' ),
						'journal'        => array( 'label' => __( 'Journal', 'doctorpilot' ), 'sanitize' => 'text' ),
						'published_date' => array( 'label' => __( 'Published date', 'doctorpilot' ), 'sanitize' => 'text' ),
						'url'            => array( 'label' => __( 'URL', 'doctorpilot' ), 'sanitize' => 'url' ),
						'doi'            => array( 'label' => __( 'DOI', 'doctorpilot' ), 'sanitize' => 'text' ),
						'description'    => array( 'label' => __( 'Description', 'doctorpilot' ), 'sanitize' => 'textarea' ),
					),
				)
			),
		);
	}

	/**
	 * Location and contact group fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function location_fields(): array {
		return array(
			self::def(
				array(
					'key'       => self::META_PREFIX . 'locations',
					'group'     => 'location',
					'type'      => 'array',
					'input'     => 'repeater',
					'sanitize'  => 'repeater',
					'public'    => true,
					'elementor' => true,
					'label'     => __( 'Location & contact', 'doctorpilot' ),
					'description' => __( 'The Free plugin stores one location. DoctorPilot Pro supports multiple.', 'doctorpilot' ),
					'repeater'  => array(
						'location_term_id'   => array( 'label' => __( 'Location', 'doctorpilot' ), 'sanitize' => 'int' ),
						'department_term_id' => array( 'label' => __( 'Department at location', 'doctorpilot' ), 'sanitize' => 'int' ),
						'phone'              => array( 'label' => __( 'Phone', 'doctorpilot' ), 'sanitize' => 'phone' ),
						'public_email'       => array( 'label' => __( 'Public email', 'doctorpilot' ), 'sanitize' => 'email' ),
						'email_is_public'    => array( 'label' => __( 'Show email publicly', 'doctorpilot' ), 'sanitize' => 'bool' ),
						'room_floor'         => array( 'label' => __( 'Room / floor', 'doctorpilot' ), 'sanitize' => 'text' ),
						'consultation_type'  => array( 'label' => __( 'Consultation type', 'doctorpilot' ), 'sanitize' => 'key' ),
						'office_hours_text'  => array( 'label' => __( 'Office hours', 'doctorpilot' ), 'sanitize' => 'textarea' ),
					),
				)
			),
		);
	}

	/**
	 * Booking group fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function booking_fields(): array {
		return array(
			self::def(
				array(
					'key'      => self::META_PREFIX . 'booking_method',
					'group'    => 'booking',
					'input'    => 'select',
					'sanitize' => 'key',
					'public'   => true,
					'options'  => array(
						'external_url' => __( 'External URL', 'doctorpilot' ),
						'phone'        => __( 'Phone', 'doctorpilot' ),
						'enquiry'      => __( 'Enquiry form', 'doctorpilot' ),
					),
					'default'  => 'external_url',
					'label'    => __( 'Booking method', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => self::META_PREFIX . 'booking_external_url',
					'group'    => 'booking',
					'sanitize' => 'url',
					'public'   => true,
					'label'    => __( 'Booking URL', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => self::META_PREFIX . 'booking_phone',
					'group'    => 'booking',
					'sanitize' => 'phone',
					'public'   => true,
					'label'    => __( 'Booking phone', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => self::META_PREFIX . 'booking_label',
					'group'    => 'booking',
					'sanitize' => 'text',
					'public'   => true,
					'label'    => __( 'Booking button label', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => self::META_PREFIX . 'consultation_duration_minutes',
					'group'    => 'booking',
					'type'     => 'int',
					'input'    => 'number',
					'sanitize' => 'nonneg_int',
					'public'   => true,
					'label'    => __( 'Consultation duration (minutes)', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'       => self::META_PREFIX . 'online_consultation',
					'group'     => 'booking',
					'type'      => 'bool',
					'input'     => 'checkbox',
					'sanitize'  => 'bool',
					'public'    => true,
					'elementor' => true,
					'label'     => __( 'Offers online consultation', 'doctorpilot' ),
				)
			),
		);
	}

	/**
	 * SEO group fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function seo_fields(): array {
		return array(
			self::def(
				array(
					'key'      => self::META_PREFIX . 'seo_title',
					'group'    => 'seo',
					'sanitize' => 'text',
					'label'    => __( 'SEO title', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => self::META_PREFIX . 'seo_description',
					'group'    => 'seo',
					'input'    => 'textarea',
					'sanitize' => 'textarea',
					'label'    => __( 'Meta description', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => self::META_PREFIX . 'seo_canonical_url',
					'group'    => 'seo',
					'sanitize' => 'url',
					'label'    => __( 'Canonical URL', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => self::META_PREFIX . 'legacy_url',
					'group'    => 'seo',
					'sanitize' => 'url',
					'label'    => __( 'Legacy URL', 'doctorpilot' ),
					'description' => __( 'Old profile URL, retained for migration redirects.', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => self::META_PREFIX . 'seo_index_status',
					'group'    => 'seo',
					'input'    => 'select',
					'sanitize' => 'key',
					'options'  => array(
						'index'   => __( 'Index (default)', 'doctorpilot' ),
						'noindex' => __( 'No index', 'doctorpilot' ),
					),
					'default'  => 'index',
					'label'    => __( 'Search engine indexing', 'doctorpilot' ),
				)
			),
		);
	}

	/**
	 * FAQ group fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function faq_fields(): array {
		return array(
			self::def(
				array(
					'key'       => self::META_PREFIX . 'faqs',
					'group'     => 'faqs',
					'type'      => 'array',
					'input'     => 'repeater',
					'sanitize'  => 'repeater',
					'public'    => true,
					'elementor' => true,
					'label'     => __( 'Frequently asked questions', 'doctorpilot' ),
					'repeater'  => array(
						'question' => array( 'label' => __( 'Question', 'doctorpilot' ), 'sanitize' => 'text' ),
						'answer'   => array( 'label' => __( 'Answer', 'doctorpilot' ), 'sanitize' => 'textarea' ),
					),
				)
			),
		);
	}

	/**
	 * Derived / internal fields (never edited directly, never public raw).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function derived_fields(): array {
		return array(
			self::def(
				array(
					'key'      => self::META_PREFIX . 'review_status',
					'group'    => 'profile',
					'storage'  => 'meta',
					'input'    => 'none',
					'sanitize' => 'review_status',
					'default'  => 'draft',
					'label'    => __( 'Review status', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'     => self::META_PREFIX . 'completeness_score',
					'storage' => 'derived',
					'type'    => 'int',
					'input'   => 'none',
					'private' => true,
					'label'   => __( 'Completeness score', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'     => self::META_PREFIX . 'search_document',
					'storage' => 'derived',
					'input'   => 'none',
					'private' => true,
					'label'   => __( 'Search document', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'     => self::META_PREFIX . 'sort_name',
					'storage' => 'derived',
					'input'   => 'none',
					'private' => true,
					'label'   => __( 'Sort name', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'     => self::META_PREFIX . 'search_index',
					'storage' => 'derived',
					'type'    => 'array',
					'input'   => 'none',
					'private' => true,
					'label'   => __( 'Search index', 'doctorpilot' ),
				)
			),
		);
	}
}
