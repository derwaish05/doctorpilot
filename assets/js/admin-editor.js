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
			var one = $count.data( 'dp-noun-one' );
			var many = $count.data( 'dp-noun-many' );
			if ( one ) {
				$count.text( n + ' ' + ( 1 === n ? one : many ) + ' selected' );
			} else {
				$count.text( n + ' selected' );
			}
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
	 * Extract the taxonomy from a chip input name: doctorpilot[terms_TAX][].
	 */
	function taxFromChip( name ) {
		var m = ( name || '' ).match( /doctorpilot\[terms_([^\]]+)\]/ );
		return m ? m[ 1 ] : '';
	}

	/**
	 * Extract the taxonomy from a sidebar metabox input name: tax_input[TAX][].
	 */
	function taxFromSidebar( name ) {
		var m = ( name || '' ).match( /tax_input\[([^\]]+)\]/ );
		return m ? m[ 1 ] : '';
	}

	/**
	 * Update a chip's selected class and its field count from its checkbox.
	 */
	function updateChipVisual( chipInput ) {
		var $chip = $( chipInput ).closest( '.dp-chip' );
		$chip.toggleClass( 'is-selected', chipInput.checked );
		updateFieldCount( $chip.closest( '.dp-field' ) );
	}

	/**
	 * Chip (pill) term selection, kept in sync with the default sidebar
	 * taxonomy metaboxes so either UI updates the doctor's terms.
	 */
	function initChips() {
		// Chip change → update visual + mirror into the sidebar checkboxes.
		$( '.dp-editor' ).on( 'change', '.dp-chip input', function () {
			updateChipVisual( this );

			var tax = taxFromChip( this.name );
			if ( ! tax ) {
				return;
			}
			$( 'input[name="tax_input[' + tax + '][]"][value="' + this.value + '"]' )
				.prop( 'checked', this.checked );
		} );

		// Index chips by taxonomy|termId for fast sidebar → chip lookup.
		var chipByKey = {};
		$( '.dp-editor .dp-chip input[type="checkbox"]' ).each( function () {
			var tax = taxFromChip( this.name );
			if ( tax ) {
				chipByKey[ tax + '|' + this.value ] = this;
			}
		} );

		// Sidebar checkbox change → mirror into the matching chip.
		$( document ).on( 'change', 'input[name^="tax_input["]', function () {
			var tax = taxFromSidebar( this.name );
			if ( ! tax ) {
				return;
			}
			var chip = chipByKey[ tax + '|' + this.value ];
			if ( chip && chip.checked !== this.checked ) {
				chip.checked = this.checked;
				updateChipVisual( chip );
			}
		} );
	}

	/**
	 * Rebuild a primary department/specialty <select> from the currently checked
	 * chips of its taxonomy, preserving the current selection when still valid.
	 */
	function rebuildPrimaryForTax( tax ) {
		$( 'select[data-dp-primary-tax="' + tax + '"]' ).each( function () {
			var $sel = $( this );
			var current = $sel.val();
			var placeholder = $sel.find( 'option[value=""]' ).first().text() || '';

			var html = '<option value="">' + placeholder + '</option>';
			var hasCurrent = false;

			$( '.dp-editor .dp-chip input[type="checkbox"]:checked' ).each( function () {
				if ( taxFromChip( this.name ) !== tax ) {
					return;
				}
				var label = $( this ).closest( '.dp-chip' ).find( '.dp-chip__label' ).text().replace( /\s+/g, ' ' ).trim();
				var safe = $( '<div>' ).text( label ).html();
				html += '<option value="' + this.value + '">' + safe + '</option>';
				if ( this.value === current ) {
					hasCurrent = true;
				}
			} );

			$sel.html( html );
			if ( hasCurrent ) {
				$sel.val( current );
			}
		} );
	}

	/**
	 * Keep the primary department/specialty selects in step with chip selection,
	 * so a newly assigned term is immediately choosable as the primary.
	 */
	function initPrimarySelects() {
		$( '.dp-editor' ).on( 'change', '.dp-chip input', function () {
			var tax = taxFromChip( this.name );
			if ( tax ) {
				rebuildPrimaryForTax( tax );
			}
		} );

		// Sidebar metabox changes mirror into chips first (see initChips), so
		// rebuild from the updated chip state afterwards.
		$( document ).on( 'change', 'input[name^="tax_input["]', function () {
			var tax = taxFromSidebar( this.name );
			if ( tax ) {
				rebuildPrimaryForTax( tax );
			}
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
			var $t = $( '[data-dp-serp-title]' );
			var t = ( this.value || '' ).trim();
			$t.text( t || $t.data( 'dp-serp-title-default' ) || 'Doctor name' );
		} );

		$( '.dp-editor' ).on( 'input', '[name*="seo_description"]', function () {
			var $d = $( '[data-dp-serp-desc]' );
			var d = ( this.value || '' ).trim();
			$d.text( d || $d.data( 'dp-serp-default' ) || $d.data( 'dp-serp-placeholder' ) );
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

	/**
	 * Bulk edit: a plain-text view over every field in the editor.
	 *
	 * Export reads the current form state into "## Section" / "### Field" blocks;
	 * Apply parses pasted text and writes it back into the same inputs. Nothing is
	 * saved until the editor presses Update, so sanitization stays server-side.
	 */
	var bulk = ( function () {
		var BOOL_YES = /^(yes|y|true|1|x|on|✓)$/i;

		function norm( str ) {
			return String( str || '' ).replace( /\s+/g, ' ' ).trim().toLowerCase();
		}

		function labelOf( $field ) {
			var $el = $field.find( '> .dp-field__head .dp-field__label' ).first();
			if ( ! $el.length ) {
				$el = $field.find( '> .dp-label' ).first();
			}
			if ( ! $el.length ) {
				$el = $field.find( '.dp-flag__title' ).first();
			}
			var $clone = $el.clone();
			$clone.find( '.dp-badge' ).remove();
			return $clone.text().replace( /\s+/g, ' ' ).trim();
		}

		function chipName( $chip ) {
			return $chip.find( '.dp-chip__label' ).clone().find( '.dp-chip__draft' ).remove().end()
				.text().replace( /\s+/g, ' ' ).trim();
		}

		function escCell( v ) {
			return String( v ).replace( /\\/g, '\\\\' ).replace( /\|/g, '\\|' ).replace( /\r?\n/g, '\\n' );
		}

		function splitRow( line ) {
			var cells = [];
			var cur = '';
			for ( var i = 0; i < line.length; i++ ) {
				var c = line.charAt( i );
				if ( '\\' === c && i + 1 < line.length ) {
					var n = line.charAt( i + 1 );
					cur += 'n' === n ? '\n' : n;
					i++;
				} else if ( '|' === c ) {
					cells.push( cur.trim() );
					cur = '';
				} else {
					cur += c;
				}
			}
			cells.push( cur.trim() );
			return cells;
		}

		function cellInput( $cell ) {
			return $cell.find( 'input[type="checkbox"], select, textarea, input[type="text"]' ).first();
		}

		function cellValue( $cell ) {
			var $in = cellInput( $cell );
			if ( ! $in.length ) {
				return '';
			}
			if ( $in.is( ':checkbox' ) ) {
				return $in.prop( 'checked' ) ? 'yes' : '';
			}
			if ( $in.is( 'select' ) ) {
				return '0' === $in.val() || '' === $in.val() ? '' : $.trim( $in.find( 'option:selected' ).text() );
			}
			return $in.val();
		}

		function setSelect( $sel, text ) {
			var want = norm( text );
			var found = false;
			if ( '' === want ) {
				$sel.val( $sel.find( 'option' ).first().val() );
				return true;
			}
			$sel.find( 'option' ).each( function () {
				if ( norm( $( this ).text() ) === want || norm( $( this ).val() ) === want ) {
					$sel.val( $( this ).val() );
					found = true;
					return false;
				}
			} );
			return found;
		}

		function setCell( $cell, text ) {
			var $in = cellInput( $cell );
			if ( ! $in.length ) {
				return true;
			}
			if ( $in.is( ':checkbox' ) ) {
				$in.prop( 'checked', BOOL_YES.test( text.trim() ) );
				return true;
			}
			if ( $in.is( 'select' ) ) {
				return setSelect( $in, text );
			}
			$in.val( text ).trigger( 'input' );
			return true;
		}

		/** Describe one .dp-field, or null when it has no bulk-editable input. */
		function describe( $f, section ) {
			var label = labelOf( $f );
			if ( ! label ) {
				return null;
			}
			var kind;
			if ( $f.find( '.dp-chips' ).length ) {
				kind = 'chips';
			} else if ( $f.find( '.dp-repeater' ).length ) {
				kind = 'repeater';
			} else if ( $f.find( '.dp-single' ).length ) {
				kind = 'single';
			} else if ( $f.find( '> .dp-flag, .dp-flag' ).length ) {
				kind = 'checkbox';
			} else if ( $f.find( 'select' ).length ) {
				kind = 'select';
			} else if ( $f.find( 'textarea' ).length ) {
				kind = 'textarea';
			} else if ( $f.find( 'input[type="text"], input[type="number"]' ).length ) {
				kind = 'text';
			} else {
				return null;
			}
			return { section: section || '', label: label, kind: kind, $f: $f };
		}

		/** Describe every field in the editor, in document order. */
		function collect() {
			var fields = [];
			$( '.dp-editor .dp-panel' ).each( function () {
				var $panel = $( this );
				var section = $.trim( $panel.find( '.dp-pane-title' ).first().text() );
				$panel.find( '.dp-pane-body .dp-field' ).each( function () {
					var f = describe( $( this ), section );
					if ( f ) {
						fields.push( f );
					}
				} );
			} );
			return fields;
		}

		function rowCells( $row ) {
			return $row.find( '.dp-row__fields > .dp-row-field' );
		}

		function readField( f ) {
			var out = [];
			switch ( f.kind ) {
				case 'chips':
					f.$f.find( '.dp-chip input:checked' ).each( function () {
						out.push( chipName( $( this ).closest( '.dp-chip' ) ) );
					} );
					return out.join( '\n' );
				case 'repeater':
					f.$f.find( '.dp-rows > .dp-row' ).each( function () {
						var cells = [];
						rowCells( $( this ) ).each( function () {
							cells.push( escCell( cellValue( $( this ) ) ) );
						} );
						out.push( cells.join( ' | ' ) );
					} );
					return out.join( '\n' );
				case 'single':
					var cells = [];
					rowCells( f.$f.find( '.dp-single' ).first() ).each( function () {
						cells.push( escCell( cellValue( $( this ) ) ) );
					} );
					return cells.join( ' | ' );
				case 'checkbox':
					return f.$f.find( 'input[type="checkbox"]' ).first().prop( 'checked' ) ? 'yes' : 'no';
				case 'select':
					var $sel = f.$f.find( 'select' ).first();
					return '' === $sel.val() ? '' : $.trim( $sel.find( 'option:selected' ).text() );
				case 'textarea':
					return f.$f.find( 'textarea' ).first().val().replace( /\r\n/g, '\n' );
				default:
					return f.$f.find( 'input[type="text"], input[type="number"]' ).first().val();
			}
		}

		function exportText() {
			var lines = [];
			var section = null;
			$.each( collect(), function ( i, f ) {
				if ( f.section !== section ) {
					section = f.section;
					lines.push( '## ' + section, '' );
				}
				lines.push( '### ' + f.label );
				var v = readField( f );
				if ( '' !== v ) {
					lines.push( v );
				}
				lines.push( '' );
			} );
			return lines.join( '\n' ).replace( /\n+$/, '\n' );
		}

		/** Split text into [{section,label,body}] blocks. */
		function parse( text ) {
			var blocks = [];
			var section = '';
			var cur = null;
			$.each( text.replace( /\r\n?/g, '\n' ).split( '\n' ), function ( i, line ) {
				var h2 = line.match( /^##\s+(.+?)\s*$/ );
				var h3 = line.match( /^###\s+(.+?)\s*$/ );
				if ( h3 ) {
					cur = { section: section, label: h3[ 1 ], body: [] };
					blocks.push( cur );
				} else if ( h2 ) {
					section = h2[ 1 ];
					cur = null;
				} else if ( cur ) {
					cur.body.push( line );
				}
			} );
			$.each( blocks, function ( i, b ) {
				b.body = b.body.join( '\n' ).replace( /^\n+|\n+$/g, '' );
			} );
			return blocks;
		}

		function applyChips( f, body, report ) {
			// Terms can be created when the form is saved; library records cannot.
			var firstName = f.$f.find( '.dp-chip input' ).not( '[name*="[new_terms_"]' ).first().attr( 'name' ) || '';
			var tax = taxFromChip( firstName );
			var canCreate = !! ( l10n.canCreateTerms && tax );

			// Names pending creation from an earlier apply are rebuilt from the text.
			f.$f.find( '.dp-chip--new' ).remove();

			var lookup = {};
			f.$f.find( '.dp-chip' ).each( function () {
				lookup[ norm( chipName( $( this ) ) ) ] = $( this ).find( 'input' )[ 0 ];
			} );
			var chosen = [];
			var toCreate = [];

			function addNew( name ) {
				var key = norm( name );
				if ( ! key ) {
					return;
				}
				if ( canCreate ) {
					if ( -1 === $.inArray( key, $.map( toCreate, norm ) ) ) {
						toCreate.push( name );
						report.created.push( f.label + ': ' + name );
					}
				} else {
					report.unmatched.push( f.label + ': ' + name );
				}
			}

			$.each( body.split( '\n' ), function ( i, raw ) {
				var line = $.trim( raw.replace( /^[-*•]\s+/, '' ) );
				if ( ! line ) {
					return;
				}
				if ( lookup[ norm( line ) ] ) {
					chosen.push( lookup[ norm( line ) ] );
					return;
				}
				// A comma/semicolon list on one line is only split when every part is a
				// known name. Anything else is one name, so a new term such as
				// "ENT, Head & Neck" is never cut in two and created as junk.
				var parts = $.grep( line.split( /\s*[;,]\s*/ ), function ( x ) { return '' !== x; } );
				var known = $.grep( parts, function ( x ) { return !! lookup[ norm( x ) ]; } );
				if ( parts.length > 1 && known.length === parts.length ) {
					$.each( parts, function ( j, part ) {
						chosen.push( lookup[ norm( part ) ] );
					} );
					return;
				}
				addNew( line );
			} );

			f.$f.find( '.dp-chip input' ).each( function () {
				var want = -1 !== chosen.indexOf( this );
				if ( this.checked !== want ) {
					this.checked = want;
					$( this ).trigger( 'change' );
				}
			} );

			// Pending chips submit under new_terms_{taxonomy}[]; unticking one drops it.
			var $chips = f.$f.find( '.dp-chips' ).first();
			$.each( toCreate, function ( i, name ) {
				var $chip = $(
					'<label class="dp-chip dp-chip--new is-selected">' +
						'<input type="checkbox" checked />' +
						'<span class="dp-chip__check dashicons dashicons-yes" aria-hidden="true"></span>' +
						'<span class="dp-chip__label"><span class="dp-chip__name"></span> <em class="dp-chip__draft"></em></span>' +
					'</label>'
				);
				$chip.find( 'input' )
					.attr( 'name', firstName.replace( '[terms_', '[new_terms_' ) )
					.val( name );
				$chip.find( '.dp-chip__name' ).text( name );
				$chip.find( '.dp-chip__draft' ).text( '(' + ( l10n.bulk.newTag || 'new' ) + ')' );
				$chips.append( $chip );
			} );
			updateFieldCount( f.$f );
		}

		function applyRows( f, body, report ) {
			var $rep = f.$f.find( '.dp-repeater' ).first();
			var $rows = $rep.find( '.dp-rows' );
			var template = $rep.find( '.dp-row-template' ).html();
			var lines = $.grep( body.split( '\n' ), function ( l ) { return '' !== $.trim( l ); } );
			$rows.empty();
			$.each( lines, function ( i, line ) {
				var $row = $( $.parseHTML( $.trim( template ) ) ).filter( '.dp-row' ).first();
				$rows.append( $row );
				var $cells = rowCells( $row );
				var cells = splitRow( line );
				if ( cells.length > $cells.length ) {
					report.extra.push( f.label + ' (row ' + ( i + 1 ) + ')' );
				}
				$cells.each( function ( c ) {
					if ( ! setCell( $( this ), cells[ c ] || '' ) ) {
						report.unmatched.push( f.label + ': ' + cells[ c ] );
					}
				} );
			} );
			renumberRepeater( $rep );
		}

		function applyField( f, body, report ) {
			switch ( f.kind ) {
				case 'chips':
					applyChips( f, body, report );
					break;
				case 'repeater':
					applyRows( f, body, report );
					break;
				case 'single':
					var cells = splitRow( body.split( '\n' )[ 0 ] || '' );
					rowCells( f.$f.find( '.dp-single' ).first() ).each( function ( c ) {
						if ( ! setCell( $( this ), cells[ c ] || '' ) ) {
							report.unmatched.push( f.label + ': ' + cells[ c ] );
						}
					} );
					break;
				case 'checkbox':
					f.$f.find( 'input[type="checkbox"]' ).first().prop( 'checked', BOOL_YES.test( $.trim( body ) ) ).trigger( 'change' );
					break;
				case 'select':
					var $sel = f.$f.find( 'select' ).first();
					if ( ! setSelect( $sel, $.trim( body ) ) ) {
						report.unmatched.push( f.label + ': ' + body );
					}
					$sel.trigger( 'change' );
					break;
				case 'textarea':
					f.$f.find( 'textarea' ).first().val( body ).trigger( 'input' );
					break;
				default:
					f.$f.find( 'input[type="text"], input[type="number"]' ).first()
						.val( body.split( '\n' )[ 0 ] ).trigger( 'input' );
			}
		}

		/** Turn a report into the status parts shared by both bulk UIs. */
		function summarize( r, t ) {
			var parts = [];
			if ( r.created.length ) {
				parts.push( t.created + ' ' + r.created.join( '; ' ) + '.' );
			}
			if ( r.unknown.length ) {
				parts.push( t.unknown + ' ' + r.unknown.join( ', ' ) + '.' );
			}
			if ( r.unmatched.length ) {
				parts.push( t.unmatched + ' ' + r.unmatched.join( '; ' ) + '.' );
			}
			if ( r.extra.length ) {
				parts.push( t.extraCells + ' ' + r.extra.join( ', ' ) + '.' );
			}
			return parts;
		}

		/** Whether a report holds anything the editor should double-check. */
		function hasWarnings( r ) {
			return r.unknown.length + r.unmatched.length + r.extra.length > 0;
		}

		function apply( text ) {
			var fields = collect();
			var blocks = parse( text );
			var report = { applied: 0, unknown: [], unmatched: [], extra: [], created: [] };
			var used = [];
			var queue = [];

			$.each( blocks, function ( i, b ) {
				var want = norm( b.label );
				var hit = null;
				$.each( fields, function ( j, f ) {
					if ( used.indexOf( j ) === -1 && norm( f.label ) === want && norm( f.section ) === norm( b.section ) ) {
						hit = j;
						return false;
					}
				} );
				if ( null === hit ) {
					$.each( fields, function ( j, f ) {
						if ( used.indexOf( j ) === -1 && norm( f.label ) === want ) {
							hit = j;
							return false;
						}
					} );
				}
				if ( null === hit ) {
					report.unknown.push( b.label );
					return;
				}
				used.push( hit );
				queue.push( { f: fields[ hit ], body: b.body } );
			} );

			// Chips first so primary department/specialty selects can match them.
			queue.sort( function ( a, b ) {
				return ( 'chips' === b.f.kind ? 1 : 0 ) - ( 'chips' === a.f.kind ? 1 : 0 );
			} );
			$.each( queue, function ( i, q ) {
				applyField( q.f, q.body, report );
				report.applied++;
			} );
			return report;
		}

		/** Read one field as bulk text. */
		function exportField( f ) {
			return readField( f );
		}

		/** Write bulk text into one field; returns the report. */
		function applyOne( f, body ) {
			var report = { applied: 1, unknown: [], unmatched: [], extra: [], created: [] };
			applyField( f, body, report );
			return report;
		}

		/** Column names of a repeater, in the order the cells are written. */
		function columns( f ) {
			var names = [];
			var $row = f.$f.find( '.dp-rows > .dp-row' ).first();
			if ( ! $row.length ) {
				$row = $( $.parseHTML( $.trim( f.$f.find( '.dp-row-template' ).html() || '' ) ) ).filter( '.dp-row' ).first();
			}
			rowCells( $row ).each( function () {
				var $c = $( this );
				var text = $c.find( '.dp-row-field__label' ).first().text() || $.trim( $c.find( '.dp-row-field__check' ).text() );
				names.push( $.trim( text ) );
			} );
			return names;
		}

		return {
			exportText: exportText,
			apply: apply,
			blocks: parse,
			describe: describe,
			exportField: exportField,
			applyOne: applyOne,
			columns: columns,
			summarize: summarize,
			hasWarnings: hasWarnings
		};
	}() );

	function initBulkEdit() {
		var $wrap = $( '.dp-bulk' );
		if ( ! $wrap.length ) {
			return;
		}
		var t = l10n.bulk || {};
		var $toggle = $wrap.find( '.dp-bulk__toggle' );
		var $panel = $wrap.find( '.dp-bulk__panel' );
		var $text = $wrap.find( '.dp-bulk__text' );
		var $status = $wrap.find( '.dp-bulk__status' );

		function say( msg, isError ) {
			$status.text( msg ).toggleClass( 'is-error', !! isError );
		}

		function open( isOpen ) {
			$panel.prop( 'hidden', ! isOpen );
			$toggle.attr( 'aria-expanded', isOpen ? 'true' : 'false' );
			$toggle.find( '.dp-bulk__toggle-label' ).text( isOpen ? t.hide : t.show );
			if ( isOpen ) {
				$text.val( bulk.exportText() );
				say( '' );
				$text.trigger( 'focus' );
			}
		}

		$toggle.on( 'click', function () {
			open( $panel.prop( 'hidden' ) );
		} );

		$wrap.on( 'click', '.dp-bulk__reset', function () {
			$text.val( bulk.exportText() );
			say( '' );
		} );

		$wrap.on( 'click', '.dp-bulk__copy', function () {
			var value = $text.val();
			var done = function () { say( t.copied ); };
			var fallback = function () {
				$text.trigger( 'select' );
				try {
					say( document.execCommand( 'copy' ) ? t.copied : t.copyFailed, ! document.execCommand );
				} catch ( e ) {
					say( t.copyFailed, true );
				}
			};
			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( value ).then( done, fallback );
			} else {
				fallback();
			}
		} );

		$wrap.on( 'click', '.dp-bulk__apply', function () {
			var text = $text.val();
			if ( ! bulk.blocks( text ).length ) {
				say( t.nothing, true );
				return;
			}
			var r = bulk.apply( text );
			var parts = [ t.applied ].concat( bulk.summarize( r, t ) );
			say( parts.join( ' ' ), bulk.hasWarnings( r ) );
		} );
	}

	/**
	 * Per-field bulk edit: a "Bulk edit" link in the header of every chip list
	 * and repeater, opening a small text box for just that field.
	 */
	function initFieldBulk() {
		var t = l10n.bulk || {};

		$( '.dp-editor .dp-field' ).each( function () {
			var $f = $( this );
			var $head = $f.children( '.dp-field__head' ).first();
			var f = bulk.describe( $f, '' );
			if ( ! $head.length || ! f || ( 'chips' !== f.kind && 'repeater' !== f.kind ) ) {
				return;
			}
			$( '<button type="button" class="button-link dp-fbulk__toggle" aria-expanded="false"></button>' )
				.text( t.show )
				.attr( 'aria-label', t.show + ': ' + f.label )
				.appendTo( $head );
		} );

		$( '.dp-editor' ).on( 'click', '.dp-fbulk__toggle', function () {
			var $btn = $( this );
			var $f = $btn.closest( '.dp-field' );
			var $panel = $f.children( '.dp-fbulk' );

			if ( $panel.length ) {
				$panel.remove();
				$btn.attr( 'aria-expanded', 'false' ).text( t.show );
				return;
			}

			var f = bulk.describe( $f, '' );
			var isTerms = 'chips' === f.kind && f.$f.find( '.dp-chip input[name*="[terms_"]' ).length > 0;
			var hint = 'chips' === f.kind
				? ( isTerms && l10n.canCreateTerms ? t.hintTerms : t.hintChips )
				: t.hintRows + ' ' + bulk.columns( f ).join( ' | ' );
			$panel = $(
				'<div class="dp-fbulk">' +
					'<textarea class="dp-fbulk__text large-text" rows="8" spellcheck="false" autocomplete="off" data-lpignore="true" data-1p-ignore></textarea>' +
					'<p class="dp-desc dp-fbulk__hint"></p>' +
					'<div class="dp-fbulk__actions">' +
						'<button type="button" class="button button-primary dp-fbulk__apply"></button>' +
						'<button type="button" class="button dp-fbulk__cancel"></button>' +
					'</div>' +
					'<div class="dp-fbulk__status" role="status" aria-live="polite"></div>' +
				'</div>'
			);
			$panel.find( '.dp-fbulk__text' ).val( bulk.exportField( f ) ).attr( 'aria-label', t.show + ': ' + f.label );
			$panel.find( '.dp-fbulk__hint' ).text( hint );
			$panel.find( '.dp-fbulk__apply' ).text( t.fieldApply );
			$panel.find( '.dp-fbulk__cancel' ).text( t.fieldClose );

			var $head = $f.children( '.dp-field__head' ).first();
			$head.after( $panel );
			$btn.attr( 'aria-expanded', 'true' ).text( t.hide );
			$panel.find( '.dp-fbulk__text' ).trigger( 'focus' );
		} );

		$( '.dp-editor' ).on( 'click', '.dp-fbulk__cancel', function () {
			var $f = $( this ).closest( '.dp-field' );
			$f.children( '.dp-fbulk' ).remove();
			$f.find( '.dp-fbulk__toggle' ).attr( 'aria-expanded', 'false' ).text( t.show ).trigger( 'focus' );
		} );

		$( '.dp-editor' ).on( 'click', '.dp-fbulk__apply', function () {
			var $f = $( this ).closest( '.dp-field' );
			var $panel = $f.children( '.dp-fbulk' );
			var f = bulk.describe( $f, '' );
			var r = bulk.applyOne( f, $panel.find( '.dp-fbulk__text' ).val().replace( /^\n+|\n+$/g, '' ) );
			var parts = [ t.applied ].concat( bulk.summarize( r, t ) );
			$panel.find( '.dp-fbulk__status' )
				.text( parts.join( ' ' ) )
				.toggleClass( 'is-error', bulk.hasWarnings( r ) );
		} );
	}

	$( function () {
		initTabs();
		initRepeaters();
		initChips();
		initPrimarySelects();
		initSeo();
		initMedia();
		initBulkEdit();
		initFieldBulk();
	} );
} )( window.jQuery );
