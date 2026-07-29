<?php
/**
 * Enquiry booking provider.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Booking;

use BrainStudioz\DoctorPilot\Fields\FieldRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves a booking CTA that points to an on-page enquiry anchor.
 *
 * The enquiry form itself is rendered by the theme/template; this provider only
 * produces a safe in-page link, so it needs no external destination.
 */
final class EnquiryProvider implements BookingProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'enquiry';
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports( int $doctor_id ): bool {
		$method = (string) get_post_meta( $doctor_id, FieldRegistry::META_PREFIX . 'booking_method', true );
		return 'enquiry' === $method;
	}

	/**
	 * {@inheritDoc}
	 */
	public function resolve( int $doctor_id, ?int $location_idx = null ) {
		$label = (string) get_post_meta( $doctor_id, FieldRegistry::META_PREFIX . 'booking_label', true );
		$label = '' !== $label ? $label : __( 'Request an appointment', 'doctorpilot' );

		return new BookingCta( 'enquiry', $label, '#doctorpilot-enquiry', '_self' );
	}
}
