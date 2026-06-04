/**
 * Header behaviours — collapsible search panel.
 */
( function () {
	'use strict';

	function initSearch() {
		var toggle = document.getElementById( 'hdr-search-toggle' );
		var panel = document.getElementById( 'hdr-search-panel' );

		if ( ! toggle || ! panel ) {
			return;
		}

		var wrap = toggle.closest( '.hdr-search' ) || panel.parentNode;

		function open() {
			panel.hidden = false;
			toggle.setAttribute( 'aria-expanded', 'true' );
			var field = panel.querySelector( '.search-field' );
			if ( field ) {
				field.focus();
			}
			document.addEventListener( 'keydown', onKeydown );
			document.addEventListener( 'click', onOutsideClick );
		}

		function close() {
			panel.hidden = true;
			toggle.setAttribute( 'aria-expanded', 'false' );
			document.removeEventListener( 'keydown', onKeydown );
			document.removeEventListener( 'click', onOutsideClick );
		}

		function isOpen() {
			return toggle.getAttribute( 'aria-expanded' ) === 'true';
		}

		function onKeydown( e ) {
			if ( e.key === 'Escape' ) {
				close();
				toggle.focus();
			}
		}

		function onOutsideClick( e ) {
			if ( wrap && ! wrap.contains( e.target ) ) {
				close();
			}
		}

		toggle.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			if ( isOpen() ) {
				close();
			} else {
				open();
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initSearch );
	} else {
		initSearch();
	}
} )();
