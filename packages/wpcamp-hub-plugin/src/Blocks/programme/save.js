/**
 * Programme Excerpt — saved markup.
 */
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
} from '@wordpress/block-editor';

import Legend from './legend';

export default function save( { attributes } ) {
	const {
		eyebrow,
		heading,
		linkLabel,
		linkUrl,
		showLegend,
		legend,
		columns,
	} = attributes;

	const blockProps = useBlockProps.save( { className: 'wpch-programme' } );

	const innerBlocksProps = useInnerBlocksProps.save( {
		className: 'wpch-programme__grid',
		style: { '--wpch-programme-cols': columns },
	} );

	return (
		<section { ...blockProps }>
			<div className="wpch-programme__inner">
				<div className="wpch-programme__header">
					<div className="wpch-programme__heading-group">
						<RichText.Content
							tagName="div"
							className="wpch-programme__eyebrow"
							value={ eyebrow }
						/>
						<RichText.Content
							tagName="h2"
							className="wpch-programme__heading"
							value={ heading }
						/>
					</div>
					{ linkLabel && (
						<a
							className="wpch-programme__link"
							href={ linkUrl || '#' }
						>
							{ linkLabel }
							<span aria-hidden="true"> →</span>
						</a>
					) }
				</div>

				{ showLegend && <Legend items={ legend } /> }

				<div { ...innerBlocksProps } />
			</div>
		</section>
	);
}
