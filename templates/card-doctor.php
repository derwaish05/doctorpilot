<?php
/**
 * Doctor card template.
 *
 * Expects $card (array) from DoctorView::card(). When rendered by the Doctor
 * Grid / List widget, $data also carries presentation flags; each is read
 * defensively so the same template still renders from the shortcode and from
 * a theme override that passes nothing but $card.
 *
 * Every metadata row is emitted only when it has a value, so the card never
 * shows an orphan icon, an empty label, or a blank line.
 *
 * Override by copying to your theme as doctorpilot/card-doctor.php.
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

$dp_classes    = isset( $data['card_classes'] ) ? (string) $data['card_classes'] : 'dp-card';
$dp_icons      = ! isset( $data['meta_icons'] ) || (bool) $data['meta_icons'];
$dp_linked     = isset( $data['card_linked'] ) && (bool) $data['card_linked'];
$dp_clamp      = isset( $data['clamp'] ) ? (int) $data['clamp'] : 0;
$dp_badge_avail = isset( $data['badge_avail'] ) && (bool) $data['badge_avail'];

/**
 * Per-part visibility, from the widget's "Card elements" switchers.
 *
 * An unknown part resolves to visible, so a shortcode, an archive template or a
 * theme override that passes no flags still renders the complete card. A hidden
 * part is not emitted at all rather than hidden with CSS: `display: none` text
 * is still read aloud by a screen reader and still indexed, so it would only be
 * hidden for a sighted visitor.
 */
$dp_parts = isset( $data['parts'] ) && is_array( $data['parts'] ) ? $data['parts'] : array();
$dp_show  = static function ( string $dp_part ) use ( $dp_parts ): bool {
	return ! isset( $dp_parts[ $dp_part ] ) || (bool) $dp_parts[ $dp_part ];
};

// Presets that lay the body over the photo need a fallback when there is no
// photo to lay it over. The JavaScript renderer appends the same class.
if ( ! $dp_show( 'image' ) ) {
	$dp_classes .= ' dp-card--no-media';
}

$dp_name       = (string) ( $card['name'] ?? '' );
$dp_url        = (string) ( $card['url'] ?? '' );
$dp_headline   = trim( (string) ( $card['headline'] ?? '' ) );
$dp_specialty  = isset( $card['primary_specialty']['name'] ) ? (string) $card['primary_specialty']['name'] : '';
$dp_department = isset( $card['primary_department']['name'] ) ? (string) $card['primary_department']['name'] : '';
$dp_years      = (int) ( $card['years_experience'] ?? 0 );

$dp_languages = array();
foreach ( (array) ( $card['languages'] ?? array() ) as $dp_lang ) {
	if ( isset( $dp_lang['name'] ) && '' !== (string) $dp_lang['name'] ) {
		$dp_languages[] = (string) $dp_lang['name'];
	}
}

/**
 * Inline metadata icons.
 *
 * Kept inline rather than as an icon-font dependency so a card carries no
 * extra request and inherits `currentColor` from the metadata token.
 */
$dp_icon = static function ( string $name ) use ( $dp_icons ): string {
	if ( ! $dp_icons ) {
		return '';
	}

	$paths = array(
		'experience' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'language'   => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18"/>',
		'check'      => '<path d="M20 6 9 17l-5-5"/>',
	);

	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	return '<span class="dp-meta__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false">' . $paths[ $name ] . '</svg></span>';
};

// Initials fallback. Built from the display name with any honorific removed,
// so "Dr. Aisha Rahman" reads as AR rather than DA.
$dp_initials = '';
if ( '' === (string) ( $card['photo']['url'] ?? '' ) ) {
	$dp_bare  = (string) preg_replace( '/^\s*(dr\.?|prof\.?|mr\.?|mrs\.?|ms\.?)\s+/i', '', $dp_name );
	$dp_words = preg_split( '/\s+/', trim( $dp_bare ) ) ?: array();

	foreach ( $dp_words as $dp_word ) {
		if ( '' !== $dp_word && function_exists( 'mb_substr' ) ) {
			$dp_initials .= mb_substr( $dp_word, 0, 1 );
		} elseif ( '' !== $dp_word ) {
			$dp_initials .= substr( $dp_word, 0, 1 );
		}

		if ( 2 <= strlen( $dp_initials ) ) {
			break;
		}
	}
}
?>
<article class="<?php echo esc_attr( $dp_classes ); ?>">
	<?php if ( $dp_show( 'image' ) ) : ?>
	<div class="dp-card__media">
		<?php if ( ! empty( $card['photo']['url'] ) ) : ?>
			<img
				src="<?php echo esc_url( (string) $card['photo']['url'] ); ?>"
				alt="<?php echo esc_attr( '' !== (string) ( $card['photo']['alt'] ?? '' ) ? (string) $card['photo']['alt'] : $dp_name ); ?>"
				<?php if ( ! empty( $card['photo']['width'] ) && ! empty( $card['photo']['height'] ) ) : ?>
					width="<?php echo esc_attr( (string) $card['photo']['width'] ); ?>"
					height="<?php echo esc_attr( (string) $card['photo']['height'] ); ?>"
				<?php endif; ?>
				loading="lazy"
				decoding="async"
			/>
		<?php else : ?>
			<span class="dp-card__placeholder" aria-hidden="true">
				<?php if ( '' !== $dp_initials ) : ?>
					<span class="dp-card__initials"><?php echo esc_html( $dp_initials ); ?></span>
				<?php endif; ?>
			</span>
		<?php endif; ?>

		<?php if ( $dp_show( 'badge_featured' ) && ! empty( $card['is_featured'] ) ) : ?>
			<div class="dp-card__badges">
				<span class="dp-badge dp-badge--featured"><?php esc_html_e( 'Featured', 'doctorpilot' ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $dp_show( 'badge_international' ) && ! empty( $card['is_international'] ) ) : ?>
			<div class="dp-card__badges dp-card__badges--end">
				<span class="dp-badge dp-badge--international"><?php esc_html_e( 'International', 'doctorpilot' ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $dp_badge_avail && ! empty( $card['accepting_new_patients'] ) ) : ?>
			<div class="dp-card__badges dp-card__badges--bottom">
				<span class="dp-badge dp-badge--availability"><?php esc_html_e( 'Accepting new patients', 'doctorpilot' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="dp-card__body">
		<?php if ( $dp_linked && '' !== $dp_url ) : ?>
			<?php /* Cover link. aria-hidden + tabindex="-1" keeps it out of the
			         keyboard path, because the visible name link below already
			         goes to the same place — two stops to one destination is a
			         screen-reader annoyance, not an affordance. */ ?>
			<a class="dp-card__cover" href="<?php echo esc_url( $dp_url ); ?>" aria-hidden="true" tabindex="-1"></a>
		<?php endif; ?>

		<h3 class="dp-card__name">
			<?php if ( '' !== $dp_url ) : ?>
				<a href="<?php echo esc_url( $dp_url ); ?>"><?php echo esc_html( $dp_name ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $dp_name ); ?>
			<?php endif; ?>
		</h3>

		<?php if ( $dp_show( 'credentials' ) && '' !== (string) ( $card['credentials'] ?? '' ) ) : ?>
			<p class="dp-card__credentials"><?php echo esc_html( (string) $card['credentials'] ); ?></p>
		<?php endif; ?>

		<?php if ( $dp_show( 'specialty' ) && '' !== $dp_specialty ) : ?>
			<p class="dp-card__specialty"><?php echo esc_html( $dp_specialty ); ?></p>
		<?php elseif ( $dp_show( 'specialty' ) && '' !== $dp_department ) : ?>
			<p class="dp-card__specialty"><?php echo esc_html( $dp_department ); ?></p>
		<?php endif; ?>

		<?php if ( $dp_show( 'bio' ) && '' !== $dp_headline ) : ?>
			<p class="dp-card__headline<?php echo $dp_clamp > 0 ? ' dp-clamp' : ''; ?>">
				<?php echo esc_html( $dp_headline ); ?>
			</p>
		<?php endif; ?>

		<?php
		// Built first so an entirely empty list is never printed at all.
		$dp_meta = array();

		// Held in a variable rather than re-tested below, because the
		// `dp-accepting` modifier is applied by position: it belongs to the last
		// row only while the availability row is the row that was appended last.
		$dp_accepting = $dp_show( 'accepting' ) && ! empty( $card['accepting_new_patients'] );

		if ( $dp_show( 'experience' ) && $dp_years > 0 ) {
			$dp_meta[] = $dp_icon( 'experience' ) . '<span class="dp-meta__value">' . esc_html(
				sprintf(
					/* translators: %d: years of experience. */
					_n( '%d year experience', '%d years experience', $dp_years, 'doctorpilot' ),
					$dp_years
				)
			) . '</span>';
		}

		if ( $dp_show( 'languages' ) && array() !== $dp_languages ) {
			$dp_meta[] = $dp_icon( 'language' ) . '<span class="dp-meta__value">' . esc_html( implode( ', ', $dp_languages ) ) . '</span>';
		}

		if ( $dp_accepting ) {
			$dp_meta[] = $dp_icon( 'check' ) . '<span class="dp-meta__value">' . esc_html__( 'Accepting new patients', 'doctorpilot' ) . '</span>';
		}
		?>

		<?php if ( array() !== $dp_meta ) : ?>
			<ul class="dp-card__meta">
				<?php foreach ( $dp_meta as $dp_index => $dp_row ) : ?>
					<li<?php echo ( $dp_accepting && count( $dp_meta ) - 1 === $dp_index ) ? ' class="dp-accepting"' : ''; ?>>
						<?php
						echo wp_kses(
							$dp_row,
							array(
								'span' => array( 'class' => array(), 'aria-hidden' => array() ),
								'svg'  => array(
									'viewbox'      => array(),
									'fill'         => array(),
									'stroke'       => array(),
									'stroke-width' => array(),
									'stroke-linecap'  => array(),
									'stroke-linejoin' => array(),
									'focusable'    => array(),
								),
								'path'   => array( 'd' => array() ),
								'circle' => array( 'cx' => array(), 'cy' => array(), 'r' => array() ),
							)
						);
						?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php
		// Resolved before the wrapper is opened, so switching both buttons off
		// removes the actions row itself rather than leaving its divider and
		// padding behind.
		$dp_profile_btn = $dp_show( 'profile_button' ) && '' !== $dp_url;
		$dp_booking_btn = $dp_show( 'booking_button' ) && ! empty( $card['booking'] ) && ! empty( $card['booking']['url'] );
		?>

		<?php if ( $dp_profile_btn || $dp_booking_btn ) : ?>
		<div class="dp-card__actions">
			<?php if ( $dp_profile_btn ) : ?>
				<a class="dp-btn dp-btn--secondary" href="<?php echo esc_url( $dp_url ); ?>">
					<?php esc_html_e( 'View Profile', 'doctorpilot' ); ?>
					<span class="dp-sr-only"><?php echo esc_html( sprintf( /* translators: %s: doctor name. */ __( 'for %s', 'doctorpilot' ), $dp_name ) ); ?></span>
				</a>
			<?php endif; ?>

			<?php if ( $dp_booking_btn ) : ?>
				<a
					class="dp-btn dp-btn--primary"
					href="<?php echo esc_url( (string) $card['booking']['url'] ); ?>"
					<?php echo '_blank' === (string) ( $card['booking']['target'] ?? '' ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
				>
					<?php echo esc_html( (string) $card['booking']['label'] ); ?>
					<span class="dp-sr-only"><?php echo esc_html( sprintf( /* translators: %s: doctor name. */ __( 'with %s', 'doctorpilot' ), $dp_name ) ); ?></span>
				</a>
			<?php endif; ?>
		</div>
		<?php endif; ?>
	</div>
</article>
