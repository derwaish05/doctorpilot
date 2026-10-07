<?php
/**
 * Doctor carousel / grid template.
 *
 * Expects $data with keys: settings, cards, display, preset, columns, uid,
 * card_classes, meta_icons, card_linked, clamp, parts, arrows,
 * dots, rewind, autoplay, autoplay_ms, arrow_pos, empty_text, is_editor.
 *
 * The track is a real scroll container, so this markup is usable before — and
 * without — the script: a visitor can swipe or scroll it, and a keyboard user
 * can focus it and scroll with the arrow keys. The buttons and dots are the
 * part that needs JavaScript, so they are emitted `hidden` and revealed by the
 * script. A control that does nothing is worse than no control.
 *
 * Override by copying to your theme as doctorpilot/carousel-doctor.php.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $data ) || ! is_array( $data ) ) {
	return;
}

$dp_cards   = (array) ( $data['cards'] ?? array() );
$dp_display = 'grid' === ( $data['display'] ?? 'carousel' ) ? 'grid' : 'carousel';
$dp_preset  = (string) ( $data['preset'] ?? 'modern' );
$dp_columns = max( 1, min( 6, (int) ( $data['columns'] ?? 3 ) ) );
$dp_uid     = (string) ( $data['uid'] ?? 'dp-car' );

if ( array() === $dp_cards ) {
	// Only reachable in the editor: the widget returns before including this
	// template when there is nothing to show on the front end.
	if ( ! empty( $data['is_editor'] ) ) {
		// Wrapped in `.dp-dir` because that is what the empty-state rule is
		// scoped to; an unwrapped paragraph would render unstyled.
		echo '<div class="dp-dir"><p class="dp-empty">' . esc_html( (string) ( $data['empty_text'] ?? '' ) ) . '</p></div>';
	}
	return;
}

/**
 * Inline chevron. Kept inline for the same reason as the card's metadata icons:
 * a control that costs an extra request is a control that arrives late.
 *
 * @param string $dp_dir Either `prev` or `next`.
 * @return string
 */
$dp_chevron = static function ( string $dp_dir ): string {
	$path = 'prev' === $dp_dir ? 'M15 5 8 12l7 7' : 'M9 5l7 7-7 7';

	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' . $path . '"/></svg>';
};

$dp_svg_kses = array(
	'svg'  => array(
		'viewbox'         => array(),
		'fill'            => array(),
		'stroke'          => array(),
		'stroke-width'    => array(),
		'stroke-linecap'  => array(),
		'stroke-linejoin' => array(),
		'aria-hidden'     => array(),
		'focusable'       => array(),
	),
	'path' => array( 'd' => array() ),
	'rect' => array( 'x' => array(), 'y' => array(), 'width' => array(), 'height' => array(), 'rx' => array() ),
);
?>
<?php if ( 'grid' === $dp_display ) : ?>

	<div class="dp-dir dp-dir--grid dp-dir--preset-<?php echo esc_attr( $dp_preset ); ?>" id="<?php echo esc_attr( $dp_uid ); ?>">
		<div class="dp-directory dp-cols-<?php echo esc_attr( (string) $dp_columns ); ?>">
			<?php
			foreach ( $dp_cards as $card ) {
				include \BrainStudioz\DoctorPilot\Frontend\Templates::locate( 'card-doctor.php' );
			}
			?>
		</div>
	</div>

<?php else : ?>

	<?php
	$dp_classes = array(
		'dp-dir',
		'dp-dir--grid',
		'dp-dir--preset-' . $dp_preset,
		'dp-carousel',
		'dp-carousel--nav-' . (string) ( $data['arrow_pos'] ?? 'edges' ),
	);

	if ( empty( $data['arrows'] ) ) {
		$dp_classes[] = 'dp-carousel--no-arrows';
	}
	?>
	<div
		class="<?php echo esc_attr( implode( ' ', $dp_classes ) ); ?>"
		id="<?php echo esc_attr( $dp_uid ); ?>"
		data-dp-carousel
		data-rewind="<?php echo empty( $data['rewind'] ) ? '0' : '1'; ?>"
		data-autoplay="<?php echo esc_attr( (string) (int) ( $data['autoplay_ms'] ?? 0 ) ); ?>"
		role="region"
		aria-roledescription="<?php esc_attr_e( 'carousel', 'doctorpilot' ); ?>"
		aria-label="<?php esc_attr_e( 'Doctors', 'doctorpilot' ); ?>"
	>
		<?php /* tabindex makes the scroll container reachable and scrollable by
		         keyboard, which a focusable-content-free scroll region otherwise
		         is not. It therefore also needs its own accessible name. */ ?>
		<div
			class="dp-carousel__viewport"
			data-dp-track
			tabindex="0"
			role="group"
			aria-label="<?php esc_attr_e( 'Doctor cards, scrollable', 'doctorpilot' ); ?>"
		>
			<div class="dp-carousel__track">
				<?php foreach ( $dp_cards as $dp_index => $card ) : ?>
					<div
						class="dp-carousel__slide"
						role="group"
						aria-roledescription="<?php esc_attr_e( 'slide', 'doctorpilot' ); ?>"
						aria-label="<?php
							echo esc_attr(
								sprintf(
									/* translators: 1: slide number, 2: total slides. */
									__( '%1$d of %2$d', 'doctorpilot' ),
									(int) $dp_index + 1,
									count( $dp_cards )
								)
							);
						?>"
					>
						<?php include \BrainStudioz\DoctorPilot\Frontend\Templates::locate( 'card-doctor.php' ); ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<?php if ( ! empty( $data['arrows'] ) ) : ?>
			<button type="button" class="dp-carousel__nav dp-carousel__nav--prev" data-dp-prev hidden>
				<?php echo wp_kses( $dp_chevron( 'prev' ), $dp_svg_kses ); ?>
				<span class="dp-sr-only"><?php esc_html_e( 'Previous doctors', 'doctorpilot' ); ?></span>
			</button>
			<button type="button" class="dp-carousel__nav dp-carousel__nav--next" data-dp-next hidden>
				<?php echo wp_kses( $dp_chevron( 'next' ), $dp_svg_kses ); ?>
				<span class="dp-sr-only"><?php esc_html_e( 'More doctors', 'doctorpilot' ); ?></span>
			</button>
		<?php endif; ?>

		<?php if ( ! empty( $data['dots'] ) || ! empty( $data['autoplay'] ) ) : ?>
			<div class="dp-carousel__controls" hidden>
				<?php if ( ! empty( $data['autoplay'] ) ) : ?>
					<?php /* WCAG 2.2.2: content that moves for more than five
					         seconds needs a mechanism to stop it, and hover or
					         focus alone does not reach a visitor using touch. */ ?>
					<button type="button" class="dp-carousel__play" data-dp-play aria-pressed="false">
						<span class="dp-carousel__play-icon" aria-hidden="true"></span>
						<span class="dp-sr-only" data-dp-play-label><?php esc_html_e( 'Pause the carousel', 'doctorpilot' ); ?></span>
					</button>
				<?php endif; ?>

				<?php if ( ! empty( $data['dots'] ) ) : ?>
					<?php /* Plain buttons are added here by the script. Not a
					         `tablist`: that role promises a screen reader a set
					         of tabpanels, roving focus and arrow-key selection,
					         none of which a position indicator has. */ ?>
					<div class="dp-carousel__dots" data-dp-dots aria-label="<?php esc_attr_e( 'Carousel pages', 'doctorpilot' ); ?>"></div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<p class="dp-sr-only" data-dp-status aria-live="polite"></p>
	</div>

<?php endif; ?>
