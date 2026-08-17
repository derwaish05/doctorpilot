<?php
/**
 * Per-element show/hide controls for the doctor card.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor\Widgets\Concerns;

use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers one switcher per visible part of the doctor card and resolves those
 * switchers into the flag array the card template and the JavaScript card
 * renderer both read.
 *
 * A hidden part is omitted from the markup rather than hidden with CSS. A card
 * with `display: none` content still ships that text to a screen reader, to a
 * crawler and over the wire, so "hidden" would only be true for a sighted
 * visitor. Omission is the only version of the setting that is true for
 * everyone.
 *
 * The map below is the single source of truth for part names. `templates/
 * card-doctor.php` reads the resolved flags from `$data['parts']`; `assets/js/
 * directory.js` reads the inverse — the hidden part names — from the
 * `data-card-hide` attribute on the directory root. The two renderers must
 * agree, or the first client-side refresh would silently bring hidden content
 * back.
 *
 * Every control defaults to `yes`, and Elementor supplies control defaults to
 * instances saved before a control existed, so adding this section leaves every
 * existing widget rendering exactly as it did.
 */
trait CardElementControls {

	/**
	 * Card part name => Elementor setting key.
	 *
	 * Declared as a method rather than a constant because trait constants
	 * require PHP 8.2 and this plugin supports older runtimes.
	 *
	 * @return array<string, string>
	 */
	private function card_element_map(): array {
		return array(
			'image'               => 'image_show',
			'badge_featured'      => 'badge_featured_show',
			'badge_international' => 'badge_international_show',
			'credentials'         => 'credentials_show',
			'specialty'           => 'specialty_show',
			'bio'                 => 'bio_show',
			'experience'          => 'experience_show',
			'languages'           => 'languages_show',
			'accepting'           => 'accepting_show',
			'profile_button'      => 'profile_button_show',
			'booking_button'      => 'booking_button_show',
		);
	}

	/**
	 * Register the "Card elements" section.
	 *
	 * Content tab, not Style: these switchers decide what exists, not how it
	 * looks.
	 *
	 * @return void
	 */
	protected function register_card_elements_section(): void {
		$this->start_controls_section(
			'section_card_elements',
			array( 'label' => __( 'Card elements', 'doctorpilot' ) )
		);

		$this->add_control(
			'card_elements_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Turn any part of the card off. A hidden part is removed from the page, not just visually hidden, so it never reaches a screen reader or a search engine. The doctor name always shows — it is the card’s link to the profile.', 'doctorpilot' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->add_control(
			'image_show',
			array(
				'label'        => __( 'Photo', 'doctorpilot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => __( 'Hides the image area entirely, including the initials fallback and any badges over it.', 'doctorpilot' ),
			)
		);

		$this->add_control(
			'badge_featured_show',
			array(
				'label'        => __( '“Featured” badge', 'doctorpilot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'image_show' => 'yes' ),
			)
		);

		$this->add_control(
			'badge_international_show',
			array(
				'label'        => __( '“International” badge', 'doctorpilot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'image_show' => 'yes' ),
			)
		);

		$this->add_control(
			'credentials_show',
			array(
				'label'        => __( 'Credentials', 'doctorpilot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'separator'    => 'before',
				'description'  => __( 'The qualifications line, for example “MD, MRCP, FRCR”.', 'doctorpilot' ),
			)
		);

		$this->add_control(
			'specialty_show',
			array(
				'label'        => __( 'Specialty or department', 'doctorpilot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'bio_show',
			array(
				'label'        => __( 'Short bio', 'doctorpilot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'experience_show',
			array(
				'label'        => __( 'Years of experience', 'doctorpilot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'separator'    => 'before',
			)
		);

		$this->add_control(
			'languages_show',
			array(
				'label'        => __( 'Languages', 'doctorpilot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'accepting_show',
			array(
				'label'        => __( '“Accepting new patients” row', 'doctorpilot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'profile_button_show',
			array(
				'label'        => __( '“View Profile” button', 'doctorpilot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'separator'    => 'before',
			)
		);

		$this->add_control(
			'booking_button_show',
			array(
				'label'        => __( 'Booking button', 'doctorpilot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Resolve the switchers into a flag per card part.
	 *
	 * Missing keys resolve to `true` so a widget saved before this section
	 * existed, a theme template override, or a shortcode that passes no
	 * settings all render the complete card.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @return array<string, bool>
	 */
	protected function card_parts( array $settings ): array {
		$parts = array();

		foreach ( $this->card_element_map() as $part => $key ) {
			$parts[ $part ] = 'yes' === ( $settings[ $key ] ?? 'yes' );
		}

		return $parts;
	}

	/**
	 * The hidden part names, for the `data-card-hide` attribute the JavaScript
	 * card renderer reads.
	 *
	 * The inverse of `card_parts()` is sent rather than the flags themselves
	 * because the common case is nothing hidden, and an empty attribute is
	 * cheaper to emit and to read than eleven booleans.
	 *
	 * @param array<string, bool> $parts Resolved part flags.
	 * @return string Comma-separated hidden part names, or an empty string.
	 */
	protected function card_hidden_parts( array $parts ): string {
		$hidden = array();

		foreach ( $parts as $part => $visible ) {
			if ( ! $visible ) {
				$hidden[] = (string) $part;
			}
		}

		return implode( ',', $hidden );
	}
}
