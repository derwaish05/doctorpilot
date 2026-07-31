<?php
/**
 * Doctor profile content (used by single template and profile shortcode).
 *
 * Expects $data (array) from DoctorView::full().
 * Override by copying to your theme as doctorpilot/profile-content.php.
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

/**
 * Render a list of terms as comma-separated text.
 *
 * @param array<int, array<string, mixed>> $terms Terms.
 * @return string
 */
$dp_term_names = static function ( array $terms ): string {
	$names = array();
	foreach ( $terms as $term ) {
		if ( isset( $term['name'] ) ) {
			$names[] = (string) $term['name'];
		}
	}

	return implode( ', ', $names );
};

$dp_specialty = isset( $data['primary_specialty']['name'] ) ? (string) $data['primary_specialty']['name'] : '';
$dp_department = isset( $data['primary_department']['name'] ) ? (string) $data['primary_department']['name'] : '';
?>
<div class="dp-profile">

	<header class="dp-profile__hero">
		<?php if ( ! empty( $data['photo']['url'] ) ) : ?>
			<div class="dp-profile__photo">
				<img src="<?php echo esc_url( (string) $data['photo']['url'] ); ?>" alt="<?php echo esc_attr( '' !== (string) $data['photo']['alt'] ? (string) $data['photo']['alt'] : (string) $data['name'] ); ?>" />
			</div>
		<?php endif; ?>

		<div class="dp-profile__intro">
			<h1 class="dp-profile__name"><?php echo esc_html( (string) $data['name'] ); ?></h1>
			<?php if ( '' !== (string) $data['credentials'] ) : ?>
				<p class="dp-profile__credentials"><?php echo esc_html( (string) $data['credentials'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $dp_specialty || '' !== $dp_department ) : ?>
				<p class="dp-profile__role"><?php echo esc_html( '' !== $dp_specialty ? $dp_specialty : $dp_department ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $data['is_international'] ) ) : ?>
				<p class="dp-profile__tags"><span class="dp-tag dp-tag--international"><?php esc_html_e( 'International doctor', 'doctorpilot' ); ?></span></p>
			<?php endif; ?>

			<?php if ( ! empty( $data['booking'] ) && ! empty( $data['booking']['url'] ) ) : ?>
				<a
					class="dp-btn dp-btn--primary"
					href="<?php echo esc_url( (string) $data['booking']['url'] ); ?>"
					<?php echo '_blank' === (string) $data['booking']['target'] ? 'target="_blank" rel="noopener"' : ''; ?>
				><?php echo esc_html( (string) $data['booking']['label'] ); ?></a>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( '' !== trim( wp_strip_all_tags( (string) $data['biography'] ) ) ) : ?>
		<section class="dp-profile__section dp-about">
			<h2><?php esc_html_e( 'About', 'doctorpilot' ); ?></h2>
			<?php echo wp_kses_post( (string) $data['biography'] ); ?>
		</section>
	<?php endif; ?>

	<?php if ( array() !== (array) $data['clinical_interests'] || array() !== (array) $data['treatments'] ) : ?>
		<section class="dp-profile__section dp-scope">
			<h2><?php esc_html_e( 'Scope of practice', 'doctorpilot' ); ?></h2>
			<?php if ( array() !== (array) $data['treatments'] ) : ?>
				<p><strong><?php esc_html_e( 'Treatments:', 'doctorpilot' ); ?></strong> <?php echo esc_html( $dp_term_names( (array) $data['treatments'] ) ); ?></p>
			<?php endif; ?>
			<?php if ( array() !== (array) $data['clinical_interests'] ) : ?>
				<ul>
					<?php foreach ( (array) $data['clinical_interests'] as $dp_interest ) : ?>
						<li><?php echo esc_html( (string) $dp_interest ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php if ( array() !== (array) $data['procedures'] ) : ?>
		<section class="dp-profile__section dp-procedures">
			<h2><?php esc_html_e( 'Procedures & treatments', 'doctorpilot' ); ?></h2>
			<ul>
				<?php foreach ( (array) $data['procedures'] as $dp_proc ) : ?>
					<li>
						<strong><?php echo esc_html( (string) ( $dp_proc['name'] ?? '' ) ); ?></strong>
						<?php if ( ! empty( $dp_proc['description'] ) ) : ?>
							<span> — <?php echo esc_html( (string) $dp_proc['description'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( array() !== (array) $data['experience'] ) : ?>
		<section class="dp-profile__section dp-experience">
			<h2><?php esc_html_e( 'Experience', 'doctorpilot' ); ?></h2>
			<ul>
				<?php foreach ( (array) $data['experience'] as $dp_exp ) : ?>
					<li>
						<?php echo esc_html( trim( (string) ( $dp_exp['primary'] ?? '' ) . ' — ' . (string) ( $dp_exp['secondary'] ?? '' ), ' —' ) ); ?>
						<?php
						$dp_range = trim( implode( '–', array_filter( array( (string) ( $dp_exp['start_year'] ?? '' ), (string) ( $dp_exp['end_year'] ?? '' ) ) ) ) );
						?>
						<?php if ( '' !== $dp_range ) : ?>
							<span>(<?php echo esc_html( $dp_range ); ?>)</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( array() !== (array) $data['education'] ) : ?>
		<section class="dp-profile__section dp-education">
			<h2><?php esc_html_e( 'Education', 'doctorpilot' ); ?></h2>
			<ul>
				<?php foreach ( (array) $data['education'] as $dp_edu ) : ?>
					<li>
						<?php echo esc_html( trim( (string) ( $dp_edu['primary'] ?? '' ) . ' — ' . (string) ( $dp_edu['secondary'] ?? '' ), ' —' ) ); ?>
						<?php
						$dp_range = trim( implode( '–', array_filter( array( (string) ( $dp_edu['start_year'] ?? '' ), (string) ( $dp_edu['end_year'] ?? '' ) ) ) ) );
						?>
						<?php if ( '' !== $dp_range ) : ?>
							<span>(<?php echo esc_html( $dp_range ); ?>)</span>
						<?php elseif ( ! empty( $dp_edu['country'] ) ) : ?>
							<span><?php echo esc_html( (string) $dp_edu['country'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( array() !== (array) $data['certifications'] ) : ?>
		<section class="dp-profile__section dp-certifications">
			<h2><?php esc_html_e( 'Certifications & memberships', 'doctorpilot' ); ?></h2>
			<ul>
				<?php foreach ( (array) $data['certifications'] as $dp_cert ) : ?>
					<li><?php echo esc_html( trim( (string) ( $dp_cert['name'] ?? '' ) . ' — ' . (string) ( $dp_cert['organization'] ?? '' ), ' —' ) ); ?></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( array() !== (array) $data['publications'] ) : ?>
		<section class="dp-profile__section dp-publications">
			<h2><?php esc_html_e( 'Publications', 'doctorpilot' ); ?></h2>
			<ul>
				<?php foreach ( (array) $data['publications'] as $dp_pub ) : ?>
					<li>
						<?php if ( ! empty( $dp_pub['url'] ) ) : ?>
							<a href="<?php echo esc_url( (string) $dp_pub['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) ( $dp_pub['title'] ?? '' ) ); ?></a>
						<?php else : ?>
							<?php echo esc_html( (string) ( $dp_pub['title'] ?? '' ) ); ?>
						<?php endif; ?>
						<?php if ( ! empty( $dp_pub['journal'] ) ) : ?>
							<em><?php echo esc_html( (string) $dp_pub['journal'] ); ?></em>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( array() !== (array) $data['faqs'] ) : ?>
		<section class="dp-profile__section dp-faqs">
			<h2><?php esc_html_e( 'Frequently asked questions', 'doctorpilot' ); ?></h2>
			<?php foreach ( (array) $data['faqs'] as $dp_faq ) : ?>
				<details>
					<summary><?php echo esc_html( (string) ( $dp_faq['question'] ?? '' ) ); ?></summary>
					<p><?php echo esc_html( (string) ( $dp_faq['answer'] ?? '' ) ); ?></p>
				</details>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>

	<?php if ( array() !== (array) $data['locations'] ) : ?>
		<section class="dp-profile__section dp-locations">
			<h2><?php esc_html_e( 'Locations & availability', 'doctorpilot' ); ?></h2>
			<?php foreach ( (array) $data['locations'] as $dp_loc ) : ?>
				<div class="dp-location">
					<?php if ( '' !== (string) ( $dp_loc['name'] ?? '' ) ) : ?>
						<h3><?php echo esc_html( (string) $dp_loc['name'] ); ?></h3>
					<?php endif; ?>
					<?php if ( '' !== (string) ( $dp_loc['room_floor'] ?? '' ) ) : ?>
						<p><?php echo esc_html( (string) $dp_loc['room_floor'] ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== (string) ( $dp_loc['phone'] ?? '' ) ) : ?>
						<p><?php esc_html_e( 'Phone:', 'doctorpilot' ); ?> <?php echo esc_html( (string) $dp_loc['phone'] ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== (string) ( $dp_loc['public_email'] ?? '' ) ) : ?>
						<p><a href="<?php echo esc_attr( 'mailto:' . antispambot( (string) $dp_loc['public_email'] ) ); ?>"><?php echo esc_html( (string) $dp_loc['public_email'] ); ?></a></p>
					<?php endif; ?>
					<?php if ( '' !== (string) ( $dp_loc['office_hours_text'] ?? '' ) ) : ?>
						<p><?php echo esc_html( (string) $dp_loc['office_hours_text'] ); ?></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>

	<p class="dp-disclaimer"><?php esc_html_e( 'This information supports finding a doctor and does not provide a medical diagnosis.', 'doctorpilot' ); ?></p>
</div>
