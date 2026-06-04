/**
 * Accent colour options for a stat, mapped to theme.json palette presets.
 */
import { __ } from '@wordpress/i18n';

export const ACCENTS = [
	{ value: 'fest-gold', label: __( 'Gold', 'wpcamp-hub' ) },
	{ value: 'fest-coral', label: __( 'Coral', 'wpcamp-hub' ) },
	{ value: 'fest-teal', label: __( 'Teal', 'wpcamp-hub' ) },
	{ value: 'fest-violet', label: __( 'Violet', 'wpcamp-hub' ) },
	{ value: 'brand', label: __( 'Brand blue', 'wpcamp-hub' ) },
];

/**
 * Format a number with a thousands separator for display.
 *
 * @param {number}  value     The number.
 * @param {boolean} separator Whether to group thousands.
 * @return {string} Formatted number.
 */
export function formatNumber( value, separator ) {
	const n = Number.isFinite( value ) ? value : 0;
	if ( ! separator ) {
		return String( n );
	}
	return n.toString().replace( /\B(?=(\d{3})+(?!\d))/g, ',' );
}
