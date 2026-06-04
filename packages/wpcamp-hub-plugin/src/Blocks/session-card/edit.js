/**
 * Session Card — editor component.
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';

import { TRACKS, trackColor } from './tracks';

export default function Edit( { attributes, setAttributes } ) {
	const { type, track, accent, title, blurb, speaker, meta, link } =
		attributes;

	const blockProps = useBlockProps( {
		className: 'wpch-card',
		style: { '--wpch-card-accent': accent },
	} );

	const onTrackChange = ( name ) =>
		setAttributes( { track: name, accent: trackColor( name ) } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Session', 'wpcamp-hub' ) }>
					<SelectControl
						label={ __( 'Track', 'wpcamp-hub' ) }
						value={ track }
						options={ TRACKS.map( ( t ) => ( {
							label: t.name,
							value: t.name,
						} ) ) }
						onChange={ onTrackChange }
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
				<div className="wpch-card__head" aria-hidden="true" />
				<div className="wpch-card__body">
					<div className="wpch-card__top">
						<RichText
							tagName="span"
							className="wpch-card__type"
							value={ type }
							allowedFormats={ [] }
							onChange={ ( v ) => setAttributes( { type: v } ) }
							placeholder={ __( 'Type', 'wpcamp-hub' ) }
						/>
						<span className="wpch-card__track">
							<span
								className="wpch-card__dot"
								aria-hidden="true"
							/>
							<RichText
								tagName="span"
								className="wpch-card__track-label"
								value={ track }
								allowedFormats={ [] }
								onChange={ ( v ) =>
									setAttributes( { track: v } )
								}
								placeholder={ __( 'Track', 'wpcamp-hub' ) }
							/>
						</span>
					</div>

					<RichText
						tagName="h3"
						className="wpch-card__title"
						value={ title }
						onChange={ ( v ) => setAttributes( { title: v } ) }
						placeholder={ __( 'Session title…', 'wpcamp-hub' ) }
					/>

					<RichText
						tagName="p"
						className="wpch-card__blurb"
						value={ blurb }
						onChange={ ( v ) => setAttributes( { blurb: v } ) }
						placeholder={ __( 'Short description…', 'wpcamp-hub' ) }
					/>

					<div className="wpch-card__foot">
						<span
							className="wpch-card__avatar"
							aria-hidden="true"
						/>
						<div className="wpch-card__person">
							<RichText
								tagName="div"
								className="wpch-card__speaker-name"
								value={ speaker }
								allowedFormats={ [] }
								onChange={ ( v ) =>
									setAttributes( { speaker: v } )
								}
								placeholder={ __( 'Speaker', 'wpcamp-hub' ) }
							/>
							<RichText
								tagName="div"
								className="wpch-card__meta"
								value={ meta }
								allowedFormats={ [] }
								onChange={ ( v ) =>
									setAttributes( { meta: v } )
								}
								placeholder={ __(
									'Time · Room',
									'wpcamp-hub'
								) }
							/>
						</div>
					</div>
				</div>
			</article>
		</>
	);
}
