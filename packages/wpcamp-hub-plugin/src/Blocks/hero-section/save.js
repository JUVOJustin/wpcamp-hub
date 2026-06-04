/**
 * Hero Section — saved markup.
 */
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
} from '@wordpress/block-editor';

import HeroCollage from './collage';

export default function save( { attributes } ) {
	const {
		eyebrow,
		location,
		heading,
		lead,
		whenText,
		dateLabel,
		showBadge,
		goingText,
		goingSubtext,
		imageUrl,
		imageAlt,
		avatars,
	} = attributes;

	const blockProps = useBlockProps.save( { className: 'wpch-hero' } );
	const innerBlocksProps = useInnerBlocksProps.save( {
		className: 'wpch-hero__actions',
	} );

	return (
		<section { ...blockProps }>
			<div className="wpch-hero__inner">
				<div className="wpch-hero__content">
					<div className="wpch-hero__eyebrow-row">
						<RichText.Content
							tagName="span"
							className="wpch-hero__eyebrow"
							value={ eyebrow }
						/>
						<span className="wpch-hero__pill wpch-hero__pill--location">
							<svg
								className="wpch-hero__pin"
								width="14"
								height="14"
								viewBox="0 0 24 24"
								fill="none"
								stroke="currentColor"
								strokeWidth="2"
								strokeLinecap="round"
								strokeLinejoin="round"
								aria-hidden="true"
							>
								<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
								<circle cx="12" cy="10" r="3" />
							</svg>
							<RichText.Content
								tagName="span"
								className="wpch-hero__pill-label"
								value={ location }
							/>
						</span>
					</div>

					<RichText.Content
						tagName="h1"
						className="wpch-hero__title"
						value={ heading }
					/>

					<RichText.Content
						tagName="p"
						className="wpch-hero__lead"
						value={ lead }
					/>

					<div className="wpch-hero__actions-row">
						<div { ...innerBlocksProps } />
						<RichText.Content
							tagName="span"
							className="wpch-hero__when"
							value={ whenText }
						/>
					</div>
				</div>

				<HeroCollage
					imageUrl={ imageUrl }
					imageAlt={ imageAlt }
					showBadge={ showBadge }
					avatars={ avatars }
					dateNode={
						<RichText.Content
							tagName="span"
							className="wpch-hero__date"
							value={ dateLabel }
						/>
					}
					goingCountNode={
						<RichText.Content
							tagName="div"
							className="wpch-hero__going-count"
							value={ goingText }
						/>
					}
					goingSubNode={
						<RichText.Content
							tagName="div"
							className="wpch-hero__going-sub"
							value={ goingSubtext }
						/>
					}
				/>
			</div>
		</section>
	);
}
