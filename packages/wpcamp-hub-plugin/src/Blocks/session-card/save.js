/**
 * Session Card — saved markup.
 *
 * Static for now; the programme block will later be able to render these cards
 * dynamically from the sessions CPT.
 */
import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { type, track, accent, title, blurb, speaker, meta, link } =
		attributes;

	const blockProps = useBlockProps.save( {
		className: 'wpch-card',
		style: { '--wpch-card-accent': accent },
	} );

	const inner = (
		<>
			<div className="wpch-card__head" aria-hidden="true" />
			<div className="wpch-card__body">
				<div className="wpch-card__top">
					<RichText.Content
						tagName="span"
						className="wpch-card__type"
						value={ type }
					/>
					<span className="wpch-card__track">
						<span className="wpch-card__dot" aria-hidden="true" />
						<RichText.Content
							tagName="span"
							className="wpch-card__track-label"
							value={ track }
						/>
					</span>
				</div>

				<RichText.Content
					tagName="h3"
					className="wpch-card__title"
					value={ title }
				/>

				<RichText.Content
					tagName="p"
					className="wpch-card__blurb"
					value={ blurb }
				/>

				<div className="wpch-card__foot">
					<span className="wpch-card__avatar" aria-hidden="true" />
					<div className="wpch-card__person">
						<RichText.Content
							tagName="div"
							className="wpch-card__speaker-name"
							value={ speaker }
						/>
						<RichText.Content
							tagName="div"
							className="wpch-card__meta"
							value={ meta }
						/>
					</div>
				</div>
			</div>
		</>
	);

	return (
		<article { ...blockProps }>
			{ link ? (
				<a className="wpch-card__link" href={ link }>
					{ inner }
				</a>
			) : (
				inner
			) }
		</article>
	);
}
