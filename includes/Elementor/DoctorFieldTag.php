<?php
/**
 * Doctor field dynamic tag (Elementor Pro).
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Elementor;

use BrainStudioz\DoctorPilot\Application\Doctors\DoctorView;
use BrainStudioz\DoctorPilot\Fields\FieldRegistry;
use Elementor\Core\DynamicTags\Tag;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A configurable dynamic tag that outputs a public doctor field for the current
 * doctor context (single-doctor templates).
 *
 * Only public, non-private, Elementor-eligible fields are selectable, and values
 * come from the allowlisted view model — private fields such as license numbers
 * can never be bound or exposed.
 */
final class DoctorFieldTag extends Tag {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'doctorpilot-field';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Doctor Field', 'doctorpilot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_group(): string {
		return 'doctorpilot';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_categories(): array {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}

	/**
	 * Register the field-selection control.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->add_control(
			'field',
			array(
				'label'   => __( 'Field', 'doctorpilot' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $this->field_options(),
				'default' => 'name',
			)
		);
	}

	/**
	 * Output the selected field's value for the current doctor.
	 *
	 * @return void
	 */
	public function render(): void {
		$queried = get_queried_object();
		if ( ! ( $queried instanceof \WP_Post ) || 'doctorpilot_doctor' !== $queried->post_type ) {
			return;
		}

		$key  = (string) $this->get_settings( 'field' );
		$data = ( new DoctorView() )->full( (int) $queried->ID );
		if ( null === $data ) {
			return;
		}

		$map = array(
			'name'             => (string) ( $data['name'] ?? '' ),
			'intro'            => (string) ( $data['intro'] ?? '' ),
			'years_experience' => (string) ( $data['years_experience'] ?? '' ),
		);

		if ( isset( $map[ $key ] ) ) {
			echo wp_kses_post( $map[ $key ] );
		}
	}

	/**
	 * The selectable field options (public, text-like fields only).
	 *
	 * @return array<string, string>
	 */
	private function field_options(): array {
		$options = array(
			'name'             => __( 'Name', 'doctorpilot' ),
			'intro'            => __( 'Short introduction', 'doctorpilot' ),
			'years_experience' => __( 'Years of experience', 'doctorpilot' ),
		);

		// Guard: never surface a private field even if a future registry change adds one.
		if ( class_exists( '\\BrainStudioz\\DoctorPilot\\Fields\\FieldRegistry' ) ) {
			foreach ( FieldRegistry::all() as $key => $field ) {
				if ( ! empty( $field['private'] ) && isset( $options[ $key ] ) ) {
					unset( $options[ $key ] );
				}
			}
		}

		return $options;
	}
}
