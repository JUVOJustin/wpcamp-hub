/**
 * Community feed post categories — label, accent colour and an inline icon.
 * Mirrors the design's feedTypeMeta. Icons are Lucide paths (ISC).
 */
import { __ } from '@wordpress/i18n';

export const CATEGORIES = {
	attendance: {
		label: __( 'Going to WCEU', 'wpcamp-hub' ),
		color: 'fest-teal',
		icon: '<path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/>',
	},
	networking: {
		label: __( 'Wants to meet', 'wpcamp-hub' ),
		color: 'brand',
		icon: '<path d="M10 2v2"/><path d="M14 2v2"/><path d="M16 8a1 1 0 0 1 1 1v8a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V9a1 1 0 0 1 1-1h14a4 4 0 1 1 0 8h-1"/><path d="M6 2v2"/>',
	},
	sideevent: {
		label: __( 'Side event', 'wpcamp-hub' ),
		color: 'fest-coral',
		icon: '<path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01"/><path d="M22 8h.01"/><path d="M15 2h.01"/><path d="M22 20h.01"/><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L12 12"/><path d="m22 13-.82-.33c-.86-.34-1.82.2-1.98 1.11c-.11.7-.72 1.22-1.43 1.22H17"/><path d="m11 2 .33.82c.34.86-.2 1.82-1.11 1.98C9.52 4.9 9 5.52 9 6.23V7"/><path d="M11 13c1.93 1.93 2.83 4.17 2 5-.83.83-3.07-.07-5-2-1.93-1.93-2.83-4.17-2-5 .83-.83 3.07.07 5 2Z"/>',
	},
	participation: {
		label: __( 'Attending an event', 'wpcamp-hub' ),
		color: 'fest-violet',
		icon: '<path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/>',
	},
	community: {
		label: __( 'Community', 'wpcamp-hub' ),
		color: 'fest-gold',
		icon: '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
	},
};

export const CATEGORY_OPTIONS = Object.keys( CATEGORIES ).map( ( key ) => ( {
	value: key,
	label: CATEGORIES[ key ].label,
} ) );

/**
 * Render an inline category icon as a React element.
 *
 * @param {string} category Category key.
 * @return {JSX.Element|null} SVG element.
 */
export function CategoryIcon( { category } ) {
	const meta = CATEGORIES[ category ];
	if ( ! meta ) {
		return null;
	}
	return (
		<svg
			className="wpch-feed__pill-icon"
			width="14"
			height="14"
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
			focusable="false"
			dangerouslySetInnerHTML={ { __html: meta.icon } }
		/>
	);
}
