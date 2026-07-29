<?php
/**
 * Interactive search template.
 *
 * Expects $data with keys: request (SearchRequest), result, cards, facets, settings.
 * Override by copying to your theme as doctorpilot/search.php.
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

/** @var \BrainStudioz\DoctorPilot\Domain\Search\SearchRequest $dp_request */
$dp_request = $data['request'];
$dp_result  = $data['result'];
$dp_facets  = $data['facets'];

$dp_param_tax = \BrainStudioz\DoctorPilot\Domain\Search\SearchRequestFactory::filter_taxonomies();
$dp_selected  = array();
foreach ( $dp_request->taxonomy_filters() as $dp_tax => $dp_slugs ) {
	$dp_param            = array_search( $dp_tax, $dp_param_tax, true );
	$dp_selected[ $dp_param ] = $dp_slugs;
}

$dp_sorts = array(
	'relevance'  => __( 'Relevance', 'doctorpilot' ),
	'name'       => __( 'Name', 'doctorpilot' ),
	'featured'   => __( 'Featured', 'doctorpilot' ),
	'experience' => __( 'Most experienced', 'doctorpilot' ),
	'recent'     => __( 'Recently added', 'doctorpilot' ),
);
?>
<div class="dp-search" data-dp-search>

	<form class="dp-search__form" method="get" role="search" aria-label="<?php esc_attr_e( 'Find a doctor', 'doctorpilot' ); ?>">

		<div class="dp-search__bar">
			<label class="screen-reader-text" for="dp-q"><?php esc_html_e( 'Search doctors', 'doctorpilot' ); ?></label>
			<input
				type="search"
				id="dp-q"
				name="q"
				value="<?php echo esc_attr( $dp_request->query() ); ?>"
				placeholder="<?php esc_attr_e( 'Search by name, department, or symptom', 'doctorpilot' ); ?>"
				autocomplete="off"
				aria-controls="dp-results"
			/>
			<button type="submit" class="dp-btn dp-btn--primary"><?php esc_html_e( 'Search', 'doctorpilot' ); ?></button>
		</div>

		<div class="dp-search__layout">
			<aside class="dp-filters" aria-label="<?php esc_attr_e( 'Filters', 'doctorpilot' ); ?>">
				<?php foreach ( $dp_facets as $dp_param => $dp_options ) : ?>
					<fieldset class="dp-filter-group">
						<legend><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $dp_param ) ) ); ?></legend>
						<?php foreach ( $dp_options as $dp_option ) : ?>
							<label class="dp-filter">
								<input
									type="checkbox"
									name="<?php echo esc_attr( (string) $dp_param ); ?>[]"
									value="<?php echo esc_attr( (string) $dp_option['slug'] ); ?>"
									<?php checked( isset( $dp_selected[ $dp_param ] ) && in_array( (string) $dp_option['slug'], (array) $dp_selected[ $dp_param ], true ) ); ?>
								/>
								<?php echo esc_html( (string) $dp_option['label'] ); ?>
								<span class="dp-filter__count">(<?php echo (int) $dp_option['count']; ?>)</span>
							</label>
						<?php endforeach; ?>
					</fieldset>
				<?php endforeach; ?>

				<fieldset class="dp-filter-group">
					<legend><?php esc_html_e( 'Options', 'doctorpilot' ); ?></legend>
					<label class="dp-filter">
						<input type="checkbox" name="accepting_new" value="1" <?php checked( true === $dp_request->accepting_new() ); ?> />
						<?php esc_html_e( 'Accepting new patients', 'doctorpilot' ); ?>
					</label>
					<label class="dp-filter">
						<input type="checkbox" name="online" value="1" <?php checked( true === $dp_request->online() ); ?> />
						<?php esc_html_e( 'Online consultation', 'doctorpilot' ); ?>
					</label>
				</fieldset>

				<noscript><button type="submit" class="dp-btn dp-btn--secondary"><?php esc_html_e( 'Apply filters', 'doctorpilot' ); ?></button></noscript>
			</aside>

			<div class="dp-search__results-wrap">
				<div class="dp-search__toolbar">
					<p class="dp-count" id="dp-count" role="status" aria-live="polite">
						<?php
						/* translators: %d: number of matching doctors. */
						echo esc_html( sprintf( _n( '%d doctor found', '%d doctors found', (int) $dp_result['total'], 'doctorpilot' ), (int) $dp_result['total'] ) );
						?>
					</p>

					<label class="dp-sort">
						<span class="screen-reader-text"><?php esc_html_e( 'Sort by', 'doctorpilot' ); ?></span>
						<select name="sort">
							<?php foreach ( $dp_sorts as $dp_value => $dp_label ) : ?>
								<option value="<?php echo esc_attr( $dp_value ); ?>" <?php selected( $dp_request->sort(), $dp_value ); ?>><?php echo esc_html( $dp_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</div>

				<div class="dp-results" id="dp-results" aria-busy="false">
					<?php if ( array() !== (array) $data['cards'] ) : ?>
						<div class="dp-directory dp-cols-3">
							<?php
							foreach ( (array) $data['cards'] as $card ) {
								include \BrainStudioz\DoctorPilot\Frontend\Templates::locate( 'card-doctor.php' );
							}
							?>
						</div>

						<?php
						echo wp_kses_post(
							(string) paginate_links(
								array(
									'total'   => (int) $dp_result['total_pages'],
									'current' => (int) $dp_result['page'],
								)
							)
						);
						?>
					<?php else : ?>
						<p class="dp-empty"><?php esc_html_e( 'No doctors matched your search.', 'doctorpilot' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</form>
</div>
