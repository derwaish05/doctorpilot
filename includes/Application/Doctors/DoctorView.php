<?php
/**
 * Public doctor view model.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Application\Doctors;

use BrainStudioz\DoctorPilot\Booking\BookingResolver;
use BrainStudioz\DoctorPilot\Fields\FieldRegistry;
use BrainStudioz\DoctorPilot\Support\Multilingual;
use BrainStudioz\DoctorPilot\Library\DoctorContentResolver;
use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;
use BrainStudioz\DoctorPilot\Taxonomies\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds allowlisted, public-safe representations of a doctor.
 *
 * Only published doctors produce output, private fields (license, internal
 * notes) are never included, and email is included only when the editor marked
 * it public. Raw post meta is never forwarded.
 */
final class DoctorView {

	/**
	 * Shared booking resolver.
	 *
	 * @var BookingResolver
	 */
	private BookingResolver $booking;

	/**
	 * Constructor.
	 *
	 * @param BookingResolver|null $booking Optional resolver (injected in tests).
	 */
	public function __construct( ?BookingResolver $booking = null ) {
		$this->booking = $booking ?? new BookingResolver();
	}

	/**
	 * Whether a post is a publicly viewable doctor.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function is_public( int $post_id ): bool {
		$post = get_post( $post_id );

		return $post instanceof \WP_Post
			&& DoctorPostType::POST_TYPE === $post->post_type
			&& 'publish' === $post->post_status;
	}

	/**
	 * Lightweight card representation for grids and lists.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	public function card( int $post_id ): ?array {
		if ( ! $this->is_public( $post_id ) ) {
			return null;
		}

		$post = get_post( $post_id );

		return array(
			'id'                     => $post_id,
			'slug'                   => $post->post_name,
			'name'                   => $this->display_name( $post ),
			'url'                    => (string) get_permalink( $post_id ),
			// The registry labels `post_excerpt` "Headline" and describes it as
			// the one line under the name on listing cards, so it is the field
			// a job title belongs in. Added to the card contract here; `full()`
			// continues to expose the same value as `intro`.
			'headline'               => (string) $post->post_excerpt,
			'photo'                  => $this->photo( $post_id ),
			'primary_department'     => $this->primary_term( $post_id, FieldRegistry::META_PREFIX . 'primary_department_id', Taxonomies::DEPARTMENT ),
			'primary_specialty'      => $this->primary_term( $post_id, FieldRegistry::META_PREFIX . 'primary_specialty_id', Taxonomies::SPECIALTY ),
			'years_experience'       => (int) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'years_experience', true ),
			'languages'              => $this->terms( $post_id, Taxonomies::LANGUAGE ),
			'is_featured'            => (bool) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'is_featured', true ),
			'is_international'        => (bool) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'is_international', true ),
			'booking'                => $this->booking_array( $post_id ),
		);
	}

	/**
	 * Full profile representation.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	public function full( int $post_id ): ?array {
		$card = $this->card( $post_id );
		if ( null === $card ) {
			return null;
		}

		$post = get_post( $post_id );

		return array_merge(
			$card,
			array(
				'intro'               => (string) $post->post_excerpt,
				'biography'           => $this->rendered_biography( (string) $post->post_content ),
				'departments'         => $this->terms( $post_id, Taxonomies::DEPARTMENT ),
				'specialties'         => $this->terms( $post_id, Taxonomies::SPECIALTY ),
				'symptoms'            => $this->terms( $post_id, Taxonomies::SYMPTOM ),
				'conditions'          => $this->terms( $post_id, Taxonomies::CONDITION ),
				'treatments'          => $this->terms( $post_id, Taxonomies::TREATMENT ),
				'clinical_interests'  => $this->string_list( $post_id, FieldRegistry::META_PREFIX . 'clinical_interests' ),
				'procedures'          => ( new DoctorContentResolver() )->resolve( $post_id, 'proc', 'public' ),
				'leadership'          => $this->repeater( $post_id, FieldRegistry::META_PREFIX . 'leadership_positions' ),
				'academic_highlights' => $this->repeater( $post_id, FieldRegistry::META_PREFIX . 'academic_highlights' ),
				'community_outreach'  => $this->string_list( $post_id, FieldRegistry::META_PREFIX . 'community_outreach' ),
				'community_outreach_title' => (string) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'community_outreach_title', true ),
				'healthcare_achievements' => $this->string_list( $post_id, FieldRegistry::META_PREFIX . 'healthcare_achievements' ),
				'healthcare_achievements_title' => (string) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'healthcare_achievements_title', true ),
				'experience'          => ( new DoctorContentResolver() )->resolve( $post_id, 'exp', 'public' ),
				'education'           => ( new DoctorContentResolver() )->resolve( $post_id, 'edu', 'public' ),
				'certifications'      => ( new DoctorContentResolver() )->resolve( $post_id, 'cert', 'public' ),
				'memberships'         => ( new DoctorContentResolver() )->resolve( $post_id, 'mem', 'public' ),
				'publications'        => ( new DoctorContentResolver() )->resolve( $post_id, 'pub', 'public' ),
				'faqs'                => ( new DoctorContentResolver() )->resolve( $post_id, 'faq', 'public' ),
				'locations'           => $this->locations( $post_id ),
				'online_consultation' => (bool) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'online_consultation', true ),
				// Free text an international doctor's profile header shows as the
				// "Upcoming Visit". On the full view only: the card has no use for it.
				'visit_dates'         => (string) get_post_meta( $post_id, FieldRegistry::META_PREFIX . 'visit_dates', true ),
			)
		);
	}

	/**
	 * Guard against re-entering the biography filter pass.
	 *
	 * @var bool
	 */
	private static bool $rendering_biography = false;

	/**
	 * Run the biography through `the_content`, with Elementor's builder
	 * injection suspended, and drop any stylesheet or script the filter chain
	 * still manages to inject.
	 *
	 * **Elementor replaces the content wholesale.** `Elementor\Frontend::apply_builder_in_content()`
	 * is hooked to `the_content` at priority 9 and does, in effect,
	 * `$content = $this->get_builder_content( get_the_ID() )` — it discards
	 * whatever was passed in and substitutes the *entire rendered document* for
	 * the current post. Calling `the_content` from inside a widget that is part
	 * of that document therefore renders the document inside itself: the About
	 * Doctor widget came out containing the Page Header, the breadcrumbs, the
	 * booking buttons and a second copy of its own heading. Elementor removes
	 * its own filter for the duration of the nested render, so the recursion
	 * stops after one level instead of hanging — which is why this looked like a
	 * display glitch rather than the infinite loop it nearly is.
	 *
	 * `remove_content_filter()` / `add_content_filter()` are Elementor's public
	 * API for exactly this (both since 1.8.0), so the suspension does not depend
	 * on knowing the callback or the priority. The filter is only restored when
	 * it was actually registered when we arrived: Elementor removes it during
	 * its own nested renders, and unconditionally re-adding it there would leave
	 * the outer content builder-injected when it should not be.
	 *
	 * The static guard is the belt to that braces. It costs nothing and it means
	 * a future Elementor — or any other plugin that renders a document from
	 * `the_content` — cannot recurse through this method.
	 *
	 * The `<style>` strip below stays. It was added for the same filter printing
	 * the document's compiled CSS inline, which should no longer reach us now
	 * that the filter is suspended; but consumers escape this string with
	 * `wp_kses_post()`, which strips a `<style>` tag and keeps its text node, so
	 * a stylesheet arriving from anywhere else would still render as body copy.
	 * A biography has no legitimate reason to carry CSS or JS.
	 *
	 * @param string $raw Raw post content.
	 * @return string Rendered biography HTML.
	 */
	private function rendered_biography( string $raw ): string {
		if ( self::$rendering_biography ) {
			return $raw;
		}

		self::$rendering_biography = true;

		$frontend = null;
		$restore  = false;

		if ( did_action( 'elementor/loaded' ) && class_exists( '\\Elementor\\Plugin' ) ) {
			$instance = \Elementor\Plugin::instance();
			$frontend = $instance->frontend ?? null;

			if ( $frontend instanceof \Elementor\Frontend ) {
				$restore = false !== has_filter( 'the_content', array( $frontend, 'apply_builder_in_content' ) );

				if ( $restore ) {
					$frontend->remove_content_filter();
				}
			}
		}

		try {
			$html = (string) apply_filters( 'the_content', $raw );
		} finally {
			if ( $restore && null !== $frontend ) {
				$frontend->add_content_filter();
			}

			self::$rendering_biography = false;
		}

		// Paired elements, contents included.
		$clean = preg_replace( '#<(style|script|template)\b[^>]*>.*?</\1\s*>#is', '', $html );
		if ( null === $clean ) {
			return $html;
		}

		// Void elements that can only be a stylesheet or preload directive here.
		$clean = preg_replace( '#<link\b[^>]*>#i', '', $clean );

		return null === $clean ? $html : $clean;
	}

	/**
	 * Build the doctor's display name including a prefix.
	 *
	 * @param \WP_Post $post Doctor post.
	 * @return string
	 */
	private function display_name( \WP_Post $post ): string {
		$prefix_key = (string) get_post_meta( $post->ID, FieldRegistry::META_PREFIX . 'prefix', true );
		$map        = array(
			'dr'   => __( 'Dr.', 'doctorpilot' ),
			'prof' => __( 'Prof.', 'doctorpilot' ),
		);
		$prefix = $map[ $prefix_key ] ?? '';
		$title  = (string) $post->post_title;

		// Avoid a doubled prefix such as "Dr. Dr. Name".
		if ( '' !== $prefix && 0 === stripos( $title, rtrim( $prefix, '.' ) ) ) {
			$prefix = '';
		}

		return trim( $prefix . ' ' . $title );
	}

	/**
	 * Featured image data.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	private function photo( int $post_id ): array {
		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( ! $thumb_id ) {
			return array(
				'url'    => '',
				'alt'    => '',
				'width'  => 0,
				'height' => 0,
			);
		}

		$src = wp_get_attachment_image_src( $thumb_id, 'medium_large' );
		if ( ! is_array( $src ) ) {
			return array(
				'url'    => '',
				'alt'    => '',
				'width'  => 0,
				'height' => 0,
			);
		}

		return array(
			'url'    => (string) $src[0],
			'alt'    => (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
		);
	}

	/**
	 * A single primary term as id/name/slug, if valid.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key with the term ID.
	 * @param string $taxonomy Taxonomy.
	 * @return array<string, mixed>|null
	 */
	private function primary_term( int $post_id, string $meta_key, string $taxonomy ): ?array {
		$term_id = (int) get_post_meta( $post_id, $meta_key, true );
		if ( $term_id <= 0 ) {
			return null;
		}

		/*
		 * The stored value is a raw term ID, and a translated doctor carries
		 * whichever ID the translation plugin copied across — normally the
		 * source language's. Rendering it unconverted puts an English
		 * department name on an Arabic card, and publishes it as
		 * `medicalSpecialty` in the JSON-LD too.
		 */
		$term_id = Multilingual::term_id( $term_id, $taxonomy );

		$term = get_term( $term_id, $taxonomy );
		if ( ! ( $term instanceof \WP_Term ) ) {
			return null;
		}

		return array(
			'id'   => $term->term_id,
			'name' => self::term_name( $term ),
			'slug' => $term->slug,
		);
	}

	/**
	 * All terms of a taxonomy as id/name/slug.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @return array<int, array<string, mixed>>
	 */
	private function terms( int $post_id, string $taxonomy ): array {
		$terms = wp_get_object_terms( $post_id, $taxonomy );
		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$out = array();
		foreach ( $terms as $term ) {
			$out[] = array(
				'id'   => $term->term_id,
				'name' => self::term_name( $term ),
				'slug' => $term->slug,
			);
		}

		return $out;
	}

	/**
	 * A term's name as authored, ready for a text context.
	 *
	 * WordPress stores term names HTML-encoded ("A &amp; B"). Consumers escape
	 * again for output — `esc_html()` in a template, `textContent` in the
	 * frontend script — so forwarding the raw value renders the entity
	 * literally. Decoding once here keeps every consumer correct.
	 *
	 * @param \WP_Term $term Term.
	 * @return string
	 */
	private static function term_name( \WP_Term $term ): string {
		return html_entity_decode( (string) $term->name, ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' );
	}

	/**
	 * A string-list meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return array<int, string>
	 */
	private function string_list( int $post_id, string $key ): array {
		$value = get_post_meta( $post_id, $key, true );

		return is_array( $value ) ? array_values( array_map( 'strval', $value ) ) : array();
	}

	/**
	 * A repeater meta value as-is (already sanitized on save).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return array<int, array<string, mixed>>
	 */
	private function repeater( int $post_id, string $key ): array {
		$value = get_post_meta( $post_id, $key, true );

		return is_array( $value ) ? $value : array();
	}

	/**
	 * Location records with email gated behind the public-email consent flag.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function locations( int $post_id ): array {
		$rows = $this->repeater( $post_id, FieldRegistry::META_PREFIX . 'locations' );
		$out  = array();

		foreach ( $rows as $row ) {
			$location_id = isset( $row['location_term_id'] ) ? (int) $row['location_term_id'] : 0;
			$term        = $location_id > 0 ? get_term( $location_id, Taxonomies::LOCATION ) : null;

			$email_public = ! empty( $row['email_is_public'] );

			$out[] = array(
				'name'              => $term instanceof \WP_Term ? $term->name : '',
				'phone'             => isset( $row['phone'] ) ? (string) $row['phone'] : '',
				'public_email'      => $email_public && isset( $row['public_email'] ) ? (string) $row['public_email'] : '',
				'room_floor'        => isset( $row['room_floor'] ) ? (string) $row['room_floor'] : '',
				'consultation_type' => isset( $row['consultation_type'] ) ? (string) $row['consultation_type'] : '',
				'office_hours_text' => isset( $row['office_hours_text'] ) ? (string) $row['office_hours_text'] : '',
			);
		}

		return $out;
	}

	/**
	 * Booking CTA as a presentation-safe array, or null.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	private function booking_array( int $post_id ): ?array {
		$cta = $this->booking->resolve_for( $post_id );

		return null === $cta ? null : $cta->to_array();
	}
}
