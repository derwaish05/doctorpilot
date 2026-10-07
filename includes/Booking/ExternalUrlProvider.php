<?php
/**
 * External URL booking provider.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Booking;

use BrainStudioz\DoctorPilot\Fields\FieldRegistry;
use BrainStudioz\DoctorPilot\Support\ErrorFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves a booking CTA that links to an external booking URL.
 */
final class ExternalUrlProvider implements BookingProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'external_url';
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports( int $doctor_id ): bool {
		$method = (string) get_post_meta( $doctor_id, FieldRegistry::META_PREFIX . 'booking_method', true );
		return 'external_url' === $method;
	}

	/**
	 * {@inheritDoc}
	 */
	public function resolve( int $doctor_id, ?int $location_idx = null ) {
		// The doctor's own URL wins; blank inherits the site-wide booking page
		// from Settings → Booking.
		$url = trim( (string) get_post_meta( $doctor_id, FieldRegistry::META_PREFIX . 'booking_external_url', true ) );
		if ( '' === $url ) {
			$url = BookingDefaults::url();
		}

		$url = esc_url_raw( $url, array( 'http', 'https' ) );

		if ( '' === $url ) {
			return ErrorFactory::create(
				ErrorFactory::PROVIDER_UNAVAILABLE,
				__( 'No booking URL is configured for this doctor or as a site default.', 'doctorpilot' ),
				422
			);
		}

		$label = BookingDefaults::label(
			(string) get_post_meta( $doctor_id, FieldRegistry::META_PREFIX . 'booking_label', true ),
			__( 'Book Appointment', 'doctorpilot' )
		);

		return new BookingCta( 'external_url', $label, $url, BookingDefaults::target() );
	}
}
