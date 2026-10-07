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
			self::highlights_fields(),
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
		$filtered = (array) apply_filters( 'doctorpilot/fields/register', $keyed );

		// Normalize every field — including any added by extensions via the
		// filter — through the defaults so downstream consumers (editor layout,
		// counts, sanitization) can always rely on keys like `box` and `row`.
		self::$cache = array();
		foreach ( $filtered as $key => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			if ( ! isset( $field['key'] ) ) {
				$field['key'] = (string) $key;
			}
			self::$cache[ $key ] = self::def( $field );
		}

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
			'highlights'     => __( 'Highlights', 'doctorpilot' ),
			'clinical'       => __( 'Clinical Services', 'doctorpilot' ),
			'history'        => __( 'Experience & Education', 'doctorpilot' ),
			'certifications' => __( 'Certifications & Publications', 'doctorpilot' ),
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
				// Editor layout hints: fields sharing a 'row' render side by side
				// (with the given grid 'width'); fields sharing a 'box' render in a
				// bordered fieldset. `placeholder` seeds inputs; `count_noun` is a
				// [singular, plural] pair for the "N selected" term count.
				'row'          => '',
				'width'        => '',
				'box'          => '',
				'placeholder'  => '',
				'count_noun'   => '',
				// For 'library' inputs: the global content-library type ('exp'|'edu').
				'library_type' => '',
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
					'row'       => 'name',
					'width'     => '150px',
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
					'row'        => 'name',
					'width'      => 'minmax(0, 1fr)',
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
					'row'        => 'name',
					'width'      => 'minmax(0, 1fr)',
					'searchable' => true,
					'elementor'  => true,
					'label'      => __( 'Last name', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'       => self::META_PREFIX . 'gender',
					'group'     => 'profile',
					'input'     => 'select',
					'sanitize'  => 'key',
					'row'       => 'identity',
					'width'     => '200px',
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
					'input'    => 'text',
					'sanitize' => 'textarea',
					'public'   => true,
					'searchable' => true,
					'elementor' => true,
					'label'    => __( 'Headline', 'doctorpilot' ),
					'description' => __( "One line under the doctor's name on listing cards.", 'doctorpilot' ),
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
					'box'       => 'listing_flags',
					'public'    => true,
					'elementor' => true,
					'label'     => __( 'Featured doctor', 'doctorpilot' ),
					'description' => __( 'Pinned above other doctors in department listings.', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'       => self::META_PREFIX . 'is_international',
					'group'     => 'profile',
					'type'      => 'bool',
					'input'     => 'checkbox',
					'sanitize'  => 'bool',
					'box'       => 'listing_flags',
					'public'    => true,
					'elementor' => true,
					'label'     => __( 'International doctor', 'doctorpilot' ),
					'description' => __( 'Shows an "International" tag on the doctor card and profile.', 'doctorpilot' ),
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
					'count_noun' => array( __( 'department', 'doctorpilot' ), __( 'departments', 'doctorpilot' ) ),
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
					'count_noun' => array( __( 'specialty', 'doctorpilot' ), __( 'specialties', 'doctorpilot' ) ),
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
					'row'       => 'primary',
					'width'     => 'minmax(0, 1fr)',
					'public'    => true,
					'elementor' => true,
					'label'     => __( 'Primary department', 'doctorpilot' ),
					'description' => __( 'Only assigned departments are listed.', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'       => self::META_PREFIX . 'primary_specialty_id',
					'group'     => 'professional',
					'type'      => 'int',
					'input'     => 'select',
					'sanitize'  => 'int',
					'row'       => 'primary',
					'width'     => 'minmax(0, 1fr)',
					'public'    => true,
					'elementor' => true,
					'label'     => __( 'Primary specialty', 'doctorpilot' ),
					'description' => __( 'Used as the profile subtitle.', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'       => self::META_PREFIX . 'years_experience',
					'group'     => 'professional',
					'type'      => 'int',
					'input'     => 'number',
					'sanitize'  => 'nonneg_int',
					'row'       => 'metrics',
					'width'     => '180px',
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
					'row'      => 'metrics',
					'width'    => 'minmax(0, 1fr)',
					'label'    => __( 'Manual order', 'doctorpilot' ),
					'placeholder' => '10',
					'description' => __( 'Lower numbers appear first when sorting is manual.', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => self::META_PREFIX . 'license_number',
					'group'    => 'professional',
					'sanitize' => 'text',
					'private'  => true,
					'box'      => 'licensing',
					'row'      => 'license',
					'width'    => 'minmax(0, 1fr)',
					'label'    => __( 'License number', 'doctorpilot' ),
					'placeholder' => 'DHA-P-0148832',
				)
			),
			self::def(
				array(
					'key'      => self::META_PREFIX . 'license_authority',
					'group'    => 'professional',
					'sanitize' => 'text',
					'private'  => true,
					'box'      => 'licensing',
					'row'      => 'license',
					'width'    => 'minmax(0, 1fr)',
					'label'    => __( 'License authority', 'doctorpilot' ),
					'placeholder' => 'Qatar Health Authority',
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
					'description' => __( 'One per line — e.g. Head of Obstetrics, Aman Hospital.', 'doctorpilot' ),
				)
			),
		);
	}

	/**
	 * Highlights group fields.
	 *
	 * The stat grid and the achievement lists that sit under it on the public
	 * profile. `academic_highlights` moved here from the professional group in
	 * 0.3.0; the meta key is unchanged, so existing rows keep rendering — only
	 * the editor tab they appear under is different.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function highlights_fields(): array {
		return array(
			self::def(
				array(
					'key'         => self::META_PREFIX . 'academic_highlights',
					'group'       => 'highlights',
					'type'        => 'array',
					'input'       => 'repeater',
					'sanitize'    => 'repeater',
					'public'      => true,
					'elementor'   => true,
					'label'       => __( 'Academic highlights', 'doctorpilot' ),
					'description' => __( 'Key figures shown as stat cards, e.g. “90+ Peer-Reviewed Publications”.', 'doctorpilot' ),
					'repeater'    => array(
						'value' => array( 'label' => __( 'Value', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => '90+', 'basis' => '90px', 'grow' => 0 ),
						'label' => array( 'label' => __( 'Label', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => __( 'Peer-Reviewed Publications', 'doctorpilot' ), 'basis' => '200px', 'grow' => 2 ),
					),
				)
			),
			self::def(
				array(
					'key'         => self::META_PREFIX . 'community_outreach_title',
					'group'       => 'highlights',
					'sanitize'    => 'text',
					'box'         => 'outreach',
					'public'      => true,
					'elementor'   => true,
					'label'       => __( 'Heading', 'doctorpilot' ),
					'description' => __( 'Shown above the list. Leave blank to use the heading set on the widget.', 'doctorpilot' ),
					'placeholder' => __( 'Community Outreach Activities / Achievements', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'         => self::META_PREFIX . 'community_outreach',
					'group'       => 'highlights',
					'type'        => 'array',
					'input'       => 'lines',
					'sanitize'    => 'lines',
					'box'         => 'outreach',
					'public'      => true,
					'elementor'   => true,
					'label'       => __( 'Activities / achievements', 'doctorpilot' ),
					'description' => __( 'One per line — e.g. Reviewer for Diagnostic and Interventional Radiology Journal.', 'doctorpilot' ),
					'placeholder' => __( "Healthcare awareness and education campaigns across TV and social media\nReviewer for Cardiovascular and Interventional Radiology Journal", 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'         => self::META_PREFIX . 'healthcare_achievements_title',
					'group'       => 'highlights',
					'sanitize'    => 'text',
					'box'         => 'profession',
					'public'      => true,
					'elementor'   => true,
					'label'       => __( 'Heading', 'doctorpilot' ),
					'description' => __( 'Shown above the list. Leave blank to use the heading set on the widget.', 'doctorpilot' ),
					'placeholder' => __( 'Health Care Profession Achievements', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'         => self::META_PREFIX . 'healthcare_achievements',
					'group'       => 'highlights',
					'type'        => 'array',
					'input'       => 'lines',
					'sanitize'    => 'lines',
					'box'         => 'profession',
					'public'      => true,
					'elementor'   => true,
					'label'       => __( 'Achievements', 'doctorpilot' ),
					'description' => __( 'One per line — e.g. 15+ Years of Clinical and Academic Experience.', 'doctorpilot' ),
					'placeholder' => __( "15+ Years of Clinical and Academic Experience\nLeadership in Interventional Radiology across Europe and the Middle East", 'doctorpilot' ),
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
					'count_noun' => array( __( 'symptom', 'doctorpilot' ), __( 'symptoms', 'doctorpilot' ) ),
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
					'count_noun' => array( __( 'treatment', 'doctorpilot' ), __( 'treatments', 'doctorpilot' ) ),
				)
			),
			self::def(
				array(
					'key'          => self::META_PREFIX . 'proc_ids',
					'group'        => 'clinical',
					'type'         => 'array',
					'input'        => 'library',
					'sanitize'     => 'term_ids',
					'library_type' => 'proc',
					'public'       => true,
					'elementor'    => true,
					'label'        => __( 'Shared procedures', 'doctorpilot' ),
					'description'  => __( 'Reusable procedure records assigned to this doctor.', 'doctorpilot' ),
					'count_noun'   => array( __( 'record', 'doctorpilot' ), __( 'records', 'doctorpilot' ) ),
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
					'label'     => __( 'Procedures (this doctor only)', 'doctorpilot' ),
					'description' => __( 'Listed in the order shown.', 'doctorpilot' ),
					'repeater'  => array(
						'name'        => array( 'label' => __( 'Name', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => __( 'Hysteroscopic polypectomy', 'doctorpilot' ), 'basis' => '260px', 'grow' => 1 ),
						'description' => array( 'label' => __( 'Description', 'doctorpilot' ), 'sanitize' => 'textarea', 'placeholder' => __( 'Optional detail for the public profile.', 'doctorpilot' ) ),
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
			// Experience — shared global records + this doctor's own entries.
			self::def(
				array(
					'key'          => self::META_PREFIX . 'exp_ids',
					'group'        => 'history',
					'type'         => 'array',
					'input'        => 'library',
					'sanitize'     => 'term_ids',
					'library_type' => 'exp',
					'box'          => 'experience',
					'public'       => true,
					'elementor'    => true,
					'label'        => __( 'Shared experience records', 'doctorpilot' ),
					'count_noun'   => array( __( 'record', 'doctorpilot' ), __( 'records', 'doctorpilot' ) ),
				)
			),
			self::def(
				array(
					'key'         => self::META_PREFIX . 'exp_local',
					'group'       => 'history',
					'type'        => 'array',
					'input'       => 'repeater',
					'sanitize'    => 'repeater',
					'box'         => 'experience',
					'public'      => true,
					'elementor'   => true,
					'label'       => __( "This doctor's own experience", 'doctorpilot' ),
					'description' => __( 'Entries that apply to this doctor only.', 'doctorpilot' ),
					'repeater'    => array(
						'primary'     => array( 'label' => __( 'Position / role', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => __( 'Consultant Obstetrician', 'doctorpilot' ), 'basis' => '220px', 'grow' => 2 ),
						'secondary'   => array( 'label' => __( 'Organization', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => __( 'Aman Hospital', 'doctorpilot' ), 'basis' => '200px', 'grow' => 2 ),
						'start_year'  => array( 'label' => __( 'Start year', 'doctorpilot' ), 'sanitize' => 'year', 'placeholder' => '2016', 'basis' => '90px', 'grow' => 0 ),
						'end_year'    => array( 'label' => __( 'End year', 'doctorpilot' ), 'sanitize' => 'year', 'placeholder' => __( 'Present', 'doctorpilot' ), 'basis' => '90px', 'grow' => 0 ),
						'country'     => array( 'label' => __( 'Country', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => __( 'UAE', 'doctorpilot' ), 'basis' => '120px', 'grow' => 0 ),
						'description' => array( 'label' => __( 'Description', 'doctorpilot' ), 'sanitize' => 'textarea', 'placeholder' => __( 'Optional.', 'doctorpilot' ) ),
					),
				)
			),
			// Education — shared global records + this doctor's own entries.
			self::def(
				array(
					'key'          => self::META_PREFIX . 'edu_ids',
					'group'        => 'history',
					'type'         => 'array',
					'input'        => 'library',
					'sanitize'     => 'term_ids',
					'library_type' => 'edu',
					'box'          => 'education',
					'public'       => true,
					'elementor'    => true,
					'label'        => __( 'Shared education records', 'doctorpilot' ),
					'count_noun'   => array( __( 'record', 'doctorpilot' ), __( 'records', 'doctorpilot' ) ),
				)
			),
			self::def(
				array(
					'key'         => self::META_PREFIX . 'edu_local',
					'group'       => 'history',
					'type'        => 'array',
					'input'       => 'repeater',
					'sanitize'    => 'repeater',
					'box'         => 'education',
					'public'      => true,
					'elementor'   => true,
					'label'       => __( "This doctor's own education", 'doctorpilot' ),
					'description' => __( 'Entries that apply to this doctor only.', 'doctorpilot' ),
					'repeater'    => array(
						'primary'     => array( 'label' => __( 'Qualification', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => __( 'MBBCh', 'doctorpilot' ), 'basis' => '200px', 'grow' => 2 ),
						'secondary'   => array( 'label' => __( 'Institution', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => __( 'Cairo University', 'doctorpilot' ), 'basis' => '220px', 'grow' => 2 ),
						'start_year'  => array( 'label' => __( 'Start year', 'doctorpilot' ), 'sanitize' => 'year', 'placeholder' => '2009', 'basis' => '90px', 'grow' => 0 ),
						'end_year'    => array( 'label' => __( 'End year', 'doctorpilot' ), 'sanitize' => 'year', 'placeholder' => '2014', 'basis' => '90px', 'grow' => 0 ),
						'country'     => array( 'label' => __( 'Country', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => __( 'Egypt', 'doctorpilot' ), 'basis' => '120px', 'grow' => 0 ),
						'description' => array( 'label' => __( 'Description', 'doctorpilot' ), 'sanitize' => 'textarea', 'placeholder' => __( 'Optional.', 'doctorpilot' ) ),
					),
				)
			),
			self::def(
				array(
					'key'          => self::META_PREFIX . 'cert_ids',
					'group'        => 'certifications',
					'type'         => 'array',
					'input'        => 'library',
					'sanitize'     => 'term_ids',
					'library_type' => 'cert',
					'public'       => true,
					'elementor'    => true,
					'label'        => __( 'Shared certifications', 'doctorpilot' ),
					'description'  => __( 'Reusable certification records assigned to this doctor.', 'doctorpilot' ),
					'count_noun'   => array( __( 'record', 'doctorpilot' ), __( 'records', 'doctorpilot' ) ),
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
					'label'     => __( 'Certifications (this doctor only)', 'doctorpilot' ),
					'description' => __( 'Credential URLs are linked from the public profile.', 'doctorpilot' ),
					'repeater'  => array(
						'name'           => array( 'label' => __( 'Name', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => __( 'MRCOG Part 3', 'doctorpilot' ), 'basis' => '200px', 'grow' => 2 ),
						'organization'   => array( 'label' => __( 'Organization', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => 'RCOG', 'basis' => '180px', 'grow' => 2 ),
						'issued_date'    => array( 'label' => __( 'Issued date', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => '2016-05', 'basis' => '120px', 'grow' => 0 ),
						'expiry_date'    => array( 'label' => __( 'Expiry date', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => '—', 'basis' => '120px', 'grow' => 0 ),
						'credential_url' => array( 'label' => __( 'Credential URL', 'doctorpilot' ), 'sanitize' => 'url', 'placeholder' => 'https://', 'basis' => '200px', 'grow' => 2 ),
					),
				)
			),
			self::def(
				array(
					'key'          => self::META_PREFIX . 'pub_ids',
					'group'        => 'certifications',
					'type'         => 'array',
					'input'        => 'library',
					'sanitize'     => 'term_ids',
					'library_type' => 'pub',
					'public'       => true,
					'elementor'    => true,
					'label'        => __( 'Shared publications', 'doctorpilot' ),
					'description'  => __( 'Reusable publication records assigned to this doctor.', 'doctorpilot' ),
					'count_noun'   => array( __( 'record', 'doctorpilot' ), __( 'records', 'doctorpilot' ) ),
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
					'label'     => __( 'Publications (this doctor only)', 'doctorpilot' ),
					'description' => __( 'DOI is preferred over a plain URL when both exist.', 'doctorpilot' ),
					'repeater'  => array(
						'title'          => array( 'label' => __( 'Title', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => __( 'Outcomes in low-risk induction', 'doctorpilot' ), 'basis' => '220px', 'grow' => 2 ),
						'journal'        => array( 'label' => __( 'Journal', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => 'BJOG', 'basis' => '160px', 'grow' => 1 ),
						'published_date' => array( 'label' => __( 'Published date', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => '2021-08', 'basis' => '120px', 'grow' => 0 ),
						'url'            => array( 'label' => __( 'URL', 'doctorpilot' ), 'sanitize' => 'url', 'placeholder' => 'https://', 'basis' => '160px', 'grow' => 1 ),
						'doi'            => array( 'label' => __( 'DOI', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => '10.1111/…', 'basis' => '140px', 'grow' => 1 ),
						'description'    => array( 'label' => __( 'Description', 'doctorpilot' ), 'sanitize' => 'textarea', 'placeholder' => __( 'Optional.', 'doctorpilot' ) ),
					),
				)
			),
			self::def(
				array(
					'key'          => self::META_PREFIX . 'mem_ids',
					'group'        => 'certifications',
					'type'         => 'array',
					'input'        => 'library',
					'sanitize'     => 'term_ids',
					'library_type' => 'mem',
					'public'       => true,
					'elementor'    => true,
					'label'        => __( 'Shared society memberships', 'doctorpilot' ),
					'description'  => __( 'Reusable society records assigned to this doctor.', 'doctorpilot' ),
					'count_noun'   => array( __( 'record', 'doctorpilot' ), __( 'records', 'doctorpilot' ) ),
				)
			),
			self::def(
				array(
					'key'       => self::META_PREFIX . 'memberships',
					'group'     => 'certifications',
					'type'      => 'array',
					'input'     => 'repeater',
					'sanitize'  => 'repeater',
					'public'    => true,
					'elementor' => true,
					'label'     => __( 'Society memberships (this doctor only)', 'doctorpilot' ),
					'description' => __( 'Professional bodies this doctor belongs to.', 'doctorpilot' ),
					'repeater'  => array(
						'society' => array( 'label' => __( 'Society', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => __( 'Royal College of Radiologists', 'doctorpilot' ), 'basis' => '220px', 'grow' => 2 ),
						'role'    => array( 'label' => __( 'Role / grade', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => __( 'Fellow', 'doctorpilot' ), 'basis' => '160px', 'grow' => 1 ),
						'year'    => array( 'label' => __( 'Year', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => '2015', 'basis' => '110px', 'grow' => 0 ),
						'url'     => array( 'label' => __( 'URL', 'doctorpilot' ), 'sanitize' => 'url', 'placeholder' => 'https://', 'basis' => '180px', 'grow' => 1 ),
					),
				)
			),
		);
	}

	/**
	 * Location and contact fields.
	 *
	 * Rendered inside the Profile tab as one flat set of inputs: a doctor has a
	 * single practice location, so a repeater with Add row / Remove offered a
	 * choice that does not exist.
	 *
	 * The storage shape is unchanged — still a list holding one row — because
	 * `DoctorView::locations()`, the JSON-LD builder, three widgets and the
	 * profile template all read it that way, and rows already in the database
	 * must keep resolving. `input => 'single'` changes only how the editor draws
	 * it. Restoring multiple locations later means flipping this back to
	 * `repeater`, with no data to convert.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function location_fields(): array {
		return array(
			self::def(
				array(
					'key'       => self::META_PREFIX . 'locations',
					'group'     => 'profile',
					'type'      => 'array',
					'input'     => 'single',
					'sanitize'  => 'repeater',
					'public'    => true,
					'elementor' => true,
					'label'     => __( 'Location & contact', 'doctorpilot' ),
					'description' => __( 'Where this doctor practises and how patients reach them.', 'doctorpilot' ),
					'repeater'  => array(
						'location_term_id'   => array( 'label' => __( 'Location', 'doctorpilot' ), 'sanitize' => 'int', 'taxonomy' => Taxonomies::LOCATION, 'basis' => '160px', 'grow' => 1 ),
						'department_term_id' => array( 'label' => __( 'Department at location', 'doctorpilot' ), 'sanitize' => 'int', 'taxonomy' => Taxonomies::DEPARTMENT, 'basis' => '180px', 'grow' => 1 ),
						'phone'              => array( 'label' => __( 'Phone', 'doctorpilot' ), 'sanitize' => 'phone', 'placeholder' => '+971 4 000 0000', 'basis' => '150px', 'grow' => 1 ),
						'public_email'       => array( 'label' => __( 'Public email', 'doctorpilot' ), 'sanitize' => 'email', 'placeholder' => 'name@hospital.ae', 'basis' => '170px', 'grow' => 1 ),
						'email_is_public'    => array( 'label' => __( 'Show email publicly', 'doctorpilot' ), 'sanitize' => 'bool' ),
						'room_floor'         => array( 'label' => __( 'Room / floor', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => '3-118', 'basis' => '120px', 'grow' => 0 ),
						'consultation_type'  => array( 'label' => __( 'Consultation type', 'doctorpilot' ), 'sanitize' => 'key', 'placeholder' => __( 'In-person, Video', 'doctorpilot' ), 'basis' => '150px', 'grow' => 1 ),
						'office_hours_text'  => array( 'label' => __( 'Office hours', 'doctorpilot' ), 'sanitize' => 'textarea', 'placeholder' => __( 'Sun–Thu, 9:00–17:00', 'doctorpilot' ) ),
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
						// Empty means "inherit". It has to be an option, and the
						// field default, or the editor would pre-select a concrete
						// method and write it on the first save — pinning every
						// doctor to one method and making the site default in
						// Settings → Booking unreachable.
						''             => __( 'Use site default', 'doctorpilot' ),
						'external_url' => __( 'External URL', 'doctorpilot' ),
						'phone'        => __( 'Phone', 'doctorpilot' ),
						'enquiry'      => __( 'Enquiry form', 'doctorpilot' ),
						'none'         => __( 'No booking button', 'doctorpilot' ),
					),
					'default'  => '',
					'label'    => __( 'Booking method', 'doctorpilot' ),
					'description' => __( 'Leave the fields below blank to inherit the site defaults from DoctorPilot → Settings → Booking. Choose “No booking button” for a doctor who does not take bookings.', 'doctorpilot' ),
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
					'placeholder' => __( 'Dr. Layla Haddad — Obstetrics & Gynaecology', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => self::META_PREFIX . 'seo_description',
					'group'    => 'seo',
					'input'    => 'textarea',
					'sanitize' => 'textarea',
					'label'    => __( 'Meta description', 'doctorpilot' ),
					'placeholder' => __( 'One or two sentences shown in search results.', 'doctorpilot' ),
				)
			),
			self::def(
				array(
					'key'      => self::META_PREFIX . 'seo_canonical_url',
					'group'    => 'seo',
					'sanitize' => 'url',
					'row'      => 'urls',
					'width'    => 'minmax(0, 1fr)',
					'label'    => __( 'Canonical URL', 'doctorpilot' ),
					'placeholder' => 'https://',
				)
			),
			self::def(
				array(
					'key'      => self::META_PREFIX . 'legacy_url',
					'group'    => 'seo',
					'sanitize' => 'url',
					'row'      => 'urls',
					'width'    => 'minmax(0, 1fr)',
					'label'    => __( 'Legacy URL', 'doctorpilot' ),
					'placeholder' => '/team/dr-layla-haddad',
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
					'key'          => self::META_PREFIX . 'faq_ids',
					'group'        => 'faqs',
					'type'         => 'array',
					'input'        => 'library',
					'sanitize'     => 'term_ids',
					'library_type' => 'faq',
					'public'       => true,
					'elementor'    => true,
					'label'        => __( 'Shared FAQs', 'doctorpilot' ),
					'description'  => __( 'Reusable FAQ records assigned to this doctor.', 'doctorpilot' ),
					'count_noun'   => array( __( 'record', 'doctorpilot' ), __( 'records', 'doctorpilot' ) ),
				)
			),
			self::def(
				array(
					'key'       => self::META_PREFIX . 'faqs',
					'group'     => 'faqs',
					'type'      => 'array',
					'input'     => 'repeater',
					'sanitize'  => 'repeater',
					'public'    => true,
					'elementor' => true,
					'label'     => __( 'Frequently asked questions (this doctor only)', 'doctorpilot' ),
					'description' => __( 'Rendered as an accordion, and as FAQ schema for search engines.', 'doctorpilot' ),
					'repeater'  => array(
						'question' => array( 'label' => __( 'Question', 'doctorpilot' ), 'sanitize' => 'text', 'placeholder' => __( 'Do I need a referral?', 'doctorpilot' ), 'basis' => '280px', 'grow' => 1 ),
						'answer'   => array( 'label' => __( 'Answer', 'doctorpilot' ), 'sanitize' => 'textarea', 'placeholder' => __( 'Keep it to two or three sentences.', 'doctorpilot' ) ),
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
