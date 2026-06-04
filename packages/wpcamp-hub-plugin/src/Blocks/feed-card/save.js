/**
 * Feed Card — saved markup.
 */
import { useBlockProps, RichText } from '@wordpress/block-editor';

import { CATEGORIES, CategoryIcon } from './categories';

export default function save( { attributes } ) {
	const { author, handle, text, category, link } = attributes;

	const meta = CATEGORIES[ category ] || CATEGORIES.networking;

	const blockProps = useBlockProps.save( {
		className: `wpch-feed wpch-feed--${ meta.color }`,
	} );

	const inner = (
		<>
			<div className="wpch-feed__head">
				<span className="wpch-feed__avatar" aria-hidden="true" />
				<div className="wpch-feed__person">
					<RichText.Content
						tagName="div"
						className="wpch-feed__author"
						value={ author }
					/>
					<RichText.Content
						tagName="div"
						className="wpch-feed__handle"
						value={ handle }
					/>
				</div>
			</div>

			<RichText.Content
				tagName="p"
				className="wpch-feed__text"
				value={ text }
			/>

			<span className="wpch-feed__pill">
				<CategoryIcon category={ category } />
				{ meta.label }
			</span>
		</>
	);

	return (
		<article { ...blockProps }>
			{ link ? (
				<a className="wpch-feed__link" href={ link }>
					{ inner }
				</a>
			) : (
				inner
			) }
		</article>
	);
}
