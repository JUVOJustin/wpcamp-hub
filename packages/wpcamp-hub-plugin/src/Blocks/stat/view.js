/**
 * Stat — front-end count-up animation.
 *
 * Each .wpch-stat__value counts from 0 up to its data-target the first time it
 * scrolls into view. Honours prefers-reduced-motion (shows the final value
 * immediately).
 */

const DURATION = 1600; // ms

function formatNumber( value, separator ) {
	const n = Math.round( value );
	if ( ! separator ) {
		return String( n );
	}
	return n.toString().replace( /\B(?=(\d{3})+(?!\d))/g, ',' );
}

function animate( el ) {
	const target = parseFloat( el.getAttribute( 'data-target' ) ) || 0;
	const separator = el.getAttribute( 'data-separator' ) === '1';

	const reduce =
		window.matchMedia &&
		window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	if ( reduce || target === 0 ) {
		el.textContent = formatNumber( target, separator );
		return;
	}

	const start = performance.now();

	function tick( now ) {
		const progress = Math.min( ( now - start ) / DURATION, 1 );
		// easeOutCubic
		const eased = 1 - Math.pow( 1 - progress, 3 );
		el.textContent = formatNumber( target * eased, separator );
		if ( progress < 1 ) {
			requestAnimationFrame( tick );
		} else {
			el.textContent = formatNumber( target, separator );
		}
	}

	requestAnimationFrame( tick );
}

function init() {
	const values = document.querySelectorAll(
		'.wpch-stat__value[data-target]'
	);
	if ( ! values.length ) {
		return;
	}

	if ( ! ( 'IntersectionObserver' in window ) ) {
		values.forEach( animate );
		return;
	}

	const observer = new IntersectionObserver(
		( entries, obs ) => {
			entries.forEach( ( entry ) => {
				if ( entry.isIntersecting ) {
					animate( entry.target );
					obs.unobserve( entry.target );
				}
			} );
		},
		{ threshold: 0.4 }
	);

	values.forEach( ( el ) => observer.observe( el ) );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
