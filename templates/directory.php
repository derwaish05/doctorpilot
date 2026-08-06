<?php
/**
 * Doctor directory template: optional search bar, filters, A–Z index, results.
 *
 * Expects $data with keys: settings, request, result, cards, layout, search_on,
 * az_on, modes, filters, facets, sort_labels, mode_labels, filter_labels,
 * default_sort, uid, rest_base, per_page, is_editor.
 *
 * Override by copying to your theme as doctorpilot/directory.php.
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
$dp_request  = $data['request'];
$dp_result   = $data['result'];
$dp_settings = (array) $data['settings'];
$dp_uid      = (string) $data['uid'];
$dp_layout   = (string) $data['layout'];
$dp_filters  = (array) $data['filters'];
$dp_facets   = (array) $data['facets'];
$dp_az       = (bool) $data['az_on'];
$dp_search   = (bool) $data['search_on'];

$dp_collapsible = 'yes' === ( $dp_settings['filters_collapsible'] ?? 'yes' ) && array() !== $dp_filters;
$dp_columns     = max( 1, min( 4, (int) ( $dp_settings['columns'] ?? 3 ) ) );

// Current taxonomy selections keyed by public param name.
$dp_param_tax = \BrainStudioz\DoctorPilot\Domain\Search\SearchRequestFactory::filter_taxonomies();
$dp_selected  = array();
foreach ( $dp_request->taxonomy_filters() as $dp_tax => $dp_slugs ) {
	$dp_param = array_search( $dp_tax, $dp_param_tax, true );
	if ( is_string( $dp_param ) ) {
		$dp_selected[ $dp_param ] = $dp_slugs;
	}
}

// Count of filters the visitor has actually applied (drives the button badge).
$dp_active = count( (array) ( $dp_result['active_filters'] ?? array() ) );

/**
 * Group cards by the first letter of the name, ignoring an honorific prefix.
 *
 * @param array<int, array<string, mixed>> $cards Card view models.
 * @return array<string, array<int, array<string, mixed>>>
 */
$dp_group_by_letter = static function ( array $cards ): array {
	$groups = array();

	foreach ( $cards as $card ) {
		$name   = (string) ( $card['name'] ?? '' );
		$name   = (string) preg_replace( '/^\s*(dr|prof|mr|mrs|ms)\.?\s+/i', '', $name );
		$letter = '#';
		if ( '' !== $name ) {
			$letter = function_exists( 'mb_substr' )
				? mb_strtoupper( mb_substr( $name, 0, 1 ) )
				: strtoupper( substr( $name, 0, 1 ) );
		}
		if ( ! preg_match( '/^[A-Z]$/', $letter ) ) {
			$letter = '#';
		}
		$groups[ $letter ][] = $card;
	}

	uksort(
		$groups,
		static function ( string $a, string $b ): int {
			if ( '#' === $a ) {
				return '#' === $b ? 0 : -1;
			}
			if ( '#' === $b ) {
				return 1;
			}

			return strcmp( $a, $b );
		}
	);

	return $groups;
};

$dp_groups  = $dp_az ? $dp_group_by_letter( (array) $data['cards'] ) : array();
$dp_letters = array_merge( array( '#' ), range( 'A', 'Z' ) );
?>
<div
	class="dp-dir dp-dir--<?php echo esc_attr( $dp_layout ); ?>"
	id="<?php echo esc_attr( $dp_uid ); ?>"
	data-dp-directory
	data-rest="<?php echo esc_url( (string) $data['rest_base'] ); ?>"
	data-layout="<?php echo esc_attr( $dp_layout ); ?>"
	data-columns="<?php echo esc_attr( (string) $dp_columns ); ?>"
	data-per-page="<?php echo esc_attr( (string) $data['per_page'] ); ?>"
	data-default-sort="<?php echo esc_attr( (string) $data['default_sort'] ); ?>"
	data-az="<?php echo $dp_az ? '1' : '0'; ?>"
>

	<?php if ( $dp_search || array() !== $dp_filters ) : ?>
		<form class="dp-dir__form" method="get" role="search" aria-label="<?php esc_attr_e( 'Find a doctor', 'doctorpilot' ); ?>">

			<div class="dp-dir__bar">
				<?php if ( $dp_search ) : ?>
					<div class="dp-dir__search">
						<label class="screen-reader-text" for="<?php echo esc_attr( $dp_uid ); ?>-q">
							<?php esc_html_e( 'Search doctors', 'doctorpilot' ); ?>
						</label>
						<input
							class="dp-dir__input"
							type="search"
							id="<?php echo esc_attr( $dp_uid ); ?>-q"
							name="q"
							value="<?php echo esc_attr( $dp_request->query() ); ?>"
							placeholder="<?php echo esc_attr( (string) ( $dp_settings['search_placeholder'] ?? __( 'Type your Search', 'doctorpilot' ) ) ); ?>"
							autocomplete="off"
							aria-controls="<?php echo esc_attr( $dp_uid ); ?>-results"
						/>
						<button type="submit" class="dp-dir__submit">
							<span class="screen-reader-text"><?php esc_html_e( 'Search', 'doctorpilot' ); ?></span>
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
								<circle cx="11" cy="11" r="7"></circle>
								<line x1="16.5" y1="16.5" x2="21" y2="21"></line>
							</svg>
						</button>
					</div>
				<?php endif; ?>

				<?php if ( $dp_search && array() !== (array) $data['modes'] ) : ?>
					<div class="dp-dir__modes" role="group" aria-label="<?php esc_attr_e( 'Search within', 'doctorpilot' ); ?>">
						<?php foreach ( (array) $data['modes'] as $dp_mode ) : ?>
							<?php $dp_mode_id = $dp_uid . '-mode-' . $dp_mode; ?>
							<input
								class="dp-dir__mode-input screen-reader-text"
								type="radio"
								name="mode"
								id="<?php echo esc_attr( $dp_mode_id ); ?>"
								value="<?php echo esc_attr( (string) $dp_mode ); ?>"
								<?php checked( $dp_request->mode(), $dp_mode ); ?>
							/>
							<label class="dp-dir__mode" for="<?php echo esc_attr( $dp_mode_id ); ?>">
								<?php echo esc_html( (string) ( $data['mode_labels'][ $dp_mode ] ?? $dp_mode ) ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="dp-dir__bar-actions">
					<?php if ( $dp_collapsible ) : ?>
						<button
							type="button"
							class="dp-dir__more"
							aria-expanded="false"
							aria-controls="<?php echo esc_attr( $dp_uid ); ?>-filters"
						>
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
								<line x1="3" y1="7" x2="21" y2="7"></line>
								<line x1="3" y1="12" x2="21" y2="12"></line>
								<line x1="3" y1="17" x2="21" y2="17"></line>
								<circle cx="9" cy="7" r="2" fill="currentColor"></circle>
								<circle cx="15" cy="12" r="2" fill="currentColor"></circle>
								<circle cx="8" cy="17" r="2" fill="currentColor"></circle>
							</svg>
							<span class="dp-dir__more-label">
								<?php echo esc_html( (string) ( $dp_settings['filters_label'] ?? __( 'More Filters', 'doctorpilot' ) ) ); ?>
							</span>
							<span class="dp-dir__more-count" data-dp-active-count <?php echo 0 === $dp_active ? 'hidden' : ''; ?>><?php echo esc_html( (string) $dp_active ); ?></span>
							<span class="dp-dir__chevron" aria-hidden="true"></span>
						</button>
					<?php endif; ?>

					<?php if ( 'yes' === ( $dp_settings['clear_show'] ?? 'yes' ) ) : ?>
						<button type="button" class="dp-dir__clear" data-dp-clear <?php echo 0 === $dp_active && ! $dp_request->has_query() ? 'hidden' : ''; ?>>
							<span aria-hidden="true">&times;</span>
							<?php esc_html_e( 'Clear', 'doctorpilot' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( array() !== $dp_filters ) : ?>
				<div
					class="dp-dir__filters<?php echo $dp_collapsible ? ' dp-dir__filters--collapsible' : ''; ?>"
					id="<?php echo esc_attr( $dp_uid ); ?>-filters"
					<?php echo $dp_collapsible ? 'data-dp-collapsible' : ''; ?>
				>
					<?php foreach ( $dp_filters as $dp_filter ) : ?>
						<?php
						$dp_label   = (string) ( $data['filter_labels'][ $dp_filter ] ?? $dp_filter );
						$dp_field_id = $dp_uid . '-f-' . $dp_filter;

						if ( 'sort' === $dp_filter ) :
							?>
							<div class="dp-dir__field">
								<label class="dp-dir__field-label" for="<?php echo esc_attr( $dp_field_id ); ?>"><?php echo esc_html( $dp_label ); ?></label>
								<select class="dp-dir__select" id="<?php echo esc_attr( $dp_field_id ); ?>" name="sort">
									<?php foreach ( (array) $data['sort_labels'] as $dp_value => $dp_sort_label ) : ?>
										<option value="<?php echo esc_attr( (string) $dp_value ); ?>" <?php selected( $dp_request->sort(), $dp_value ); ?>>
											<?php echo esc_html( (string) $dp_sort_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<?php
						elseif ( 'gender' === $dp_filter ) :
							?>
							<div class="dp-dir__field">
								<label class="dp-dir__field-label" for="<?php echo esc_attr( $dp_field_id ); ?>"><?php echo esc_html( $dp_label ); ?></label>
								<select class="dp-dir__select" id="<?php echo esc_attr( $dp_field_id ); ?>" name="gender">
									<option value=""><?php esc_html_e( 'All', 'doctorpilot' ); ?></option>
									<?php
									$dp_genders = array(
										'female' => __( 'Female', 'doctorpilot' ),
										'male'   => __( 'Male', 'doctorpilot' ),
										'other'  => __( 'Other', 'doctorpilot' ),
									);
									foreach ( $dp_genders as $dp_value => $dp_gender_label ) :
										?>
										<option value="<?php echo esc_attr( $dp_value ); ?>" <?php selected( $dp_request->gender(), $dp_value ); ?>>
											<?php echo esc_html( $dp_gender_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<?php
						elseif ( in_array( $dp_filter, array( 'accepting_new', 'online' ), true ) ) :
							$dp_checked = 'accepting_new' === $dp_filter ? $dp_request->accepting_new() : $dp_request->online();
							?>
							<div class="dp-dir__field dp-dir__field--toggle">
								<label class="dp-dir__toggle" for="<?php echo esc_attr( $dp_field_id ); ?>">
									<input
										type="checkbox"
										id="<?php echo esc_attr( $dp_field_id ); ?>"
										name="<?php echo esc_attr( $dp_filter ); ?>"
										value="1"
										<?php checked( true === $dp_checked ); ?>
									/>
									<span><?php echo esc_html( $dp_label ); ?></span>
								</label>
							</div>
							<?php
						elseif ( isset( $dp_facets[ $dp_filter ] ) && array() !== $dp_facets[ $dp_filter ] ) :
							$dp_current = isset( $dp_selected[ $dp_filter ][0] ) ? (string) $dp_selected[ $dp_filter ][0] : '';
							?>
							<div class="dp-dir__field">
								<label class="dp-dir__field-label" for="<?php echo esc_attr( $dp_field_id ); ?>"><?php echo esc_html( $dp_label ); ?></label>
								<select class="dp-dir__select" id="<?php echo esc_attr( $dp_field_id ); ?>" name="<?php echo esc_attr( $dp_filter ); ?>">
									<option value=""><?php esc_html_e( 'All', 'doctorpilot' ); ?></option>
									<?php foreach ( (array) $dp_facets[ $dp_filter ] as $dp_option ) : ?>
										<option value="<?php echo esc_attr( (string) $dp_option['slug'] ); ?>" <?php selected( $dp_current, (string) $dp_option['slug'] ); ?>>
											<?php echo esc_html( (string) $dp_option['label'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<?php
						elseif ( (bool) $data['is_editor'] ) :
							?>
							<p class="dp-dir__notice">
								<?php
								printf(
									/* translators: %s: filter name. */
									esc_html__( 'The %s filter is hidden: no published doctors use those terms yet, or the filter is disabled in DoctorPilot settings.', 'doctorpilot' ),
									esc_html( $dp_label )
								);
								?>
							</p>
							<?php
						endif;
						?>
					<?php endforeach; ?>

					<noscript>
						<button type="submit" class="dp-btn dp-btn--primary"><?php esc_html_e( 'Apply filters', 'doctorpilot' ); ?></button>
					</noscript>
				</div>
			<?php endif; ?>
		</form>
	<?php endif; ?>

	<?php if ( $dp_az ) : ?>
		<nav class="dp-dir__az" aria-label="<?php esc_attr_e( 'Filter by first letter', 'doctorpilot' ); ?>">
			<?php foreach ( $dp_letters as $dp_letter ) : ?>
				<?php $dp_has = isset( $dp_groups[ $dp_letter ] ); ?>
				<a
					class="dp-dir__az-item<?php echo $dp_has ? '' : ' is-empty'; ?>"
					href="#<?php echo esc_attr( $dp_uid . '-az-' . ( '#' === $dp_letter ? 'num' : $dp_letter ) ); ?>"
					data-dp-letter="<?php echo esc_attr( $dp_letter ); ?>"
					<?php echo $dp_has ? '' : 'aria-disabled="true"'; ?>
				><?php echo esc_html( $dp_letter ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<?php if ( 'yes' === ( $dp_settings['count_show'] ?? 'yes' ) ) : ?>
		<p class="dp-dir__count" data-dp-count role="status" aria-live="polite">
			<?php
			/* translators: %d: number of matching doctors. */
			echo esc_html( sprintf( _n( '%d doctor found', '%d doctors found', (int) $dp_result['total'], 'doctorpilot' ), (int) $dp_result['total'] ) );
			?>
		</p>
	<?php endif; ?>

	<div class="dp-dir__results" id="<?php echo esc_attr( $dp_uid ); ?>-results" data-dp-results aria-busy="false">
		<?php if ( array() === (array) $data['cards'] ) : ?>
			<p class="dp-empty"><?php echo esc_html( (string) ( $dp_settings['empty_text'] ?? __( 'No doctors matched your search.', 'doctorpilot' ) ) ); ?></p>

		<?php elseif ( $dp_az ) : ?>
			<?php foreach ( $dp_groups as $dp_letter => $dp_letter_cards ) : ?>
				<section
					class="dp-dir__group"
					id="<?php echo esc_attr( $dp_uid . '-az-' . ( '#' === $dp_letter ? 'num' : $dp_letter ) ); ?>"
					data-dp-group="<?php echo esc_attr( (string) $dp_letter ); ?>"
				>
					<h3 class="dp-dir__group-letter"><?php echo esc_html( (string) $dp_letter ); ?></h3>
					<div class="dp-dir__rows dp-cols-<?php echo esc_attr( (string) $dp_columns ); ?>">
						<?php
						foreach ( $dp_letter_cards as $card ) {
							include \BrainStudioz\DoctorPilot\Frontend\Templates::locate( 'row-doctor.php' );
						}
						?>
					</div>
				</section>
			<?php endforeach; ?>

		<?php elseif ( 'list' === $dp_layout ) : ?>
			<div class="dp-dir__rows dp-cols-<?php echo esc_attr( (string) $dp_columns ); ?>">
				<?php
				foreach ( (array) $data['cards'] as $card ) {
					include \BrainStudioz\DoctorPilot\Frontend\Templates::locate( 'row-doctor.php' );
				}
				?>
			</div>

		<?php else : ?>
			<div class="dp-directory dp-cols-<?php echo esc_attr( (string) $dp_columns ); ?>">
				<?php
				foreach ( (array) $data['cards'] as $card ) {
					include \BrainStudioz\DoctorPilot\Frontend\Templates::locate( 'card-doctor.php' );
				}
				?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $dp_result['truncated'] ) ) : ?>
			<p class="dp-dir__notice">
				<?php
				printf(
					/* translators: 1: number listed, 2: total matches. */
					esc_html__( 'Showing the first %1$d of %2$d doctors. Narrow the search to see the rest.', 'doctorpilot' ),
					count( (array) $data['cards'] ),
					(int) $dp_result['total']
				);
				?>
			</p>
		<?php endif; ?>

		<?php
		if ( ! $dp_az
			&& 'yes' === ( $dp_settings['pagination'] ?? 'yes' )
			&& (int) $dp_result['total_pages'] > 1 ) {
			echo '<nav class="dp-dir__pagination" aria-label="' . esc_attr__( 'Doctor results pages', 'doctorpilot' ) . '">';
			echo wp_kses_post(
				(string) paginate_links(
					array(
						'total'   => (int) $dp_result['total_pages'],
						'current' => (int) $dp_result['page'],
					)
				)
			);
			echo '</nav>';
		}
		?>
	</div>
</div>
