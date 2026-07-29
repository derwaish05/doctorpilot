/**
 * DoctorPilot interactive directory search.
 *
 * Progressive enhancement over the server-rendered `[doctorpilot_search]` form:
 * no-reload search via the REST API, History API state, debounced input, and
 * stale-request cancellation. With JavaScript disabled the form still works as
 * a plain GET request.
 *
 * @package BrainStudioz\DoctorPilot
 */
( function () {
	'use strict';

	var config = window.doctorPilotSearch || {};
	if ( ! config.restBase ) {
		return;
	}

	var root = document.querySelector( '[data-dp-search]' );
	if ( ! root ) {
		return;
	}

	var form = root.querySelector( '.dp-search__form' );
	var results = root.querySelector( '#dp-results' );
	var count = root.querySelector( '#dp-count' );
	var input = root.querySelector( '#dp-q' );
	var controller = null;
	var debounceTimer = null;

	/**
	 * Collect the current form state into a flat params object.
	 */
	function collectParams() {
		var params = {};
		var grouped = {};

		Array.prototype.forEach.call( form.elements, function ( el ) {
			if ( ! el.name || el.disabled ) {
				return;
			}
			var name = el.name.replace( /\[\]$/, '' );

			if ( el.type === 'checkbox' ) {
				if ( ! el.checked ) {
					return;
				}
				if ( el.name.slice( -2 ) === '[]' ) {
					grouped[ name ] = grouped[ name ] || [];
					grouped[ name ].push( el.value );
				} else {
					params[ name ] = el.value;
				}
			} else if ( el.value !== '' ) {
				params[ name ] = el.value;
			}
		} );

		Object.keys( grouped ).forEach( function ( key ) {
			params[ key ] = grouped[ key ].join( ',' );
		} );

		return params;
	}

	/**
	 * Serialize params to a query string.
	 */
	function toQuery( params ) {
		return Object.keys( params )
			.filter( function ( k ) {
				return params[ k ] !== '' && params[ k ] != null;
			} )
			.map( function ( k ) {
				return encodeURIComponent( k ) + '=' + encodeURIComponent( params[ k ] );
			} )
			.join( '&' );
	}

	/**
	 * Announce and set the result count.
	 */
	function setCount( total ) {
		if ( ! count ) {
			return;
		}
		var tmpl = ( config.strings && config.strings.resultsCount ) || '%d';
		count.textContent = tmpl.replace( '%d', total );
	}

	/**
	 * Build a card element from a view-model object (safe DOM, no innerHTML).
	 */
	function renderCard( card ) {
		var article = document.createElement( 'article' );
		article.className = 'dp-card';

		var media = document.createElement( 'div' );
		media.className = 'dp-card__media';
		if ( card.photo && card.photo.url ) {
			var img = document.createElement( 'img' );
			img.src = card.photo.url;
			img.alt = card.photo.alt || card.name;
			img.loading = 'lazy';
			media.appendChild( img );
		} else {
			var ph = document.createElement( 'span' );
			ph.className = 'dp-card__placeholder';
			media.appendChild( ph );
		}
		article.appendChild( media );

		var body = document.createElement( 'div' );
		body.className = 'dp-card__body';

		var h3 = document.createElement( 'h3' );
		h3.className = 'dp-card__name';
		var link = document.createElement( 'a' );
		link.href = card.url;
		link.textContent = card.name;
		h3.appendChild( link );
		body.appendChild( h3 );

		if ( card.credentials ) {
			var cred = document.createElement( 'p' );
			cred.className = 'dp-card__credentials';
			cred.textContent = card.credentials;
			body.appendChild( cred );
		}

		var specialty = ( card.primary_specialty && card.primary_specialty.name ) ||
			( card.primary_department && card.primary_department.name ) || '';
		if ( specialty ) {
			var sp = document.createElement( 'p' );
			sp.className = 'dp-card__specialty';
			sp.textContent = specialty;
			body.appendChild( sp );
		}

		var actions = document.createElement( 'div' );
		actions.className = 'dp-card__actions';
		var view = document.createElement( 'a' );
		view.className = 'dp-btn dp-btn--secondary';
		view.href = card.url;
		view.textContent = ( config.strings && config.strings.viewProfile ) || 'View Profile';
		actions.appendChild( view );

		if ( card.booking && card.booking.url ) {
			var book = document.createElement( 'a' );
			book.className = 'dp-btn dp-btn--primary';
			book.href = card.booking.url;
			book.textContent = card.booking.label;
			if ( card.booking.target === '_blank' ) {
				book.target = '_blank';
				book.rel = 'noopener';
			}
			actions.appendChild( book );
		}
		body.appendChild( actions );

		article.appendChild( body );
		return article;
	}

	/**
	 * Render the results payload.
	 */
	function render( payload ) {
		results.setAttribute( 'aria-busy', 'false' );
		results.innerHTML = '';

		var data = payload.data || [];
		setCount( ( payload.meta && payload.meta.total ) || 0 );

		if ( ! data.length ) {
			var empty = document.createElement( 'p' );
			empty.className = 'dp-empty';
			empty.textContent = ( config.strings && config.strings.empty ) || 'No doctors found.';
			results.appendChild( empty );
			return;
		}

		var grid = document.createElement( 'div' );
		grid.className = 'dp-directory dp-cols-3';
		data.forEach( function ( card ) {
			grid.appendChild( renderCard( card ) );
		} );
		results.appendChild( grid );
	}

	/**
	 * Perform a search request, cancelling any in-flight request.
	 */
	function search( push ) {
		var params = collectParams();
		var query = toQuery( params );

		if ( push ) {
			var url = window.location.pathname + ( query ? '?' + query : '' );
			window.history.pushState( params, '', url );
		}

		if ( controller ) {
			controller.abort();
		}
		controller = ( typeof AbortController !== 'undefined' ) ? new AbortController() : null;

		results.setAttribute( 'aria-busy', 'true' );

		fetch( config.restBase + '/doctors?' + query, {
			signal: controller ? controller.signal : undefined,
			headers: { Accept: 'application/json' }
		} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( payload ) {
				render( payload );
			} )
			.catch( function ( err ) {
				if ( err && err.name === 'AbortError' ) {
					return;
				}
				results.setAttribute( 'aria-busy', 'false' );
				results.innerHTML = '';
				var msg = document.createElement( 'p' );
				msg.className = 'dp-error';
				msg.setAttribute( 'role', 'alert' );
				msg.textContent = ( config.strings && config.strings.error ) || 'Something went wrong.';
				results.appendChild( msg );
			} );
	}

	// Prevent full-page submit; search in place.
	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		search( true );
	} );

	// Debounced typing.
	if ( input ) {
		input.addEventListener( 'input', function () {
			window.clearTimeout( debounceTimer );
			debounceTimer = window.setTimeout( function () {
				search( true );
			}, 300 );
		} );
	}

	// Filter and sort changes.
	form.addEventListener( 'change', function ( e ) {
		if ( e.target && ( e.target.type === 'checkbox' || e.target.tagName === 'SELECT' ) ) {
			search( true );
		}
	} );

	// Pagination via delegated clicks on generated links.
	results.addEventListener( 'click', function ( e ) {
		var link = e.target.closest ? e.target.closest( 'a.page-numbers' ) : null;
		if ( ! link ) {
			return;
		}
		e.preventDefault();
		var match = link.href.match( /[?&]paged?=(\d+)/ );
		var page = match ? match[ 1 ] : '1';
		var params = collectParams();
		params.page = page;
		var query = toQuery( params );
		window.history.pushState( params, '', window.location.pathname + '?' + query );
		if ( controller ) {
			controller.abort();
		}
		results.setAttribute( 'aria-busy', 'true' );
		fetch( config.restBase + '/doctors?' + query, { headers: { Accept: 'application/json' } } )
			.then( function ( r ) { return r.json(); } )
			.then( render );
	} );

	// Restore state on back/forward.
	window.addEventListener( 'popstate', function () {
		search( false );
	} );
} )();
