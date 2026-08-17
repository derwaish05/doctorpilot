/**
 * DoctorPilot — Doctor Carousel / Grid behaviour.
 *
 * The track is already a scroll-snapping scroll container before this file
 * loads, so nothing here is required for the content to be reachable. What it
 * adds is the page maths: how many pages the track holds at the current
 * breakpoint, which one is on screen, and the buttons and dots that move
 * between them. Every movement is expressed as a scroll on the real container
 * rather than a transform, so the browser's own snapping, momentum and
 * reduced-motion handling stay in charge and a mid-flick tap is never fighting
 * a script-driven animation.
 *
 * Two measurement rules follow from that, and both matter:
 *
 * A page is `slides-in-view` slides, not one viewport width. The two are not
 * the same number — a row of N slides also carries N gaps, one of which sits
 * past the viewport edge — so paging by `clientWidth` would drift by one gap
 * per page and, with a large gap set, eventually land a slide short. Movement
 * is therefore measured against the real position of the slide being moved to.
 *
 * Positions are read from `getBoundingClientRect()` and applied with
 * `scrollBy`, which are both physical-pixel coordinate systems. `scrollLeft` is
 * not: it runs negative in a right-to-left document, and a carousel built on it
 * would refuse to move on every RTL site.
 *
 * Controls are emitted `hidden` by the template and revealed here. A site with
 * JavaScript disabled or broken gets a clean scrollable strip instead of
 * buttons that do nothing.
 *
 * @package BrainStudioz\DoctorPilot
 */

( function () {
	'use strict';

	var config = window.doctorPilotCarousel || {};
	var strings = config.strings || {};

	/**
	 * Every live carousel on the page.
	 *
	 * Held at module scope for two reasons. The page-level listeners below are
	 * registered once and fan out to this list, rather than each carousel adding
	 * its own copy to `window`. And Elementor replaces a widget's whole DOM node
	 * on every editor re-render, which would otherwise leave the previous
	 * instance's interval firing against a detached tree forever — the list is
	 * what makes those findable, and stoppable.
	 *
	 * @type {Array}
	 */
	var instances = [];

	/**
	 * Whether the visitor has asked their system to reduce motion.
	 *
	 * Read on every use rather than cached: the preference can change while the
	 * page is open, and a carousel that keeps animating after the visitor
	 * changed their mind is exactly the failure the preference exists to
	 * prevent.
	 */
	function reducedMotion() {
		return !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
	}

	/**
	 * Translate with a %1$d / %2$d style placeholder pair.
	 */
	function format( template, one, two ) {
		return String( template || '' ).replace( '%1$d', one ).replace( '%2$d', two );
	}

	/**
	 * Run a callback against every carousel still in the document, dropping the
	 * ones that are not.
	 */
	function each( callback ) {
		instances = instances.filter( function ( instance ) {
			if ( ! document.contains( instance.root ) ) {
				instance.destroy();
				return false;
			}
			return true;
		} );

		instances.forEach( callback );
	}

	/**
	 * One carousel.
	 */
	function Carousel( root ) {
		this.root = root;
		this.viewport = root.querySelector( '[data-dp-track]' );
		this.track = root.querySelector( '.dp-carousel__track' );

		if ( ! this.viewport || ! this.track ) {
			return;
		}

		this.slides = Array.prototype.slice.call( this.track.children );

		this.prev = root.querySelector( '[data-dp-prev]' );
		this.next = root.querySelector( '[data-dp-next]' );
		this.dotsWrap = root.querySelector( '[data-dp-dots]' );
		this.playBtn = root.querySelector( '[data-dp-play]' );
		this.playLabel = root.querySelector( '[data-dp-play-label]' );
		this.status = root.querySelector( '[data-dp-status]' );
		this.controls = root.querySelector( '.dp-carousel__controls' );

		this.rewind = root.getAttribute( 'data-rewind' ) === '1';
		this.autoplayMs = parseInt( root.getAttribute( 'data-autoplay' ), 10 ) || 0;

		this.perView = 1;
		this.pages = 1;
		this.page = 0;
		this.timer = null;
		this.frame = null;
		this.dead = false;
		// Autoplay is suspended by several independent things at once — a hover,
		// a focus, a hidden tab — so it is a set of reasons rather than a
		// boolean. Clearing one must not resume while another still holds.
		this.holds = {};
		this.userPaused = false;

		// Reveal first, measure second: measuring decides whether the controls
		// are worth showing at all, and revealing afterwards would undo it.
		this.bind();
		this.reveal();
		this.measure();

		if ( this.autoplayMs > 0 && ! reducedMotion() ) {
			this.start();
		} else if ( this.playBtn ) {
			// Autoplay refused before it ever ran. The button would be a lie.
			this.playBtn.hidden = true;
		}
	}

	/**
	 * Show the controls the template shipped hidden.
	 */
	Carousel.prototype.reveal = function () {
		if ( this.prev ) { this.prev.hidden = false; }
		if ( this.next ) { this.next.hidden = false; }
		if ( this.controls ) { this.controls.hidden = false; }
		this.root.classList.add( 'dp-carousel--ready' );
	};

	/**
	 * Wire this carousel's own listeners. Page-level ones live at module scope.
	 */
	Carousel.prototype.bind = function () {
		var self = this;

		if ( this.prev ) {
			this.prev.addEventListener( 'click', function () {
				self.go( self.page - 1, true );
			} );
		}

		if ( this.next ) {
			this.next.addEventListener( 'click', function () {
				self.go( self.page + 1, true );
			} );
		}

		if ( this.playBtn ) {
			this.playBtn.addEventListener( 'click', function () {
				self.toggle();
			} );
		}

		// Scroll position is the single source of truth for which page is
		// current — including when the visitor swiped there, which no click
		// handler would ever hear about.
		this.viewport.addEventListener( 'scroll', function () {
			if ( self.frame ) {
				return;
			}
			self.frame = window.requestAnimationFrame( function () {
				self.frame = null;
				self.sync();
			} );
		}, { passive: true } );

		this.viewport.addEventListener( 'keydown', function ( event ) {
			// Only when the scroll container itself has focus. The cards inside
			// it contain links and buttons, and stealing Home/End/arrows from a
			// focused link would move the page out from under it.
			if ( event.target !== self.viewport ) {
				return;
			}

			if ( event.key === 'ArrowRight' ) {
				event.preventDefault();
				self.go( self.page + 1, true );
			} else if ( event.key === 'ArrowLeft' ) {
				event.preventDefault();
				self.go( self.page - 1, true );
			} else if ( event.key === 'Home' ) {
				event.preventDefault();
				self.go( 0, true );
			} else if ( event.key === 'End' ) {
				event.preventDefault();
				self.go( self.pages - 1, true );
			}
		} );

		this.root.addEventListener( 'mouseenter', function () { self.hold( 'hover' ); } );
		this.root.addEventListener( 'mouseleave', function () { self.release( 'hover' ); } );
		this.root.addEventListener( 'focusin', function () { self.hold( 'focus' ); } );
		this.root.addEventListener( 'focusout', function ( event ) {
			if ( ! self.root.contains( event.relatedTarget ) ) {
				self.release( 'focus' );
			}
		} );
		this.viewport.addEventListener( 'pointerdown', function () { self.hold( 'pointer' ); } );

		// A breakpoint change alters slides-in-view, and therefore the page
		// count and which page the current scroll offset belongs to.
		if ( window.ResizeObserver ) {
			this.observer = new window.ResizeObserver( function () {
				self.measure();
			} );
			this.observer.observe( this.viewport );
		}
	};

	/**
	 * Stop everything and let the instance be collected.
	 */
	Carousel.prototype.destroy = function () {
		this.dead = true;
		this.stop();

		if ( this.observer ) {
			this.observer.disconnect();
			this.observer = null;
		}
	};

	/* ---------------------------------------------------------------------
	 * Measurement
	 * ------------------------------------------------------------------ */

	/**
	 * Slides visible at the current breakpoint.
	 *
	 * Read from the custom property the Columns control writes, because that is
	 * the number the stylesheet divides the track by — measuring the rendered
	 * width instead would be the same value arrived at less reliably, and would
	 * disagree with the CSS the moment a slide hit a minimum width.
	 */
	Carousel.prototype.slidesInView = function () {
		var declared = 0;

		if ( window.getComputedStyle ) {
			declared = parseInt( window.getComputedStyle( this.root ).getPropertyValue( '--dpcar-slides' ), 10 );
		}

		if ( ! declared || declared < 1 ) {
			declared = 1;
		}

		return Math.min( declared, Math.max( 1, this.slides.length ) );
	};

	/**
	 * Recompute the page count and rebuild the dots.
	 */
	Carousel.prototype.measure = function () {
		this.perView = this.slidesInView();
		this.pages = Math.max( 1, Math.ceil( this.slides.length / this.perView ) );

		this.buildDots();
		this.sync();

		// Nothing to page through: the controls would be decoration.
		var idle = this.pages < 2;
		this.root.classList.toggle( 'dp-carousel--static', idle );
		if ( this.prev ) { this.prev.hidden = idle; }
		if ( this.next ) { this.next.hidden = idle; }
		if ( this.controls ) { this.controls.hidden = idle; }

		if ( idle ) {
			this.stop();
		}
	};

	/**
	 * How far the track can scroll, in physical pixels.
	 */
	Carousel.prototype.maxScroll = function () {
		return Math.max( 0, this.viewport.scrollWidth - this.viewport.clientWidth );
	};

	/**
	 * The current scroll offset as a positive distance from the start edge.
	 *
	 * `scrollLeft` is 0 at the start and negative going left in a right-to-left
	 * document, so the absolute value is the direction-neutral reading.
	 */
	Carousel.prototype.offset = function () {
		return Math.abs( this.viewport.scrollLeft );
	};

	/**
	 * Rebuild the dot list to match the current page count.
	 */
	Carousel.prototype.buildDots = function () {
		if ( ! this.dotsWrap || this.dotsWrap.childNodes.length === this.pages ) {
			return;
		}

		var self = this;

		while ( this.dotsWrap.firstChild ) {
			this.dotsWrap.removeChild( this.dotsWrap.firstChild );
		}

		var i;
		for ( i = 0; i < this.pages; i++ ) {
			( function ( index ) {
				/*
				 * Plain buttons, not `role="tab"`. A tab implies a tabpanel it
				 * controls, roving focus and arrow-key selection; the dots have
				 * none of that, and claiming the role would promise a screen
				 * reader a structure that is not there.
				 */
				var dot = document.createElement( 'button' );
				dot.type = 'button';
				dot.className = 'dp-carousel__dot';
				dot.setAttribute( 'aria-label', format( strings.page, index + 1, self.pages ) );
				dot.addEventListener( 'click', function () {
					self.go( index, true );
				} );
				self.dotsWrap.appendChild( dot );
			} )( i );
		}
	};

	/* ---------------------------------------------------------------------
	 * Movement
	 * ------------------------------------------------------------------ */

	/**
	 * Move to a page. `announce` marks a movement the visitor asked for, which
	 * is the only kind worth reading out.
	 */
	Carousel.prototype.go = function ( index, announce ) {
		if ( this.pages < 2 ) {
			return;
		}

		if ( index < 0 ) {
			index = this.rewind ? this.pages - 1 : 0;
		} else if ( index > this.pages - 1 ) {
			index = this.rewind ? 0 : this.pages - 1;
		}

		var slide = this.slides[ Math.min( index * this.perView, this.slides.length - 1 ) ];
		if ( ! slide ) {
			return;
		}

		var viewRect = this.viewport.getBoundingClientRect();
		var slideRect = slide.getBoundingClientRect();

		// Bring the target slide's leading edge to the viewport's leading edge.
		// Which edge that is depends on the writing direction; the arithmetic
		// does not, because both rects are physical.
		var delta = this.rtl() ? ( slideRect.right - viewRect.right ) : ( slideRect.left - viewRect.left );

		this.viewport.scrollBy( {
			left: delta,
			behavior: reducedMotion() ? 'auto' : 'smooth'
		} );

		if ( announce && this.status ) {
			this.status.textContent = format( strings.page, index + 1, this.pages );
		}
	};

	/**
	 * Whether the track lays out right-to-left.
	 */
	Carousel.prototype.rtl = function () {
		if ( ! window.getComputedStyle ) {
			return false;
		}

		return window.getComputedStyle( this.viewport ).direction === 'rtl';
	};

	/**
	 * Read the current page back out of the scroll position and repaint the
	 * controls to match.
	 */
	Carousel.prototype.sync = function () {
		var max = this.maxScroll();
		var pos = this.offset();

		if ( pos <= 1 ) {
			this.page = 0;
		} else if ( max > 0 && pos >= max - 1 ) {
			// The last page is short whenever the slide count is not a whole
			// number of pages, so the end of the track is the last page by
			// definition — deriving it from a slide index would be off by the
			// size of that shortfall.
			this.page = this.pages - 1;
		} else {
			this.page = Math.max( 0, Math.min( this.pages - 1, Math.round( this.leadSlide() / this.perView ) ) );
		}

		if ( this.dotsWrap ) {
			Array.prototype.forEach.call( this.dotsWrap.childNodes, function ( dot, index ) {
				if ( index === this.page ) {
					dot.setAttribute( 'aria-current', 'true' );
				} else {
					dot.removeAttribute( 'aria-current' );
				}
			}, this );
		}

		// With wrap-around on, an end is never a dead end, so the buttons stay
		// live. Without it they have to say so.
		if ( ! this.rewind ) {
			if ( this.prev ) { this.prev.disabled = this.page === 0; }
			if ( this.next ) { this.next.disabled = this.page >= this.pages - 1; }
		}
	};

	/**
	 * Index of the slide nearest the viewport's leading edge.
	 */
	Carousel.prototype.leadSlide = function () {
		var rtl = this.rtl();
		var viewRect = this.viewport.getBoundingClientRect();
		var edge = rtl ? viewRect.right : viewRect.left;

		var best = 0;
		var bestDistance = Infinity;

		for ( var i = 0; i < this.slides.length; i++ ) {
			var rect = this.slides[ i ].getBoundingClientRect();
			var distance = Math.abs( ( rtl ? rect.right : rect.left ) - edge );

			if ( distance < bestDistance ) {
				bestDistance = distance;
				best = i;
			}
		}

		return best;
	};

	/* ---------------------------------------------------------------------
	 * Autoplay
	 * ------------------------------------------------------------------ */

	Carousel.prototype.start = function () {
		var self = this;

		this.stop();

		if ( this.dead || this.autoplayMs <= 0 || this.userPaused || this.pages < 2 || reducedMotion() ) {
			return;
		}

		for ( var reason in this.holds ) {
			if ( Object.prototype.hasOwnProperty.call( this.holds, reason ) && this.holds[ reason ] ) {
				return;
			}
		}

		this.timer = window.setInterval( function () {
			// Without wrap-around, stopping at the end is the honest behaviour:
			// bouncing back to the start is a movement the visitor did not ask
			// for and cannot predict.
			if ( ! self.rewind && self.page >= self.pages - 1 ) {
				self.stop();
				return;
			}
			self.go( self.page + 1, false );
		}, this.autoplayMs );

		this.paint( false );
	};

	Carousel.prototype.stop = function () {
		if ( this.timer ) {
			window.clearInterval( this.timer );
			this.timer = null;
		}
	};

	Carousel.prototype.hold = function ( reason ) {
		this.holds[ reason ] = true;
		this.stop();
	};

	/**
	 * Clear one hold. A reason that was never held is not a reason to restart
	 * the interval — otherwise a pointerup anywhere on the page would reset the
	 * countdown of every carousel on it, and a visitor who taps more often than
	 * the delay would never see it advance.
	 */
	Carousel.prototype.release = function ( reason ) {
		if ( ! this.holds[ reason ] ) {
			return;
		}

		this.holds[ reason ] = false;
		this.start();
	};

	/**
	 * The explicit pause the WCAG requirement is about. Unlike a hover, it
	 * survives the pointer leaving.
	 */
	Carousel.prototype.toggle = function () {
		this.userPaused = ! this.userPaused;

		if ( this.userPaused ) {
			this.stop();
		} else {
			this.start();
		}

		this.paint( this.userPaused );
	};

	/**
	 * Repaint the pause button for a state.
	 */
	Carousel.prototype.paint = function ( paused ) {
		if ( ! this.playBtn ) {
			return;
		}

		this.playBtn.setAttribute( 'aria-pressed', paused ? 'true' : 'false' );

		if ( this.playLabel ) {
			this.playLabel.textContent = paused ? ( strings.play || '' ) : ( strings.pause || '' );
		}
	};

	/* ---------------------------------------------------------------------
	 * Page-level listeners and boot
	 * ------------------------------------------------------------------ */

	/*
	 * Registered once, not once per carousel. `pointercancel` matters as much as
	 * `pointerup`: a touch that turns into a pan on a scroll container ends as a
	 * cancel, so listening only for the up would leave the hold set for the life
	 * of the page and autoplay would never resume after the first swipe.
	 */
	window.addEventListener( 'pointerup', function () {
		each( function ( instance ) { instance.release( 'pointer' ); } );
	} );

	window.addEventListener( 'pointercancel', function () {
		each( function ( instance ) { instance.release( 'pointer' ); } );
	} );

	window.addEventListener( 'resize', function () {
		each( function ( instance ) {
			if ( ! instance.observer ) {
				instance.measure();
			}
		} );
	} );

	document.addEventListener( 'visibilitychange', function () {
		each( function ( instance ) {
			if ( document.hidden ) {
				instance.hold( 'hidden' );
			} else {
				instance.release( 'hidden' );
			}
		} );
	} );

	/**
	 * Boot every carousel in a scope, once each, retiring any that Elementor has
	 * replaced.
	 */
	function init( scope ) {
		each( function () {} );

		var roots = ( scope || document ).querySelectorAll( '[data-dp-carousel]' );

		Array.prototype.forEach.call( roots, function ( root ) {
			if ( root.dpCarousel ) {
				return;
			}
			root.dpCarousel = new Carousel( root );
			instances.push( root.dpCarousel );
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
			'frontend/element_ready/doctorpilot-doctor-carousel.default',
			function ( $scope ) {
				init( $scope && $scope[ 0 ] ? $scope[ 0 ] : document );
			}
		);
	} );
} )();
