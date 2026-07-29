<?php
/**
 * Booking provider registry and resolver.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds the registered booking providers and resolves a doctor's CTA.
 *
 * Free registers External URL, Phone, and Enquiry providers. Pro can register
 * additional providers (e.g. BookPilot) through the `doctorpilot/booking/providers`
 * filter. Every resolved CTA is revalidated before it is returned; on failure a
 * safe fallback is used rather than a fabricated appointment.
 */
final class BookingResolver {

	/**
	 * Registered providers keyed by id.
	 *
	 * @var array<string, BookingProviderInterface>
	 */
	private array $providers = array();

	/**
	 * Build the resolver with the Free providers plus any registered by add-ons.
	 */
	public function __construct() {
		$defaults = array(
			new ExternalUrlProvider(),
			new PhoneProvider(),
			new EnquiryProvider(),
		);

		/**
		 * Filter the list of booking providers.
		 *
		 * @param array<int, BookingProviderInterface> $defaults Provider instances.
		 */
		$providers = apply_filters( 'doctorpilot/booking/providers', $defaults );

		foreach ( (array) $providers as $provider ) {
			if ( $provider instanceof BookingProviderInterface ) {
				$this->providers[ $provider->id() ] = $provider;
			}
		}
	}

	/**
	 * Resolve the booking CTA for a doctor, or null when none is available.
	 *
	 * @param int      $doctor_id    Doctor post ID.
	 * @param int|null $location_idx Optional location index.
	 * @return BookingCta|null
	 */
	public function resolve_for( int $doctor_id, ?int $location_idx = null ): ?BookingCta {
		foreach ( $this->providers as $provider ) {
			if ( ! $provider->supports( $doctor_id ) ) {
				continue;
			}

			$cta = $provider->resolve( $doctor_id, $location_idx );

			if ( $cta instanceof BookingCta && $this->is_safe( $cta ) ) {
				return $cta;
			}

			// A provider that matched but failed does not fall through to another
			// provider's data; return no CTA so the UI can hide the button safely.
			return null;
		}

		return null;
	}

	/**
	 * Final safety revalidation of a resolved CTA destination.
	 *
	 * @param BookingCta $cta Candidate CTA.
	 * @return bool
	 */
	private function is_safe( BookingCta $cta ): bool {
		$destination = $cta->destination();

		if ( '' === $destination ) {
			return false;
		}

		// Allow in-page anchors and tel: links.
		if ( '#' === $destination[0] || 0 === strpos( $destination, 'tel:' ) ) {
			return true;
		}

		// Otherwise require a safe http(s) URL.
		$safe = esc_url_raw( $destination, array( 'http', 'https' ) );

		return '' !== $safe && $safe === $destination;
	}
}
