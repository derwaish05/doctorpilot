/**
 * DoctorPilot admin editor interactions: tabs, repeaters, and media selection.
 *
 * @package BrainStudioz\DoctorPilot
 */
( function ( $ ) {
	'use strict';

	var l10n = window.doctorPilotEditor || {};

	/**
	 * Accessible tab switching.
	 */
	function initTabs() {
		$( '.dp-tabs' ).on( 'click', '.dp-tab', function () {
			var $tab = $( this );
			var panelId = $tab.data( 'panel' );

			$tab
				.closest( '.dp-editor' )
				.find( '.dp-tab' )
				.removeClass( 'is-active' )
				.attr( 'aria-selected', 'false' );

			$tab.addClass( 'is-active' ).attr( 'aria-selected', 'true' );

			var $editor = $tab.closest( '.dp-editor' );
			$editor.find( '.dp-panel' ).removeClass( 'is-active' ).attr( 'hidden', 'hidden' );
			$editor.find( '#' + panelId ).addClass( 'is-active' ).removeAttr( 'hidden' );
		} );

		// Keyboard support: arrow keys move between tabs.
		$( '.dp-tabs' ).on( 'keydown', '.dp-tab', function ( e ) {
			var $tabs = $( this ).closest( '.dp-tabs' ).find( '.dp-tab' );
			var index = $tabs.index( this );

			if ( 39 === e.which || 40 === e.which ) {
				e.preventDefault();
				$tabs.eq( ( index + 1 ) % $tabs.length ).trigger( 'click' ).focus();
			} else if ( 37 === e.which || 38 === e.which ) {
				e.preventDefault();
				$tabs.eq( ( index - 1 + $tabs.length ) % $tabs.length ).trigger( 'click' ).focus();
			}
		} );
	}

	/**
	 * Renumber a repeater's input name indices to match DOM order, and refresh
	 * its field count. Keeps saved data ordered and free of index collisions.
	 */
	function renumberRepeater( $repeater ) {
		$repeater.find( '.dp-rows > .dp-row' ).each( function ( i ) {
			$( this ).find( '[name]' ).each( function () {
				var name = $( this ).attr( 'name' );
				$( this ).attr(
					'name',
					name.replace( /^(doctorpilot\[[^\]]*\]\[)(?:\d+|__INDEX__)(\].*)$/, '$1' + i + '$2' )
				);
			} );
		} );
		updateFieldCount( $repeater.closest( '.dp-field' ) );
	}

	/**
	 * Update the "N selected" / "N rows" count on a field header.
	 */
	function updateFieldCount( $field ) {
		var $count = $field.find( '> .dp-field__head .dp-field__count' ).first();
		if ( ! $count.length ) {
			return;
		}
		var unit = $count.data( 'dp-count' );
		var n;
		if ( 'row' === unit ) {
			n = $field.find( '.dp-rows > .dp-row' ).length;
			$count.text( n + ' ' + ( 1 === n ? 'row' : 'rows' ) );
		} else {
			n = $field.find( '.dp-chip input:checked' ).length;
			$count.text( n + ' selected' );
		}
	}

	/**
	 * Repeater add/remove with index re-numbering, plus drag-sort.
	 */
	function initRepeaters() {
		$( '.dp-repeater' ).on( 'click', '.dp-add-row', function () {
			var $repeater = $( this ).closest( '.dp-repeater' );
			var template = $repeater.find( '.dp-row-template' ).html();
			$repeater.find( '.dp-rows' ).append( template );
			renumberRepeater( $repeater );
		} );

		$( '.dp-repeater' ).on( 'click', '.dp-remove-row', function () {
			if ( l10n.confirmRow && ! window.confirm( l10n.confirmRow ) ) {
				return;
			}
			var $repeater = $( this ).closest( '.dp-repeater' );
			$( this ).closest( '.dp-row' ).remove();
			renumberRepeater( $repeater );
		} );

		if ( $.fn.sortable ) {
			$( '.dp-repeater .dp-rows' ).sortable( {
				handle: '.dp-row__handle',
				items: '> .dp-row',
				placeholder: 'dp-row dp-row--placeholder',
				forcePlaceholderSize: true,
				update: function () {
					renumberRepeater( $( this ).closest( '.dp-repeater' ) );
				}
			} );
		}
	}

	/**
	 * Chip (pill) term selection: toggle the visual state and refresh counts.
	 */
	function initChips() {
		$( '.dp-editor' ).on( 'change', '.dp-chip input', function () {
			var $chip = $( this ).closest( '.dp-chip' );
			$chip.toggleClass( 'is-selected', this.checked );
			updateFieldCount( $chip.closest( '.dp-field' ) );
		} );
	}

	/**
	 * SEO character counters and the live Google-style search preview.
	 */
	function initSeo() {
		$( '.dp-editor' ).on( 'input', '[data-dp-counter]', function () {
			var len = ( this.value || '' ).length;
			$( this ).nextAll( '.dp-counter' ).first().find( '.dp-counter__n' ).text( len );
		} );

		$( '.dp-editor' ).on( 'input', '[name*="seo_title"]', function () {
			var t = ( this.value || '' ).trim();
			$( '[data-dp-serp-title]' ).text( t || 'Doctor name' );
		} );

		$( '.dp-editor' ).on( 'input', '[name*="seo_description"]', function () {
			var $d = $( '[data-dp-serp-desc]' );
			var d = ( this.value || '' ).trim();
			$d.text( d || $d.data( 'dp-serp-placeholder' ) );
		} );
	}

	/**
	 * Media selection for image fields.
	 */
	function initMedia() {
		$( '.dp-editor' ).on( 'click', '.dp-media-choose', function ( e ) {
			e.preventDefault();
			var $wrap = $( this ).closest( '.dp-media' );

			var frame = window.wp.media( {
				title: l10n.chooseImage || 'Choose image',
				multiple: false
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				$wrap.find( 'input[type="hidden"]' ).val( attachment.id );
				$wrap.find( '.dp-media-preview' ).html( '<img src="' + attachment.url + '" alt="" />' );
			} );

			frame.open();
		} );

		$( '.dp-editor' ).on( 'click', '.dp-media-remove', function ( e ) {
			e.preventDefault();
			var $wrap = $( this ).closest( '.dp-media' );
			$wrap.find( 'input[type="hidden"]' ).val( '' );
			$wrap.find( '.dp-media-preview' ).empty();
		} );
	}

	$( function () {
		initTabs();
		initRepeaters();
		initChips();
		initSeo();
		initMedia();
	} );
} )( window.jQuery );
