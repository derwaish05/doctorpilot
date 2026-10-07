/**
 * Doctor Search Index.
 *
 * The block is fully rendered server-side and every entry is a real link, so
 * this script only ever: switches tabs, filters the rows already on the page,
 * and offers suggestions under the input. It never re-renders an entry, and it
 * never fetches the doctor list — that is the search page's job.
 */
( function () {
	'use strict';

	var CONFIG = window.doctorPilotIndex || {};
	var STRINGS = CONFIG.strings || {};

	function text( key, fallback ) {
		return typeof STRINGS[ key ] === 'string' ? STRINGS[ key ] : fallback;
	}

	function normalize( value ) {
		return String( value || '' ).trim().toLowerCase();
	}

	/**
	 * Only ever navigate to http(s) URLs we built or the REST API returned.
	 *
	 * @param {string} url Candidate URL.
	 * @return {string} The URL, or '' when it is not a plain web link.
	 */
	function safeUrl( url ) {
		var value = String( url || '' ).trim();

		// `//host/x` is off-site, so a leading slash only counts as same-origin
		// when the next character is not one.
		if ( /^https?:\/\//i.test( value ) || ( value.charAt( 0 ) === '/' && value.charAt( 1 ) !== '/' ) ) {
			return value;
		}

		return '';
	}

	function Index( root ) {
		this.root = root;
		this.input = root.querySelector( '[data-dp-input]' );
		this.submit = root.querySelector( '[data-dp-submit]' );
		this.panel = root.querySelector( '[data-dp-suggest-panel]' );
		this.tabs = Array.prototype.slice.call( root.querySelectorAll( '[data-dp-tab]' ) );
		this.panels = Array.prototype.slice.call( root.querySelectorAll( '[data-dp-panel]' ) );
		this.searchPage = root.getAttribute( 'data-dp-search-page' ) || '';
		this.active = root.getAttribute( 'data-dp-active' ) || '';
		this.letter = '';
		this.query = '';
		this.suggestOn = root.getAttribute( 'data-dp-suggest' ) === '1' && !! this.panel;
		this.timer = null;
		this.controller = null;
		this.items = [];
		this.cursor = -1;

		this.moreBtn = root.querySelector( '[data-dp-more]' );
		this.filters = root.querySelector( '[data-dp-collapsible]' );
		this.selects = Array.prototype.slice.call( root.querySelectorAll( '[data-dp-filter]' ) );

		this.bind();
		this.collapse( false );
		this.syncFilters();
		this.filter();
	}

	Index.prototype.bind = function () {
		var self = this;

		// Plain buttons now, so no roving tabindex and no arrow-key handling:
		// each one is in the tab order on its own. The roving pattern belonged
		// to the tablist these used to be, and with nothing open on load it
		// would have left every button unreachable from the keyboard.
		this.tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				self.setTab( tab.getAttribute( 'data-dp-tab' ) || '' );
			} );
		} );

		this.panels.forEach( function ( panel ) {
			panel.querySelectorAll( '[data-dp-letter]' ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					// Letters with no entries stay in the tab order for discovery,
					// so the click has to be ignored here rather than by `disabled`.
					if ( 'true' === button.getAttribute( 'aria-disabled' ) ) {
						return;
					}

					var value = button.getAttribute( 'data-dp-letter' ) || '';
					self.letter = self.letter === value ? '' : value;
					self.filter();
				} );
			} );
		} );

		if ( this.input ) {
			this.input.addEventListener( 'input', function () {
				self.query = self.input.value;
				self.filter();
				self.scheduleSuggest();
			} );

			this.input.addEventListener( 'keydown', function ( event ) {
				self.onKey( event );
			} );

			this.input.addEventListener( 'focus', function () {
				if ( self.items.length ) {
					self.open();
				}
			} );
		}

		if ( this.submit ) {
			this.submit.addEventListener( 'click', function () {
				self.go();
			} );
		}

		if ( this.moreBtn && this.filters ) {
			this.moreBtn.addEventListener( 'click', function () {
				self.collapse( self.moreBtn.getAttribute( 'aria-expanded' ) !== 'true' );
			} );
		}

		this.selects.forEach( function ( select ) {
			select.addEventListener( 'change', function () {
				// Sort reorders rows, which hiding cannot do, so it is the one
				// filter that has to move nodes rather than toggle them.
				if ( 'sort' === select.getAttribute( 'data-dp-filter' ) ) {
					self.reorder();
				}

				self.filter();
			} );
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! self.root.contains( event.target ) ) {
				self.close();
			}
		} );
	};

	/**
	 * The current value of one filter select, or '' when the block omits it.
	 *
	 * @param {string} name Filter name.
	 * @return {string} Selected value.
	 */
	Index.prototype.filterValue = function ( name ) {
		for ( var i = 0; i < this.selects.length; i++ ) {
			if ( this.selects[ i ].getAttribute( 'data-dp-filter' ) === name ) {
				return this.selects[ i ].value || '';
			}
		}

		return '';
	};

	/**
	 * Show or hide the filter row.
	 *
	 * @param {boolean} open Whether the row is open.
	 */
	Index.prototype.collapse = function ( open ) {
		if ( ! this.moreBtn || ! this.filters ) {
			return;
		}

		this.moreBtn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		this.filters.hidden = ! open;
		this.root.classList.toggle( 'dp-index--filters-open', open );
	};

	/**
	 * Reverse each list for Z → A and back for A → Z.
	 *
	 * The server prints the rows in the chosen order already, so this only has
	 * to handle a change made after load. Reversing the current DOM order rather
	 * than sorting by a key keeps the two directions exact mirrors of each
	 * other — sorting client-side with a different collation than PHP used would
	 * quietly reshuffle names the A–Z bar has already filed under a letter.
	 */
	Index.prototype.reorder = function () {
		var desc = 'name_desc' === this.filterValue( 'sort' );

		this.panels.forEach( function ( panel ) {
			var list = panel.querySelector( '[data-dp-rows]' );
			if ( ! list ) {
				return;
			}

			var rows = Array.prototype.slice.call( list.querySelectorAll( '[data-dp-row]' ) );
			if ( rows.length < 2 ) {
				return;
			}

			var reversed = list.getAttribute( 'data-dp-reversed' ) === '1';
			if ( reversed === desc ) {
				return;
			}

			rows.reverse().forEach( function ( row ) {
				list.appendChild( row );
			} );
			list.setAttribute( 'data-dp-reversed', desc ? '1' : '0' );
		} );
	};

	/**
	 * Enable the filter row only on a tab its filters can act on.
	 *
	 * Gender and Languages describe doctors, so on a list of departments,
	 * specialties or symptoms they have nothing to match — and a live control
	 * that changes nothing is worse than one that is plainly unavailable. The
	 * whole row goes with them rather than just those two: Sort is the only
	 * survivor, and leaving a "More Filters" button that opens onto a single
	 * control reads as something half-broken.
	 *
	 * The values are kept, not reset, so returning to By Doctor Name restores
	 * the visitor's choice instead of quietly discarding it.
	 */
	Index.prototype.syncFilters = function () {
		if ( ! this.moreBtn ) {
			return;
		}

		var panel = null;
		var active = this.active;

		this.panels.forEach( function ( candidate ) {
			if ( candidate.getAttribute( 'data-dp-panel' ) === active ) {
				panel = candidate;
			}
		} );

		// Enabled only while the doctor-name list is open — the one list these
		// filters can act on. That covers the closed state the block loads in
		// as well as the term tabs.
		var off = ! panel || 'names' !== panel.getAttribute( 'data-dp-type' );

		this.moreBtn.disabled = off;
		this.moreBtn.setAttribute( 'aria-disabled', off ? 'true' : 'false' );

		if ( off ) {
			this.collapse( false );
		}
	};

	/**
	 * Show one panel and mark its tab selected.
	 *
	 * @param {string} key Panel key.
	 */
	Index.prototype.setTab = function ( key ) {
		// Pressing the open one closes it, back to the bare search bar the block
		// loads in. Without that the starting state would be unreachable after
		// the first press — a section you can open but never shut.
		var next = ( key && key !== this.active ) ? key : '';

		this.active = next;
		// A letter belongs to the list it was picked on, not to the block.
		this.letter = '';

		this.tabs.forEach( function ( tab ) {
			var on = '' !== next && tab.getAttribute( 'data-dp-tab' ) === next;
			tab.classList.toggle( 'is-active', on );
			tab.setAttribute( 'aria-expanded', on ? 'true' : 'false' );
		} );

		this.panels.forEach( function ( panel ) {
			panel.hidden = panel.getAttribute( 'data-dp-panel' ) !== next;
		} );

		this.syncFilters();
		this.filter();
	};

	/**
	 * Hide the rows that do not match the active letter and typed text. Rows are
	 * never removed, so clearing the box restores the full list with no request.
	 */
	Index.prototype.filter = function () {
		var letter = this.letter;
		var query = normalize( this.query );
		var gender = this.filterValue( 'gender' );
		var language = this.filterValue( 'language' );

		this.panels.forEach( function ( panel ) {
			var shown = 0;

			panel.querySelectorAll( '[data-dp-row]' ).forEach( function ( row ) {
				var okLetter = ! letter || ( row.getAttribute( 'data-dp-letter' ) || '' ) === letter;
				var okQuery = ! query || ( row.getAttribute( 'data-dp-text' ) || '' ).indexOf( query ) !== -1;

				// Only doctor rows carry these attributes. A term row has no
				// gender to compare, so it must pass rather than vanish — those
				// filters reach it through its link instead.
				var rowGender = row.getAttribute( 'data-dp-gender' );
				var okGender = ! gender || null === rowGender || rowGender === gender;

				var rowLangs = row.getAttribute( 'data-dp-langs' );
				var okLanguage = ! language
					|| null === rowLangs
					|| ( ' ' + rowLangs + ' ' ).indexOf( ' ' + language + ' ' ) !== -1;

				var visible = okLetter && okQuery && okGender && okLanguage;

				row.hidden = ! visible;
				if ( visible ) {
					shown += 1;
				}
			} );

			panel.querySelectorAll( '[data-dp-letter]' ).forEach( function ( button ) {
				button.classList.toggle( 'is-active', ( button.getAttribute( 'data-dp-letter' ) || '' ) === letter );
			} );

			var none = panel.querySelector( '[data-dp-none]' );
			if ( none ) {
				none.hidden = shown !== 0;
			}

			var count = panel.querySelector( '[data-dp-count]' );
			if ( count ) {
				var noun = shown === 1
					? panel.getAttribute( 'data-dp-single' )
					: panel.getAttribute( 'data-dp-plural' );
				count.textContent = shown + ' ' + ( noun || '' );
			}
		} );
	};

	/**
	 * Submitting without picking anything hands the typed text to the search
	 * page, which is the only place that can answer it.
	 */
	Index.prototype.go = function () {
		var query = String( this.query || '' ).trim();
		var base = safeUrl( this.searchPage );

		if ( ! base ) {
			return;
		}

		var url = base + ( base.indexOf( '?' ) === -1 ? '?' : '&' ) + 'q=' + encodeURIComponent( query );
		window.location.href = url;
	};

	// ---------------------------------------------------------------- suggest

	Index.prototype.scheduleSuggest = function () {
		var self = this;

		if ( ! this.suggestOn ) {
			return;
		}

		window.clearTimeout( this.timer );

		if ( normalize( this.query ).length < ( CONFIG.minChars || 2 ) ) {
			this.close();
			return;
		}

		this.timer = window.setTimeout( function () {
			self.fetchSuggest();
		}, 180 );
	};

	Index.prototype.fetchSuggest = function () {
		var self = this;
		var base = CONFIG.restBase;

		if ( ! base ) {
			return;
		}

		// One request in flight: an older answer must never overwrite a newer one.
		if ( this.controller && typeof this.controller.abort === 'function' ) {
			this.controller.abort();
		}
		this.controller = typeof window.AbortController === 'function' ? new window.AbortController() : null;

		var url = base.replace( /\/$/, '' ) + '/doctors/suggest?q=' + encodeURIComponent( this.query );

		window.fetch( url, {
			credentials: 'same-origin',
			signal: this.controller ? this.controller.signal : undefined
		} )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( function ( body ) {
				self.render( body && body.data ? body.data : [] );
			} )
			.catch( function () {
				/* Aborted or offline: leave whatever is on screen. */
			} );
	};

	/**
	 * Group the suggestions by type and draw them. A doctor carries its own
	 * profile URL; a term is turned into a filter on the search page here,
	 * because the target page is this widget's choice, not the API's.
	 *
	 * @param {Array} rows Suggestion rows.
	 */
	Index.prototype.render = function ( rows ) {
		if ( ! this.panel ) {
			return;
		}

		var groups = [
			{ type: 'doctor', label: text( 'doctors', 'Doctors' ), param: '' },
			{ type: 'department', label: text( 'departments', 'Departments' ), param: 'department' },
			{ type: 'specialty', label: text( 'specialties', 'Specialties' ), param: 'specialty' },
			{ type: 'symptom', label: text( 'symptoms', 'Symptoms' ), param: 'symptom' }
		];

		var searchPage = safeUrl( this.searchPage );
		var html = '';
		this.items = [];
		var self = this;

		groups.forEach( function ( group ) {
			var matches = rows.filter( function ( row ) {
				return row && row.type === group.type;
			} );

			if ( ! matches.length ) {
				return;
			}

			var body = '';

			matches.forEach( function ( row ) {
				var url = '';

				if ( group.param ) {
					if ( searchPage && row.slug ) {
						url = searchPage + ( searchPage.indexOf( '?' ) === -1 ? '?' : '&' ) +
							group.param + '=' + encodeURIComponent( row.slug );
					}
				} else {
					url = safeUrl( row.url );
				}

				if ( ! url ) {
					return;
				}

				var id = self.optionId( self.items.length );
				self.items.push( url );
				body += '<a class="dp-index__suggest-item" role="option" id="' + escapeAttr( id ) +
					'" href="' + escapeAttr( url ) + '">' + escapeHtml( row.label ) + '</a>';
			} );

			if ( ! body ) {
				return;
			}

			var all = '';
			if ( group.param && searchPage ) {
				all = '<a class="dp-index__suggest-all" href="' + escapeAttr( searchPage ) + '">' +
					escapeHtml( text( 'viewAll', 'View all' ) ) + '</a>';
			}

			// The title carries the "View all" link, so it sits outside the group:
			// a listbox may only own options and groups.
			html += '<p class="dp-index__suggest-title">' + escapeHtml( group.label ) + all + '</p>' +
				'<div class="dp-index__suggest-group" role="group" aria-label="' +
				escapeAttr( group.label ) + '">' + body + '</div>';
		} );

		if ( ! html ) {
			html = '<p class="dp-index__suggest-empty">' + escapeHtml( text( 'noMatches', 'No matches.' ) ) + '</p>';
		}

		this.panel.innerHTML = html;
		this.cursor = -1;
		this.open();
	};

	/**
	 * A stable id per option, so `aria-activedescendant` can name one.
	 *
	 * @param {number} i Option position.
	 * @return {string} Element id.
	 */
	Index.prototype.optionId = function ( i ) {
		if ( ! this.uid ) {
			this.uid = ( this.input && this.input.id ? this.input.id : 'dp-index' ) + '-opt-';
		}

		return this.uid + i;
	};

	Index.prototype.open = function () {
		if ( ! this.panel ) {
			return;
		}

		this.panel.hidden = false;
		if ( this.input ) {
			this.input.setAttribute( 'aria-expanded', 'true' );
		}
	};

	Index.prototype.close = function () {
		if ( ! this.panel ) {
			return;
		}

		this.panel.hidden = true;
		this.cursor = -1;
		if ( this.input ) {
			this.input.setAttribute( 'aria-expanded', 'false' );
			this.input.removeAttribute( 'aria-activedescendant' );
		}
	};

	Index.prototype.onKey = function ( event ) {
		var links = this.panel ? Array.prototype.slice.call( this.panel.querySelectorAll( '.dp-index__suggest-item' ) ) : [];

		if ( 'Escape' === event.key ) {
			this.close();
			return;
		}

		if ( 'Enter' === event.key ) {
			event.preventDefault();
			if ( this.cursor >= 0 && links[ this.cursor ] ) {
				window.location.href = links[ this.cursor ].getAttribute( 'href' );
				return;
			}
			this.go();
			return;
		}

		if ( 'ArrowDown' !== event.key && 'ArrowUp' !== event.key ) {
			return;
		}

		if ( ! links.length ) {
			return;
		}

		event.preventDefault();
		this.cursor += 'ArrowDown' === event.key ? 1 : -1;

		if ( this.cursor < 0 ) {
			this.cursor = links.length - 1;
		}
		if ( this.cursor >= links.length ) {
			this.cursor = 0;
		}

		links.forEach( function ( link, i ) {
			link.classList.toggle( 'is-active', i === this.cursor );
			link.setAttribute( 'aria-selected', i === this.cursor ? 'true' : 'false' );
		}, this );

		if ( this.input ) {
			this.input.setAttribute( 'aria-activedescendant', links[ this.cursor ].id || '' );
		}

		links[ this.cursor ].scrollIntoView( { block: 'nearest' } );
	};

	function escapeHtml( value ) {
		return String( value == null ? '' : value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function escapeAttr( value ) {
		return escapeHtml( value ).replace( /'/g, '&#39;' );
	}

	function boot( scope ) {
		( scope || document ).querySelectorAll( '[data-dp-index-widget]' ).forEach( function ( root ) {
			if ( ! root.dpIndex ) {
				root.dpIndex = new Index( root );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			boot( document );
		} );
	} else {
		boot( document );
	}

	// Elementor rebuilds a widget's DOM after an edit, so re-boot the new copy.
	window.addEventListener( 'elementor/frontend/init', function () {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction(
				'frontend/element_ready/doctorpilot-doctor-index.default',
				function ( $scope ) {
					boot( $scope && $scope[ 0 ] ? $scope[ 0 ] : document );
				}
			);
		}
	} );
}() );
