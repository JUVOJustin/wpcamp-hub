/**
 * WPCAMP-HUB programme tracks and their accent colours.
 * Shared by the session card (track selector) and the programme legend.
 */
export const TRACKS = [
	{ name: 'Building', color: '#3858E9' },
	{ name: 'Designing', color: '#FF5A4D' },
	{ name: 'Community', color: '#FFB020' },
	{ name: 'Business', color: '#14B8A6' },
	{ name: 'Open web', color: '#7C5CFF' },
];

/**
 * Look up a track's accent colour by name.
 *
 * @param {string} name Track name.
 * @return {string} Hex colour (falls back to brand blue).
 */
export function trackColor( name ) {
	const found = TRACKS.find( ( t ) => t.name === name );
	return found ? found.color : '#3858E9';
}
