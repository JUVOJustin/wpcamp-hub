/**
 * Stat — editor component.
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	__experimentalNumberControl as NumberControl,
	ToggleControl,
	SelectControl,
} from '@wordpress/components';

import { ACCENTS, formatNumber } from './accents';

export default function Edit( { attributes, setAttributes } ) {
	const { number, prefix, suffix, thousandsSeparator, label, accent } =
		attributes;

	const blockProps = useBlockProps( {
		className: `wpch-stat wpch-stat--${ accent }`,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Stat', 'wpcamp-hub' ) }>
					<NumberControl
						label={ __( 'Number', 'wpcamp-hub' ) }
						value={ number }
						onChange={ ( v ) =>
							setAttributes( { number: parseFloat( v ) || 0 } )
						}
					/>
					<ToggleControl
						label={ __( 'Thousands separator', 'wpcamp-hub' ) }
						checked={ thousandsSeparator }
						onChange={ ( v ) =>
							setAttributes( { thousandsSeparator: v } )
						}
					/>
					<SelectControl
						label={ __( 'Accent colour', 'wpcamp-hub' ) }
						value={ accent }
						options={ ACCENTS }
						onChange={ ( v ) => setAttributes( { accent: v } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="wpch-stat__num">
					<RichText
						tagName="span"
						className="wpch-stat__prefix"
						value={ prefix }
						allowedFormats={ [] }
						onChange={ ( v ) => setAttributes( { prefix: v } ) }
						placeholder={ __( '±', 'wpcamp-hub' ) }
					/>
					<span className="wpch-stat__value">
						{ formatNumber( number, thousandsSeparator ) }
					</span>
					<RichText
						tagName="span"
						className="wpch-stat__suffix"
						value={ suffix }
						allowedFormats={ [] }
						onChange={ ( v ) => setAttributes( { suffix: v } ) }
						placeholder={ __( '+', 'wpcamp-hub' ) }
					/>
				</div>
				<RichText
					tagName="div"
					className="wpch-stat__label"
					value={ label }
					allowedFormats={ [] }
					onChange={ ( v ) => setAttributes( { label: v } ) }
					placeholder={ __( 'Label…', 'wpcamp-hub' ) }
				/>
			</div>
		</>
	);
}
