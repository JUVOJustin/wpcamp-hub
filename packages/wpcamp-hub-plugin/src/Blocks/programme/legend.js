/**
 * Programme track legend — renders the coloured track chips.
 * Shared between edit and save so the markup stays identical.
 */
export default function Legend( { items } ) {
	if ( ! items || ! items.length ) {
		return null;
	}
	return (
		<div className="wpch-programme__legend">
			{ items.map( ( t, i ) => (
				<span key={ i } className="wpch-programme__legend-item">
					<span
						className="wpch-programme__legend-dot"
						style={ { background: t.color } }
						aria-hidden="true"
					/>
					{ t.name }
				</span>
			) ) }
		</div>
	);
}
