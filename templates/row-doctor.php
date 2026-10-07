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

$dp_row_specialty  = isset( $card['primary_specialty']['name'] ) ? (string) $card['primary_specialty']['name'] : '';
$dp_row_department = isset( $card['primary_department']['name'] ) ? (string) $card['primary_department']['name'] : '';

/*
 * The card's specialty switchers govern this row's only metadata line too. They
 * are the same fields from the same widget, so honouring them in one layout and
 * not the other would make the switches look broken to anyone who flips the
 * widget to the compact list.
 *
 * A row has one line where the card has two, so the two candidates are joined
 * rather than collapsed by precedence. Collapsing would make the Department
 * switch a no-op here — a doctor with a specialty would print the specialty
 * either way — and a switch that does nothing in one layout reads as broken.
 * The resolution above the join is the card's, unchanged.
 */
$dp_row_parts = isset( $data['parts'] ) && is_array( $data['parts'] ) ? $data['parts'] : array();

$dp_row_on  = static function ( string $dp_part ) use ( $dp_row_parts ): bool {
	return ! isset( $dp_row_parts[ $dp_part ] ) || (bool) $dp_row_parts[ $dp_part ];
};
$dp_row_opt = static function ( string $dp_part ) use ( $dp_row_parts ): bool {
	return isset( $dp_row_parts[ $dp_part ] ) && (bool) $dp_row_parts[ $dp_part ];
};

$dp_row_department_line = $dp_row_opt( 'department' ) ? $dp_row_department : '';

$dp_row_specialty_line = '';
if ( $dp_row_on( 'specialty' ) ) {
	$dp_row_specialty_line = $dp_row_specialty;

	if ( '' === $dp_row_specialty_line && '' === $dp_row_department_line && $dp_row_on( 'specialty_fallback' ) ) {
		$dp_row_specialty_line = $dp_row_department;
	}
}

$dp_row_meta = implode(
	/* translators: separator between the specialty and department on a compact result row. */
	__( ' · ', 'doctorpilot' ),
	array_filter( array( $dp_row_specialty_line, $dp_row_department_line ), 'strlen' )
);
?>
<article class="dp-dir__row dp-dir__row--result">
	<a class="dp-dir__row-name" href="<?php echo esc_url( (string) $card['url'] ); ?>">
		<?php echo esc_html( (string) $card['name'] ); ?>
	</a>
	<?php if ( '' !== $dp_row_meta ) : ?>
		<p class="dp-dir__row-meta"><?php echo esc_html( $dp_row_meta ); ?></p>
	<?php endif; ?>
</article>
