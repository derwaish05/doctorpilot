<?php
/**
 * Doctor card template.
 *
 * Expects $card (array) from DoctorView::card().
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

$dp_specialty = isset( $card['primary_specialty']['name'] ) ? (string) $card['primary_specialty']['name'] : '';
$dp_department = isset( $card['primary_department']['name'] ) ? (string) $card['primary_department']['name'] : '';
$dp_languages = array();
foreach ( (array) ( $card['languages'] ?? array() ) as $dp_lang ) {
	if ( isset( $dp_lang['name'] ) ) {
		$dp_languages[] = (string) $dp_lang['name'];
	}
}
?>
<article class="dp-card">
	<div class="dp-card__media">
		<?php if ( ! empty( $card['photo']['url'] ) ) : ?>
			<img
				src="<?php echo esc_url( (string) $card['photo']['url'] ); ?>"
				alt="<?php echo esc_attr( '' !== (string) $card['photo']['alt'] ? (string) $card['photo']['alt'] : (string) $card['name'] ); ?>"
				loading="lazy"
			/>
		<?php else : ?>
			<span class="dp-card__placeholder" aria-hidden="true"></span>
		<?php endif; ?>
		<?php if ( ! empty( $card['is_featured'] ) ) : ?>
			<span class="dp-badge dp-badge--featured"><?php esc_html_e( 'Featured', 'doctorpilot' ); ?></span>
		<?php endif; ?>
	</div>

	<div class="dp-card__body">
		<h3 class="dp-card__name">
			<a href="<?php echo esc_url( (string) $card['url'] ); ?>"><?php echo esc_html( (string) $card['name'] ); ?></a>
		</h3>

		<?php if ( '' !== (string) $card['credentials'] ) : ?>
			<p class="dp-card__credentials"><?php echo esc_html( (string) $card['credentials'] ); ?></p>
		<?php endif; ?>

		<?php if ( '' !== $dp_specialty ) : ?>
			<p class="dp-card__specialty"><?php echo esc_html( $dp_specialty ); ?></p>
		<?php elseif ( '' !== $dp_department ) : ?>
			<p class="dp-card__specialty"><?php echo esc_html( $dp_department ); ?></p>
		<?php endif; ?>

		<ul class="dp-card__meta">
			<?php if ( (int) $card['years_experience'] > 0 ) : ?>
				<li>
					<?php
					/* translators: %d: years of experience. */
					echo esc_html( sprintf( _n( '%d year experience', '%d years experience', (int) $card['years_experience'], 'doctorpilot' ), (int) $card['years_experience'] ) );
					?>
				</li>
			<?php endif; ?>
			<?php if ( array() !== $dp_languages ) : ?>
				<li><?php echo esc_html( implode( ', ', $dp_languages ) ); ?></li>
			<?php endif; ?>
			<?php if ( ! empty( $card['accepting_new_patients'] ) ) : ?>
				<li class="dp-accepting"><?php esc_html_e( 'Accepting new patients', 'doctorpilot' ); ?></li>
			<?php endif; ?>
		</ul>

		<div class="dp-card__actions">
			<a class="dp-btn dp-btn--secondary" href="<?php echo esc_url( (string) $card['url'] ); ?>"><?php esc_html_e( 'View Profile', 'doctorpilot' ); ?></a>
			<?php if ( ! empty( $card['booking'] ) && ! empty( $card['booking']['url'] ) ) : ?>
				<a
					class="dp-btn dp-btn--primary"
					href="<?php echo esc_url( (string) $card['booking']['url'] ); ?>"
					<?php echo '_blank' === (string) $card['booking']['target'] ? 'target="_blank" rel="noopener"' : ''; ?>
				><?php echo esc_html( (string) $card['booking']['label'] ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</article>
