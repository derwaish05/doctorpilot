<?php
/**
 * Compact doctor row used by the directory list layout and A–Z index.
 *
 * Expects $card (array) from DoctorView::card().
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
?>
<article class="dp-dir__row">
	<a class="dp-dir__row-name" href="<?php echo esc_url( (string) $card['url'] ); ?>">
		<?php echo esc_html( (string) $card['name'] ); ?>
	</a>
	<?php if ( '' !== $dp_row_meta ) : ?>
		<p class="dp-dir__row-meta"><?php echo esc_html( $dp_row_meta ); ?></p>
	<?php endif; ?>
</article>
