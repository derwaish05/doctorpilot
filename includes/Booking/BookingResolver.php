<?php
/**
 * Booking provider registry and resolver.
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
 * Holds the registered booking providers and resolves a doctor's CTA.
 *
 * Registers the External URL, Phone and Enquiry providers; add-ons may register
 * more through the `doctorpilot/booking/providers` filter. A doctor with no
 * method of their own — or one naming a provider that is no longer installed —
 * inherits the site default from Settings → Booking. Every resolved CTA is
 * revalidated before it is returned; on failure the button is hidden rather
 * than pointed somewhere unintended.
 */
final class BookingResolver {

	/**
	 * Booking method meaning "this doctor takes no bookings".
	 *
	 * Distinct from an unset method, which inherits the site default.
	 *
	 * @var string
	 */
	private const OPT_OUT = 'none';

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

		return $this->resolve_default( $doctor_id, $location_idx );
	}

	/**
	 * Resolve through the site-wide default method when no provider claimed the
	 * doctor.
	 *
	 * That happens for a doctor who has never had a booking method saved, and
	 * for one still carrying a method whose provider no longer exists — a
	 * profile left on `bookpilot` after that provider was removed. Inheriting
	 * the site default keeps their button working instead of silently dropping
	 * it. A doctor whose own provider matched and failed never reaches here.
	 *
	 * @param int      $doctor_id    Doctor post ID.
	 * @param int|null $location_idx Optional location index.
	 * @return BookingCta|null
	 */
	private function resolve_default( int $doctor_id, ?int $location_idx ): ?BookingCta {
		// An explicit opt-out. Without this a doctor who takes no bookings would
		// inherit the site default and show a button the admin just turned off.
		if ( self::OPT_OUT === (string) get_post_meta( $doctor_id, FieldRegistry::META_PREFIX . 'booking_method', true ) ) {
			return null;
		}

		$provider = $this->providers[ BookingDefaults::method() ] ?? null;

		if ( ! $provider instanceof BookingProviderInterface ) {
			return null;
		}

		$cta = $provider->resolve( $doctor_id, $location_idx );

		return $cta instanceof BookingCta && $this->is_safe( $cta ) ? $cta : null;
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
