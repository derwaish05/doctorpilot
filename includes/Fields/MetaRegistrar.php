<?php
/**
 * Registers doctor post meta from the field registry.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Fields;

use BrainStudioz\DoctorPilot\PostTypes\DoctorPostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers every meta-stored field with WordPress.
 *
 * All doctor meta is registered private (leading underscore, `show_in_rest`
 * false) so the core REST controller never exposes it. Public exposure is the
 * sole responsibility of DoctorPilot's own allowlisted view model.
 */
final class MetaRegistrar {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ), 7 );
	}

	/**
	 * Register post meta for each meta-stored field.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		foreach ( FieldRegistry::meta_fields() as $field ) {
			$key = (string) $field['key'];

			register_post_meta(
				DoctorPostType::POST_TYPE,
				$key,
				array(
					'type'              => $this->wp_type( (string) $field['type'] ),
					'single'            => true,
					'show_in_rest'      => false,
					'auth_callback'     => static function (): bool {
						return current_user_can( 'edit_doctorpilot_doctors' );
					},
					'sanitize_callback' => static function ( $value ) use ( $field ) {
						return Sanitizer::apply( $field, $value );
					},
				)
			);
		}
	}

	/**
	 * Map a registry type to a WordPress meta type.
	 *
	 * @param string $type Registry type.
	 * @return string
	 */
	private function wp_type( string $type ): string {
		switch ( $type ) {
			case 'int':
			case 'bool':
				return 'integer';
			case 'array':
				return 'array';
			default:
				return 'string';
		}
	}
}
