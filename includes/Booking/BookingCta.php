<?php
/**
 * Booking call-to-action value object.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An immutable, already-validated booking CTA ready for rendering.
 *
 * Contains only presentation-safe data. It never carries provider credentials
 * and never fabricates an availability time.
 */
final class BookingCta {

	/**
	 * CTA type: external_url|phone|enquiry.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * Button label.
	 *
	 * @var string
	 */
	private string $label;

	/**
	 * Destination: a URL, a `tel:` URL, or an in-page anchor.
	 *
	 * @var string
	 */
	private string $destination;

	/**
	 * Link target: _self or _blank.
	 *
	 * @var string
	 */
	private string $target;

	/**
	 * Whether availability shown (if any) is provider-verified.
	 *
	 * @var bool
	 */
	private bool $availability_verified;

	/**
	 * Constructor.
	 *
	 * @param string $type                  CTA type.
	 * @param string $label                 Button label.
	 * @param string $destination           Destination URL/anchor.
	 * @param string $target                Link target.
	 * @param bool   $availability_verified Whether availability is verified.
	 */
	public function __construct( string $type, string $label, string $destination, string $target = '_self', bool $availability_verified = false ) {
		$this->type                  = $type;
		$this->label                 = $label;
		$this->destination           = $destination;
		$this->target                = in_array( $target, array( '_self', '_blank' ), true ) ? $target : '_self';
		$this->availability_verified = $availability_verified;
	}

	/**
	 * CTA type.
	 *
	 * @return string
	 */
	public function type(): string {
		return $this->type;
	}

	/**
	 * Button label.
	 *
	 * @return string
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * Destination URL or anchor.
	 *
	 * @return string
	 */
	public function destination(): string {
		return $this->destination;
	}

	/**
	 * Link target.
	 *
	 * @return string
	 */
	public function target(): string {
		return $this->target;
	}

	/**
	 * Whether availability is provider-verified.
	 *
	 * @return bool
	 */
	public function availability_verified(): bool {
		return $this->availability_verified;
	}

	/**
	 * Presentation-safe array form for view models.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'type'                  => $this->type,
			'label'                 => $this->label,
			'url'                   => $this->destination,
			'target'                => $this->target,
			'availability_verified' => $this->availability_verified,
		);
	}
}
