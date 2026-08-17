<?php
/**
 * BookPilot booking provider.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Booking;

use BrainStudioz\DoctorPilot\Booking\BookingCta;
use BrainStudioz\DoctorPilot\Booking\BookingProviderInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves a booking CTA that links to a BookPilot AI booking page.
 *
 * DoctorPilot never owns appointments; this provider only builds a safe link to
 * the configured BookPilot booking base for the doctor's service. Availability
 * is never fabricated, and no credentials are ever emitted publicly. It is
 * registered through the `doctorpilot/booking/providers` filter.
 */
final class BookPilotProvider implements BookingProviderInterface {

	private const META_METHOD  = '_doctorpilot_booking_method';
	private const META_SERVICE = '_doctorpilot_bookpilot_service_id';

	/**
	 * Register this provider with the booking resolver.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter(
			'doctorpilot/booking/providers',
			static function ( array $providers ): array {
				$providers[] = new self();

				return $providers;
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'bookpilot';
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports( int $doctor_id ): bool {
		return 'bookpilot' === (string) get_post_meta( $doctor_id, self::META_METHOD, true );
	}

	/**
	 * {@inheritDoc}
	 */
	public function resolve( int $doctor_id, ?int $location_idx = null ) {
		$base = self::booking_base();
		if ( '' === $base ) {
			return new \WP_Error( 'doctorpilot_provider_unavailable', __( 'BookPilot is not configured.', 'doctorpilot' ) );
		}

		$service = sanitize_text_field( (string) get_post_meta( $doctor_id, self::META_SERVICE, true ) );
		$url     = add_query_arg(
			array_filter(
				array(
					'doctor'  => $doctor_id,
					'service' => '' !== $service ? $service : null,
				)
			),
			$base
		);

		$url = esc_url_raw( $url, array( 'http', 'https' ) );
		if ( '' === $url ) {
			return new \WP_Error( 'doctorpilot_provider_unavailable', __( 'Could not build a BookPilot link.', 'doctorpilot' ) );
		}

		$label = (string) get_post_meta( $doctor_id, '_doctorpilot_booking_label', true );
		$label = '' !== $label ? $label : __( 'Book with BookPilot', 'doctorpilot' );

		// Availability is never fabricated: availability_verified stays false.
		return new BookingCta( 'bookpilot', $label, $url, '_blank', false );
	}

	/**
	 * The configured public BookPilot booking base URL.
	 *
	 * Stored in a dedicated option (not autoloaded); no secret/API key is used
	 * for building the public link.
	 *
	 * @return string
	 */
	private static function booking_base(): string {
		$settings = get_option( 'bstdz_doctorpilot_pro_bookpilot', array() );
		$base     = is_array( $settings ) && isset( $settings['booking_base'] ) ? (string) $settings['booking_base'] : '';

		return esc_url_raw( $base, array( 'http', 'https' ) );
	}
}
