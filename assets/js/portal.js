/**
 * WooCommerce Customer Portal — front-end behaviour.
 *
 * Dependency-free vanilla JavaScript, no build step. Every enhancement is
 * additive: with JavaScript disabled the portal still renders, navigates and
 * stays fully keyboard accessible.
 *
 * Responsibilities
 *   - Full-bleed measurement (escaping the theme's content column safely)
 *   - Mobile navigation drawer (focus trap, Escape, scrim, scroll lock)
 *   - Sliding active-section indicator in the sidebar
 *   - Elevation state for the sticky header
 *   - Pointer-tracked wash on the sign-in card
 *
 * @package WooCommerce_Customer_Portal
 */

( function () {
	'use strict';

	var CONFIG = window.wcpPortalConfig || {};
	var MOBILE_BREAKPOINT = typeof CONFIG.mobileBreak === 'number' ? CONFIG.mobileBreak : 1024;
	var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

	/*
	 * How long a navigation may take before it is worth acknowledging. Short
	 * enough that a slow load feels answered, long enough that a fast one never
	 * flashes a skeleton on its way out.
	 */
	var PENDING_DELAY = 180;

	/**
	 * Whether the visitor has asked for reduced motion.
	 *
	 * Read at call time rather than cached, so a mid-session OS change is
	 * respected without a reload.
	 *
	 * @return {boolean}
	 */
	function prefersReducedMotion() {
		return (
			typeof window.matchMedia === 'function' &&
			window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
		);
	}

	/**
	 * Whether premium motion is allowed: the visitor has not asked for reduced
	 * motion and the administrator has not switched it off.
	 *
	 * @return {boolean}
	 */
	function motionAllowed() {
		var theme = CONFIG.theme || {};

		return ! prefersReducedMotion() && false !== theme.motion;
	}

	/**
	 * Whether 3D card effects are allowed: motion is allowed, the pointer is
	 * fine, and the administrator has not switched them off.
	 *
	 * @return {boolean}
	 */
	function effectsAllowed() {
		var theme = CONFIG.theme || {};

		return motionAllowed() && hasFinePointer() && ! isMobileViewport() && false !== theme.effects3d;
	}

	/**
	 * Whether the device has a precise pointer that can hover.
	 *
	 * Gates decorative pointer effects so touch devices never pay for them.
	 *
	 * @return {boolean}
	 */
	function hasFinePointer() {
		return (
			typeof window.matchMedia === 'function' &&
			window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches
		);
	}

	/**
	 * Whether the viewport is currently in drawer territory.
	 *
	 * @return {boolean}
	 */
	function isMobileViewport() {
		return window.innerWidth <= MOBILE_BREAKPOINT;
	}

	/**
	 * Run a callback on the next frame, collapsing repeated calls.
	 *
	 * @param {Function} fn Callback.
	 * @return {Function} Throttled callback.
	 */
	function rafThrottle( fn ) {
		var scheduled = false;

		return function () {
			if ( scheduled ) {
				return;
			}

			scheduled = true;

			window.requestAnimationFrame( function () {
				scheduled = false;
				fn();
			} );
		};
	}

	/* ------------------------------------------------------------------ */
	/* Portal instance                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Whether an element occupies space without showing anything.
	 *
	 * Removing a page title often leaves its wrapper behind -- Twenty
	 * Twenty-Four keeps a padded group where the title block used to be. That
	 * wrapper is layout, not content, so the portal is allowed to rise through
	 * it. Anything that would actually be seen -- text, media, a background, a
	 * border -- counts as content and stops the portal there.
	 *
	 * @param {HTMLElement} el Candidate element.
	 * @return {boolean} True when nothing about the element is visible.
	 */
	function isVisuallyEmpty( el ) {
		if ( el.textContent && el.textContent.trim() ) {
			return false;
		}

		if ( el.querySelector( 'img, picture, svg, video, canvas, iframe, object, embed, input, button, select, textarea, hr' ) ) {
			return false;
		}

		var style = window.getComputedStyle( el );

		if ( 'none' !== style.backgroundImage ) {
			return false;
		}

		// Any background the visitor could see means the space is the theme's.
		// Computed values are either `rgba(r, g, b, a)` or an opaque `rgb(...)`.
		var bg = style.backgroundColor;

		if ( 0 === bg.indexOf( 'rgba(' ) ) {
			if ( parseFloat( bg.slice( 5, -1 ).split( ',' )[ 3 ] ) > 0 ) {
				return false;
			}
		} else if ( bg && 'transparent' !== bg ) {
			return false;
		}

		if (
			parseFloat( style.borderTopWidth ) ||
			parseFloat( style.borderBottomWidth ) ||
			parseFloat( style.borderLeftWidth ) ||
			parseFloat( style.borderRightWidth )
		) {
			return false;
		}

		return true;
	}

	/**
	 * Wire up a single portal root.
	 *
	 * @param {HTMLElement} root Element carrying [data-wcp-portal].
	 */
	function Portal( root ) {
		this.root = root;
		this.sidebar = root.querySelector( '[data-wcp-sidebar]' );
		this.scrim = root.querySelector( '[data-wcp-scrim]' );
		this.toggle = root.querySelector( '[data-wcp-drawer-toggle]' );
		this.closeButton = root.querySelector( '[data-wcp-drawer-close]' );
		this.nav = root.querySelector( '[data-wcp-nav]' );
		this.indicator = root.querySelector( '[data-wcp-nav-indicator]' );
		this.header = root.querySelector( '.wcp-header' );
		this.sentinel = root.querySelector( '[data-wcp-header-sentinel]' );

		this.isFullBleed = 'full' === root.getAttribute( 'data-wcp-layout' );

		// PHP decides whether this page's theme chrome was suppressed and says
		// so on the body, so the script never has to guess at the theme.
		this.isChromeless = document.body.classList.contains( 'wcp-portal-chromeless' );

		this.drawerOpen = false;
		this.lastFocused = null;

		this.bindFullBleed();
		this.bindAppearance();
		this.bindSectionNav();
		this.bindPendingNavigation();
		this.bindForms();
		this.bindDrawer();
		this.bindIndicator();
		this.bindHeader();
		this.bindSpotlight();
		this.bindTilt();
		this.bindReveal();
	}

	/* --- Reveal on scroll ------------------------------------------------ */

	/**
	 * Fade elements in as they reach the viewport.
	 *
	 * Used for one thing: the portal footer, which is usually below the fold
	 * and would otherwise play its entrance where nobody is looking.
	 *
	 * The hidden state is applied by script, never by the stylesheet, so a
	 * page without JavaScript -- or with reduced motion asked for, or with the
	 * administrator's motion switch off -- simply renders the footer visible.
	 * Nothing is observed after it has been revealed once.
	 *
	 * @return {void}
	 */
	Portal.prototype.bindReveal = function () {
		var targets = this.root.querySelectorAll( '[data-wcp-reveal]' );

		if ( ! targets.length || ! motionAllowed() || ! window.IntersectionObserver ) {
			return;
		}

		var observer = new window.IntersectionObserver(
			function ( entries ) {
				for ( var i = 0; i < entries.length; i++ ) {
					if ( ! entries[ i ].isIntersecting ) {
						continue;
					}

					entries[ i ].target.classList.add( 'is-revealed' );
					observer.unobserve( entries[ i ].target );
				}
			},
			{
				rootMargin: '0px 0px -6% 0px',
				threshold: 0.04,
			}
		);

		for ( var i = 0; i < targets.length; i++ ) {
			// Anything already on screen is simply shown: hiding it first so
			// it can fade back in would be motion for its own sake, and on a
			// short page the footer is visible from the start.
			if ( targets[ i ].getBoundingClientRect().top < window.innerHeight ) {
				targets[ i ].classList.add( 'is-revealed' );

				continue;
			}

			targets[ i ].setAttribute( 'data-wcp-reveal-ready', '' );
			observer.observe( targets[ i ] );
		}
	};

	/* --- Full-bleed ---------------------------------------------------- */

	/**
	 * Let the portal escape the theme's content column.
	 *
	 * Measures how far the portal sits from each viewport edge and writes the
	 * gap back as a custom property, which CSS applies as negative margin.
	 * `clientWidth` is used rather than `innerWidth` so the scrollbar is
	 * excluded and the page never gains a horizontal scrollbar of its own.
	 *
	 * @return {void}
	 */
	Portal.prototype.bindFullBleed = function () {
		var self = this;

		if ( ! this.isFullBleed ) {
			return;
		}

		var sync = rafThrottle( function () {
			self.syncFullBleed();
			self.syncVerticalFit();
		} );

		this.syncFullBleed();
		this.syncVerticalFit();

		window.addEventListener( 'resize', sync );
		window.addEventListener( 'orientationchange', sync );

		// A theme that reveals a sticky bar, or an image that loads late, can
		// change the column width after first paint.
		if ( 'undefined' !== typeof window.ResizeObserver ) {
			new window.ResizeObserver( sync ).observe( document.documentElement );
		}

		if ( document.fonts && document.fonts.ready && document.fonts.ready.then ) {
			document.fonts.ready.then( sync ).catch( function () {} );
		}
	};

	/**
	 * Measure and apply the current bleed.
	 *
	 * @return {void}
	 */
	Portal.prototype.syncFullBleed = function () {
		// Drop the previous offsets first, so the measurement reflects where
		// the theme naturally places the portal rather than where we left it.
		this.root.removeAttribute( 'data-wcp-bleed' );
		this.root.style.setProperty( '--wcp-bleed-left', '0px' );
		this.root.style.setProperty( '--wcp-bleed-right', '0px' );

		var rect = this.root.getBoundingClientRect();
		var viewport = document.documentElement.clientWidth;

		// Guard against a hidden or unmeasurable container.
		if ( ! rect.width || ! viewport ) {
			return;
		}

		// Signed on purpose. A positive offset means the theme's column is
		// holding the portal in and the margin pulls it out; a negative one
		// means the theme overshot -- `width: 100vw` includes the scrollbar --
		// and the same margin pulls it back, killing the horizontal scrollbar
		// that would otherwise appear.
		var left = Math.round( rect.left );
		var right = Math.round( viewport - rect.right );

		// The theme's own full-width handling already got us there. Adding
		// margins now would only undo it.
		if ( Math.abs( left ) < 2 && Math.abs( right ) < 2 ) {
			return;
		}

		this.root.style.setProperty( '--wcp-bleed-left', left + 'px' );
		this.root.style.setProperty( '--wcp-bleed-right', right + 'px' );
		this.root.setAttribute( 'data-wcp-bleed', '' );
	};

	/**
	 * Find the bottom edge of whatever actually sits above the portal.
	 *
	 * Walks the ancestor chain and, at every level, takes the lowest visible
	 * preceding sibling. On a block theme that lands on the site header; on a
	 * page that puts a paragraph above the shortcode it lands on the
	 * paragraph. Either way the answer is "where the empty space starts",
	 * which is the only space it is safe to reclaim.
	 *
	 * Out-of-flow siblings are skipped because their box says nothing about
	 * where the document flow actually ended -- except a fixed or sticky site
	 * header, which is exactly the thing the portal should start beneath.
	 *
	 * @return {number} Viewport-relative Y of the ceiling.
	 */
	Portal.prototype.measureCeiling = function () {
		var ceiling = 0;
		var node = this.root;

		while ( node && node.parentElement ) {
			var sibling = node.previousElementSibling;

			while ( sibling ) {
				var style = window.getComputedStyle( sibling );

				if ( 'absolute' !== style.position && 'none' !== style.display && ! isVisuallyEmpty( sibling ) ) {
					var box = sibling.getBoundingClientRect();

					if ( box.height > 0 && box.width > 0 ) {
						ceiling = Math.max( ceiling, box.bottom );
					}
				}

				sibling = sibling.previousElementSibling;
			}

			node = node.parentElement;
		}

		return ceiling;
	};

	/**
	 * Close the theme's vertical whitespace around a full-width portal.
	 *
	 * Only runs when PHP has already suppressed the page title, so a portal
	 * that is still framed as ordinary page content keeps the theme's spacing
	 * exactly as the theme intended.
	 *
	 * @return {void}
	 */
	Portal.prototype.syncVerticalFit = function () {
		if ( ! this.isChromeless ) {
			return;
		}

		// Measure from where the theme would put us, not from where we last
		// left ourselves.
		this.root.removeAttribute( 'data-wcp-fit' );
		this.root.style.setProperty( '--wcp-pull-top', '0px' );
		this.root.style.setProperty( '--wcp-pull-bottom', '0px' );

		var rect = this.root.getBoundingClientRect();

		if ( ! rect.height ) {
			return;
		}

		var ceiling = this.measureCeiling();
		var gap = parseFloat( window.getComputedStyle( this.root ).getPropertyValue( '--wcp-chrome-gap' ) ) || 0;
		var pullTop = Math.max( 0, Math.round( rect.top - ceiling - gap ) );

		// Below the portal the reclaimable space runs to the next thing in the
		// document -- or, when the theme footer has been suppressed and nothing
		// follows us at all, to the end of the document itself. In that case
		// every pixel below our bottom edge is the theme's trailing padding,
		// and the portal's own footer should be the last thing on the page.
		var floor = this.measureFloor();

		if ( null === floor ) {
			floor = this.documentFloor();
		}

		var pullBottom = Math.max( 0, Math.round( floor - rect.bottom ) );

		this.root.style.setProperty( '--wcp-pull-top', pullTop + 'px' );
		this.root.style.setProperty( '--wcp-pull-bottom', pullBottom + 'px' );

		// The portal now starts at the ceiling, so that is exactly how much
		// viewport height the site chrome is using.
		this.root.style.setProperty( '--wcp-chrome-top', Math.max( 0, Math.round( ceiling ) ) + 'px' );
		this.root.setAttribute( 'data-wcp-fit', '' );
	};

	/**
	 * Find the top edge of whatever follows the portal.
	 *
	 * @return {number|null} Viewport-relative Y, or null when nothing follows.
	 */
	Portal.prototype.measureFloor = function () {
		var floor = null;
		var node = this.root;

		while ( node && node.parentElement ) {
			var sibling = node.nextElementSibling;

			while ( sibling ) {
				var style = window.getComputedStyle( sibling );

				if ( 'absolute' !== style.position && 'fixed' !== style.position && 'none' !== style.display && ! isVisuallyEmpty( sibling ) ) {
					var box = sibling.getBoundingClientRect();

					if ( box.height > 0 && box.width > 0 ) {
						floor = null === floor ? box.top : Math.min( floor, box.top );
					}
				}

				sibling = sibling.nextElementSibling;
			}

			node = node.parentElement;
		}

		return floor;
	};

	/**
	 * The end of the document, in viewport coordinates.
	 *
	 * Measured rather than assumed, because the trailing space belongs to
	 * ancestor padding the portal cannot see from its own box: the theme's
	 * content group pads its bottom edge whether anything follows or not.
	 *
	 * @return {number} Viewport-relative Y of the document's bottom edge.
	 */
	Portal.prototype.documentFloor = function () {
		var doc = document.documentElement;
		var scrolled = window.scrollY || doc.scrollTop || 0;

		return doc.scrollHeight - scrolled;
	};

	/* --- Pending navigation -------------------------------------------- */

	/**
	 * Acknowledge a navigation that is taking a moment.
	 *
	 * Orders are server-rendered, so opening one is a real page load. On a fast
	 * connection that is instantaneous and needs no feedback; on a slow one the
	 * customer clicks and nothing happens. The skeleton bridges exactly that
	 * gap -- and only that gap, because it is revealed on a delay. A skeleton
	 * that flashes for 40ms is worse than no skeleton at all.
	 *
	 * @return {void}
	 */
	Portal.prototype.bindPendingNavigation = function () {
		var self = this;
		var triggers = this.root.querySelectorAll( '[data-wcp-pending]' );

		if ( ! triggers.length ) {
			return;
		}

		this.ordersPanel = this.root.querySelector( '[data-wcp-orders]' );
		this.skeleton = this.root.querySelector( '[data-wcp-skeleton]' );
		this.pendingTimer = null;

		var onClick = function ( event ) {
			// Anything that is not a plain left click is the browser's to
			// handle -- new tab, download, context menu.
			if ( event.defaultPrevented || 0 !== event.button ) {
				return;
			}

			if ( event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
				return;
			}

			var link = event.currentTarget;
			var href = link.getAttribute( 'href' );

			if ( ! href || '#' === href.charAt( 0 ) || link.hasAttribute( 'target' ) ) {
				return;
			}

			self.schedulePending( link );
		};

		for ( var i = 0; i < triggers.length; i++ ) {
			triggers[ i ].addEventListener( 'click', onClick );
		}

		// Coming back through the bfcache restores the old DOM verbatim,
		// skeleton and all. Clear it or the customer returns to a loading
		// screen that will never finish.
		window.addEventListener( 'pageshow', function () {
			self.clearPending();
		} );
	};

	/**
	 * Show the pending state, but only if the load outlasts the delay.
	 *
	 * @param {HTMLElement} link The activated control.
	 * @return {void}
	 */
	Portal.prototype.schedulePending = function ( link ) {
		var self = this;

		this.clearPending();

		this.pendingTimer = window.setTimeout( function () {
			self.showPending( link );
		}, PENDING_DELAY );
	};

	/**
	 * Swap the orders list for its skeleton.
	 *
	 * @param {HTMLElement} link The activated control.
	 * @return {void}
	 */
	Portal.prototype.showPending = function ( link ) {
		if ( this.skeleton && this.ordersPanel ) {
			this.skeleton.hidden = false;
			this.ordersPanel.setAttribute( 'data-loading', '' );
			this.ordersPanel.setAttribute( 'aria-busy', 'true' );

			return;
		}

		// No list to replace -- a back link, or the error screen. The control
		// itself carries the state instead.
		if ( link && link.classList.contains( 'wcp-button' ) ) {
			link.classList.add( 'is-loading' );
		}
	};

	/**
	 * Undo the pending state.
	 *
	 * @return {void}
	 */
	Portal.prototype.clearPending = function () {
		if ( this.pendingTimer ) {
			window.clearTimeout( this.pendingTimer );
			this.pendingTimer = null;
		}

		if ( this.skeleton ) {
			this.skeleton.hidden = true;
		}

		if ( this.ordersPanel ) {
			this.ordersPanel.removeAttribute( 'data-loading' );
			this.ordersPanel.removeAttribute( 'aria-busy' );
		}

		var loading = this.root.querySelectorAll( '.wcp-button.is-loading' );

		for ( var i = 0; i < loading.length; i++ ) {
			loading[ i ].classList.remove( 'is-loading' );
		}
	};

	/* --- Forms ---------------------------------------------------------- */

	/**
	 * Submit portal forms without a page reload.
	 *
	 * Enhancement only. The forms are real `<form method="post">` elements with
	 * a real action and a nonce, and they work with this script removed -- the
	 * server handles them, validates identically and redirects. What this adds
	 * is keeping the customer's place, and reporting failures against the field
	 * that caused them instead of at the top of a fresh page.
	 *
	 * @return {void}
	 */
	Portal.prototype.bindForms = function () {
		var self = this;
		var forms = this.root.querySelectorAll( '[data-wcp-form]' );

		if ( ! forms.length || ! window.fetch || ! CONFIG.restUrl ) {
			return;
		}

		for ( var i = 0; i < forms.length; i++ ) {
			( function ( form ) {
				form.addEventListener( 'submit', function ( event ) {
					event.preventDefault();
					self.submitForm( form );
				} );
			} )( forms[ i ] );
		}

		this.bindCountryChange();
	};

	/**
	 * Send one form to its REST route and render the outcome.
	 *
	 * @param {HTMLFormElement} form The form.
	 * @return {void}
	 */
	Portal.prototype.submitForm = function ( form ) {
		var self = this;

		if ( form.hasAttribute( 'data-wcp-busy' ) ) {
			return;
		}

		var endpoint = this.endpointFor( form );

		if ( ! endpoint ) {
			// Nothing sensible to call: let the browser post it normally.
			form.submit();

			return;
		}

		var body = {};
		var data = new window.FormData( form );

		data.forEach( function ( value, key ) {
			body[ key ] = value;
		} );

		this.setFormBusy( form, true );
		this.clearFieldErrors( form );

		window.fetch( endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': CONFIG.restNonce || '',
				'X-WCP-Nonce': CONFIG.nonce || '',
			},
			body: JSON.stringify( body ),
		} )
			.then( function ( response ) {
				return response.json().then( function ( payload ) {
					return { ok: response.ok, status: response.status, payload: payload };
				} );
			} )
			.then( function ( result ) {
				self.setFormBusy( form, false );

				if ( result.ok ) {
					self.onFormSuccess( form, result.payload );

					return;
				}

				self.onFormError( form, result.payload, result.status );
			} )
			.catch( function () {
				self.setFormBusy( form, false );
				// A network failure is the one case where the server never
				// spoke, so nothing can be said about specific fields.
				self.showFeedback(
					form,
					'error',
					( CONFIG.i18n && CONFIG.i18n.networkError ) || 'Could not reach the server. Check your connection and try again.'
				);
			} );
	};

	/**
	 * The REST route a form posts to.
	 *
	 * @param {HTMLFormElement} form The form.
	 * @return {string} Empty when unknown.
	 */
	Portal.prototype.endpointFor = function ( form ) {
		var kind = form.getAttribute( 'data-wcp-form' );

		if ( 'profile' === kind ) {
			return CONFIG.restUrl + 'profile';
		}

		if ( 'address' === kind ) {
			var type = form.getAttribute( 'data-wcp-address-type' );

			// Whitelisted here as well as on the server, so a tampered
			// attribute cannot aim the request somewhere else.
			if ( 'billing' === type || 'shipping' === type ) {
				return CONFIG.restUrl + 'addresses/' + type;
			}
		}

		return '';
	};

	/**
	 * Successful save.
	 *
	 * @param {HTMLFormElement} form    The form.
	 * @param {Object}          payload Response body.
	 * @return {void}
	 */
	Portal.prototype.onFormSuccess = function ( form, payload ) {
		this.showFeedback( form, 'success', payload && payload.message ? payload.message : '' );
		this.flashSuccess( form );

		// A password form must not keep the secrets it just sent.
		var passwords = form.querySelectorAll( 'input[type="password"]' );

		for ( var i = 0; i < passwords.length; i++ ) {
			passwords[ i ].value = '';
		}

		// Reflect what the server actually stored, which may differ from what
		// was typed -- a postcode gets reformatted, an email lowercased.
		if ( payload && payload.values ) {
			for ( var key in payload.values ) {
				if ( ! Object.prototype.hasOwnProperty.call( payload.values, key ) ) {
					continue;
				}

				var input = form.querySelector( '[name="' + key + '"]' );

				if ( input && 'password' !== input.type ) {
					input.value = payload.values[ key ];
				}
			}
		}
	};

	/**
	 * Failed save.
	 *
	 * @param {HTMLFormElement} form    The form.
	 * @param {Object}          payload Response body.
	 * @param {number}          status  HTTP status.
	 * @return {void}
	 */
	Portal.prototype.onFormError = function ( form, payload, status ) {
		var message = payload && payload.message ? payload.message : '';
		var fields = payload && payload.data && payload.data.fields ? payload.data.fields : null;
		var first = null;

		if ( fields ) {
			for ( var key in fields ) {
				if ( ! Object.prototype.hasOwnProperty.call( fields, key ) ) {
					continue;
				}

				var node = this.setFieldError( form, key, fields[ key ] );

				if ( node && ! first ) {
					first = node;
				}
			}
		}

		if ( 401 === status || 403 === status ) {
			// The session or the nonce is gone. Reloading is the only honest
			// recovery, and the customer has to be told before it happens.
			message = message || ( ( CONFIG.i18n && CONFIG.i18n.sessionExpired ) || 'Your session has expired.' );
		}

		this.showFeedback( form, 'error', message );

		// Move to the first bad field, so a keyboard or screen-reader user is
		// taken to the problem rather than told one exists.
		if ( first ) {
			var input = first.querySelector( 'input, select, textarea' );

			if ( input ) {
				input.focus();
			}
		}
	};

	/**
	 * Mark one field invalid.
	 *
	 * @param {HTMLFormElement} form    The form.
	 * @param {string}          key     Field name.
	 * @param {string}          message Error message.
	 * @return {HTMLElement|null} The field wrapper.
	 */
	Portal.prototype.setFieldError = function ( form, key, message ) {
		var field = form.querySelector( '[data-wcp-field="' + key + '"]' );

		if ( ! field ) {
			return null;
		}

		var target = field.querySelector( '[data-wcp-field-error] span' );
		var holder = field.querySelector( '[data-wcp-field-error]' );
		var input = field.querySelector( 'input, select, textarea' );

		if ( target ) {
			target.textContent = message;
		}

		if ( holder ) {
			holder.hidden = false;
		}

		field.classList.add( 'wcp-field--error' );

		if ( input ) {
			input.setAttribute( 'aria-invalid', 'true' );

			// The error element is always in the DOM, so it is already named by
			// `aria-describedby` -- it only has to stop being hidden.
			if ( holder && holder.id && ( input.getAttribute( 'aria-describedby' ) || '' ).indexOf( holder.id ) === -1 ) {
				var described = input.getAttribute( 'aria-describedby' );
				input.setAttribute( 'aria-describedby', described ? described + ' ' + holder.id : holder.id );
			}
		}

		return field;
	};

	/**
	 * Clear every field error on a form.
	 *
	 * @param {HTMLFormElement} form The form.
	 * @return {void}
	 */
	Portal.prototype.clearFieldErrors = function ( form ) {
		var fields = form.querySelectorAll( '[data-wcp-field]' );

		for ( var i = 0; i < fields.length; i++ ) {
			fields[ i ].classList.remove( 'wcp-field--error' );

			var holder = fields[ i ].querySelector( '[data-wcp-field-error]' );

			if ( holder ) {
				holder.hidden = true;
			}

			var input = fields[ i ].querySelector( 'input, select, textarea' );

			if ( input ) {
				input.removeAttribute( 'aria-invalid' );
			}
		}
	};

	/**
	 * Show or clear a form's status message.
	 *
	 * @param {HTMLFormElement} form    The form.
	 * @param {string}          status  `success` or `error`.
	 * @param {string}          message Text.
	 * @return {void}
	 */
	Portal.prototype.showFeedback = function ( form, status, message ) {
		var box = form.querySelector( '[data-wcp-feedback]' );

		if ( ! box ) {
			return;
		}

		var text = box.querySelector( '[data-wcp-feedback-text]' );

		box.classList.remove( 'wcp-feedback--success', 'wcp-feedback--error' );

		if ( ! message ) {
			box.hidden = true;

			return;
		}

		box.classList.add( 'wcp-feedback--' + ( 'success' === status ? 'success' : 'error' ) );

		if ( text ) {
			// Assigned rather than appended: the region already exists and is
			// live, so changing its text is what gets announced.
			text.textContent = message;
		}

		box.hidden = false;
	};

	/**
	 * Show a one-shot check mark on the submit button after a save.
	 *
	 * Purely decorative: the live region carries the real confirmation. The
	 * class is removed again so a second save can replay it.
	 *
	 * @param {HTMLFormElement} form The form.
	 * @return {void}
	 */
	Portal.prototype.flashSuccess = function ( form ) {
		var button = form.querySelector( '[data-wcp-submit]' );

		if ( ! button || ! motionAllowed() ) {
			return;
		}

		button.classList.add( 'is-success' );

		window.setTimeout( function () {
			button.classList.remove( 'is-success' );
		}, 1400 );
	};

	/**
	 * Toggle a form's busy state.
	 *
	 * @param {HTMLFormElement} form The form.
	 * @param {boolean}         busy Whether a request is in flight.
	 * @return {void}
	 */
	Portal.prototype.setFormBusy = function ( form, busy ) {
		var button = form.querySelector( '[data-wcp-submit]' );

		if ( busy ) {
			form.setAttribute( 'data-wcp-busy', '' );
			form.setAttribute( 'aria-busy', 'true' );
		} else {
			form.removeAttribute( 'data-wcp-busy' );
			form.removeAttribute( 'aria-busy' );
		}

		if ( button ) {
			button.disabled = busy;
			button.classList.toggle( 'is-loading', busy );
		}
	};

	/**
	 * Rebuild the state field when the country changes.
	 *
	 * Which fields exist, what they are called and whether they are required
	 * are WooCommerce's rules, and they differ per country. Rather than
	 * reimplement any of that in the browser, the form asks the server what the
	 * field set has become and updates the one control that actually changes
	 * shape: a country with registered states needs a <select>, one without
	 * needs free text.
	 *
	 * @return {void}
	 */
	Portal.prototype.bindCountryChange = function () {
		var self = this;
		var forms = this.root.querySelectorAll( '[data-wcp-address-type]' );

		for ( var i = 0; i < forms.length; i++ ) {
			( function ( form ) {
				var type = form.getAttribute( 'data-wcp-address-type' );
				var country = form.querySelector( '[name="' + type + '_country"]' );

				if ( ! country ) {
					return;
				}

				country.addEventListener( 'change', function () {
					self.refreshAddressFields( form, type, country.value );
				} );
			} )( forms[ i ] );
		}
	};

	/**
	 * Fetch the field set for a country and apply what changed.
	 *
	 * @param {HTMLFormElement} form    The form.
	 * @param {string}          type    `billing` or `shipping`.
	 * @param {string}          country Selected country code.
	 * @return {void}
	 */
	Portal.prototype.refreshAddressFields = function ( form, type, country ) {
		var self = this;

		if ( 'billing' !== type && 'shipping' !== type ) {
			return;
		}

		window.fetch( CONFIG.restUrl + 'addresses/' + type + '?country=' + encodeURIComponent( country ), {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': CONFIG.restNonce || '' },
		} )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( function ( payload ) {
				if ( payload && payload.fields ) {
					self.applyAddressFields( form, payload.fields );
				}
			} )
			.catch( function () {
				// The form still submits and the server still validates; the
				// only cost of a failed refresh is a stale state control.
			} );
	};

	/**
	 * Apply a fetched field set to the rendered form.
	 *
	 * @param {HTMLFormElement} form   The form.
	 * @param {Array}           fields Field definitions.
	 * @return {void}
	 */
	Portal.prototype.applyAddressFields = function ( form, fields ) {
		for ( var i = 0; i < fields.length; i++ ) {
			var def = fields[ i ];
			var wrapper = form.querySelector( '[data-wcp-field="' + def.key + '"]' );

			if ( ! wrapper ) {
				continue;
			}

			this.applyRequired( wrapper, def );

			var control = wrapper.querySelector( 'input, select' );

			if ( ! control ) {
				continue;
			}

			var isSelect = 'SELECT' === control.tagName;

			if ( def.type === ( isSelect ? 'select' : 'text' ) ) {
				// Same control type: only the options can have changed.
				if ( isSelect ) {
					this.fillOptions( control, def.options, control.value );
				}

				continue;
			}

			this.swapControl( wrapper, control, def );
		}
	};

	/**
	 * Update a field's label and required state.
	 *
	 * @param {HTMLElement} wrapper Field wrapper.
	 * @param {Object}      def     Field definition.
	 * @return {void}
	 */
	Portal.prototype.applyRequired = function ( wrapper, def ) {
		var labelText = wrapper.querySelector( '.wcp-field__label > span:first-child' );
		var control = wrapper.querySelector( 'input, select' );

		if ( labelText && def.label ) {
			labelText.textContent = def.label;
		}

		if ( control ) {
			control.required = !! def.required;
		}

		var marker = wrapper.querySelector( '.wcp-field__required' );
		var optional = wrapper.querySelector( '.wcp-field__optional' );

		if ( marker ) {
			marker.hidden = ! def.required;
		}

		if ( optional ) {
			optional.hidden = !! def.required;
		}
	};

	/**
	 * Replace an input with a select, or the reverse.
	 *
	 * @param {HTMLElement} wrapper Field wrapper.
	 * @param {HTMLElement} control Existing control.
	 * @param {Object}      def     Field definition.
	 * @return {void}
	 */
	Portal.prototype.swapControl = function ( wrapper, control, def ) {
		var replacement;
		var id = control.id;
		var described = control.getAttribute( 'aria-describedby' );

		if ( 'select' === def.type ) {
			replacement = document.createElement( 'select' );
			replacement.className = 'wcp-input wcp-select';
			this.fillOptions( replacement, def.options, '' );
		} else {
			replacement = document.createElement( 'input' );
			replacement.type = 'text';
			replacement.className = 'wcp-input';
			replacement.value = '';
		}

		replacement.id = id;
		replacement.name = def.key;
		replacement.required = !! def.required;

		if ( described ) {
			replacement.setAttribute( 'aria-describedby', described );
		}

		// A select needs the chevron wrapper; an input must not keep it.
		var existingWrap = wrapper.querySelector( '.wcp-select-wrap' );

		if ( 'select' === def.type ) {
			if ( existingWrap ) {
				existingWrap.replaceChild( replacement, control );
			} else {
				var wrap = document.createElement( 'div' );
				wrap.className = 'wcp-select-wrap';
				wrap.appendChild( replacement );
				control.parentNode.replaceChild( wrap, control );
			}

			return;
		}

		if ( existingWrap ) {
			existingWrap.parentNode.replaceChild( replacement, existingWrap );

			return;
		}

		control.parentNode.replaceChild( replacement, control );
	};

	/**
	 * Populate a select's options.
	 *
	 * @param {HTMLSelectElement} select   The select.
	 * @param {Object}            options  Value => label.
	 * @param {string}            selected Value to preselect.
	 * @return {void}
	 */
	Portal.prototype.fillOptions = function ( select, options, selected ) {
		var placeholder = ( CONFIG.i18n && CONFIG.i18n.selectPlaceholder ) || 'Select…';

		select.innerHTML = '';

		var blank = document.createElement( 'option' );
		blank.value = '';
		blank.textContent = placeholder;
		select.appendChild( blank );

		for ( var value in options ) {
			if ( ! Object.prototype.hasOwnProperty.call( options, value ) ) {
				continue;
			}

			var option = document.createElement( 'option' );
			option.value = value;
			// textContent, never innerHTML: these labels come from the server
			// but there is no reason to hand them an HTML parser.
			option.textContent = options[ value ];

			if ( value === selected ) {
				option.selected = true;
			}

			select.appendChild( option );
		}
	};

	/* --- Appearance ----------------------------------------------------- */

	/**
	 * Wire the appearance switcher: colour scheme and visual theme.
	 *
	 * The document attributes are already correct by the time this runs -- a
	 * synchronous script in <head> set them before first paint, which is the
	 * only way to avoid a flash when the stored preference is dark. All this
	 * adds is the control, and keeping the control's state honest.
	 *
	 * Two independent choices, both stored locally and never sent anywhere:
	 *   appearance  system | light | dark   ("system" = nothing stored)
	 *   visual      aurora | obsidian | pearl | midnight | emerald
	 *
	 * With no stored appearance the portal follows the operating system, and
	 * keeps following it: the `change` listener means a customer who has never
	 * picked a scheme sees the portal switch when their machine does.
	 *
	 * @return {void}
	 */
	Portal.prototype.bindAppearance = function () {
		var self = this;
		var config = CONFIG.theme || {};

		this.themeKey = config.storageKey || 'wcp-theme';
		this.themeAttr = config.attribute || 'data-wcp-theme';
		this.visualKey = config.visualKey || 'wcp-visual';
		this.visualAttr = config.visualAttribute || 'data-wcp-visual';
		this.visualPresets = config.visualPresets || [ 'aurora', 'obsidian', 'pearl', 'midnight', 'emerald' ];
		this.visualDefault = config.visualDefault || 'aurora';
		this.visualLabels = config.visualLabels || {};
		this.appearanceOpeners = [];

		this.syncAppearanceControls();

		var switchers = this.root.querySelectorAll( '[data-wcp-appearance]' );

		for ( var i = 0; i < switchers.length; i++ ) {
			this.bindAppearanceMenu( switchers[ i ] );
		}

		// Anything else on the page that offers to open the switcher -- the
		// account panel's theme row, for instance. Delegated from the root so
		// it keeps working after a section swap replaces the content.
		this.root.addEventListener( 'click', function ( event ) {
			var opener = event.target.closest ? event.target.closest( '[data-wcp-appearance-open]' ) : null;

			if ( ! opener || ! self.appearanceOpeners.length ) {
				return;
			}

			event.preventDefault();
			self.appearanceOpeners[ 0 ]();
		} );

		if ( window.matchMedia ) {
			var query = window.matchMedia( '(prefers-color-scheme: dark)' );

			var onSystemChange = function ( event ) {
				// Only while nothing outranks the operating system: neither an
				// explicit choice by the customer nor a fixed default set by the
				// administrator.
				var adminDefault = config.adminDefault || 'system';

				if ( ! self.storedTheme() && 'system' === adminDefault ) {
					self.setTheme( event.matches ? 'dark' : 'light', false );
				}
			};

			if ( query.addEventListener ) {
				query.addEventListener( 'change', onSystemChange );
			} else if ( query.addListener ) {
				query.addListener( onSystemChange );
			}
		}
	};

	/**
	 * One switcher: trigger, popover, two radio groups.
	 *
	 * Keyboard: the trigger opens the dialog and focus moves to the checked
	 * option of the first group; arrow keys move within a group and select;
	 * Tab moves between groups and out; Escape closes and restores focus to
	 * the trigger, as does a click anywhere outside.
	 *
	 * @param {HTMLElement} switcher The `[data-wcp-appearance]` wrapper.
	 * @return {void}
	 */
	Portal.prototype.bindAppearanceMenu = function ( switcher ) {
		var self = this;
		var trigger = switcher.querySelector( '[data-wcp-appearance-trigger]' );
		var menu = switcher.querySelector( '[data-wcp-appearance-menu]' );

		if ( ! trigger || ! menu ) {
			return;
		}

		var onDocumentClick = function ( event ) {
			if ( ! switcher.contains( event.target ) ) {
				close( false );
			}
		};

		var onDocumentKey = function ( event ) {
			if ( 'Escape' === event.key ) {
				event.preventDefault();
				close( true );
			}
		};

		var open = function () {
			menu.hidden = false;
			trigger.setAttribute( 'aria-expanded', 'true' );
			self.syncAppearanceControls();

			var checked = menu.querySelector( '[role="radio"][aria-checked="true"]' ) || menu.querySelector( '[role="radio"]' );

			if ( checked ) {
				checked.focus();
			}

			document.addEventListener( 'click', onDocumentClick, true );
			document.addEventListener( 'keydown', onDocumentKey );
		};

		var close = function ( restoreFocus ) {
			if ( menu.hidden ) {
				return;
			}

			menu.hidden = true;
			trigger.setAttribute( 'aria-expanded', 'false' );
			document.removeEventListener( 'click', onDocumentClick, true );
			document.removeEventListener( 'keydown', onDocumentKey );

			if ( restoreFocus ) {
				trigger.focus();
			}
		};

		trigger.addEventListener( 'click', function () {
			if ( menu.hidden ) {
				open();
			} else {
				close( true );
			}
		} );

		this.appearanceOpeners.push( open );

		menu.addEventListener( 'click', function ( event ) {
			var option = event.target.closest ? event.target.closest( '[role="radio"]' ) : null;

			if ( ! option ) {
				return;
			}

			self.chooseAppearanceOption( option );
		} );

		// Roving focus within each radio group.
		menu.addEventListener( 'keydown', function ( event ) {
			var option = event.target.closest ? event.target.closest( '[role="radio"]' ) : null;

			if ( ! option ) {
				return;
			}

			var group = option.closest( '[role="radiogroup"]' );
			var options = group ? Array.prototype.slice.call( group.querySelectorAll( '[role="radio"]' ) ) : [ option ];
			var index = options.indexOf( option );
			var next = -1;

			if ( 'ArrowRight' === event.key || 'ArrowDown' === event.key ) {
				next = ( index + 1 ) % options.length;
			} else if ( 'ArrowLeft' === event.key || 'ArrowUp' === event.key ) {
				next = ( index - 1 + options.length ) % options.length;
			} else if ( 'Home' === event.key ) {
				next = 0;
			} else if ( 'End' === event.key ) {
				next = options.length - 1;
			} else if ( ' ' === event.key || 'Enter' === event.key ) {
				event.preventDefault();
				self.chooseAppearanceOption( option );

				return;
			}

			if ( next >= 0 ) {
				event.preventDefault();
				self.chooseAppearanceOption( options[ next ] );
				options[ next ].focus();
			}
		} );

		// Focus leaving the dialog altogether (Tab past the last option)
		// closes it, so nothing stays open off-screen.
		menu.addEventListener( 'focusout', function ( event ) {
			if ( event.relatedTarget && ! switcher.contains( event.relatedTarget ) ) {
				close( false );
			}
		} );
	};

	/**
	 * Apply the choice a radio option represents.
	 *
	 * @param {HTMLElement} option The option element.
	 * @return {void}
	 */
	Portal.prototype.chooseAppearanceOption = function ( option ) {
		var mode = option.getAttribute( 'data-wcp-appearance-mode' );
		var visual = option.getAttribute( 'data-wcp-visual-option' );

		if ( mode ) {
			this.setAppearanceMode( mode );
		} else if ( visual ) {
			this.setVisual( visual, true );
		}
	};

	/**
	 * Set the appearance mode: `system`, `light` or `dark`.
	 *
	 * "System" is stored as nothing at all -- the absence of a choice is what
	 * lets the operating system (and the administrator's default) decide.
	 *
	 * @param {string} mode The mode.
	 * @return {void}
	 */
	Portal.prototype.setAppearanceMode = function ( mode ) {
		if ( 'light' === mode || 'dark' === mode ) {
			this.setTheme( mode, true );

			return;
		}

		try {
			window.localStorage.removeItem( this.themeKey );
		} catch ( error ) {}

		var adminDefault = ( CONFIG.theme && CONFIG.theme.adminDefault ) || 'system';
		var resolved = adminDefault;

		if ( 'light' !== resolved && 'dark' !== resolved ) {
			resolved = ( window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches ) ? 'dark' : 'light';
		}

		this.setTheme( resolved, false );
	};

	/**
	 * The theme currently applied to the document.
	 *
	 * @return {string} `dark` or `light`.
	 */
	Portal.prototype.currentTheme = function () {
		return 'dark' === document.documentElement.getAttribute( this.themeAttr ) ? 'dark' : 'light';
	};

	/**
	 * The customer's explicitly stored choice, if any.
	 *
	 * @return {string} `dark`, `light`, or an empty string.
	 */
	Portal.prototype.storedTheme = function () {
		try {
			var value = window.localStorage.getItem( this.themeKey );

			return ( 'dark' === value || 'light' === value ) ? value : '';
		} catch ( error ) {
			// Storage throws outright in some privacy modes. A theme
			// preference is never worth breaking a page over.
			return '';
		}
	};

	/**
	 * The customer's explicitly stored visual theme, if any.
	 *
	 * @return {string} A preset slug, or an empty string.
	 */
	Portal.prototype.storedVisual = function () {
		try {
			var value = window.localStorage.getItem( this.visualKey );

			return this.visualPresets.indexOf( value ) >= 0 ? value : '';
		} catch ( error ) {
			return '';
		}
	};

	/**
	 * Apply a colour scheme, optionally remembering it.
	 *
	 * @param {string}  theme   `dark` or `light`.
	 * @param {boolean} persist Whether this was a deliberate choice.
	 * @return {void}
	 */
	Portal.prototype.setTheme = function ( theme, persist ) {
		theme = 'dark' === theme ? 'dark' : 'light';

		document.documentElement.setAttribute( this.themeAttr, theme );

		if ( persist ) {
			try {
				window.localStorage.setItem( this.themeKey, theme );
			} catch ( error ) {}
		}

		this.syncAppearanceControls();
	};

	/**
	 * Apply a visual theme preset, optionally remembering it.
	 *
	 * @param {string}  visual  Preset slug.
	 * @param {boolean} persist Whether this was a deliberate choice.
	 * @return {void}
	 */
	Portal.prototype.setVisual = function ( visual, persist ) {
		if ( this.visualPresets.indexOf( visual ) < 0 ) {
			visual = this.visualDefault;
		}

		document.documentElement.setAttribute( this.visualAttr, visual );

		if ( persist ) {
			try {
				window.localStorage.setItem( this.visualKey, visual );
			} catch ( error ) {}
		}

		this.syncAppearanceControls();
	};

	/**
	 * Make every switcher's checked state match reality.
	 *
	 * @return {void}
	 */
	Portal.prototype.syncAppearanceControls = function () {
		var mode = this.storedTheme() || 'system';
		var visual = document.documentElement.getAttribute( this.visualAttr ) || this.visualDefault;
		var modes = this.root.querySelectorAll( '[data-wcp-appearance-mode]' );
		var visuals = this.root.querySelectorAll( '[data-wcp-visual-option]' );
		var i;

		for ( i = 0; i < modes.length; i++ ) {
			var isMode = modes[ i ].getAttribute( 'data-wcp-appearance-mode' ) === mode;

			modes[ i ].setAttribute( 'aria-checked', isMode ? 'true' : 'false' );
			modes[ i ].setAttribute( 'tabindex', isMode ? '0' : '-1' );
		}

		for ( i = 0; i < visuals.length; i++ ) {
			var isVisual = visuals[ i ].getAttribute( 'data-wcp-visual-option' ) === visual;

			visuals[ i ].setAttribute( 'aria-checked', isVisual ? 'true' : 'false' );
			visuals[ i ].setAttribute( 'tabindex', isVisual ? '0' : '-1' );
		}

		// The account panel names the active theme. The server cannot know it,
		// so it renders the configured default and this corrects it.
		var names = this.root.querySelectorAll( '[data-wcp-visual-label]' );

		for ( i = 0; i < names.length; i++ ) {
			if ( this.visualLabels[ visual ] ) {
				names[ i ].textContent = this.visualLabels[ visual ];
			}
		}
	};

	/* --- Section navigation --------------------------------------------- */

	/**
	 * Swap portal sections without a full page load.
	 *
	 * A progressive enhancement over the server-rendered portal, not a rewrite
	 * of it. Every link still points at a real, shareable URL that renders the
	 * same page on its own; this intercepts the click, fetches that URL, and
	 * replaces the content column with the part of the response that differs.
	 * Remove the script and the portal keeps working exactly as before.
	 *
	 * The response is the whole page, parsed with `DOMParser` and read for the
	 * pieces that change. That is deliberately dumber than a JSON API: there is
	 * one renderer, on the server, and no second template layer in the browser
	 * that could drift away from it.
	 *
	 * @return {void}
	 */
	Portal.prototype.bindSectionNav = function () {
		var self = this;

		this.content = this.root.querySelector( '[data-wcp-content]' );
		this.sectionSkeleton = this.root.querySelector( '[data-wcp-section-skeleton]' );

		if ( ! this.content || ! window.fetch || ! window.history || ! window.history.pushState || ! window.DOMParser ) {
			return;
		}

		this.navToken = 0;

		this.root.addEventListener( 'click', function ( event ) {
			var link = event.target.closest ? event.target.closest( '[data-wcp-link]' ) : null;

			if ( ! link || ! self.root.contains( link ) ) {
				return;
			}

			if ( event.defaultPrevented || 0 !== event.button ) {
				return;
			}

			if ( event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
				return;
			}

			if ( link.hasAttribute( 'target' ) || link.hasAttribute( 'download' ) ) {
				return;
			}

			var url = self.sameOriginUrl( link.getAttribute( 'href' ) );

			if ( ! url ) {
				return;
			}

			event.preventDefault();

			// The section swap owns the loading state from here; the in-panel
			// skeleton would otherwise fire underneath it.
			self.clearPending();
			self.navigateTo( url, true );
		} );

		window.addEventListener( 'popstate', function ( event ) {
			if ( event.state && event.state.wcp ) {
				self.navigateTo( window.location.href, false );
			}
		} );

		// Mark the entry the customer arrived on, so Back from a swapped
		// section returns here rather than leaving the portal.
		window.history.replaceState( { wcp: true }, '', window.location.href );
	};

	/**
	 * Resolve an href to a same-origin, same-page portal URL.
	 *
	 * Cross-origin links, and links to a different page entirely, are left to
	 * the browser -- swapping only the content column would be wrong for both.
	 *
	 * @param {string} href Raw href.
	 * @return {string} Absolute URL, or an empty string.
	 */
	Portal.prototype.sameOriginUrl = function ( href ) {
		if ( ! href || '#' === href.charAt( 0 ) ) {
			return '';
		}

		var url;

		try {
			url = new window.URL( href, window.location.href );
		} catch ( error ) {
			return '';
		}

		if ( url.origin !== window.location.origin ) {
			return '';
		}

		if ( url.pathname !== window.location.pathname ) {
			return '';
		}

		return url.href;
	};

	/**
	 * Fetch a portal URL and swap in its content.
	 *
	 * @param {string}  url  Absolute URL.
	 * @param {boolean} push Whether to add a history entry.
	 * @return {void}
	 */
	Portal.prototype.navigateTo = function ( url, push ) {
		var self = this;
		var token = ++this.navToken;

		this.beginSwitching();

		window.fetch( url, {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
		} )
			.then( function ( response ) {
				return response.ok ? response.text() : null;
			} )
			.then( function ( html ) {
				// A newer navigation started while this was in flight; its
				// result is the one that should win.
				if ( token !== self.navToken ) {
					return;
				}

				if ( null === html ) {
					window.location.href = url;

					return;
				}

				self.applyDocument( html, url, push );
			} )
			.catch( function () {
				if ( token === self.navToken ) {
					// Fall back to a real navigation rather than stranding the
					// customer on a half-updated screen.
					window.location.href = url;
				}
			} );
	};

	/**
	 * Replace the content column from a fetched document.
	 *
	 * @param {string}  html Response body.
	 * @param {string}  url  The URL that produced it.
	 * @param {boolean} push Whether to add a history entry.
	 * @return {void}
	 */
	Portal.prototype.applyDocument = function ( html, url, push ) {
		var parsed = new window.DOMParser().parseFromString( html, 'text/html' );
		var incoming = parsed.querySelector( '[data-wcp-content]' );

		if ( ! incoming ) {
			window.location.href = url;

			return;
		}

		this.endSwitching();

		this.content.innerHTML = incoming.innerHTML;

		// New content slides into place. The class is cleared when the
		// animation ends so the next swap can play it again.
		if ( motionAllowed() ) {
			var content = this.content;

			content.classList.add( 'is-entering' );
			content.addEventListener( 'animationend', function onEnter() {
				content.classList.remove( 'is-entering' );
				content.removeEventListener( 'animationend', onEnter );
			} );
		}

		this.syncChrome( parsed );

		if ( push ) {
			window.history.pushState( { wcp: true }, '', url );
		}

		document.title = parsed.title || document.title;

		// The swapped-in markup carries its own controls.
		this.rebindContent();

		this.positionIndicator( true );

		// Focus the content region so a keyboard or screen-reader user lands in
		// the new section rather than back at the top of the document with no
		// indication anything happened.
		var main = this.root.querySelector( '.wcp-main' );

		if ( main ) {
			main.focus();
		}

		if ( ! isMobileViewport() ) {
			window.scrollTo( { top: this.scrollTarget(), behavior: prefersReducedMotion() ? 'auto' : 'smooth' } );
		}
	};

	/**
	 * Update the parts of the shell that are outside the content column.
	 *
	 * @param {Document} parsed The fetched document.
	 * @return {void}
	 */
	Portal.prototype.syncChrome = function ( parsed ) {
		var pairs = [
			[ '[data-wcp-page-title]', 'textContent' ],
			[ '[data-wcp-page-subtitle]', 'textContent' ],
		];

		for ( var i = 0; i < pairs.length; i++ ) {
			var target = this.root.querySelector( pairs[ i ][ 0 ] );
			var source = parsed.querySelector( pairs[ i ][ 0 ] );

			if ( target && source ) {
				target.textContent = source.textContent;
			}
		}

		// Active navigation state, including `aria-current`.
		var incomingLinks = parsed.querySelectorAll( '[data-wcp-nav-link]' );
		var currentLinks = this.root.querySelectorAll( '[data-wcp-nav-link]' );

		for ( var j = 0; j < currentLinks.length && j < incomingLinks.length; j++ ) {
			currentLinks[ j ].className = incomingLinks[ j ].className;

			if ( incomingLinks[ j ].hasAttribute( 'aria-current' ) ) {
				currentLinks[ j ].setAttribute( 'aria-current', incomingLinks[ j ].getAttribute( 'aria-current' ) );
			} else {
				currentLinks[ j ].removeAttribute( 'aria-current' );
			}
		}

		// The footer lives outside the swapped content column but reports on
		// the section (active link) and on account state a form may just have
		// changed, so its contents are taken from the fetched document too.
		var footer = this.root.querySelector( '[data-wcp-footer]' );
		var incomingFooter = parsed.querySelector( '[data-wcp-footer]' );

		if ( footer && incomingFooter ) {
			footer.innerHTML = incomingFooter.innerHTML;
			this.syncAppearanceControls();
		}

		var incomingSection = parsed.querySelector( '[data-wcp-portal]' );

		if ( incomingSection && incomingSection.getAttribute( 'data-wcp-section' ) ) {
			this.root.setAttribute( 'data-wcp-section', incomingSection.getAttribute( 'data-wcp-section' ) );
		}
	};

	/**
	 * Re-attach behaviour to freshly swapped markup.
	 *
	 * @return {void}
	 */
	Portal.prototype.rebindContent = function () {
		this.bindForms();
		this.bindPendingNavigation();

		this.bindSpotlight();
		this.bindTilt();

		// Swapped-in markup can carry its own appearance readouts.
		this.syncAppearanceControls();
	};

	/**
	 * Where the page should sit after a section swap.
	 *
	 * @return {number}
	 */
	Portal.prototype.scrollTarget = function () {
		var rect = this.root.getBoundingClientRect();

		return Math.max( 0, Math.round( rect.top + window.pageYOffset ) );
	};

	/**
	 * Enter the switching state, revealing the skeleton if it lasts.
	 *
	 * @return {void}
	 */
	Portal.prototype.beginSwitching = function () {
		var self = this;

		this.root.setAttribute( 'data-wcp-switching', '' );
		this.content.setAttribute( 'aria-busy', 'true' );

		// Old content fades and lifts away while the next section loads.
		if ( motionAllowed() ) {
			this.content.classList.add( 'is-leaving' );
		}

		window.clearTimeout( this.switchTimer );

		this.switchTimer = window.setTimeout( function () {
			if ( self.sectionSkeleton ) {
				self.sectionSkeleton.hidden = false;
				self.content.hidden = true;
			}
		}, PENDING_DELAY );
	};

	/**
	 * Leave the switching state.
	 *
	 * @return {void}
	 */
	Portal.prototype.endSwitching = function () {
		window.clearTimeout( this.switchTimer );

		this.root.removeAttribute( 'data-wcp-switching' );
		this.content.removeAttribute( 'aria-busy' );
		this.content.classList.remove( 'is-leaving' );
		this.content.hidden = false;

		if ( this.sectionSkeleton ) {
			this.sectionSkeleton.hidden = true;
		}
	};

	/* --- Mobile drawer ------------------------------------------------- */

	Portal.prototype.bindDrawer = function () {
		var self = this;

		if ( ! this.sidebar || ! this.toggle ) {
			return;
		}

		this.toggle.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			self.setDrawer( ! self.drawerOpen );
		} );

		if ( this.closeButton ) {
			this.closeButton.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				self.setDrawer( false );
			} );
		}

		if ( this.scrim ) {
			this.scrim.addEventListener( 'click', function () {
				self.setDrawer( false );
			} );
		}

		document.addEventListener( 'keydown', function ( event ) {
			if ( ! self.drawerOpen ) {
				return;
			}

			if ( 'Escape' === event.key || 'Esc' === event.key ) {
				event.preventDefault();
				self.setDrawer( false );
				return;
			}

			if ( 'Tab' === event.key ) {
				self.trapFocus( event );
			}
		} );

		// Following a link inside the drawer navigates away; close first so a
		// cached back-navigation does not restore an open drawer.
		this.sidebar.addEventListener( 'click', function ( event ) {
			if ( self.drawerOpen && event.target.closest( 'a[href]' ) ) {
				self.setDrawer( false );
			}
		} );

		window.addEventListener(
			'resize',
			rafThrottle( function () {
				if ( self.drawerOpen && ! isMobileViewport() ) {
					self.setDrawer( false );
				}
			} )
		);
	};

	/**
	 * Open or close the drawer.
	 *
	 * @param {boolean} open Desired state.
	 */
	Portal.prototype.setDrawer = function ( open ) {
		if ( open === this.drawerOpen ) {
			return;
		}

		this.drawerOpen = open;
		this.root.classList.toggle( 'is-drawer-open', open );
		this.toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		document.documentElement.classList.toggle( 'wcp-scroll-lock', open );

		if ( open ) {
			this.lastFocused = document.activeElement;

			var target = this.closeButton || this.sidebar.querySelector( FOCUSABLE );

			if ( target ) {
				// Wait a frame so focus lands after the drawer starts moving.
				window.requestAnimationFrame( function () {
					target.focus();
				} );
			}

			return;
		}

		if ( this.lastFocused && typeof this.lastFocused.focus === 'function' ) {
			this.lastFocused.focus();
		} else if ( this.toggle ) {
			this.toggle.focus();
		}

		this.lastFocused = null;
	};

	/**
	 * Keep Tab focus inside the open drawer.
	 *
	 * @param {KeyboardEvent} event Tab keydown.
	 */
	Portal.prototype.trapFocus = function ( event ) {
		var focusable = Array.prototype.filter.call(
			this.sidebar.querySelectorAll( FOCUSABLE ),
			function ( element ) {
				return null !== element.offsetParent;
			}
		);

		if ( ! focusable.length ) {
			return;
		}

		var first = focusable[ 0 ];
		var last = focusable[ focusable.length - 1 ];
		var active = document.activeElement;

		if ( event.shiftKey && ( active === first || ! this.sidebar.contains( active ) ) ) {
			event.preventDefault();
			last.focus();
			return;
		}

		if ( ! event.shiftKey && active === last ) {
			event.preventDefault();
			first.focus();
		}
	};

	/* --- Sliding nav indicator ----------------------------------------- */

	Portal.prototype.bindIndicator = function () {
		var self = this;

		if ( ! this.nav || ! this.indicator ) {
			return;
		}

		this.positionIndicator( false );

		// Move the indicator the moment a section is chosen, so the click
		// feels answered before the page navigation completes.
		this.nav.addEventListener( 'click', function ( event ) {
			var link = event.target.closest( '[data-wcp-nav-link]' );

			if ( ! link || link.classList.contains( 'is-active' ) ) {
				return;
			}

			Array.prototype.forEach.call(
				self.nav.querySelectorAll( '[data-wcp-nav-link]' ),
				function ( item ) {
					item.classList.remove( 'is-active' );
					item.removeAttribute( 'aria-current' );
				}
			);

			link.classList.add( 'is-active' );
			link.setAttribute( 'aria-current', 'page' );
			self.positionIndicator( ! prefersReducedMotion() );
		} );

		var reposition = rafThrottle( function () {
			self.positionIndicator( false );
		} );

		window.addEventListener( 'resize', reposition );

		if ( 'undefined' !== typeof window.ResizeObserver ) {
			new window.ResizeObserver( reposition ).observe( this.nav );
		}

		// Web fonts can reflow the nav after first paint.
		if ( document.fonts && document.fonts.ready && document.fonts.ready.then ) {
			document.fonts.ready.then( reposition ).catch( function () {} );
		}
	};

	/**
	 * Align the indicator with the active nav link.
	 *
	 * @param {boolean} animate Whether the move should transition.
	 */
	Portal.prototype.positionIndicator = function ( animate ) {
		var active = this.nav.querySelector( '[data-wcp-nav-link].is-active' );

		if ( ! active ) {
			this.nav.removeAttribute( 'data-indicator-ready' );
			return;
		}

		var navRect = this.nav.getBoundingClientRect();
		var linkRect = active.getBoundingClientRect();

		if ( ! linkRect.height ) {
			// Sidebar is off-canvas and unmeasurable; retry when it is shown.
			return;
		}

		if ( ! animate ) {
			this.indicator.style.transition = 'none';
		}

		this.nav.style.setProperty( '--wcp-indicator-y', ( linkRect.top - navRect.top ) + 'px' );
		this.nav.style.setProperty( '--wcp-indicator-height', linkRect.height + 'px' );
		this.nav.setAttribute( 'data-indicator-ready', '' );

		if ( ! animate ) {
			// Force a reflow so the jump is not transitioned, then restore.
			void this.indicator.offsetWidth;
			this.indicator.style.transition = '';
		}
	};

	/* --- Pointer spotlight ---------------------------------------------- */

	/**
	 * Track the pointer across selected premium surfaces.
	 *
	 * Decorative only: skipped entirely for coarse pointers, for visitors who
	 * asked for reduced motion and when the administrator has turned motion
	 * off. It never moves layout -- it only repositions a gradient on a
	 * compositor layer, at most once per frame.
	 *
	 * @return {void}
	 */
	Portal.prototype.bindSpotlight = function () {
		if ( ! hasFinePointer() || ! motionAllowed() ) {
			return;
		}

		var surfaces = this.root.querySelectorAll( '[data-wcp-spotlight]:not([data-wcp-spotlight-ready])' );

		Array.prototype.forEach.call( surfaces, function ( surface ) {
			var frame = null;

			surface.setAttribute( 'data-wcp-spotlight-ready', '' );

			surface.addEventListener(
				'pointermove',
				function ( event ) {
					if ( null !== frame ) {
						return;
					}

					frame = window.requestAnimationFrame( function () {
						frame = null;

						var rect = surface.getBoundingClientRect();

						if ( ! rect.width || ! rect.height ) {
							return;
						}

						surface.style.setProperty( '--wcp-spot-x', ( ( ( event.clientX - rect.left ) / rect.width ) * 100 ).toFixed( 2 ) + '%' );
						surface.style.setProperty( '--wcp-spot-y', ( ( ( event.clientY - rect.top ) / rect.height ) * 100 ).toFixed( 2 ) + '%' );
					} );
				},
				{ passive: true }
			);

			surface.addEventListener( 'pointerenter', function () {
				surface.setAttribute( 'data-spotlight-active', '' );
			} );

			surface.addEventListener( 'pointerleave', function () {
				surface.removeAttribute( 'data-spotlight-active' );

				if ( null !== frame ) {
					window.cancelAnimationFrame( frame );
					frame = null;
				}
			} );
		} );
	};

	/* --- 3D tilt ---------------------------------------------------------- */

	/**
	 * Tilt selected cards towards the pointer.
	 *
	 * A small rotation (5deg by default, capped at 6) on the card and a
	 * `translateZ` on its `.wcp-depth-*` children, both applied purely
	 * through CSS custom properties so the stylesheet owns the transform and
	 * can disable it. Gated on a fine pointer, reduced motion, and the
	 * administrator's 3D switch; a card inside a form does not tilt while a
	 * field in it has focus, so nothing moves under a typing cursor.
	 *
	 * @return {void}
	 */
	Portal.prototype.bindTilt = function () {
		if ( ! effectsAllowed() ) {
			return;
		}

		var cards = this.root.querySelectorAll( '[data-wcp-tilt]:not([data-wcp-tilt-ready])' );

		Array.prototype.forEach.call( cards, function ( card ) {
			var frame = null;
			var max = parseFloat( card.getAttribute( 'data-wcp-tilt-max' ) );

			if ( isNaN( max ) ) {
				max = 5;
			}

			max = Math.min( 6, Math.max( 0, max ) );

			card.setAttribute( 'data-wcp-tilt-ready', '' );

			var reset = function () {
				card.classList.remove( 'is-tilting' );
				card.style.removeProperty( '--wcp-tilt-x' );
				card.style.removeProperty( '--wcp-tilt-y' );

				if ( null !== frame ) {
					window.cancelAnimationFrame( frame );
					frame = null;
				}
			};

			card.addEventListener(
				'pointermove',
				function ( event ) {
					if ( null !== frame ) {
						return;
					}

					// Never tilt while the customer is typing into the card.
					var active = document.activeElement;

					if ( active && card.contains( active ) && active.matches && active.matches( 'input, select, textarea' ) ) {
						reset();

						return;
					}

					frame = window.requestAnimationFrame( function () {
						frame = null;

						var rect = card.getBoundingClientRect();

						if ( ! rect.width || ! rect.height ) {
							return;
						}

						// -0.5 … 0.5 across each axis, centre = no tilt.
						var px = ( event.clientX - rect.left ) / rect.width - 0.5;
						var py = ( event.clientY - rect.top ) / rect.height - 0.5;

						card.classList.add( 'is-tilting' );
						card.style.setProperty( '--wcp-tilt-x', ( -py * max * 2 ).toFixed( 2 ) + 'deg' );
						card.style.setProperty( '--wcp-tilt-y', ( px * max * 2 ).toFixed( 2 ) + 'deg' );
					} );
				},
				{ passive: true }
			);

			card.addEventListener( 'pointerleave', reset );
			card.addEventListener( 'pointercancel', reset );
		} );
	};

	/* --- Sticky header elevation --------------------------------------- */

	Portal.prototype.bindHeader = function () {
		var self = this;

		if ( ! this.header || ! this.sentinel || 'undefined' === typeof window.IntersectionObserver ) {
			return;
		}

		var observer = new window.IntersectionObserver(
			function ( entries ) {
				self.header.classList.toggle( 'is-stuck', ! entries[ 0 ].isIntersecting );
			},
			{ threshold: [ 0 ] }
		);

		observer.observe( this.sentinel );
	};

	/* ------------------------------------------------------------------ */
	/* Bootstrap                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Initialise every portal on the page.
	 */
	function init() {
		var roots = document.querySelectorAll( '[data-wcp-portal]' );

		Array.prototype.forEach.call( roots, function ( root ) {
			if ( root.hasAttribute( 'data-wcp-ready' ) ) {
				return;
			}

			root.setAttribute( 'data-wcp-ready', '' );

			try {
				new Portal( root );
			} catch ( error ) {
				// A broken enhancement must never take the page down; the
				// portal remains usable without JavaScript.
				if ( window.console && window.console.error ) {
					window.console.error( '[wcp] portal init failed', error );
				}
			}
		} );
	}

	/*
	 * The script is enqueued in the footer, so by the time it runs the portal
	 * itself is already parsed -- and initialising *now* rather than waiting
	 * for DOMContentLoaded means the full-bleed fit is applied before the
	 * browser's first paint, instead of shifting the page afterwards.
	 *
	 * What is not parsed yet is whatever follows the portal, which the bottom
	 * measurement reads. That corrects itself: the ResizeObserver watching the
	 * document element fires as the rest of the page arrives. `init()` is
	 * idempotent, so the DOMContentLoaded pass below remains a safety net for
	 * a portal that was not in the document yet.
	 */
	init();

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	}
} )();
