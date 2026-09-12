/**
 * WooCommerce Customer Portal — admin behaviour.
 *
 * Enhances the plugin-list shortcode hint with click-to-copy and adds the
 * settings-screen conveniences (landing-section guard, accent preview).
 * All user-facing text comes from markup rendered by PHP, so nothing here
 * needs translating.
 *
 * @package WooCommerce_Customer_Portal
 */

( function () {
	'use strict';

	/**
	 * Make the shortcode snippet in the plugin row copyable.
	 */
	function init() {
		var snippets = document.querySelectorAll( '.wcp-row-meta code' );

		Array.prototype.forEach.call( snippets, function ( snippet ) {
			snippet.setAttribute( 'role', 'button' );
			snippet.setAttribute( 'tabindex', '0' );

			function copy() {
				if ( ! navigator.clipboard || ! navigator.clipboard.writeText ) {
					return;
				}

				navigator.clipboard
					.writeText( snippet.textContent )
					.then( function () {
						snippet.classList.add( 'is-copied' );

						window.setTimeout( function () {
							snippet.classList.remove( 'is-copied' );
						}, 1400 );
					} )
					.catch( function () {} );
			}

			snippet.addEventListener( 'click', copy );
			snippet.addEventListener( 'keydown', function ( event ) {
				if ( 'Enter' === event.key || ' ' === event.key ) {
					event.preventDefault();
					copy();
				}
			} );
		} );
	}

	/**
	 * Settings screen: keep the landing-section choices honest and preview
	 * the accent as it is picked.
	 *
	 * Everything here is a convenience. The server re-validates every value on
	 * save, so a browser that ignores all of this still cannot store a landing
	 * section that is disabled or an accent that is not a colour.
	 */
	function initSettings() {
		var landing = document.querySelector( '[data-wcp-default-section]' );
		var checks = document.querySelectorAll( '[data-wcp-section]' );

		if ( landing && checks.length ) {
			var syncLanding = function () {
				var enabled = {};

				Array.prototype.forEach.call( checks, function ( check ) {
					enabled[ check.getAttribute( 'data-wcp-section' ) ] = check.checked;
				} );

				Array.prototype.forEach.call( landing.options, function ( option ) {
					option.disabled = ! enabled[ option.value ];
				} );

				// If the current choice was just disabled, move to the first
				// enabled option so the select never shows an impossible value.
				if ( landing.options[ landing.selectedIndex ] && landing.options[ landing.selectedIndex ].disabled ) {
					for ( var i = 0; i < landing.options.length; i++ ) {
						if ( ! landing.options[ i ].disabled ) {
							landing.selectedIndex = i;
							break;
						}
					}
				}
			};

			Array.prototype.forEach.call( checks, function ( check ) {
				check.addEventListener( 'change', syncLanding );
			} );

			syncLanding();
		}

		var picker = document.querySelector( '[data-wcp-accent]' );
		var preview = document.querySelector( '[data-wcp-accent-preview]' );
		var hex = document.querySelector( '[data-wcp-accent-hex]' );
		var contrast = document.querySelector( '[data-wcp-accent-contrast]' );

		if ( ! picker ) {
			return;
		}

		var luminance = function ( rgb ) {
			var parts = rgb.map( function ( channel ) {
				var c = channel / 255;

				return c <= 0.03928 ? c / 12.92 : Math.pow( ( c + 0.055 ) / 1.055, 2.4 );
			} );

			return 0.2126 * parts[ 0 ] + 0.7152 * parts[ 1 ] + 0.0722 * parts[ 2 ];
		};

		var ratio = function ( a, b ) {
			var la = luminance( a );
			var lb = luminance( b );

			return ( Math.max( la, lb ) + 0.05 ) / ( Math.min( la, lb ) + 0.05 );
		};

		var update = function () {
			var value = picker.value;

			if ( ! /^#[0-9a-f]{6}$/i.test( value ) ) {
				return;
			}

			var rgb = [
				parseInt( value.slice( 1, 3 ), 16 ),
				parseInt( value.slice( 3, 5 ), 16 ),
				parseInt( value.slice( 5, 7 ), 16 ),
			];

			// The same choice the server makes: whichever of white or near-black
			// reads better on this colour.
			var onWhite = ratio( rgb, [ 255, 255, 255 ] );
			var onDark = ratio( rgb, [ 16, 17, 28 ] );
			var on = onWhite >= onDark ? '#ffffff' : '#10111c';
			var best = Math.max( onWhite, onDark );

			if ( preview ) {
				preview.style.setProperty( '--wcp-admin-accent', value );
				preview.style.setProperty( '--wcp-admin-accent-on', on );
			}

			if ( hex ) {
				hex.textContent = value;
			}

			if ( contrast ) {
				var rounded = Math.round( best * 100 ) / 100;

				contrast.textContent = rounded + ':1 contrast';
				contrast.classList.toggle( 'wcp-accent__contrast--ok', best >= 4.5 );
				contrast.classList.toggle( 'wcp-accent__contrast--warn', best < 4.5 );
			}
		};

		picker.addEventListener( 'input', update );
		picker.addEventListener( 'change', update );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init();
			initSettings();
		} );
	} else {
		init();
		initSettings();
	}
} )();
