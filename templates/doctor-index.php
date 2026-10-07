<?php
/**
 * Doctor Search Index: search bar, mode tabs, A–Z row and browsable lists.
 *
 * Every entry is a real link, rendered server-side: a doctor to their profile,
 * a term to the search page with that filter applied. JavaScript only hides
 * rows and adds the suggestion panel, so the block works with JS off.
 *
 * Expects `$data` in scope, from `DoctorIndex::render()`.
 *
 * @package BrainStudioz\DoctorPilot
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dp_uid     = (string) ( $data['uid'] ?? 'dpi' );
$dp_indexes = (array) ( $data['indexes'] ?? array() );
$dp_active  = (string) ( $data['active'] ?? '' );
$dp_letters = (array) ( $data['letters'] ?? array() );
$dp_cols    = (int) ( $data['columns'] ?? 4 );
$dp_tabbed  = 1 < count( $dp_indexes );

$dp_filters     = (array) ( $data['filters'] ?? array() );
$dp_facets      = (array) ( $data['facets'] ?? array() );
$dp_sort        = (string) ( $data['sort'] ?? 'name' );
$dp_collapsible = ! empty( $data['collapsible'] );
$dp_flabels     = (array) ( $data['filter_labels'] ?? array() );
$dp_slabels     = (array) ( $data['sort_labels'] ?? array() );
?>
<div
	class="dp-index"
	data-dp-index-widget
	data-dp-active="<?php echo esc_attr( $dp_active ); ?>"
	data-dp-suggest="<?php echo ! empty( $data['suggest'] ) ? '1' : '0'; ?>"
	data-dp-search-page="<?php echo esc_url( (string) ( $data['search_page'] ?? '' ) ); ?>"
	data-dp-empty="<?php echo esc_attr( (string) ( $data['empty_text'] ?? '' ) ); ?>"
>
	<div class="dp-dir__bar dp-index__bar">
		<?php if ( ! empty( $data['search'] ) ) : ?>
			<div class="dp-index__search-wrap">
				<div class="dp-dir__search">
					<label class="screen-reader-text" for="<?php echo esc_attr( $dp_uid ); ?>-q">
						<?php esc_html_e( 'Search doctors', 'doctorpilot' ); ?>
					</label>
					<input
						class="dp-dir__input"
						type="search"
						id="<?php echo esc_attr( $dp_uid ); ?>-q"
						name="q"
						value=""
						placeholder="<?php echo esc_attr( (string) ( $data['placeholder'] ?? '' ) ); ?>"
						autocomplete="off"
						<?php if ( ! empty( $data['suggest'] ) ) : ?>
							role="combobox"
							aria-expanded="false"
							aria-autocomplete="list"
							aria-controls="<?php echo esc_attr( $dp_uid ); ?>-suggest"
						<?php endif; ?>
						data-dp-input
					/>
					<button type="button" class="dp-dir__submit" data-dp-submit>
						<span class="screen-reader-text"><?php esc_html_e( 'Search', 'doctorpilot' ); ?></span>
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
							<circle cx="11" cy="11" r="7"></circle>
							<line x1="16.5" y1="16.5" x2="21" y2="21"></line>
						</svg>
					</button>
				</div>

				<?php if ( ! empty( $data['suggest'] ) ) : ?>
					<div
						class="dp-index__suggest"
						id="<?php echo esc_attr( $dp_uid ); ?>-suggest"
						role="listbox"
						data-dp-suggest-panel
						hidden
					></div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $dp_tabbed ) : ?>
			<?php
			/*
			 * Disclosure buttons, not a tablist. The block opens with nothing
			 * chosen — just the search bar — and a tablist is required to have
			 * exactly one selected tab at all times. Announcing "tab, not
			 * selected" three times over would describe a broken tablist rather
			 * than a set of closed sections. `aria-expanded` says what is
			 * actually true, and it leaves every button in the tab order instead
			 * of the roving tabindex a tablist needs, which would have left no
			 * focusable button at all while nothing is open.
			 */
			?>
			<div class="dp-dir__modes dp-index__modes" role="group" aria-label="<?php esc_attr_e( 'Browse by', 'doctorpilot' ); ?>">
				<?php foreach ( $dp_indexes as $dp_key => $dp_index ) : ?>
					<?php $dp_is_active = (string) $dp_key === $dp_active; ?>
					<button
						type="button"
						class="dp-dir__mode<?php echo $dp_is_active ? ' is-active' : ''; ?>"
						id="<?php echo esc_attr( $dp_uid . '-tab-' . (string) $dp_key ); ?>"
						aria-expanded="<?php echo $dp_is_active ? 'true' : 'false'; ?>"
						aria-controls="<?php echo esc_attr( $dp_uid . '-panel-' . (string) $dp_key ); ?>"
						data-dp-tab="<?php echo esc_attr( (string) $dp_key ); ?>"
					><?php echo esc_html( (string) ( $dp_index['tab'] ?? $dp_key ) ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( $dp_collapsible ) : ?>
			<?php
			/*
			 * Disabled from the first paint when the tab that opens is a term
			 * list, so it never flickers from enabled to disabled as the script
			 * catches up — and so it is already correct with JavaScript off,
			 * where the tab cannot change.
			 */
			$dp_active_type = (string) ( $dp_indexes[ $dp_active ]['type'] ?? '' );
			// Enabled only while the doctor-name list is open — the one list its
			// filters can act on. That covers the closed state too, which is how
			// the block loads.
			$dp_filters_off = 'names' !== $dp_active_type;
			?>
			<div class="dp-index__actions">
				<button
					type="button"
					class="dp-dir__more dp-index__action"
					aria-expanded="false"
					aria-controls="<?php echo esc_attr( $dp_uid ); ?>-filters"
					<?php echo $dp_filters_off ? 'disabled aria-disabled="true"' : ''; ?>
					data-dp-more
				>
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
						<line x1="3" y1="7" x2="21" y2="7"></line>
						<line x1="3" y1="12" x2="21" y2="12"></line>
						<line x1="3" y1="17" x2="21" y2="17"></line>
						<circle cx="9" cy="7" r="2" fill="currentColor"></circle>
						<circle cx="15" cy="12" r="2" fill="currentColor"></circle>
						<circle cx="8" cy="17" r="2" fill="currentColor"></circle>
					</svg>
					<span><?php echo esc_html( (string) ( $data['filters_label'] ?? '' ) ); ?></span>
					<span class="dp-dir__chevron" aria-hidden="true"></span>
				</button>
			</div>
		<?php endif; ?>

		<?php if ( array() !== $dp_filters ) : ?>
			<?php
			/*
			 * A form of its own, inside the bar rather than around it. The search
			 * box must stay outside: it navigates to the search page, while these
			 * controls act on the list right here, and one form cannot submit to
			 * two places. `flex-basis: 100%` drops it onto its own line inside the
			 * band, so it reads as a second row of the same bar.
			 *
			 * A plain GET form, so the row still works with JavaScript off: the
			 * server reads the same parameter names back and renders the list
			 * already sorted and narrowed.
			 */
			?>
			<form class="dp-index__filter-form" method="get">
				<div
					class="dp-dir__filters<?php echo $dp_collapsible ? ' dp-dir__filters--collapsible' : ''; ?>"
					id="<?php echo esc_attr( $dp_uid ); ?>-filters"
					<?php echo $dp_collapsible ? 'data-dp-collapsible hidden' : ''; ?>
				>
					<?php foreach ( $dp_filters as $dp_filter ) : ?>
						<?php
						$dp_label    = (string) ( $dp_flabels[ $dp_filter ] ?? $dp_filter );
						$dp_field_id = $dp_uid . '-f-' . $dp_filter;

						if ( 'sort' === $dp_filter ) :
							?>
							<div class="dp-dir__field">
								<label class="dp-dir__field-label" for="<?php echo esc_attr( $dp_field_id ); ?>"><?php echo esc_html( $dp_label ); ?></label>
								<select class="dp-dir__select" id="<?php echo esc_attr( $dp_field_id ); ?>" name="sort" data-dp-filter="sort">
									<?php foreach ( $dp_slabels as $dp_value => $dp_sort_label ) : ?>
										<option value="<?php echo esc_attr( (string) $dp_value ); ?>" <?php selected( $dp_sort, $dp_value ); ?>>
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
								<select class="dp-dir__select" id="<?php echo esc_attr( $dp_field_id ); ?>" name="gender" data-dp-filter="gender">
									<option value=""><?php esc_html_e( 'All', 'doctorpilot' ); ?></option>
									<?php
									$dp_genders = array(
										'female' => __( 'Female', 'doctorpilot' ),
										'male'   => __( 'Male', 'doctorpilot' ),
										'other'  => __( 'Other', 'doctorpilot' ),
									);
									foreach ( $dp_genders as $dp_value => $dp_gender_label ) :
										?>
										<option value="<?php echo esc_attr( $dp_value ); ?>" <?php selected( (string) ( $data['gender'] ?? '' ), $dp_value ); ?>>
											<?php echo esc_html( $dp_gender_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<?php
						elseif ( isset( $dp_facets[ $dp_filter ] ) && array() !== (array) $dp_facets[ $dp_filter ] ) :
							?>
							<div class="dp-dir__field">
								<label class="dp-dir__field-label" for="<?php echo esc_attr( $dp_field_id ); ?>"><?php echo esc_html( $dp_label ); ?></label>
								<select class="dp-dir__select" id="<?php echo esc_attr( $dp_field_id ); ?>" name="<?php echo esc_attr( (string) $dp_filter ); ?>" data-dp-filter="<?php echo esc_attr( (string) $dp_filter ); ?>">
									<option value=""><?php esc_html_e( 'All', 'doctorpilot' ); ?></option>
									<?php foreach ( (array) $dp_facets[ $dp_filter ] as $dp_option ) : ?>
										<option value="<?php echo esc_attr( (string) $dp_option['slug'] ); ?>" <?php selected( (string) ( $data['language'] ?? '' ), (string) $dp_option['slug'] ); ?>>
											<?php echo esc_html( (string) $dp_option['label'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<?php
						endif;
						?>
					<?php endforeach; ?>

					<noscript>
						<button type="submit" class="dp-btn dp-btn--primary"><?php esc_html_e( 'Apply filters', 'doctorpilot' ); ?></button>
					</noscript>
				</div>
			</form>
		<?php endif; ?>
	</div>

	<?php foreach ( $dp_indexes as $dp_key => $dp_index ) : ?>
		<?php
		$dp_is_active = (string) $dp_key === $dp_active;
		$dp_available = (array) ( $dp_index['letters'] ?? array() );
		$dp_items     = (array) ( $dp_index['items'] ?? array() );
		?>
		<section
			class="dp-index__panel"
			id="<?php echo esc_attr( $dp_uid . '-panel-' . (string) $dp_key ); ?>"
			<?php if ( $dp_tabbed ) : ?>
				aria-labelledby="<?php echo esc_attr( $dp_uid . '-tab-' . (string) $dp_key ); ?>"
			<?php endif; ?>
			data-dp-panel="<?php echo esc_attr( (string) $dp_key ); ?>"
			data-dp-type="<?php echo esc_attr( (string) ( $dp_index['type'] ?? '' ) ); ?>"
			data-dp-single="<?php echo esc_attr( (string) ( $dp_index['single'] ?? '' ) ); ?>"
			data-dp-plural="<?php echo esc_attr( (string) ( $dp_index['label'] ?? '' ) ); ?>"
			<?php echo $dp_is_active ? '' : 'hidden'; ?>
		>
			<?php if ( ! empty( $dp_index['az'] ) ) : ?>
				<nav class="dp-dir__az dp-index__az" aria-label="<?php esc_attr_e( 'Filter by first letter', 'doctorpilot' ); ?>">
					<?php foreach ( $dp_letters as $dp_value => $dp_label ) : ?>
						<?php
						// The "all" entry clears the filter rather than selecting a
						// bucket, so it is always available and starts out active.
						$dp_all = '' === (string) $dp_value;
						$dp_has = $dp_all || in_array( (string) $dp_value, $dp_available, true );
						?>
						<button
							type="button"
							class="dp-dir__az-item<?php echo $dp_has ? '' : ' is-empty'; ?><?php echo $dp_all ? ' is-active' : ''; ?>"
							data-dp-letter="<?php echo esc_attr( (string) $dp_value ); ?>"
							<?php echo $dp_has ? '' : 'aria-disabled="true"'; ?>
						><?php echo esc_html( (string) $dp_label ); ?></button>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>

			<?php if ( ! empty( $data['count'] ) ) : ?>
				<p class="dp-dir__count dp-index__count" data-dp-count>
					<?php
					printf(
						'%1$s %2$s',
						esc_html( (string) count( $dp_items ) ),
						esc_html( 1 === count( $dp_items ) ? (string) $dp_index['single'] : (string) $dp_index['label'] )
					);
					?>
				</p>
			<?php endif; ?>

			<p class="dp-empty dp-index__empty" data-dp-none hidden>
				<?php echo esc_html( (string) ( $data['empty_text'] ?? '' ) ); ?>
			</p>

			<?php if ( array() !== $dp_items ) : ?>
				<div class="dp-dir__rows dp-cols-<?php echo esc_attr( (string) $dp_cols ); ?>" data-dp-rows>
					<?php foreach ( $dp_items as $dp_item ) : ?>
						<?php
						$dp_name = (string) ( $dp_item['name'] ?? '' );
						$dp_url  = (string) ( $dp_item['url'] ?? '' );
						$dp_meta = (string) ( $dp_item['meta'] ?? '' );

						if ( '' === $dp_name || '' === $dp_url ) {
							continue;
						}
						?>
						<article
							class="dp-dir__row dp-index__row"
							data-dp-row
							data-dp-letter="<?php echo esc_attr( (string) ( $dp_item['letter'] ?? '' ) ); ?>"
							data-dp-text="<?php echo esc_attr( function_exists( 'mb_strtolower' ) ? mb_strtolower( $dp_name ) : strtolower( $dp_name ) ); ?>"
							<?php
							// Only doctor rows carry these; a department has no
							// gender, so its row is never hidden by that filter.
							if ( 'names' === (string) ( $dp_index['type'] ?? '' ) ) :
								?>
								data-dp-gender="<?php echo esc_attr( (string) ( $dp_item['gender'] ?? '' ) ); ?>"
								data-dp-langs="<?php echo esc_attr( implode( ' ', array_map( 'strval', (array) ( $dp_item['langs'] ?? array() ) ) ) ); ?>"
							<?php endif; ?>
						>
							<a class="dp-dir__row-name" href="<?php echo esc_url( $dp_url ); ?>"><?php echo esc_html( $dp_name ); ?></a>
							<?php if ( '' !== $dp_meta && ! empty( $data['meta'] ) ) : ?>
								<span class="dp-dir__row-meta"><?php echo esc_html( $dp_meta ); ?></span>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $dp_index['truncated'] ) ) : ?>
				<p class="dp-dir__notice">
					<?php
					printf(
						/* translators: 1: number listed, 2: index name, e.g. "departments". */
						esc_html__( 'This index lists the first %1$d %2$s.', 'doctorpilot' ),
						absint( count( $dp_items ) ),
						esc_html( function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $dp_index['label'] ) : strtolower( (string) $dp_index['label'] ) )
					);
					?>
				</p>
			<?php endif; ?>
		</section>
	<?php endforeach; ?>
</div>
