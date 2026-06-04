/**
 * Feed Card — editor component.
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';

import { CATEGORIES, CATEGORY_OPTIONS, CategoryIcon } from './categories';

export default function Edit( { attributes, setAttributes } ) {
	const { author, handle, text, category, link } = attributes;

	const meta = CATEGORIES[ category ] || CATEGORIES.networking;

	const blockProps = useBlockProps( {
		className: `wpch-feed wpch-feed--${ meta.color }`,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Feed post', 'wpcamp-hub' ) }>
					<SelectControl
						label={ __( 'Category', 'wpcamp-hub' ) }
						value={ category }
						options={ CATEGORY_OPTIONS }
						onChange={ ( v ) => setAttributes( { category: v } ) }
					/>
					<TextControl
						label={ __( 'Link (optional)', 'wpcamp-hub' ) }
						type="url"
						value={ link }
						onChange={ ( v ) => setAttributes( { link: v } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<article { ...blockProps }>
				<div className="wpch-feed__head">
					<span className="wpch-feed__avatar" aria-hidden="true" />
					<div className="wpch-feed__person">
						<RichText
							tagName="div"
							className="wpch-feed__author"
							value={ author }
							allowedFormats={ [] }
							onChange={ ( v ) => setAttributes( { author: v } ) }
							placeholder={ __( 'Author name', 'wpcamp-hub' ) }
						/>
						<RichText
							tagName="div"
							className="wpch-feed__handle"
							value={ handle }
							allowedFormats={ [] }
							onChange={ ( v ) => setAttributes( { handle: v } ) }
							placeholder={ __( '@handle · time', 'wpcamp-hub' ) }
						/>
					</div>
				</div>

				<RichText
					tagName="p"
					className="wpch-feed__text"
					value={ text }
					onChange={ ( v ) => setAttributes( { text: v } ) }
					placeholder={ __( 'What are they saying…', 'wpcamp-hub' ) }
				/>

				<span className="wpch-feed__pill">
					<CategoryIcon category={ category } />
					{ meta.label }
				</span>
			</article>
		</>
	);
}
