/**
 * Decorative hero collage — geometric SVG (or a custom image), confetti, the
 * ticket pill and an optional "attendees going" float card. Purely
 * presentational; shared by edit & save so the editor preview matches the
 * saved markup.
 *
 * The editable text nodes (date, going count/subtext) are passed in as
 * already-rendered elements (RichText in the editor, RichText.Content on save)
 * so attribute sourcing stays consistent.
 */

const AVATAR_TONES = [ 'b300', 'b400', 'b200', 'b300' ];

/**
 * Render the attendee avatar circles. Falls back to coloured placeholders when
 * no avatar images have been added.
 *
 * @param {Array} avatars List of { url, alt, id } image objects.
 * @return {Array} Avatar circle elements.
 */
function renderAvatars( avatars ) {
	if ( avatars && avatars.length ) {
		return avatars.map( ( a, i ) =>
			a && a.url ? (
				<img
					key={ i }
					className="wpch-hero__avatar wpch-hero__avatar-img"
					src={ a.url }
					alt={ a.alt || '' }
					data-id={ a.id || undefined }
				/>
			) : (
				<span
					key={ i }
					className={ `wpch-hero__avatar wpch-hero__avatar--${
						AVATAR_TONES[ i % AVATAR_TONES.length ]
					}` }
				/>
			)
		);
	}

	return AVATAR_TONES.map( ( tone, i ) => (
		<span
			key={ i }
			className={ `wpch-hero__avatar wpch-hero__avatar--${ tone }` }
		/>
	) );
}

function GeoArt() {
	return (
		<svg
			viewBox="0 0 360 460"
			preserveAspectRatio="xMidYMid slice"
			width="100%"
			height="100%"
			aria-hidden="true"
			focusable="false"
		>
			<circle cx="250" cy="120" r="120" fill="#3858E9" />
			<path d="M0 320 A150 150 0 0 1 300 320 Z" fill="#FF5A4D" />
			<circle cx="96" cy="150" r="60" fill="#FFB020" />
			<circle cx="250" cy="120" r="40" fill="#ECE7FF" />
			<circle cx="300" cy="300" r="34" fill="#14B8A6" />
			<g fill="#15235e">
				<circle cx="60" cy="60" r="8" />
				<circle cx="92" cy="60" r="8" />
				<circle cx="124" cy="60" r="8" />
			</g>
			<rect
				x="40"
				y="400"
				width="120"
				height="16"
				rx="8"
				fill="#7C5CFF"
			/>
		</svg>
	);
}

export default function HeroCollage( {
	imageUrl,
	imageAlt,
	showBadge,
	avatars,
	dateNode,
	goingCountNode,
	goingSubNode,
} ) {
	return (
		<div className="wpch-hero__media">
			<div className="wpch-hero__geo">
				{ imageUrl ? (
					<img
						className="wpch-hero__img"
						src={ imageUrl }
						alt={ imageAlt || '' }
					/>
				) : (
					<GeoArt />
				) }
			</div>

			<span
				className="wpch-hero__confetti wpch-hero__confetti--1"
				aria-hidden="true"
			/>
			<span
				className="wpch-hero__confetti wpch-hero__confetti--2"
				aria-hidden="true"
			/>
			<span
				className="wpch-hero__confetti wpch-hero__confetti--3"
				aria-hidden="true"
			/>

			<div className="wpch-hero__pill wpch-hero__pill--rotated">
				<span className="wpch-hero__perf" aria-hidden="true" />
				{ dateNode }
			</div>

			{ showBadge && (
				<div className="wpch-hero__float-card">
					<div className="wpch-hero__stack" aria-hidden="true">
						{ renderAvatars( avatars ) }
					</div>
					<div>
						{ goingCountNode }
						{ goingSubNode }
					</div>
				</div>
			) }
		</div>
	);
}
