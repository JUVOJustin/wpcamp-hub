/**
 * Tweets archive — filter rail + AJAX pagination for the community feed.
 *
 * The left rail filters by category (with live counts), event and sort; the
 * timeline has its own search. Filtering resets to page 1 and replaces the
 * grid; "Load more" appends the next page. All requests hit the plugin's
 * wpcamp_tweet_feed AJAX action, which returns the same card markup the server
 * rendered on first paint. Without JS the <noscript> submit falls back to a
 * normal filtered URL.
 */
( function () {
	'use strict';

	var cfg = window.wpcampHubFeed;
	if ( ! cfg || ! cfg.ajaxUrl ) {
		return;
	}

	var root = document.querySelector( '[data-feed-root]' );
	if ( ! root ) {
		return;
	}

	var grid = root.querySelector( '[data-feed-grid]' );
	var countEl = root.querySelector( '[data-feed-count]' );
	var emptyEl = root.querySelector( '[data-feed-empty]' );
	var moreBtn = root.querySelector( '[data-feed-more]' );
	var stats = Array.prototype.slice.call(
		root.querySelectorAll( '[data-category]' )
	);
	var eventSelect = root.querySelector( '[data-feed-event]' );
	var sortSelect = root.querySelector( '[data-feed-sort]' );
	var searchInput = root.querySelector( '[data-feed-search]' );
	var filtersForm = root.querySelector( '[data-feed-filters]' );

	var state = {
		category: 'all',
		event: 0,
		search: '',
		sort: 'newest',
		page: 1,
		maxPages: 1,
		loading: false,
	};

	// Seed state from the server-rendered grid + active rail row / controls.
	if ( grid ) {
		state.page = parseInt( grid.getAttribute( 'data-page' ), 10 ) || 1;
		state.maxPages =
			parseInt( grid.getAttribute( 'data-max-pages' ), 10 ) || 1;
	}
	var activeStat = root.querySelector( '[data-category].is-active' );
	if ( activeStat ) {
		state.category = activeStat.getAttribute( 'data-category' ) || 'all';
	}
	if ( eventSelect ) {
		state.event = parseInt( eventSelect.value, 10 ) || 0;
	}
	if ( sortSelect ) {
		state.sort = sortSelect.value || 'newest';
	}
	if ( searchInput ) {
		state.search = searchInput.value || '';
	}

	if ( filtersForm && filtersForm.tagName === 'FORM' ) {
		filtersForm.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
		} );
	}

	function request( params, onDone ) {
		var body = new URLSearchParams();
		body.set( 'action', cfg.action );
		body.set( 'nonce', cfg.nonce );
		body.set( 'category', params.category || 'all' );
		body.set( 'event', String( params.event || 0 ) );
		body.set( 'search', params.search || '' );
		body.set( 'sort', params.sort || 'newest' );
		body.set( 'paged', String( params.paged || 1 ) );

		fetch( cfg.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			credentials: 'same-origin',
			body: body.toString(),
		} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( res ) {
				onDone( res && res.success ? res.data : null );
			} )
			.catch( function () {
				onDone( null );
			} );
	}

	function setCount( found ) {
		if ( ! countEl ) {
			return;
		}
		var n = typeof found === 'number' ? found : 0;
		countEl.textContent =
			n === 1 ? '1 post' : n.toLocaleString() + ' posts';
	}

	function setLoading( on ) {
		state.loading = on;
		root.classList.toggle( 'is-loading', on );
		if ( moreBtn ) {
			moreBtn.disabled = on;
		}
	}

	function toggleMore() {
		if ( moreBtn ) {
			moreBtn.hidden = state.page >= state.maxPages;
		}
	}

	// Replace the grid (filter change / fresh query).
	function refresh() {
		setLoading( true );
		state.page = 1;
		request(
			{
				category: state.category,
				event: state.event,
				search: state.search,
				sort: state.sort,
				paged: 1,
			},
			function ( data ) {
				setLoading( false );
				if ( ! data ) {
					return;
				}
				grid.innerHTML = data.html || '';
				state.maxPages = data.maxPages || 1;
				setCount( data.found );
				if ( emptyEl ) {
					emptyEl.hidden = !! data.html;
				}
				toggleMore();
			}
		);
	}

	// Append the next page (load more).
	function loadMore() {
		if ( state.loading || state.page >= state.maxPages ) {
			return;
		}
		setLoading( true );
		var next = state.page + 1;
		request(
			{
				category: state.category,
				event: state.event,
				search: state.search,
				sort: state.sort,
				paged: next,
			},
			function ( data ) {
				setLoading( false );
				if ( ! data ) {
					return;
				}
				grid.insertAdjacentHTML( 'beforeend', data.html || '' );
				state.page = next;
				state.maxPages = data.maxPages || state.maxPages;
				toggleMore();
			}
		);
	}

	// ---- wiring -------------------------------------------------------------
	stats.forEach( function ( stat ) {
		stat.addEventListener( 'click', function () {
			stats.forEach( function ( s ) {
				var on = s === stat;
				s.classList.toggle( 'is-active', on );
				s.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			} );
			state.category = stat.getAttribute( 'data-category' ) || 'all';
			refresh();
		} );
	} );

	if ( eventSelect ) {
		eventSelect.addEventListener( 'change', function () {
			state.event = parseInt( eventSelect.value, 10 ) || 0;
			refresh();
		} );
	}

	if ( sortSelect ) {
		sortSelect.addEventListener( 'change', function () {
			state.sort = sortSelect.value || 'newest';
			refresh();
		} );
	}

	if ( searchInput ) {
		var debounce;
		searchInput.addEventListener( 'input', function () {
			window.clearTimeout( debounce );
			debounce = window.setTimeout( function () {
				state.search = searchInput.value || '';
				refresh();
			}, 300 );
		} );
	}

	if ( moreBtn ) {
		moreBtn.addEventListener( 'click', loadMore );
	}
} )();
