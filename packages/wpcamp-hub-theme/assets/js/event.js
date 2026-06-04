/**
 * Single event — List / Map / Timetable view switcher + Leaflet map.
 */
( function () {
	'use strict';

	function initSwitcher( root ) {
		var buttons = root.querySelectorAll( '.wpch-sv__btn' );
		var views = root.querySelectorAll( '.wpch-sv__view' );
		var mapInited = false;

		function activate( view ) {
			buttons.forEach( function ( b ) {
				var on = b.getAttribute( 'data-view' ) === view;
				b.classList.toggle( 'is-active', on );
				b.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			} );
			views.forEach( function ( v ) {
				v.classList.toggle(
					'is-active',
					v.getAttribute( 'data-view' ) === view
				);
			} );

			if ( view === 'map' && ! mapInited ) {
				mapInited = initMap( root );
			}
		}

		buttons.forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				activate( b.getAttribute( 'data-view' ) );
			} );
		} );
	}

	function initMap( root ) {
		if ( typeof window.L === 'undefined' ) {
			return false;
		}

		var el = root.querySelector( '#wpch-event-map' );
		var lat = parseFloat( root.getAttribute( 'data-lat' ) );
		var lng = parseFloat( root.getAttribute( 'data-lng' ) );

		if ( ! el || isNaN( lat ) || isNaN( lng ) ) {
			return false;
		}

		// Self-hosted marker images.
		var base =
			( window.wpcampHubEvent && window.wpcampHubEvent.markerBase ) || '';
		window.L.Icon.Default.mergeOptions( {
			iconUrl: base + 'marker-icon.png',
			iconRetinaUrl: base + 'marker-icon-2x.png',
			shadowUrl: base + 'marker-shadow.png',
		} );

		var map = window.L.map( el, {
			scrollWheelZoom: false,
		} ).setView( [ lat, lng ], 14 );

		// CARTO Voyager tiles (OpenStreetMap data).
		window.L.tileLayer(
			'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
			{
				attribution:
					'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
				subdomains: 'abcd',
				maxZoom: 20,
			}
		).addTo( map );

		var label = root.getAttribute( 'data-label' ) || '';
		var sessions = parseSessions( root );

		var popup = '<strong>' + escapeHtml( label ) + '</strong>';
		if ( sessions.length ) {
			popup += '<ul class="wpch-sv__map-list">';
			sessions.forEach( function ( s ) {
				popup +=
					'<li><a href="' +
					encodeURI( s.url ) +
					'">' +
					escapeHtml( s.title ) +
					'</a>' +
					( s.time ? ' <span>' + escapeHtml( s.time ) + '</span>' : '' ) +
					'</li>';
			} );
			popup += '</ul>';
		}

		window.L.marker( [ lat, lng ] ).addTo( map ).bindPopup( popup ).openPopup();

		// Leaflet needs a resize nudge when revealed from a hidden container.
		setTimeout( function () {
			map.invalidateSize();
		}, 50 );

		return true;
	}

	function parseSessions( root ) {
		var el = root.querySelector( '#wpch-event-map' );
		if ( ! el ) {
			return [];
		}
		try {
			return JSON.parse( el.getAttribute( 'data-sessions' ) || '[]' );
		} catch ( e ) {
			return [];
		}
	}

	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = str == null ? '' : String( str );
		return div.innerHTML;
	}

	function init() {
		document.querySelectorAll( '.wpch-sv' ).forEach( initSwitcher );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
