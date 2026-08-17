<?php
/**
 * One entry in a directory browse index — a doctor name, department, specialty
 * or symptom.
 *
 * The entry is a link that sets the index's search parameter, so it filters the
 * doctor results below rather than navigating away from them. Without
 * JavaScript the same link is a plain GET that reloads the page in the selected
 * state; the script intercepts it and refreshes the results in place.
 *
 * Expects $term (array) with name, value, letter, and optionally meta — plus
 * $dp_param (the parameter this index writes), $dp_pick (the selected value),
 * and $dp_letter / $dp_query (the active A–Z and free-text filters). Rows that
 * do not match are rendered but hidden, so the same markup serves the
 * no-JavaScript `?letter=` / `?q=` links and instant client-side filtering.
 *
 * Override by copying to your theme as doctorpilot/row-term.php.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $term ) || ! is_array( $term ) ) {
	return;
}

$dp_term_letter = (string) ( $term['letter'] ?? '#' );
$dp_term_name   = (string) ( $term['name'] ?? '' );
$dp_term_value  = (string) ( $term['value'] ?? '' );
$dp_term_meta   = (string) ( $term['meta'] ?? '' );
$dp_term_param  = isset( $dp_param ) ? (string) $dp_param : '';
$dp_term_picked = isset( $dp_pick ) && '' !== (string) $dp_pick && (string) $dp_pick === $dp_term_value;

$dp_term_hidden = isset( $dp_letter ) && '' !== (string) $dp_letter && (string) $dp_letter !== $dp_term_letter;

if ( ! $dp_term_hidden && isset( $dp_query ) && '' !== (string) $dp_query ) {
	$dp_term_hidden = false === stripos( $dp_term_name, (string) $dp_query );
}

// Selecting toggles: clicking the current selection clears it. The URL is
// escaped again below rather than trusting the closure, because a theme may
// override the parent template and supply its own.
$dp_term_href = ( isset( $dp_state_url ) && is_callable( $dp_state_url ) && '' !== $dp_term_param )
	? $dp_state_url( array( $dp_term_param => $dp_term_picked ? null : $dp_term_value ) )
	: '';
?>
<article
	class="dp-dir__row<?php echo $dp_term_picked ? ' is-selected' : ''; ?>"
	data-dp-letter="<?php echo esc_attr( $dp_term_letter ); ?>"
	data-dp-value="<?php echo esc_attr( $dp_term_value ); ?>"
	<?php echo $dp_term_hidden ? 'hidden' : ''; ?>
>
	<a
		class="dp-dir__row-name"
		href="<?php echo esc_url( $dp_term_href ); ?>"
		data-dp-select
		<?php echo $dp_term_picked ? 'aria-current="true"' : ''; ?>
	><?php echo esc_html( $dp_term_name ); ?></a>
	<?php if ( '' !== $dp_term_meta ) : ?>
		<p class="dp-dir__row-meta"><?php echo esc_html( $dp_term_meta ); ?></p>
	<?php endif; ?>
</article>
