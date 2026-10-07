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
 * Override by copying to your theme as doctorpilot/card-doctor.php. Note that a
 * copy taken from an older release keeps rendering on page load but not after a
 * filter or search change: `assets/js/directory.js` rebuilds cards from this
 * version's rules, so an override has to track the parts this file reads from
 * `$data['parts']`.
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

/**
 * Opt-in parts, which default the other way: absent means off.
 *
 * The department line is the only one so far. It has to be opt-in because the
 * card has always printed a single line here — the specialty, or the department
 * standing in for it — so defaulting the new line to visible would add a row to
 * every already-built page on upgrade.
 */
$dp_opt = static function ( string $dp_part ) use ( $dp_parts ): bool {
	return isset( $dp_parts[ $dp_part ] ) && (bool) $dp_parts[ $dp_part ];
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

/*
 * Specialty and department are two lines, resolved together because the
 * department can supply either one and must never supply both.
 * `Directory.prototype.cardLines` in `assets/js/directory.js` resolves the same
 * three flags the same way; change one and you must change the other.
 *
 * The department line carries `dp-card__specialty` as well as its own class so
 * it inherits the widget's existing specialty typography rather than falling
 * back to unstyled body copy.
 */
$dp_department_line = $dp_opt( 'department' ) ? $dp_department : '';

$dp_specialty_line = '';
if ( $dp_show( 'specialty' ) ) {
	$dp_specialty_line = $dp_specialty;

	// A stand-in only, so it is dropped the moment the department has a line of
	// its own — the same name twice running reads as a bug.
	if ( '' === $dp_specialty_line && '' === $dp_department_line && $dp_show( 'specialty_fallback' ) ) {
		$dp_specialty_line = $dp_department;
	}
}

// Emitted from one loop rather than two conditionals so that a card showing a
// single line prints byte-for-byte what it printed before the department line
// existed. Two adjacent `if` blocks would leave an extra run of indentation
// between them, which is invisible on the page but changes the response an
// upstream cache is holding an ETag for.
$dp_specialty_rows = array();

if ( '' !== $dp_specialty_line ) {
	$dp_specialty_rows[] = array(
		'class' => 'dp-card__specialty',
		'text'  => $dp_specialty_line,
	);
}

if ( '' !== $dp_department_line ) {
	$dp_specialty_rows[] = array(
		'class' => 'dp-card__specialty dp-card__department',
		'text'  => $dp_department_line,
	);
}

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

		<?php foreach ( $dp_specialty_rows as $dp_row ) : ?>
			<p class="<?php echo esc_attr( $dp_row['class'] ); ?>"><?php echo esc_html( $dp_row['text'] ); ?></p>
		<?php endforeach; ?>

		<?php if ( $dp_show( 'bio' ) && '' !== $dp_headline ) : ?>
			<p class="dp-card__headline<?php echo $dp_clamp > 0 ? ' dp-clamp' : ''; ?>">
				<?php echo esc_html( $dp_headline ); ?>
			</p>
		<?php endif; ?>

		<?php
		// Built first so an entirely empty list is never printed at all.
		$dp_meta = array();

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
		?>

		<?php if ( array() !== $dp_meta ) : ?>
			<ul class="dp-card__meta">
				<?php foreach ( $dp_meta as $dp_row ) : ?>
					<li>
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

		// The widget's Booking button link, when set, replaces every doctor's own
		// destination — and gives a doctor with no booking method a button too.
		// The JavaScript renderer reads the same value from `data-booking-url`
		// and falls back to the same label.
		$dp_book_link  = isset( $data['booking_link'] ) && is_array( $data['booking_link'] ) ? $data['booking_link'] : array();
		$dp_book_over  = (string) ( $dp_book_link['url'] ?? '' );
		$dp_book_url   = '' !== $dp_book_over ? $dp_book_over : (string) ( $card['booking']['url'] ?? '' );
		$dp_book_label = '' !== (string) ( $card['booking']['label'] ?? '' ) ? (string) $card['booking']['label'] : __( 'Book an Appointment', 'doctorpilot' );
		$dp_book_attrs = '' !== $dp_book_over
			? (string) ( $dp_book_link['attrs'] ?? '' )
			: ( '_blank' === (string) ( $card['booking']['target'] ?? '' ) ? ' target="_blank" rel="noopener noreferrer"' : '' );

		$dp_booking_btn = $dp_show( 'booking_button' ) && '' !== $dp_book_url;
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
					href="<?php echo esc_url( $dp_book_url ); ?>"
					<?php echo $dp_book_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant, safe attribute string built above. ?>
				>
					<?php echo esc_html( $dp_book_label ); ?>
					<span class="dp-sr-only"><?php echo esc_html( sprintf( /* translators: %s: doctor name. */ __( 'with %s', 'doctorpilot' ), $dp_name ) ); ?></span>
				</a>
			<?php endif; ?>
		</div>
		<?php endif; ?>
	</div>
</article>
