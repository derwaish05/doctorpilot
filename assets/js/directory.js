/**
 * DoctorPilot directory widget: no-reload search, filters, and A–Z index.
 *
 * Progressive enhancement over the server-rendered markup in
 * `templates/directory.php`. Every instance on the page is initialised
 * independently and scopes all lookups to its own root, so several widgets can
 * coexist. Only the first instance writes History API state, so two widgets
 * never fight over the shared query string. With JavaScript disabled the same
 * markup still works as a plain GET form.
 *
 * @package BrainStudioz\DoctorPilot
 */
( function () {
	'use strict';

	var config = window.doctorPilotDirectory || {};
	var strings = config.strings || {};
	var AZ_MAX_PAGES = 10;
	var MAX_PER_PAGE = 48;

	/**
	 * Translate with a %d placeholder.
	 */
	function format( template, value ) {
		return String( template || '%d' ).replace( '%d', value );
	}

	/**
	 * Only allow links the browser can safely follow.
	 */
	function safeUrl( url ) {
		return /^(https?:|\/|#|\?)/i.test( String( url || '' ) ) ? String( url ) : '';
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
	 * Strip an honorific so grouping matches the server-side letter logic.
	 */
	function letterFor( name ) {
		var clean = String( name || '' ).replace( /^\s*(dr|prof|mr|mrs|ms)\.?\s+/i, '' );
		var first = clean.charAt( 0 ).toUpperCase();

		return /^[A-Z]$/.test( first ) ? first : '#';
	}

	/**
	 * One directory widget instance.
	 */
	function Directory( root, syncUrl ) {
		this.root = root;
		this.syncUrl = syncUrl;
		this.form = root.querySelector( '.dp-dir__form' );
		this.results = root.querySelector( '[data-dp-results]' );
		this.count = root.querySelector( '[data-dp-count]' );
		this.badge = root.querySelector( '[data-dp-active-count]' );
		this.clearBtn = root.querySelector( '[data-dp-clear]' );
		this.moreBtn = root.querySelector( '.dp-dir__more' );
		this.filters = root.querySelector( '[data-dp-collapsible]' );
		this.azNav = root.querySelector( '.dp-dir__az' );
		this.restBase = root.getAttribute( 'data-rest' ) || '';
		this.layout = root.getAttribute( 'data-layout' ) || 'grid';
		this.columns = root.getAttribute( 'data-columns' ) || '3';
		this.perPage = parseInt( root.getAttribute( 'data-per-page' ), 10 ) || 12;
		this.defaultSort = root.getAttribute( 'data-default-sort' ) || 'name';
		this.isAz = root.getAttribute( 'data-az' ) === '1';
		this.controller = null;
		this.timer = null;
		this.letter = '';

		this.bind();
	}

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
				self.search();
			} );

			this.form.addEventListener( 'change', function ( event ) {
				var target = event.target;
				if ( ! target ) {
					return;
				}
				if ( target.tagName === 'SELECT' || target.type === 'checkbox' || target.type === 'radio' ) {
					self.search();
				}
			} );

			this.form.addEventListener( 'input', function ( event ) {
				if ( ! event.target || event.target.type !== 'search' ) {
					return;
				}
				window.clearTimeout( self.timer );
				self.timer = window.setTimeout( function () {
					self.search();
				}, 300 );
			} );

			// Let a checked mode tab be unchecked, returning to an unscoped search.
			this.form.addEventListener( 'click', function ( event ) {
				var label = event.target.closest ? event.target.closest( '.dp-dir__mode' ) : null;
				if ( ! label ) {
					return;
				}
				var input = document.getElementById( label.getAttribute( 'for' ) );
				if ( input && input.checked ) {
					event.preventDefault();
					input.checked = false;
					self.search();
				}
			} );
		}

		if ( this.clearBtn ) {
			this.clearBtn.addEventListener( 'click', function () {
				self.clear();
			} );
		}

		if ( this.azNav ) {
			this.azNav.addEventListener( 'click', function ( event ) {
				var item = event.target.closest ? event.target.closest( '[data-dp-letter]' ) : null;
				if ( ! item || item.classList.contains( 'is-empty' ) ) {
					return;
				}
				event.preventDefault();
				var value = item.getAttribute( 'data-dp-letter' );
				self.letter = self.letter === value ? '' : value;
				self.applyLetter();
			} );
		}

		this.results.addEventListener( 'click', function ( event ) {
			var link = event.target.closest ? event.target.closest( 'a.page-numbers' ) : null;
			if ( ! link ) {
				return;
			}
			event.preventDefault();
			var match = link.href.match( /[?&]paged?=(\d+)/ );
			self.search( match ? match[ 1 ] : '1' );
			self.results.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		} );
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
	 * Count the filters the visitor applied (everything except the free-text query).
	 */
	Directory.prototype.activeCount = function ( params ) {
		var self = this;

		return Object.keys( params ).filter( function ( key ) {
			if ( key === 'q' || key === 'page' || key === 'per_page' || key === 'mode' ) {
				return false;
			}
			if ( key === 'sort' ) {
				return params.sort !== self.defaultSort;
			}

			return params[ key ] !== '';
		} ).length;
	};

	/**
	 * Reset every control and re-run the search.
	 */
	Directory.prototype.clear = function () {
		var defaultSort = this.defaultSort;

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
				} else if ( el.type === 'search' ) {
					el.value = '';
				}
			} );
		}
		this.letter = '';
		this.search();
	};

	/**
	 * Run a search, cancelling any in-flight request.
	 */
	Directory.prototype.search = function ( page ) {
		var self = this;
		var params = this.collectParams();

		if ( page ) {
			params.page = page;
		}

		if ( this.syncUrl ) {
			window.history.replaceState(
				{ dpDirectory: true },
				'',
				window.location.pathname + ( toQuery( params ) ? '?' + toQuery( params ) : '' )
			);
		}

		this.setBusy( true );
		this.updateChrome( params );

		if ( this.controller ) {
			this.controller.abort();
		}
		this.controller = typeof AbortController !== 'undefined' ? new AbortController() : null;

		var request = this.isAz ? this.fetchAll( params ) : this.fetchPage( params, params.page || 1, this.perPage );

		request
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
	 * Walk pages until every match is loaded (bounded), for the A–Z index.
	 */
	Directory.prototype.fetchAll = function ( params ) {
		var self = this;
		var collected = [];

		function next( page, first ) {
			return self.fetchPage( params, page, MAX_PER_PAGE ).then( function ( payload ) {
				collected = collected.concat( payload.data || [] );
				var meta = payload.meta || {};
				var totalPages = parseInt( meta.total_pages, 10 ) || 1;

				if ( page < totalPages && page < AZ_MAX_PAGES ) {
					return next( page + 1, first || payload );
				}

				var base = first || payload;

				return {
					data: collected,
					meta: base.meta || {},
					truncated: collected.length < ( parseInt( meta.total, 10 ) || 0 )
				};
			} );
		}

		return next( 1, null );
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
			this.clearBtn.hidden = active === 0 && ! params.q;
		}
	};

	/**
	 * Toggle the busy state on the results region.
	 */
	Directory.prototype.setBusy = function ( busy ) {
		this.results.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		this.root.classList.toggle( 'dp-dir--loading', busy );
	};

	/**
	 * Build a full doctor card, mirroring `templates/card-doctor.php`.
	 */
	Directory.prototype.renderCard = function ( card ) {
		var article = document.createElement( 'article' );
		article.className = 'dp-card';

		var media = document.createElement( 'div' );
		media.className = 'dp-card__media';

		if ( card.photo && safeUrl( card.photo.url ) ) {
			var img = document.createElement( 'img' );
			img.src = safeUrl( card.photo.url );
			img.alt = card.photo.alt || card.name || '';
			img.loading = 'lazy';
			media.appendChild( img );
		} else {
			var placeholder = document.createElement( 'span' );
			placeholder.className = 'dp-card__placeholder';
			placeholder.setAttribute( 'aria-hidden', 'true' );
			media.appendChild( placeholder );
		}

		if ( card.is_featured ) {
			media.appendChild( this.badgeEl( 'dp-badge dp-badge--featured', strings.featured ) );
		}
		if ( card.is_international ) {
			media.appendChild( this.badgeEl( 'dp-badge dp-badge--international', strings.international ) );
		}
		article.appendChild( media );

		var body = document.createElement( 'div' );
		body.className = 'dp-card__body';

		var heading = document.createElement( 'h3' );
		heading.className = 'dp-card__name';
		heading.appendChild( this.linkEl( card.url, card.name ) );
		body.appendChild( heading );

		if ( card.credentials ) {
			body.appendChild( this.textEl( 'p', 'dp-card__credentials', card.credentials ) );
		}

		var specialty = ( card.primary_specialty && card.primary_specialty.name ) ||
			( card.primary_department && card.primary_department.name ) || '';
		if ( specialty ) {
			body.appendChild( this.textEl( 'p', 'dp-card__specialty', specialty ) );
		}

		var meta = document.createElement( 'ul' );
		meta.className = 'dp-card__meta';

		if ( card.years_experience > 0 ) {
			var years = card.years_experience === 1 ? strings.yearSingular : strings.yearPlural;
			meta.appendChild( this.textEl( 'li', '', format( years, card.years_experience ) ) );
		}
		if ( card.languages && card.languages.length ) {
			meta.appendChild( this.textEl( 'li', '', card.languages.map( function ( lang ) {
				return lang.name;
			} ).join( ', ' ) ) );
		}
		if ( card.accepting_new_patients ) {
			meta.appendChild( this.textEl( 'li', 'dp-accepting', strings.acceptingNew ) );
		}
		if ( meta.childNodes.length ) {
			body.appendChild( meta );
		}

		var actions = document.createElement( 'div' );
		actions.className = 'dp-card__actions';

		var profile = this.linkEl( card.url, strings.viewProfile );
		profile.className = 'dp-btn dp-btn--secondary';
		actions.appendChild( profile );

		if ( card.booking && safeUrl( card.booking.url ) ) {
			var book = this.linkEl( card.booking.url, card.booking.label );
			book.className = 'dp-btn dp-btn--primary';
			if ( card.booking.target === '_blank' ) {
				book.target = '_blank';
				book.rel = 'noopener';
			}
			actions.appendChild( book );
		}
		body.appendChild( actions );

		article.appendChild( body );

		return article;
	};

	/**
	 * Build a compact list row, mirroring `templates/row-doctor.php`.
	 */
	Directory.prototype.renderRow = function ( card ) {
		var article = document.createElement( 'article' );
		article.className = 'dp-dir__row';

		var link = this.linkEl( card.url, card.name );
		link.className = 'dp-dir__row-name';
		article.appendChild( link );

		var meta = ( card.primary_specialty && card.primary_specialty.name ) ||
			( card.primary_department && card.primary_department.name ) || '';
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

	Directory.prototype.badgeEl = function ( className, text ) {
		return this.textEl( 'span', className, text );
	};

	/**
	 * Paint a results payload.
	 */
	Directory.prototype.render = function ( payload ) {
		var self = this;
		var cards = payload.data || [];
		var meta = payload.meta || {};
		var total = parseInt( meta.total, 10 ) || 0;

		this.setBusy( false );
		this.results.textContent = '';

		if ( this.count ) {
			this.count.textContent = format( total === 1 ? strings.countSingular : strings.countPlural, total );
		}

		if ( ! cards.length ) {
			this.results.appendChild( this.textEl( 'p', 'dp-empty', strings.empty ) );
			this.refreshAz( {} );

			return;
		}

		if ( this.isAz ) {
			var groups = {};
			cards.forEach( function ( card ) {
				var letter = letterFor( card.name );
				groups[ letter ] = groups[ letter ] || [];
				groups[ letter ].push( card );
			} );

			Object.keys( groups ).sort( function ( a, b ) {
				if ( a === '#' ) {
					return b === '#' ? 0 : -1;
				}
				if ( b === '#' ) {
					return 1;
				}

				return a < b ? -1 : 1;
			} ).forEach( function ( letter ) {
				var section = document.createElement( 'section' );
				section.className = 'dp-dir__group';
				section.setAttribute( 'data-dp-group', letter );
				section.id = self.root.id + '-az-' + ( letter === '#' ? 'num' : letter );
				section.appendChild( self.textEl( 'h3', 'dp-dir__group-letter', letter ) );

				var rows = document.createElement( 'div' );
				rows.className = 'dp-dir__rows dp-cols-' + self.columns;
				groups[ letter ].forEach( function ( card ) {
					rows.appendChild( self.renderRow( card ) );
				} );
				section.appendChild( rows );
				self.results.appendChild( section );
			} );

			if ( payload.truncated ) {
				this.results.appendChild( this.textEl( 'p', 'dp-dir__notice', format( strings.truncated, total ) ) );
			}

			// A letter the new result set no longer contains would hide every
			// group, leaving an empty region under a non-zero count.
			if ( this.letter && ! Object.prototype.hasOwnProperty.call( groups, this.letter ) ) {
				this.letter = '';
			}

			this.refreshAz( groups );
			this.applyLetter();

			return;
		}

		var container = document.createElement( 'div' );

		if ( this.layout === 'list' ) {
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

		this.results.appendChild( container );
		this.renderPagination( meta );
	};

	/**
	 * Rebuild simple numeric pagination for the current page.
	 */
	Directory.prototype.renderPagination = function ( meta ) {
		var totalPages = parseInt( meta.total_pages, 10 ) || 1;
		var current = parseInt( meta.page, 10 ) || 1;

		if ( totalPages < 2 ) {
			return;
		}

		var nav = document.createElement( 'nav' );
		nav.className = 'dp-dir__pagination';
		nav.setAttribute( 'aria-label', strings.pagination || 'Pages' );

		for ( var page = 1; page <= totalPages; page++ ) {
			if ( page === current ) {
				var currentEl = this.textEl( 'span', 'page-numbers current', page );
				currentEl.setAttribute( 'aria-current', 'page' );
				nav.appendChild( currentEl );
			} else {
				var link = this.textEl( 'a', 'page-numbers', page );
				link.href = '?paged=' + page;
				nav.appendChild( link );
			}
		}

		this.results.appendChild( nav );
	};

	/**
	 * Mark A–Z letters that have no results in the current payload.
	 */
	Directory.prototype.refreshAz = function ( groups ) {
		if ( ! this.azNav ) {
			return;
		}

		Array.prototype.forEach.call( this.azNav.querySelectorAll( '[data-dp-letter]' ), function ( item ) {
			var has = Object.prototype.hasOwnProperty.call( groups, item.getAttribute( 'data-dp-letter' ) );
			item.classList.toggle( 'is-empty', ! has );
			if ( has ) {
				item.removeAttribute( 'aria-disabled' );
			} else {
				item.setAttribute( 'aria-disabled', 'true' );
			}
		} );
	};

	/**
	 * Show only the selected letter group (empty selection shows all).
	 */
	Directory.prototype.applyLetter = function () {
		var letter = this.letter;

		Array.prototype.forEach.call( this.results.querySelectorAll( '[data-dp-group]' ), function ( group ) {
			group.hidden = letter !== '' && group.getAttribute( 'data-dp-group' ) !== letter;
		} );

		if ( this.azNav ) {
			Array.prototype.forEach.call( this.azNav.querySelectorAll( '[data-dp-letter]' ), function ( item ) {
				item.classList.toggle( 'is-active', letter !== '' && item.getAttribute( 'data-dp-letter' ) === letter );
			} );
		}
	};

	/**
	 * Replace the results with a recoverable error message.
	 */
	Directory.prototype.renderError = function () {
		this.setBusy( false );
		this.results.textContent = '';
		var message = this.textEl( 'p', 'dp-error', strings.error );
		message.setAttribute( 'role', 'alert' );
		this.results.appendChild( message );
	};

	/**
	 * Boot every directory on the page (and any added later by Elementor).
	 */
	function init( scope ) {
		var roots = ( scope || document ).querySelectorAll( '[data-dp-directory]' );

		Array.prototype.forEach.call( roots, function ( root, index ) {
			if ( root.dpDirectory ) {
				return;
			}
			root.dpDirectory = new Directory( root, index === 0 && document.querySelectorAll( '[data-dp-directory]' ).length === 1 );
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
