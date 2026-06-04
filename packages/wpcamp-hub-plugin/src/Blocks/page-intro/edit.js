/**
 * Page Intro — editor component.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function Edit( { attributes, setAttributes } ) {
	const { eyebrow, title, lead } = attributes;

	const blockProps = useBlockProps( { className: 'wpch-intro' } );

	return (
		<section { ...blockProps }>
			<div className="wpch-intro__inner">
				<RichText
					tagName="div"
					className="wpch-intro__eyebrow"
					value={ eyebrow }
					allowedFormats={ [] }
					onChange={ ( v ) => setAttributes( { eyebrow: v } ) }
					placeholder={ __( 'Eyebrow…', 'wpcamp-hub' ) }
				/>
				<RichText
					tagName="h1"
					className="wpch-intro__title"
					value={ title }
					onChange={ ( v ) => setAttributes( { title: v } ) }
					placeholder={ __( 'Page title…', 'wpcamp-hub' ) }
				/>
				<RichText
					tagName="p"
					className="wpch-intro__lead"
					value={ lead }
					onChange={ ( v ) => setAttributes( { lead: v } ) }
					placeholder={ __( 'Lead paragraph…', 'wpcamp-hub' ) }
				/>
			</div>
		</section>
	);
}
