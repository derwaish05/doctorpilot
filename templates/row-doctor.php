<?php
/**
 * Compact doctor row used by the directory's list layout.
 *
 * Expects $card (array) from DoctorView::card(). Unlike a browse-index entry
 * this row links to the doctor's profile: it is a result, not a control.
 *
 * Override by copying to your theme as doctorpilot/row-doctor.php.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $card ) || ! is_array( $card ) ) {
	return;
}

$dp_row_meta = isset( $card['primary_specialty']['name'] ) ? (string) $card['primary_specialty']['name'] : '';
if ( '' === $dp_row_meta ) {
	$dp_row_meta = isset( $card['primary_department']['name'] ) ? (string) $card['primary_department']['name'] : '';
}

// The card's "Specialty or department" switcher governs this row's only
// metadata line too. It is the same field from the same widget, so honouring it
// in one layout and not the other would make the switch look broken to anyone
// who flips the widget to the compact list.
if ( isset( $data['parts']['specialty'] ) && ! $data['parts']['specialty'] ) {
	$dp_row_meta = '';
}
?>
<article class="dp-dir__row dp-dir__row--result">
	<a class="dp-dir__row-name" href="<?php echo esc_url( (string) $card['url'] ); ?>">
		<?php echo esc_html( (string) $card['name'] ); ?>
	</a>
	<?php if ( '' !== $dp_row_meta ) : ?>
		<p class="dp-dir__row-meta"><?php echo esc_html( $dp_row_meta ); ?></p>
	<?php endif; ?>
</article>
