<?php
/**
 * Phone booking provider.
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
 * Resolves a booking CTA that dials a phone number.
 */
final class PhoneProvider implements BookingProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'phone';
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports( int $doctor_id ): bool {
		$method = (string) get_post_meta( $doctor_id, FieldRegistry::META_PREFIX . 'booking_method', true );
		return 'phone' === $method;
	}

	/**
	 * {@inheritDoc}
	 */
	public function resolve( int $doctor_id, ?int $location_idx = null ) {
		$phone = (string) get_post_meta( $doctor_id, FieldRegistry::META_PREFIX . 'booking_phone', true );
		$digits = preg_replace( '/[^0-9+]/', '', $phone );

		if ( '' === (string) $digits ) {
			return ErrorFactory::create(
				ErrorFactory::PROVIDER_UNAVAILABLE,
				__( 'No booking phone number is configured for this doctor.', 'doctorpilot' ),
				422
			);
		}

		$label = (string) get_post_meta( $doctor_id, FieldRegistry::META_PREFIX . 'booking_label', true );
		$label = '' !== $label ? $label : __( 'Call to book', 'doctorpilot' );

		return new BookingCta( 'phone', $label, 'tel:' . $digits, '_self' );
	}
}
