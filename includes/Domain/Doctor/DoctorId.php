<?php
/**
 * Doctor identifier value object.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Domain\Doctor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A validated doctor post identifier.
 */
final class DoctorId {

	/**
	 * The post ID.
	 *
	 * @var int
	 */
	private int $id;

	/**
	 * Constructor.
	 *
	 * @param int $id Positive post ID.
	 *
	 * @throws \InvalidArgumentException When the ID is not positive.
	 */
	public function __construct( int $id ) {
		if ( $id <= 0 ) {
			throw new \InvalidArgumentException( 'DoctorId must be a positive integer.' );
		}

		$this->id = $id;
	}

	/**
	 * Get the integer value.
	 *
	 * @return int
	 */
	public function value(): int {
		return $this->id;
	}
}
