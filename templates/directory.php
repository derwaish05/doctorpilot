<?php
/**
 * Doctor directory template: optional search bar, browse tabs, filters, an A–Z
 * browse index for the active tab, and the doctor results below it.
 *
 * The index and the results are two separate regions that are on screen at the
 * same time. A tab decides which index is shown; picking an entry in that index
 * writes the index's own search parameter (`doctor`, `department`, `specialty`,
 * `symptom`) and narrows the results underneath. Nothing is ever swapped out,
 * so a visitor can see what they picked and what it produced together.
 *
 * Expects $data with keys: settings, request, indexes, selected, cards, result,
 * active_index, letter, query, layout, search_on, index_modes, index_params,
 * modes, filters, facets, sort_labels, mode_labels, filter_labels, default_sort,
 * uid, rest_base, lang, per_page, is_editor.
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
$dp_settings = (array) $data['settings'];
$dp_uid      = (string) $data['uid'];
$dp_layout   = (string) $data['layout'];
$dp_filters  = (array) $data['filters'];
$dp_facets   = (array) $data['facets'];
$dp_search   = (bool) $data['search_on'];
$dp_indexes  = (array) $data['indexes'];
$dp_selected = (array) $data['selected'];
$dp_active   = (string) $data['active_index'];
$dp_letter   = (string) $data['letter'];
$dp_cards    = (array) $data['cards'];
$dp_result   = (array) $data['result'];

$dp_columns    = max( 1, min( 4, (int) ( $dp_settings['columns'] ?? 3 ) ) );
$dp_empty_text = (string) ( $dp_settings['empty_text'] ?? __( 'No doctors matched your search.', 'doctorpilot' ) );

// Current taxonomy selections keyed by public param name.
$dp_param_tax = \BrainStudioz\DoctorPilot\Domain\Search\SearchRequestFactory::filter_taxonomies();
$dp_tax_sel   = array();
foreach ( $dp_request->taxonomy_filters() as $dp_tax => $dp_slugs ) {
	$dp_param = array_search( $dp_tax, $dp_param_tax, true );
	if ( is_string( $dp_param ) ) {
		$dp_tax_sel[ $dp_param ] = $dp_slugs;
	}
}

$dp_active_count = count( (array) ( $dp_result['active_filters'] ?? array() ) );
$dp_query        = (string) ( $data['query'] ?? '' );

/*
 * The letter row as value => label. The first entry's value is the empty string
 * — the value "no letter is active" already carries — so choosing it clears the
 * filter rather than selecting a twenty-seventh bucket, and it is the entry
 * highlighted when nothing is applied. Its label is translatable so a site can
 * spell it "All"; '#' is the default because every other button in the row is
 * one glyph wide.
 */
$dp_letters = array( '' => _x( '#', 'A–Z index: show every entry', 'doctorpilot' ) );
foreach ( range( 'A', 'Z' ) as $dp_alpha ) {
	$dp_letters[ (string) $dp_alpha ] = (string) $dp_alpha;
}

// A browse index owns its parameter, so the More Filters row must not render a
// second control for it: the same value settable from two places is a state the
// two controls can disagree about.
$dp_index_params = array_values( (array) $data['index_params'] );
$dp_filters      = array_values(
	array_filter(
		$dp_filters,
		static function ( $dp_filter ) use ( $dp_index_params, $dp_indexes ): bool {
			return ! ( in_array( $dp_filter, $dp_index_params, true ) && isset( $dp_indexes[ $dp_filter ] ) );
		}
	)
);

// Decided after that narrowing: an index can leave the filter row empty, and a
// More Filters button that opens nothing is worse than no button.
$dp_collapsible = 'yes' === ( $dp_settings['filters_collapsible'] ?? 'yes' ) && array() !== $dp_filters;

/**
 * Entries left visible under the active letter and free-text filters. Rows for
 * everything else are still rendered (hidden) so the client can restore them.
 *
 * @param array<int, array<string, mixed>> $items  Index items.
 * @param string                           $letter Active letter.
 * @param string                           $query  Active query.
 * @return int
 */
$dp_visible_items = static function ( array $items, string $letter, string $query ): int {
	$visible = 0;

	foreach ( $items as $item ) {
		if ( '' !== $letter && (string) ( $item['letter'] ?? '' ) !== $letter ) {
			continue;
		}
		if ( '' !== $query && false === stripos( (string) ( $item['name'] ?? '' ), $query ) ) {
			continue;
		}
		++$visible;
	}

	return $visible;
};

/**
 * Build a URL for this widget's own state links (A–Z, tabs, index entries)
 * without JavaScript.
 *
 * @param array<string, string|null> $args Query arguments to set (null removes).
 * @return string
 */
$dp_state_url = static function ( array $args ): string {
	$current = array();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only GET state.
	foreach ( (array) wp_unslash( $_GET ) as $dp_key => $dp_value ) {
		if ( is_scalar( $dp_value ) ) {
			$current[ sanitize_key( (string) $dp_key ) ] = (string) $dp_value;
		}
	}

	foreach ( $args as $dp_key => $dp_value ) {
		if ( null === $dp_value || '' === $dp_value ) {
			unset( $current[ $dp_key ] );
		} else {
			$current[ $dp_key ] = $dp_value;
		}
	}

	unset( $current['page'], $current['paged'] );

	// Under pretty permalinks the page number lives in the path. Left in place,
	// every A–Z, tab, and index link generated from page 3 would stay pinned to
	// page 3 — or 404 once the new selection has fewer pages.
	$base = (string) strtok( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ), '?' );
	$base = (string) preg_replace( '#/page/\d+/?$#', '/', $base );

	return esc_url( array() === $current ? $base : $base . '?' . http_build_query( $current ) );
};
?>
<div
	class="dp-dir dp-dir--<?php echo esc_attr( $dp_layout ); ?> dp-dir--preset-<?php echo esc_attr( (string) ( $data['preset'] ?? 'modern' ) ); ?>"
	id="<?php echo esc_attr( $dp_uid ); ?>"
	data-dp-directory
	data-rest="<?php echo esc_url( (string) $data['rest_base'] ); ?>"
	data-lang="<?php echo esc_attr( (string) ( $data['lang'] ?? '' ) ); ?>"
	data-layout="<?php echo esc_attr( $dp_layout ); ?>"
	data-columns="<?php echo esc_attr( (string) $dp_columns ); ?>"
	data-per-page="<?php echo esc_attr( (string) $data['per_page'] ); ?>"
	data-default-sort="<?php echo esc_attr( (string) $data['default_sort'] ); ?>"
	data-index-modes="<?php echo esc_attr( implode( ',', (array) $data['index_modes'] ) ); ?>"
	data-index-params="<?php echo esc_attr( (string) wp_json_encode( (array) $data['index_params'] ) ); ?>"
	data-active-index="<?php echo esc_attr( $dp_active ); ?>"
	data-letter="<?php echo esc_attr( $dp_letter ); ?>"
	data-card-classes="<?php echo esc_attr( (string) ( $data['card_classes'] ?? 'dp-card' ) ); ?>"
	data-list-style="<?php echo esc_attr( (string) ( $dp_settings['list_style'] ?? 'rows' ) ); ?>"
	<?php if ( '' !== (string) ( $data['legacy_style'] ?? '' ) ) : ?>
		style="<?php echo esc_attr( (string) $data['legacy_style'] ); ?>"
	<?php endif; ?>
	data-meta-icons="<?php echo ( ! isset( $data['meta_icons'] ) || $data['meta_icons'] ) ? '1' : '0'; ?>"
	data-clamp="<?php echo esc_attr( (string) (int) ( $data['clamp'] ?? 0 ) ); ?>"
	<?php /* Card parts switched off, and opt-in parts switched on, so cards
	         rebuilt client-side after a filter change match the ones rendered
	         here. Both are empty for a card left at its defaults. */ ?>
	data-card-hide="<?php echo esc_attr( (string) ( $data['hidden_parts'] ?? '' ) ); ?>"
	data-card-show="<?php echo esc_attr( (string) ( $data['shown_parts'] ?? '' ) ); ?>"
	<?php /* The widget's Booking button link, so cards rebuilt client-side point
	         where the ones rendered here do. All three are empty when the editor
	         set none. */ ?>
	data-booking-url="<?php echo esc_attr( (string) ( $data['booking_link']['url'] ?? '' ) ); ?>"
	data-booking-target="<?php echo esc_attr( (string) ( $data['booking_link']['target'] ?? '' ) ); ?>"
	data-booking-rel="<?php echo esc_attr( (string) ( $data['booking_link']['rel'] ?? '' ) ); ?>"
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
					<div class="dp-dir__modes" role="group" aria-label="<?php esc_attr_e( 'Browse by', 'doctorpilot' ); ?>">
						<?php foreach ( (array) $data['modes'] as $dp_mode ) : ?>
							<?php $dp_mode_id = $dp_uid . '-mode-' . $dp_mode; ?>
							<input
								class="dp-dir__mode-input"
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
							<span class="dp-dir__more-count" data-dp-active-count <?php echo 0 === $dp_active_count ? 'hidden' : ''; ?>><?php echo esc_html( (string) $dp_active_count ); ?></span>
							<span class="dp-dir__chevron" aria-hidden="true"></span>
						</button>
					<?php endif; ?>

					<?php if ( 'yes' === ( $dp_settings['clear_show'] ?? 'yes' ) ) : ?>
						<button type="button" class="dp-dir__clear" data-dp-clear <?php echo 0 === $dp_active_count && ! $dp_request->has_query() && '' === $dp_active && '' === $dp_letter ? 'hidden' : ''; ?>>
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
						$dp_label    = (string) ( $data['filter_labels'][ $dp_filter ] ?? $dp_filter );
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
						elseif ( 'international' === $dp_filter ) :
							// Tri-state, so a select rather than the checkbox the
							// other boolean filters use: "National" is a choice a
							// visitor makes, and a checkbox can only say
							// "international" or "no opinion".
							$dp_intl = $dp_request->international();
							?>
							<div class="dp-dir__field">
								<label class="dp-dir__field-label" for="<?php echo esc_attr( $dp_field_id ); ?>"><?php echo esc_html( $dp_label ); ?></label>
								<select class="dp-dir__select" id="<?php echo esc_attr( $dp_field_id ); ?>" name="international">
									<option value=""><?php esc_html_e( 'All', 'doctorpilot' ); ?></option>
									<option value="0" <?php selected( false === $dp_intl ); ?>><?php esc_html_e( 'National', 'doctorpilot' ); ?></option>
									<option value="1" <?php selected( true === $dp_intl ); ?>><?php esc_html_e( 'International', 'doctorpilot' ); ?></option>
								</select>
							</div>
							<?php
						elseif ( 'online' === $dp_filter ) :
							$dp_checked = $dp_request->online();
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
							$dp_current = isset( $dp_tax_sel[ $dp_filter ][0] ) ? (string) $dp_tax_sel[ $dp_filter ][0] : '';
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

			<?php
			// The active index writes its selection through a hidden field so it
			// travels with every other control in one form submission.
			foreach ( (array) $data['index_params'] as $dp_mode => $dp_param ) :
				if ( ! isset( $dp_indexes[ $dp_mode ] ) ) {
					continue;
				}
				?>
				<input
					type="hidden"
					name="<?php echo esc_attr( (string) $dp_param ); ?>"
					value="<?php echo esc_attr( (string) ( $dp_selected[ $dp_mode ] ?? '' ) ); ?>"
				/>
			<?php endforeach; ?>
		</form>
	<?php endif; ?>

	<?php if ( array() !== $dp_indexes ) : ?>
		<div class="dp-dir__index" data-dp-index <?php echo '' === $dp_active ? 'hidden' : ''; ?>>
			<?php foreach ( $dp_indexes as $dp_key => $dp_index ) : ?>
				<?php
				$dp_is_active = $dp_key === $dp_active;
				$dp_available = (array) ( $dp_index['letters'] ?? array() );
				$dp_shown     = $dp_visible_items( (array) $dp_index['items'], $dp_letter, $dp_query );
				$dp_pick      = (string) ( $dp_selected[ $dp_key ] ?? '' );
				$dp_param     = (string) $dp_index['param'];
				?>
				<section
					class="dp-dir__panel"
					data-dp-panel="<?php echo esc_attr( (string) $dp_key ); ?>"
					data-dp-label="<?php echo esc_attr( (string) $dp_index['label'] ); ?>"
					data-dp-label-single="<?php echo esc_attr( (string) $dp_index['single'] ); ?>"
					aria-label="<?php echo esc_attr( (string) $dp_index['label'] ); ?>"
					<?php echo $dp_is_active ? '' : 'hidden'; ?>
				>

					<?php if ( ! empty( $dp_index['az'] ) ) : ?>
						<nav class="dp-dir__az" aria-label="<?php esc_attr_e( 'Filter by first letter', 'doctorpilot' ); ?>">
							<?php foreach ( $dp_letters as $dp_index_letter => $dp_index_label ) : ?>
								<?php
								$dp_index_letter = (string) $dp_index_letter;
								// The "all" entry clears the filter rather than
								// selecting a bucket, so it is always available and
								// its link drops the parameter instead of toggling it.
								$dp_all = '' === $dp_index_letter;
								$dp_has = $dp_all || in_array( $dp_index_letter, $dp_available, true );
								?>
								<a
									class="dp-dir__az-item<?php echo $dp_has ? '' : ' is-empty'; ?><?php echo $dp_letter === $dp_index_letter ? ' is-active' : ''; ?>"
									href="<?php echo $dp_state_url( array( 'letter' => ( $dp_all || $dp_letter === $dp_index_letter ) ? null : $dp_index_letter ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>"
									data-dp-letter="<?php echo esc_attr( $dp_index_letter ); ?>"
									<?php echo $dp_has ? '' : 'aria-disabled="true"'; ?>
								><?php echo esc_html( (string) $dp_index_label ); ?></a>
							<?php endforeach; ?>
						</nav>
					<?php endif; ?>

					<?php if ( 'yes' === ( $dp_settings['count_show'] ?? 'yes' ) ) : ?>
						<p class="dp-dir__count dp-dir__count--index" data-dp-count>
							<?php
							printf(
								'%1$s %2$s',
								esc_html( (string) $dp_shown ),
								esc_html( 1 === $dp_shown ? (string) $dp_index['single'] : (string) $dp_index['label'] )
							);
							?>
						</p>
					<?php endif; ?>

					<?php if ( 0 === $dp_shown ) : ?>
						<p class="dp-empty" data-dp-empty>
							<?php
							printf(
								/* translators: %s: index name, e.g. "departments". */
								esc_html__( 'No %s matched your search.', 'doctorpilot' ),
								esc_html( strtolower( (string) $dp_index['label'] ) )
							);
							?>
						</p>
					<?php endif; ?>

					<?php if ( array() !== (array) $dp_index['items'] ) : ?>
						<div class="dp-dir__rows dp-cols-<?php echo esc_attr( (string) $dp_columns ); ?>" data-dp-rows>
							<?php
							foreach ( (array) $dp_index['items'] as $term ) {
								include \BrainStudioz\DoctorPilot\Frontend\Templates::locate( 'row-term.php' );
							}
							?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $dp_index['truncated'] ) ) : ?>
						<p class="dp-dir__notice">
							<?php
							printf(
								/* translators: 1: number listed, 2: index name, e.g. "departments". */
								esc_html__( 'This index lists the first %1$d %2$s.', 'doctorpilot' ),
								absint( count( (array) $dp_index['items'] ) ),
								esc_html( strtolower( (string) $dp_index['label'] ) )
							);
							?>
						</p>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="dp-dir__results" id="<?php echo esc_attr( $dp_uid ); ?>-results" data-dp-results aria-busy="false">
		<section class="dp-dir__panel" data-dp-panel="doctor" aria-label="<?php esc_attr_e( 'Doctors', 'doctorpilot' ); ?>">

			<?php if ( 'yes' === ( $dp_settings['count_show'] ?? 'yes' ) ) : ?>
				<p class="dp-dir__count" data-dp-count role="status" aria-live="polite">
					<?php
					$dp_total = (int) ( $dp_result['total'] ?? 0 );
					/* translators: %d: number of matching doctors. */
					echo esc_html( sprintf( _n( '%d doctor found', '%d doctors found', $dp_total, 'doctorpilot' ), $dp_total ) );
					?>
				</p>
			<?php endif; ?>

			<?php if ( array() === $dp_cards ) : ?>
				<p class="dp-empty"><?php echo esc_html( $dp_empty_text ); ?></p>

			<?php elseif ( 'list' === $dp_layout && 'cards' === (string) ( $dp_settings['list_style'] ?? 'rows' ) ) : ?>
				<?php /* Image-card list. Opt-in: the default stays the compact
				         row list so existing list widgets are untouched. */ ?>
				<div class="dp-directory dp-directory--list" data-dp-rows>
					<?php
					foreach ( $dp_cards as $card ) {
						include \BrainStudioz\DoctorPilot\Frontend\Templates::locate( 'card-doctor.php' );
					}
					?>
				</div>

			<?php elseif ( 'list' === $dp_layout ) : ?>
				<div class="dp-dir__rows dp-cols-<?php echo esc_attr( (string) $dp_columns ); ?>" data-dp-rows>
					<?php
					foreach ( $dp_cards as $card ) {
						include \BrainStudioz\DoctorPilot\Frontend\Templates::locate( 'row-doctor.php' );
					}
					?>
				</div>

			<?php else : ?>
				<div class="dp-directory dp-cols-<?php echo esc_attr( (string) $dp_columns ); ?>" data-dp-rows>
					<?php
					foreach ( $dp_cards as $card ) {
						include \BrainStudioz\DoctorPilot\Frontend\Templates::locate( 'card-doctor.php' );
					}
					?>
				</div>
			<?php endif; ?>

			<?php
			if ( 'yes' === ( $dp_settings['pagination'] ?? 'yes' )
				&& (int) ( $dp_result['total_pages'] ?? 1 ) > 1 ) {
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
		</section>
	</div>
</div>
