/**
 * Page Intro — saved markup.
 */
import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { eyebrow, title, lead } = attributes;

	const blockProps = useBlockProps.save( { className: 'wpch-intro' } );

	return (
		<section { ...blockProps }>
			<div className="wpch-intro__inner">
				<RichText.Content
					tagName="div"
					className="wpch-intro__eyebrow"
					value={ eyebrow }
				/>
				<RichText.Content
					tagName="h1"
					className="wpch-intro__title"
					value={ title }
				/>
				<RichText.Content
					tagName="p"
					className="wpch-intro__lead"
					value={ lead }
				/>
			</div>
		</section>
	);
}
