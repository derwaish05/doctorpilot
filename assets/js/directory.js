/**
 * DoctorPilot directory widget: no-reload search, filters, browse index, A–Z.
 *
 * Progressive enhancement over the server-rendered markup in
 * `templates/directory.php`. The page has two regions that are visible at the
 * same time: a browse index (one pre-rendered panel per tab — doctor names,
 * departments, specialties, symptoms) and the doctor results below it. A tab
 * decides which index panel is shown; picking an entry in that index writes the
 * index's own search parameter into the form and refreshes the results from the
 * public REST route. The index itself is already in the DOM, so switching tabs,
 * filtering by letter, and typing never cost a request.
 *
 * Every instance scopes its lookups to its own root, so several widgets can
 * coexist on a page. Only a lone instance writes History API state, so two
 * widgets never fight over the shared query string. With JavaScript disabled the
 * same markup works as a plain GET form: every tab, letter, and index entry is a
 * real link carrying the same state this script would have set.
 *
 * @package BrainStudioz\DoctorPilot
 */
( function () {
	'use strict';

	var config = window.doctorPilotDirectory || {};
	var strings = config.strings || {};

	var SVG_NS = 'http://www.w3.org/2000/svg';

	/**
	 * Metadata icon geometry, mirroring the `$dp_icon` helper in
	 * `templates/card-doctor.php`. Declared as data rather than markup so the
	 * renderer never needs an innerHTML sink.
	 */
	var META_ICON_PATHS = {
		experience: [
			{ tag: 'circle', attrs: { cx: '12', cy: '12', r: '9' } },
			{ tag: 'path', attrs: { d: 'M12 7v5l3 2' } }
		],
		language: [
			{ tag: 'circle', attrs: { cx: '12', cy: '12', r: '9' } },
			{ tag: 'path', attrs: { d: 'M3 12h18M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18' } }
		]
	};

	/**
	 * One skeleton card. Mirrors the real card box so swapping it for a result
	 * changes no dimensions.
	 *
	 * `withMedia` has to be passed in: a skeleton that draws a photo block for a
	 * widget with the photo switched off is taller than the card that replaces
	 * it, and the results region would jump on every keystroke — the one thing
	 * the skeleton exists to prevent.
	 */
	function buildSkeleton( withMedia ) {
		var card = document.createElement( 'div' );
		card.className = 'dp-skel';

		if ( withMedia !== false ) {
			var media = document.createElement( 'div' );
			media.className = 'dp-skel__media';
			card.appendChild( media );
		}

		var body = document.createElement( 'div' );
		body.className = 'dp-skel__body';

		[ 'dp-skel__line dp-skel__line--lg', 'dp-skel__line dp-skel__line--sm', 'dp-skel__line', 'dp-skel__line dp-skel__line--btn' ]
			.forEach( function ( className ) {
				var line = document.createElement( 'div' );
				line.className = className;
				body.appendChild( line );
			} );

		card.appendChild( body );

		return card;
	}

	/**
	 * Translate with a %d or %s placeholder.
	 */
	function format( template, value ) {
		return String( template || '%d' ).replace( '%d', value ).replace( '%s', value );
	}

	/**
	 * Up to two initials from a display name, mirroring the PHP fallback.
	 * The honorific is stripped first so "Dr. Aisha Rahman" reads AR, not DA.
	 */
	function cardInitials( name ) {
		var bare = String( name || '' ).replace( /^\s*(dr\.?|prof\.?|mr\.?|mrs\.?|ms\.?)\s+/i, '' );
		var words = bare.split( /\s+/ ).filter( Boolean );
		var out = '';

		for ( var i = 0; i < words.length && out.length < 2; i++ ) {
			out += words[ i ].charAt( 0 );
		}

		return out.toUpperCase();
	}

	/**
	 * Only allow links the browser can safely follow.
	 */
	function safeUrl( url ) {
		// Mirrors the server-side allowlist in BookingResolver: tel: and
		// mailto: are legitimate booking destinations, and omitting them meant
		// a Call button rendered on first paint then disappeared on the first
		// AJAX refresh.
		return /^(https?:|tel:|mailto:|\/|#|\?)/i.test( String( url || '' ) ) ? String( url ) : '';
	}

	/**
	 * Serialize a params object into a query string.
	 */
	function toQuery( params ) {
		return Object.keys( params )
			.filter( function ( key ) {
				return params[ key ] !== '' && params[ key ] != null;
			} )
			.map( function ( key ) {
				return encodeURIComponent( key ) + '=' + encodeURIComponent( params[ key ] );
			} )
			.join( '&' );
	}

	/**
	 * Page number from a pagination link.
	 *
	 * The first paint is rendered by `paginate_links()`, which emits pretty
	 * permalinks (`/find-a-doctor/page/2/`), while this script re-renders
	 * pagination as `?paged=N`. Both forms must be readable or the first click
	 * on a freshly loaded page would silently refetch page 1.
	 */
	function pageFromHref( href ) {
		var url = String( href || '' );
		var match = url.match( /[?&]paged?=(\d+)/ ) || url.match( /\/page\/(\d+)/ );

		return match ? match[ 1 ] : '1';
	}

	/**
	 * The current path with any pretty-permalink page segment removed.
	 *
	 * Under pretty permalinks the page number lives in the path, not the query
	 * string. Writing new state onto `/find-a-doctor/page/3/` would leave the URL
	 * claiming page 3 while the widget shows page 1, and a reload would then land
	 * somewhere the visitor never chose.
	 */
	function basePath() {
		return window.location.pathname.replace( /\/page\/\d+\/?$/, '/' );
	}

	/**
	 * Parse the mode => parameter map the server rendered onto the root.
	 */
	function parseParams( raw ) {
		try {
			var parsed = JSON.parse( raw || '{}' );

			return parsed && typeof parsed === 'object' ? parsed : {};
		} catch ( error ) {
			return {};
		}
	}

	/**
	 * One directory widget instance.
	 */
	function Directory( root, syncUrl ) {
		this.root = root;
		this.syncUrl = syncUrl;
		this.form = root.querySelector( '.dp-dir__form' );
		this.index = root.querySelector( '[data-dp-index]' );
		this.results = root.querySelector( '[data-dp-results]' );
		this.doctorPanel = root.querySelector( '[data-dp-panel="doctor"]' );
		this.badge = root.querySelector( '[data-dp-active-count]' );
		this.clearBtn = root.querySelector( '[data-dp-clear]' );
		this.moreBtn = root.querySelector( '.dp-dir__more' );
		this.filters = root.querySelector( '[data-dp-collapsible]' );
		this.restBase = root.getAttribute( 'data-rest' ) || '';

		// Language of the page this widget is on. Empty on a single-language
		// site, in which case it is never sent.
		this.lang = root.getAttribute( 'data-lang' ) || '';
		this.layout = root.getAttribute( 'data-layout' ) || 'grid';
		this.columns = root.getAttribute( 'data-columns' ) || '3';
		this.perPage = parseInt( root.getAttribute( 'data-per-page' ), 10 ) || 12;
		this.defaultSort = root.getAttribute( 'data-default-sort' ) || 'name';
		this.indexModes = ( root.getAttribute( 'data-index-modes' ) || '' ).split( ',' ).filter( Boolean );
		this.indexParams = parseParams( root.getAttribute( 'data-index-params' ) );
		this.activeIndex = root.getAttribute( 'data-active-index' ) || '';
		this.letter = root.getAttribute( 'data-letter' ) || '';
		// Presentation flags resolved server-side, so a card rebuilt after an
		// AJAX refresh carries the identical class list and structure as the
		// one PHP rendered. If these two ever diverge the grid silently
		// restyles itself on the first keystroke.
		this.cardClasses = root.getAttribute( 'data-card-classes' ) || 'dp-card';
		this.listStyle = root.getAttribute( 'data-list-style' ) || 'rows';
		this.metaIcons = root.getAttribute( 'data-meta-icons' ) !== '0';
		this.clamp = parseInt( root.getAttribute( 'data-clamp' ), 10 ) || 0;

		// Card parts switched off in the widget, as a lookup. Stored as the
		// hidden set rather than the visible one so an unknown part name always
		// reads as visible — the same defaulting `card-doctor.php` applies, which
		// is what keeps a stale cached attribute from blanking a card.
		this.hiddenParts = {};
		( root.getAttribute( 'data-card-hide' ) || '' ).split( ',' ).forEach( function ( part ) {
			var name = part.trim();
			if ( name ) {
				this.hiddenParts[ name ] = true;
			}
		}, this );

		// Opt-in parts, listed the other way round: absent means off. The
		// department line is one, because a card that has never shown it must
		// not start showing it just because a filter changed.
		this.shownParts = {};
		( root.getAttribute( 'data-card-show' ) || '' ).split( ',' ).forEach( function ( part ) {
			var name = part.trim();
			if ( name ) {
				this.shownParts[ name ] = true;
			}
		}, this );

		if ( this.hiddenParts.image ) {
			this.cardClasses += ' dp-card--no-media';
		}

		this.controller = null;
		this.timer = null;

		this.panels = {};
		var self = this;
		Array.prototype.forEach.call( root.querySelectorAll( '[data-dp-index] [data-dp-panel]' ), function ( panel ) {
			self.panels[ panel.getAttribute( 'data-dp-panel' ) ] = panel;
		} );

		this.bind();
		this.applyFilters();
	}

	/**
	 * The index panel a mode value maps to, or '' when the mode browses nothing.
	 */
	Directory.prototype.indexFor = function ( mode ) {
		return this.indexModes.indexOf( mode ) !== -1 && this.panels[ mode ] ? mode : '';
	};

	/**
	 * The currently visible index panel, or null when no tab is open.
	 */
	Directory.prototype.panel = function () {
		return this.panels[ this.activeIndex ] || null;
	};

	/**
	 * A form field by name, scoped to this widget.
	 */
	Directory.prototype.field = function ( name ) {
		return this.form ? this.form.querySelector( '[name="' + name + '"]' ) : null;
	};

	/**
	 * Wire up all interactions.
	 */
	Directory.prototype.bind = function () {
		var self = this;

		if ( this.filters ) {
			this.collapse( false );
		}

		if ( this.moreBtn && this.filters ) {
			this.moreBtn.addEventListener( 'click', function () {
				self.collapse( self.moreBtn.getAttribute( 'aria-expanded' ) !== 'true' );
			} );
		}

		if ( this.form ) {
			this.form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				window.clearTimeout( self.timer );
				self.update();
			} );

			this.form.addEventListener( 'change', function ( event ) {
				var target = event.target;
				if ( ! target ) {
					return;
				}
				if ( target.type === 'radio' && target.name === 'mode' ) {
					self.setIndex( self.indexFor( target.value ) );
				}
				if ( target.tagName === 'SELECT' || target.type === 'checkbox' || target.type === 'radio' ) {
					self.update();
				}
			} );

			// Typing filters the open index instantly and, after a pause,
			// refreshes the results — the two regions answer the same query.
			this.form.addEventListener( 'input', function ( event ) {
				if ( ! event.target || event.target.type !== 'search' ) {
					return;
				}
				window.clearTimeout( self.timer );
				self.applyFilters();

				self.timer = window.setTimeout( function () {
					self.update();
				}, 300 );
			} );

			// Let a checked tab be unchecked, closing the index.
			this.form.addEventListener( 'click', function ( event ) {
				var label = event.target.closest ? event.target.closest( '.dp-dir__mode' ) : null;
				if ( ! label ) {
					return;
				}
				var input = document.getElementById( label.getAttribute( 'for' ) );
				if ( input && input.checked ) {
					event.preventDefault();
					input.checked = false;
					self.setIndex( '' );
					self.update();
				}
			} );
		}

		if ( this.clearBtn ) {
			this.clearBtn.addEventListener( 'click', function () {
				self.clear();
			} );
		}

		// The A–Z bar and the index entries are inside the index region.
		if ( this.index ) {
			this.index.addEventListener( 'click', function ( event ) {
				if ( ! event.target.closest ) {
					return;
				}

				var azItem = event.target.closest( '.dp-dir__az-item' );
				if ( azItem ) {
					event.preventDefault();
					if ( azItem.classList.contains( 'is-empty' ) ) {
						return;
					}
					// The "all" entry's value is '', so this clears the filter
					// through the same toggle every other letter uses.
					var value = azItem.getAttribute( 'data-dp-letter' ) || '';
					self.letter = self.letter === value ? '' : value;
					self.applyFilters();
					self.pushState();

					return;
				}

				var entry = event.target.closest( '[data-dp-select]' );
				if ( entry ) {
					event.preventDefault();
					self.select( entry );
				}
			} );
		}

		// Pagination lives in the results region, which is re-rendered.
		if ( this.results ) {
			this.results.addEventListener( 'click', function ( event ) {
				var pageLink = event.target.closest ? event.target.closest( 'a.page-numbers' ) : null;
				if ( ! pageLink ) {
					return;
				}
				event.preventDefault();
				self.update( pageFromHref( pageLink.href ) );
				self.results.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			} );
		}
	};

	/**
	 * Show or hide the collapsible filter row.
	 */
	Directory.prototype.collapse = function ( open ) {
		if ( ! this.filters || ! this.moreBtn ) {
			return;
		}
		this.moreBtn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		this.filters.hidden = ! open;
		this.root.classList.toggle( 'dp-dir--filters-open', open );
	};

	/**
	 * Open one index panel, or close the index region entirely.
	 *
	 * Switching tabs drops the previous tab's selection: a department filter
	 * left behind by a tab the visitor has moved on from would keep narrowing
	 * the results with no visible control explaining why.
	 */
	Directory.prototype.setIndex = function ( key ) {
		var self = this;

		this.activeIndex = this.panels[ key ] ? key : '';
		this.letter = '';

		Object.keys( this.panels ).forEach( function ( name ) {
			self.panels[ name ].hidden = name !== self.activeIndex;
			if ( name !== self.activeIndex ) {
				self.setSelection( name, '' );
			}
		} );

		if ( this.index ) {
			this.index.hidden = this.activeIndex === '';
		}
		this.root.setAttribute( 'data-active-index', this.activeIndex );
		this.applyFilters();
	};

	/**
	 * Record an index selection in the form and mark the chosen row.
	 */
	Directory.prototype.setSelection = function ( mode, value ) {
		var param = this.indexParams[ mode ];
		var field = param ? this.field( param ) : null;
		var panel = this.panels[ mode ];

		if ( field ) {
			field.value = value;
		}

		if ( ! panel ) {
			return;
		}

		Array.prototype.forEach.call( panel.querySelectorAll( '.dp-dir__row' ), function ( row ) {
			var picked = value !== '' && row.getAttribute( 'data-dp-value' ) === value;
			var link = row.querySelector( '[data-dp-select]' );

			row.classList.toggle( 'is-selected', picked );
			if ( ! link ) {
				return;
			}
			if ( picked ) {
				link.setAttribute( 'aria-current', 'true' );
			} else {
				link.removeAttribute( 'aria-current' );
			}
		} );
	};

	/**
	 * The value currently selected in an index panel.
	 */
	Directory.prototype.selection = function ( mode ) {
		var param = this.indexParams[ mode ];
		var field = param ? this.field( param ) : null;

		return field ? field.value : '';
	};

	/**
	 * Handle a click on an index entry: select it, or clear it if it was already
	 * selected, then refresh the results.
	 */
	Directory.prototype.select = function ( link ) {
		var row = link.closest( '.dp-dir__row' );
		if ( ! row || this.activeIndex === '' ) {
			return;
		}

		var value = row.getAttribute( 'data-dp-value' ) || '';
		var next = this.selection( this.activeIndex ) === value ? '' : value;

		this.setSelection( this.activeIndex, next );
		this.update();
	};

	/**
	 * Read the current form state.
	 */
	Directory.prototype.collectParams = function () {
		var params = {};

		if ( ! this.form ) {
			return params;
		}

		Array.prototype.forEach.call( this.form.elements, function ( el ) {
			if ( ! el.name || el.disabled ) {
				return;
			}
			if ( el.type === 'checkbox' || el.type === 'radio' ) {
				if ( el.checked ) {
					params[ el.name ] = el.value;
				}

				return;
			}
			if ( el.value !== '' ) {
				params[ el.name ] = el.value;
			}
		} );

		if ( ! params.sort ) {
			params.sort = this.defaultSort;
		}

		return params;
	};

	/**
	 * Count the filters the visitor applied.
	 *
	 * Deliberately mirrors `SearchService::active_filters()`, which is what the
	 * server-rendered badge counts: free text, page size, the open tab, and sort
	 * order are excluded. Counting sort here would make the badge jump by one
	 * the first time the visitor touched any control.
	 */
	Directory.prototype.activeCount = function ( params ) {
		return Object.keys( params ).filter( function ( key ) {
			if ( key === 'q' || key === 'page' || key === 'per_page' || key === 'mode' || key === 'sort' ) {
				return false;
			}

			return params[ key ] !== '';
		} ).length;
	};

	/**
	 * Mirror the current state into the URL.
	 */
	Directory.prototype.pushState = function ( extra ) {
		if ( ! this.syncUrl ) {
			return;
		}

		var params = this.collectParams();
		if ( extra ) {
			Object.keys( extra ).forEach( function ( key ) {
				params[ key ] = extra[ key ];
			} );
		}
		if ( this.letter ) {
			params.letter = this.letter;
		}

		var query = toQuery( params );
		window.history.replaceState(
			{ dpDirectory: true },
			'',
			basePath() + ( query ? '?' + query : '' )
		);
	};

	/**
	 * Reset every control, clear all index selections, and close the index.
	 */
	Directory.prototype.clear = function () {
		var defaultSort = this.defaultSort;
		var self = this;

		if ( this.form ) {
			Array.prototype.forEach.call( this.form.elements, function ( el ) {
				if ( ! el.name ) {
					return;
				}
				if ( el.type === 'checkbox' || el.type === 'radio' ) {
					el.checked = false;
				} else if ( el.tagName === 'SELECT' ) {
					// Sort returns to the widget's configured default, not to the
					// first option — otherwise clearing leaves a filter "active".
					el.value = el.name === 'sort' ? defaultSort : '';
					if ( el.selectedIndex < 0 ) {
						el.selectedIndex = 0;
					}
				} else if ( el.type === 'search' || el.type === 'hidden' ) {
					el.value = '';
				}
			} );
		}

		Object.keys( this.panels ).forEach( function ( mode ) {
			self.setSelection( mode, '' );
		} );

		this.setIndex( '' );
		this.update();
	};

	/**
	 * Apply the current state: filter the open index locally, refetch doctors.
	 */
	Directory.prototype.update = function ( page ) {
		var params = this.collectParams();

		this.updateChrome( params );
		this.applyFilters();
		this.search( params, page );
	};

	/**
	 * Show only the index entries matching the active letter and query.
	 *
	 * The results region is never filtered here: it is refreshed from the server
	 * so its count and pagination always describe the whole match set.
	 */
	Directory.prototype.applyFilters = function () {
		var panel = this.panel();
		if ( ! panel ) {
			return;
		}

		var letter = this.letter;
		var input = this.form ? this.form.querySelector( 'input[type="search"]' ) : null;
		var needle = input ? input.value.trim().toLowerCase() : '';
		var visible = 0;
		// Letters that still have an entry under the current text query. A
		// letter the query has emptied must stop looking clickable, or it leads
		// to a blank index.
		var reachable = {};

		Array.prototype.forEach.call( panel.querySelectorAll( '.dp-dir__row' ), function ( row ) {
			var name = row.querySelector( '.dp-dir__row-name' );
			var text = name ? name.textContent.toLowerCase() : '';
			var rowLetter = row.getAttribute( 'data-dp-letter' );
			var matchesQuery = ! needle || text.indexOf( needle ) !== -1;
			var show = matchesQuery && ( ! letter || rowLetter === letter );

			if ( matchesQuery ) {
				reachable[ rowLetter ] = true;
			}

			row.hidden = ! show;
			if ( show ) {
				visible++;
			}
		} );

		Array.prototype.forEach.call( panel.querySelectorAll( '.dp-dir__az-item' ), function ( item ) {
			var value = item.getAttribute( 'data-dp-letter' ) || '';
			// Compared as equals rather than truthy, so the "all" entry — whose
			// value is the empty string, like "no letter" — lights up when
			// nothing is filtered, the way an "All" option in a select does.
			var active = value === letter;
			// The all entry is never empty: it clears the filter rather than
			// matching rows. Neither is the active letter, even once the query
			// has emptied it — it is the only control that can release itself,
			// and `is-empty` makes it unclickable.
			var has = '' === value || reachable[ value ] === true || active;

			item.classList.toggle( 'is-active', active );
			item.classList.toggle( 'is-empty', ! has );
			if ( has ) {
				item.removeAttribute( 'aria-disabled' );
			} else {
				item.setAttribute( 'aria-disabled', 'true' );
			}
		} );

		this.setIndexCount( panel, visible );
		this.setIndexEmpty( panel, visible );
	};

	/**
	 * Show an empty state when filtering an index leaves nothing visible.
	 * Without this the panel would just go blank, with no explanation.
	 */
	Directory.prototype.setIndexEmpty = function ( panel, visible ) {
		var note = panel.querySelector( '[data-dp-empty]' );
		var rows = panel.querySelector( '[data-dp-rows]' );

		if ( visible > 0 ) {
			if ( note ) {
				note.parentNode.removeChild( note );
			}

			return;
		}

		// An index with no entries at all has no rows container, and the server
		// already rendered the note. Removing it here would leave a bare count.
		if ( ! note && rows ) {
			var label = String( panel.getAttribute( 'data-dp-label' ) || '' ).toLowerCase();
			note = this.textEl( 'p', 'dp-empty', String( strings.emptyTerms || 'Nothing matched your search.' ).replace( '%s', label ) );
			note.setAttribute( 'data-dp-empty', '' );
			rows.parentNode.insertBefore( note, rows );
		}
	};

	/**
	 * Update an index panel's count line ("11 Departments").
	 */
	Directory.prototype.setIndexCount = function ( panel, visible ) {
		var count = panel.querySelector( '[data-dp-count]' );
		if ( ! count ) {
			return;
		}

		var label = visible === 1
			? panel.getAttribute( 'data-dp-label-single' )
			: panel.getAttribute( 'data-dp-label' );

		count.textContent = visible + ' ' + ( label || '' );
	};

	/**
	 * Reflect the request in the toolbar chrome.
	 */
	Directory.prototype.updateChrome = function ( params ) {
		var active = this.activeCount( params );

		if ( this.badge ) {
			this.badge.textContent = String( active );
			this.badge.hidden = active === 0;
		}
		if ( this.clearBtn ) {
			this.clearBtn.hidden = active === 0 && ! params.q && ! this.activeIndex && ! this.letter;
		}
	};

	/**
	 * Fetch doctor results, cancelling any in-flight request.
	 */
	Directory.prototype.search = function ( params, page ) {
		var self = this;

		if ( page ) {
			params.page = page;
		}

		this.pushState( page ? { page: page } : null );
		this.setBusy( true );

		if ( this.controller ) {
			this.controller.abort();
		}
		this.controller = typeof AbortController !== 'undefined' ? new AbortController() : null;

		this.fetchPage( params, params.page || 1, this.perPage )
			.then( function ( payload ) {
				self.render( payload );
			} )
			.catch( function ( error ) {
				if ( error && error.name === 'AbortError' ) {
					return;
				}
				self.renderError();
			} );
	};

	/**
	 * Fetch a single page of results.
	 */
	Directory.prototype.fetchPage = function ( params, page, perPage ) {
		var query = {};
		Object.keys( params ).forEach( function ( key ) {
			query[ key ] = params[ key ];
		} );
		query.page = page;
		query.per_page = perPage;
		delete query.letter;

		if ( this.lang ) {
			query.lang = this.lang;
		}

		return fetch( this.restBase + '/doctors?' + toQuery( query ), {
			signal: this.controller ? this.controller.signal : undefined,
			headers: { Accept: 'application/json' }
		} ).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( 'request-failed' );
			}

			return response.json();
		} );
	};

	/**
	 * Toggle the busy state on the results region.
	 */
	Directory.prototype.setBusy = function ( busy ) {
		if ( this.results ) {
			this.results.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		}
		this.root.classList.toggle( 'dp-dir--loading', busy );

		if ( busy ) {
			this.showSkeletons();
		} else {
			this.clearSkeletons();
		}
	};

	/**
	 * Show placeholder cards while a request is in flight.
	 *
	 * The skeletons are appended alongside the existing results rather than
	 * replacing them, so the region never collapses to zero height mid-request
	 * and the page does not jump. They are `aria-hidden`; the result count is
	 * already a live region and announces the real outcome once it lands.
	 */
	Directory.prototype.showSkeletons = function () {
		if ( ! this.doctorPanel || this.doctorPanel.querySelector( '[data-dp-skeletons]' ) ) {
			return;
		}

		var existing = this.doctorPanel.querySelector( '[data-dp-rows]' );
		var count = Math.min( this.perPage, 6 );

		var wrap = document.createElement( 'div' );
		if ( this.layout === 'list' && this.listStyle === 'cards' ) {
			wrap.className = 'dp-directory dp-directory--list';
		} else if ( this.layout === 'list' ) {
			wrap.className = 'dp-dir__rows';
		} else {
			wrap.className = 'dp-directory dp-cols-' + this.columns;
		}
		wrap.setAttribute( 'data-dp-skeletons', '' );
		wrap.setAttribute( 'aria-hidden', 'true' );

		for ( var i = 0; i < count; i++ ) {
			wrap.appendChild( buildSkeleton( this.showPart( 'image' ) ) );
		}

		if ( existing ) {
			existing.parentNode.insertBefore( wrap, existing );
			existing.style.display = 'none';
		} else {
			this.doctorPanel.appendChild( wrap );
		}
	};

	/**
	 * Remove skeletons and restore any rows they stood in for.
	 */
	Directory.prototype.clearSkeletons = function () {
		if ( ! this.doctorPanel ) {
			return;
		}

		var skeletons = this.doctorPanel.querySelector( '[data-dp-skeletons]' );
		if ( skeletons ) {
			skeletons.parentNode.removeChild( skeletons );
		}

		var rows = this.doctorPanel.querySelector( '[data-dp-rows]' );
		if ( rows ) {
			rows.style.display = '';
		}
	};

	/**
	 * Whether a card part survived the widget's "Card elements" switchers.
	 *
	 * Mirrors the `$dp_show()` closure in `templates/card-doctor.php`: an
	 * unlisted part is visible.
	 */
	Directory.prototype.showPart = function ( part ) {
		return ! this.hiddenParts[ part ];
	};

	/**
	 * Whether an opt-in card part was switched on.
	 *
	 * Mirrors the `$dp_opt()` closure in `templates/card-doctor.php`: an
	 * unlisted part is absent, the opposite of `showPart`.
	 */
	Directory.prototype.optPart = function ( part ) {
		return !! this.shownParts[ part ];
	};

	/**
	 * Resolve the specialty and department lines a card should print.
	 *
	 * Mirrors the block of the same name in `templates/card-doctor.php`. The two
	 * lines are resolved together because the department can supply either one
	 * and must never supply both: when it has a line of its own the specialty
	 * line stops falling back to it, so a doctor with no specialty gets one
	 * department row rather than two.
	 *
	 * @return {{specialty: string, department: string}}
	 */
	Directory.prototype.cardLines = function ( card ) {
		var specialty = ( card.primary_specialty && card.primary_specialty.name ) || '';
		var department = ( card.primary_department && card.primary_department.name ) || '';

		var departmentLine = this.optPart( 'department' ) ? department : '';
		var specialtyLine = '';

		if ( this.showPart( 'specialty' ) ) {
			specialtyLine = specialty;

			if ( ! specialtyLine && ! departmentLine && this.showPart( 'specialty_fallback' ) ) {
				specialtyLine = department;
			}
		}

		return { specialty: specialtyLine, department: departmentLine };
	};

	/**
	 * Build a full doctor card, mirroring `templates/card-doctor.php`.
	 */
	Directory.prototype.renderCard = function ( card ) {
		var article = document.createElement( 'article' );
		article.className = this.cardClasses;

		var media = document.createElement( 'div' );
		media.className = 'dp-card__media';

		if ( card.photo && safeUrl( card.photo.url ) ) {
			var img = document.createElement( 'img' );
			img.src = safeUrl( card.photo.url );
			img.alt = card.photo.alt || card.name || '';
			img.loading = 'lazy';
			img.decoding = 'async';
			// Intrinsic dimensions let the browser reserve the box before the
			// bytes arrive, which is the other half of the no-layout-shift
			// guarantee the CSS aspect-ratio box makes.
			if ( card.photo.width && card.photo.height ) {
				img.width = card.photo.width;
				img.height = card.photo.height;
			}
			media.appendChild( img );
		} else {
			var placeholder = document.createElement( 'span' );
			placeholder.className = 'dp-card__placeholder';
			placeholder.setAttribute( 'aria-hidden', 'true' );

			var initials = cardInitials( card.name );
			if ( initials ) {
				placeholder.appendChild( this.textEl( 'span', 'dp-card__initials', initials ) );
			}
			media.appendChild( placeholder );
		}

		if ( card.is_featured && this.showPart( 'badge_featured' ) ) {
			var featuredRail = document.createElement( 'div' );
			featuredRail.className = 'dp-card__badges';
			featuredRail.appendChild( this.textEl( 'span', 'dp-badge dp-badge--featured', strings.featured ) );
			media.appendChild( featuredRail );
		}
		if ( card.is_international && this.showPart( 'badge_international' ) ) {
			var intlRail = document.createElement( 'div' );
			intlRail.className = 'dp-card__badges dp-card__badges--end';
			intlRail.appendChild( this.textEl( 'span', 'dp-badge dp-badge--international', strings.international ) );
			media.appendChild( intlRail );
		}

		// Appended last so the badge rails above can still be built and simply
		// discarded with the media block they live in.
		if ( this.showPart( 'image' ) ) {
			article.appendChild( media );
		}

		var body = document.createElement( 'div' );
		body.className = 'dp-card__body';

		if ( article.className.indexOf( 'dp-card--linked' ) !== -1 && safeUrl( card.url ) ) {
			var cover = document.createElement( 'a' );
			cover.className = 'dp-card__cover';
			cover.href = safeUrl( card.url );
			cover.setAttribute( 'aria-hidden', 'true' );
			cover.setAttribute( 'tabindex', '-1' );
			body.appendChild( cover );
		}

		var heading = document.createElement( 'h3' );
		heading.className = 'dp-card__name';
		if ( safeUrl( card.url ) ) {
			heading.appendChild( this.linkEl( card.url, card.name ) );
		} else {
			heading.textContent = String( card.name == null ? '' : card.name );
		}
		body.appendChild( heading );

		var lines = this.cardLines( card );
		if ( lines.specialty ) {
			body.appendChild( this.textEl( 'p', 'dp-card__specialty', lines.specialty ) );
		}
		if ( lines.department ) {
			body.appendChild(
				this.textEl( 'p', 'dp-card__specialty dp-card__department', lines.department )
			);
		}

		var headline = String( card.headline == null ? '' : card.headline ).trim();
		if ( headline && this.showPart( 'bio' ) ) {
			body.appendChild(
				this.textEl( 'p', 'dp-card__headline' + ( this.clamp > 0 ? ' dp-clamp' : '' ), headline )
			);
		}

		var meta = document.createElement( 'ul' );
		meta.className = 'dp-card__meta';

		if ( card.years_experience > 0 && this.showPart( 'experience' ) ) {
			var years = card.years_experience === 1 ? strings.yearSingular : strings.yearPlural;
			meta.appendChild( this.metaRow( 'experience', format( years, card.years_experience ), '' ) );
		}
		if ( card.languages && card.languages.length && this.showPart( 'languages' ) ) {
			meta.appendChild( this.metaRow( 'language', card.languages.map( function ( lang ) {
				return lang.name;
			} ).join( ', ' ), '' ) );
		}
		if ( meta.childNodes.length ) {
			body.appendChild( meta );
		}

		var actions = document.createElement( 'div' );
		actions.className = 'dp-card__actions';

		if ( safeUrl( card.url ) && this.showPart( 'profile_button' ) ) {
			var profile = this.linkEl( card.url, strings.viewProfile );
			profile.className = 'dp-btn dp-btn--secondary';
			if ( strings.profileFor ) {
				profile.appendChild( this.textEl( 'span', 'dp-sr-only', format( strings.profileFor, card.name ) ) );
			}
			actions.appendChild( profile );
		}

		if ( card.booking && safeUrl( card.booking.url ) && this.showPart( 'booking_button' ) ) {
			var book = this.linkEl( card.booking.url, card.booking.label );
			book.className = 'dp-btn dp-btn--primary';
			if ( strings.bookingWith ) {
				book.appendChild( this.textEl( 'span', 'dp-sr-only', format( strings.bookingWith, card.name ) ) );
			}
			if ( card.booking.target === '_blank' ) {
				book.target = '_blank';
				book.rel = 'noopener noreferrer';
			}
			actions.appendChild( book );
		}

		// An empty actions row would still draw its divider and padding.
		if ( actions.childNodes.length ) {
			body.appendChild( actions );
		}

		article.appendChild( body );

		return article;
	};

	/**
	 * One metadata row, mirroring the `$dp_icon` helper in card-doctor.php.
	 *
	 * The icon is built with createElementNS and a static path string — never
	 * from response data — so no server value ever reaches an innerHTML sink.
	 */
	Directory.prototype.metaRow = function ( icon, text, className ) {
		var li = document.createElement( 'li' );
		if ( className ) {
			li.className = className;
		}

		if ( this.metaIcons && META_ICON_PATHS[ icon ] ) {
			var wrap = document.createElement( 'span' );
			wrap.className = 'dp-meta__icon';
			wrap.setAttribute( 'aria-hidden', 'true' );

			var svg = document.createElementNS( SVG_NS, 'svg' );
			svg.setAttribute( 'viewBox', '0 0 24 24' );
			svg.setAttribute( 'fill', 'none' );
			svg.setAttribute( 'stroke', 'currentColor' );
			svg.setAttribute( 'stroke-width', '1.8' );
			svg.setAttribute( 'stroke-linecap', 'round' );
			svg.setAttribute( 'stroke-linejoin', 'round' );
			svg.setAttribute( 'focusable', 'false' );

			META_ICON_PATHS[ icon ].forEach( function ( shape ) {
				var node = document.createElementNS( SVG_NS, shape.tag );
				Object.keys( shape.attrs ).forEach( function ( name ) {
					node.setAttribute( name, shape.attrs[ name ] );
				} );
				svg.appendChild( node );
			} );

			wrap.appendChild( svg );
			li.appendChild( wrap );
		}

		li.appendChild( this.textEl( 'span', 'dp-meta__value', text ) );

		return li;
	};

	/**
	 * Build a compact result row, mirroring `templates/row-doctor.php`.
	 */
	Directory.prototype.renderRow = function ( card ) {
		var article = document.createElement( 'article' );
		article.className = 'dp-dir__row dp-dir__row--result';

		var link = this.linkEl( card.url, card.name );
		link.className = 'dp-dir__row-name';
		article.appendChild( link );

		// A row has one line where the card has two, so the two are joined —
		// the same rule `templates/row-doctor.php` applies. Collapsing to the
		// first non-empty one instead would make the Department switch a no-op
		// for every doctor who has a specialty.
		var rowLines = this.cardLines( card );
		var meta = [ rowLines.specialty, rowLines.department ]
			.filter( function ( line ) { return !! line; } )
			.join( strings.metaSeparator || ' · ' );
		if ( meta ) {
			article.appendChild( this.textEl( 'p', 'dp-dir__row-meta', meta ) );
		}

		return article;
	};

	/**
	 * Element helpers (text nodes only — never innerHTML with response data).
	 */
	Directory.prototype.textEl = function ( tag, className, text ) {
		var el = document.createElement( tag );
		if ( className ) {
			el.className = className;
		}
		el.textContent = String( text == null ? '' : text );

		return el;
	};

	Directory.prototype.linkEl = function ( url, text ) {
		var link = document.createElement( 'a' );
		var href = safeUrl( url );
		if ( href ) {
			link.href = href;
		}
		link.textContent = String( text == null ? '' : text );

		return link;
	};

	/**
	 * Paint a doctor results payload into the results region.
	 */
	Directory.prototype.render = function ( payload ) {
		var self = this;
		var panel = this.doctorPanel;
		var cards = payload.data || [];
		var meta = payload.meta || {};
		var total = parseInt( payload.total, 10 ) || parseInt( meta.total, 10 ) || 0;

		this.setBusy( false );

		if ( ! panel ) {
			return;
		}

		var count = panel.querySelector( '[data-dp-count]' );
		if ( count ) {
			count.textContent = format( total === 1 ? strings.countSingular : strings.countPlural, total );
		}

		// Replace everything below the count line.
		Array.prototype.forEach.call(
			panel.querySelectorAll( '[data-dp-rows], .dp-empty, .dp-error, .dp-dir__pagination, .dp-dir__notice' ),
			function ( node ) {
				node.parentNode.removeChild( node );
			}
		);

		if ( ! cards.length ) {
			panel.appendChild( this.textEl( 'p', 'dp-empty', strings.empty ) );

			return;
		}

		var container = document.createElement( 'div' );
		container.setAttribute( 'data-dp-rows', '' );

		if ( this.layout === 'list' && this.listStyle === 'cards' ) {
			// Image-card list. Column count does not apply — the cards are
			// full-width rows — so the container carries no dp-cols- class.
			container.className = 'dp-directory dp-directory--list';
			cards.forEach( function ( card ) {
				container.appendChild( self.renderCard( card ) );
			} );
		} else if ( this.layout === 'list' ) {
			container.className = 'dp-dir__rows dp-cols-' + this.columns;
			cards.forEach( function ( card ) {
				container.appendChild( self.renderRow( card ) );
			} );
		} else {
			container.className = 'dp-directory dp-cols-' + this.columns;
			cards.forEach( function ( card ) {
				container.appendChild( self.renderCard( card ) );
			} );
		}
		panel.appendChild( container );

		this.renderPagination( panel, meta );
	};

	/**
	 * Rebuild simple numeric pagination for the current page.
	 */
	Directory.prototype.renderPagination = function ( panel, meta ) {
		var totalPages = parseInt( meta.total_pages, 10 ) || 1;
		var current = parseInt( meta.page, 10 ) || 1;

		if ( totalPages < 2 ) {
			return;
		}

		var nav = document.createElement( 'nav' );
		nav.className = 'dp-dir__pagination';
		nav.setAttribute( 'aria-label', strings.pagination || 'Pages' );

		// The click handler intercepts these, but the href still has to be a
		// correct destination: middle-click, "open in new tab", and copy-link all
		// bypass the handler, and a bare `?paged=N` would drop every filter.
		var state = this.collectParams();
		if ( this.letter ) {
			state.letter = this.letter;
		}
		delete state.page;

		for ( var page = 1; page <= totalPages; page++ ) {
			if ( page === current ) {
				var currentEl = this.textEl( 'span', 'page-numbers current', page );
				currentEl.setAttribute( 'aria-current', 'page' );
				nav.appendChild( currentEl );
			} else {
				var link = this.textEl( 'a', 'page-numbers', page );
				state.paged = page;
				link.href = basePath() + '?' + toQuery( state );
				nav.appendChild( link );
			}
		}

		panel.appendChild( nav );
	};

	/**
	 * Replace the results with a recoverable error message.
	 */
	Directory.prototype.renderError = function () {
		var panel = this.doctorPanel;

		this.setBusy( false );
		if ( ! panel ) {
			return;
		}

		Array.prototype.forEach.call(
			panel.querySelectorAll( '[data-dp-rows], .dp-empty, .dp-error, .dp-dir__pagination, .dp-dir__notice' ),
			function ( node ) {
				node.parentNode.removeChild( node );
			}
		);

		var message = this.textEl( 'p', 'dp-error', strings.error );
		message.setAttribute( 'role', 'alert' );
		panel.appendChild( message );
	};

	/**
	 * Boot every directory on the page (and any added later by Elementor).
	 */
	function init( scope ) {
		var roots = ( scope || document ).querySelectorAll( '[data-dp-directory]' );

		Array.prototype.forEach.call( roots, function ( root ) {
			if ( root.dpDirectory ) {
				return;
			}
			root.dpDirectory = new Directory( root, document.querySelectorAll( '[data-dp-directory]' ).length === 1 );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init( document );
		} );
	} else {
		init( document );
	}

	// Elementor re-renders widgets in the editor preview.
	window.addEventListener( 'elementor/frontend/init', function () {
		if ( ! window.elementorFrontend || ! window.elementorFrontend.hooks ) {
			return;
		}
		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/doctorpilot-grid-list.default',
			function ( $scope ) {
				init( $scope && $scope[ 0 ] ? $scope[ 0 ] : document );
			}
		);
	} );
} )();
