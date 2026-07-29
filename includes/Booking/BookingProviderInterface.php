<?php
/**
 * Booking provider contract.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract every booking provider implements.
 *
 * A provider resolves a doctor (and optional location) into a validated
 * {@see BookingCta}, or a WP_Error when it cannot. Providers never store
 * appointments in the Free plugin and never expose credentials.
 */
interface BookingProviderInterface {

	/**
	 * Stable provider identifier.
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Whether this provider can build a CTA for the given doctor.
	 *
	 * @param int $doctor_id Doctor post ID.
	 * @return bool
	 */
	public function supports( int $doctor_id ): bool;

	/**
	 * Resolve a booking CTA for a doctor.
	 *
	 * @param int      $doctor_id    Doctor post ID.
	 * @param int|null $location_idx Optional location index.
	 * @return BookingCta|\WP_Error
	 */
	public function resolve( int $doctor_id, ?int $location_idx = null );
}
