<?php
/**
 * Symptom term metadata and curated relationships.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

namespace BrainStudioz\DoctorPilot\Taxonomies;

use BrainStudioz\DoctorPilot\Support\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds curated metadata to Symptom terms.
 *
 * Symptom terms may carry synonyms, a non-diagnostic explanation, curated
 * relationships to departments/specialties, an A–Z listing flag, and SEO
 * fields. Relationships are curated by editors; the plugin never invents a
 * clinical relationship. All writes require the term-management capability and
 * a valid nonce.
 */
final class SymptomTermMeta {

	private const ALT_NAMES        = '_doctorpilot_alt_names';
	private const SHORT_EXPLANATION = '_doctorpilot_short_explanation';
	private const AZ_LISTED        = '_doctorpilot_az_listed';
	private const PRIORITY         = '_doctorpilot_priority';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( Taxonomies::SYMPTOM . '_add_form_fields', array( $this, 'render_add_fields' ) );
		add_action( Taxonomies::SYMPTOM . '_edit_form_fields', array( $this, 'render_edit_fields' ) );
		add_action( 'created_' . Taxonomies::SYMPTOM, array( $this, 'save_fields' ) );
		add_action( 'edited_' . Taxonomies::SYMPTOM, array( $this, 'save_fields' ) );
	}

	/**
	 * Render fields on the "add term" screen.
	 *
	 * @return void
	 */
	public function render_add_fields(): void {
		wp_nonce_field( 'doctorpilot_symptom_meta', 'doctorpilot_symptom_meta_nonce' );
		echo '<div class="form-field">';
		echo '<label for="dp-alt-names">' . esc_html__( 'Alternative names', 'doctorpilot' ) . '</label>';
		echo '<input type="text" name="dp_alt_names" id="dp-alt-names" value="" />';
		echo '<p>' . esc_html__( 'Comma-separated synonyms patients might search for.', 'doctorpilot' ) . '</p>';
		echo '</div>';

		echo '<div class="form-field">';
		echo '<label for="dp-short-explanation">' . esc_html__( 'Short explanation', 'doctorpilot' ) . '</label>';
		echo '<textarea name="dp_short_explanation" id="dp-short-explanation" rows="3"></textarea>';
		echo '<p>' . esc_html__( 'Informational only. Not a diagnosis.', 'doctorpilot' ) . '</p>';
		echo '</div>';

		echo '<div class="form-field">';
		echo '<label><input type="checkbox" name="dp_az_listed" value="1" /> ' . esc_html__( 'Include in the A–Z symptom directory', 'doctorpilot' ) . '</label>';
		echo '</div>';
	}

	/**
	 * Render fields on the "edit term" screen.
	 *
	 * @param \WP_Term $term Term being edited.
	 * @return void
	 */
	public function render_edit_fields( \WP_Term $term ): void {
		$alt_names   = (string) get_term_meta( $term->term_id, self::ALT_NAMES, true );
		$explanation = (string) get_term_meta( $term->term_id, self::SHORT_EXPLANATION, true );
		$az_listed   = (bool) get_term_meta( $term->term_id, self::AZ_LISTED, true );

		wp_nonce_field( 'doctorpilot_symptom_meta', 'doctorpilot_symptom_meta_nonce' );

		echo '<tr class="form-field"><th scope="row"><label for="dp-alt-names">' . esc_html__( 'Alternative names', 'doctorpilot' ) . '</label></th><td>';
		printf( '<input type="text" name="dp_alt_names" id="dp-alt-names" value="%s" class="regular-text" />', esc_attr( $alt_names ) );
		echo '<p class="description">' . esc_html__( 'Comma-separated synonyms.', 'doctorpilot' ) . '</p></td></tr>';

		echo '<tr class="form-field"><th scope="row"><label for="dp-short-explanation">' . esc_html__( 'Short explanation', 'doctorpilot' ) . '</label></th><td>';
		printf( '<textarea name="dp_short_explanation" id="dp-short-explanation" rows="3" class="large-text">%s</textarea>', esc_textarea( $explanation ) );
		echo '<p class="description">' . esc_html__( 'Informational only. Not a diagnosis.', 'doctorpilot' ) . '</p></td></tr>';

		echo '<tr class="form-field"><th scope="row">' . esc_html__( 'A–Z directory', 'doctorpilot' ) . '</th><td>';
		printf(
			'<label><input type="checkbox" name="dp_az_listed" value="1" %s /> %s</label>',
			checked( $az_listed, true, false ),
			esc_html__( 'Include in the A–Z symptom directory', 'doctorpilot' )
		);
		echo '</td></tr>';
	}

	/**
	 * Persist symptom term meta with capability and nonce checks.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_fields( int $term_id ): void {
		if ( ! current_user_can( Capabilities::MANAGE_TERMS ) ) {
			return;
		}

		$nonce = isset( $_POST['doctorpilot_symptom_meta_nonce'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['doctorpilot_symptom_meta_nonce'] ) )
			: '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'doctorpilot_symptom_meta' ) ) {
			return;
		}

		$alt_names = isset( $_POST['dp_alt_names'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['dp_alt_names'] ) )
			: '';
		update_term_meta( $term_id, self::ALT_NAMES, $alt_names );

		$explanation = isset( $_POST['dp_short_explanation'] )
			? sanitize_textarea_field( wp_unslash( (string) $_POST['dp_short_explanation'] ) )
			: '';
		update_term_meta( $term_id, self::SHORT_EXPLANATION, $explanation );

		update_term_meta( $term_id, self::AZ_LISTED, empty( $_POST['dp_az_listed'] ) ? 0 : 1 );
	}
}
