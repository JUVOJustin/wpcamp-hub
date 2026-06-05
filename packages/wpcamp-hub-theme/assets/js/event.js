/**
 * Single event — List / Map / Timetable view switcher + Leaflet map.
 *
 * Map (prototype logic): markers for every event that has coordinates. The
 * current event is highlighted and preselected. Clicking an event marker shows
 * that event's rooms — each listing its sessions — in the sidebar.
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
		var sidebar = root.querySelector( '#wpch-event-sidebar' );
		if ( ! el || ! sidebar ) {
			return false;
		}

		var events = parseJson( el.getAttribute( 'data-events' ) );
		var currentId = el.getAttribute( 'data-current' );
		if ( ! events.length ) {
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

		var map = window.L.map( el, { scrollWheelZoom: false } );

		window.L.tileLayer(
			'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
			{
				attribution:
					'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
				subdomains: 'abcd',
				maxZoom: 20,
			}
		).addTo( map );

		var bounds = [];
		var currentMarker = null;
		var markers = {};

		events.forEach( function ( ev ) {
			var marker = window.L.marker( [ ev.lat, ev.lng ] ).addTo( map );
			marker.bindTooltip( ev.title, { direction: 'top' } );
			marker.on( 'click', function () {
				selectEvent( ev, marker );
			} );
			markers[ ev.id ] = marker;
			bounds.push( [ ev.lat, ev.lng ] );
			if ( String( ev.id ) === String( currentId ) ) {
				currentMarker = marker;
			}
		} );

		if ( bounds.length > 1 ) {
			map.fitBounds( bounds, { padding: [ 40, 40 ], maxZoom: 14 } );
		} else {
			map.setView( bounds[ 0 ], 14 );
		}

		function selectEvent( ev, marker ) {
			renderSidebar( sidebar, ev );
			el.querySelectorAll( '.leaflet-marker-icon' ).forEach( function ( m ) {
				m.classList.remove( 'wpch-marker--active' );
			} );
			if ( marker && marker._icon ) {
				marker._icon.classList.add( 'wpch-marker--active' );
			}
		}

		// Preselect the current event.
		var current = events.filter( function ( e ) {
			return String( e.id ) === String( currentId );
		} )[ 0 ];
		if ( current ) {
			selectEvent( current, currentMarker );
		}

		setTimeout( function () {
			map.invalidateSize();
		}, 50 );

		return true;
	}

	function renderSidebar( sidebar, ev ) {
		var html =
			'<div class="wpch-sv__sidebar-head">' +
			'<div class="wpch-sv__sidebar-title">' +
			escapeHtml( ev.title ) +
			'</div>';
		if ( ev.location ) {
			html +=
				'<div class="wpch-sv__sidebar-loc">' +
				escapeHtml( ev.location ) +
				'</div>';
		}
		html += '</div>';

		if ( ! ev.rooms || ! ev.rooms.length ) {
			html +=
				'<p class="wpch-sv__sidebar-empty">' +
				escapeHtml( 'No sessions listed for this event.' ) +
				'</p>';
			sidebar.innerHTML = html;
			return;
		}

		ev.rooms.forEach( function ( room ) {
			html +=
				'<div class="wpch-sv__room">' +
				'<div class="wpch-sv__room-name">' +
				escapeHtml( room.name ) +
				'</div><ul class="wpch-sv__room-sessions">';
			room.sessions.forEach( function ( s ) {
				html +=
					'<li style="--wpch-track:' +
					cssColor( s.color ) +
					'"><a href="' +
					encodeURI( s.url ) +
					'">' +
					'<span class="wpch-sv__room-dot"></span>' +
					'<span class="wpch-sv__room-title">' +
					escapeHtml( s.title ) +
					'</span>' +
					( s.time
						? '<span class="wpch-sv__room-time">' +
						  escapeHtml( s.time ) +
						  '</span>'
						: '' ) +
					'</a></li>';
			} );
			html += '</ul></div>';
		} );

		sidebar.innerHTML = html;
	}

	function parseJson( str ) {
		try {
			return JSON.parse( str || '[]' );
		} catch ( e ) {
			return [];
		}
	}

	function cssColor( c ) {
		// Allow only hex / simple css var values to be inlined.
		return /^#[0-9a-fA-F]{3,8}$|^var\([\w-]+\)$/.test( c ) ? c : '#3858e9';
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
