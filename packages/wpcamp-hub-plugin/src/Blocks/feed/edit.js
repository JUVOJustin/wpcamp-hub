/**
 * Community Feed — editor component.
 *
 * The heading group stays inline-editable; the grouped feed itself is rendered
 * by the server (ServerSideRender) so the editor shows the same grouping logic
 * as the front end.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit( { attributes, setAttributes } ) {
	const {
		eyebrow,
		heading,
		linkLabel,
		linkUrl,
		eventsCount,
		tweetsPerEvent,
		columns,
		showEmpty,
	} = attributes;

	const blockProps = useBlockProps( { className: 'wpch-feeds' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Feed', 'wpcamp-hub' ) }>
					<RangeControl
						label={ __( 'Events to show', 'wpcamp-hub' ) }
						help={ __(
							'Each event becomes its own feed (grouped by hashtag).',
							'wpcamp-hub'
						) }
						value={ eventsCount }
						min={ 1 }
						max={ 12 }
						onChange={ ( v ) =>
							setAttributes( { eventsCount: v } )
						}
					/>
					<RangeControl
						label={ __( 'Posts per event', 'wpcamp-hub' ) }
						value={ tweetsPerEvent }
						min={ 1 }
						max={ 12 }
						onChange={ ( v ) =>
							setAttributes( { tweetsPerEvent: v } )
						}
					/>
					<RangeControl
						label={ __( 'Columns', 'wpcamp-hub' ) }
						value={ columns }
						min={ 1 }
						max={ 4 }
						onChange={ ( v ) => setAttributes( { columns: v } ) }
					/>
					<ToggleControl
						label={ __( 'Show events without posts', 'wpcamp-hub' ) }
						checked={ showEmpty }
						onChange={ ( v ) => setAttributes( { showEmpty: v } ) }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Header link', 'wpcamp-hub' ) }
					initialOpen={ false }
				>
					<TextControl
						label={ __( 'Link label', 'wpcamp-hub' ) }
						value={ linkLabel }
						onChange={ ( v ) => setAttributes( { linkLabel: v } ) }
					/>
					<TextControl
						label={ __( 'Link URL', 'wpcamp-hub' ) }
						type="url"
						value={ linkUrl }
						onChange={ ( v ) => setAttributes( { linkUrl: v } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<section { ...blockProps }>
				<div className="wpch-feeds__inner">
					<div className="wpch-feeds__header">
						<div className="wpch-feeds__heading-group">
							<RichText
								tagName="div"
								className="wpch-feeds__eyebrow"
								value={ eyebrow }
								allowedFormats={ [] }
								onChange={ ( v ) =>
									setAttributes( { eyebrow: v } )
								}
								placeholder={ __( 'Eyebrow', 'wpcamp-hub' ) }
							/>
							<RichText
								tagName="h2"
								className="wpch-feeds__heading"
								value={ heading }
								allowedFormats={ [] }
								onChange={ ( v ) =>
									setAttributes( { heading: v } )
								}
								placeholder={ __( 'Heading', 'wpcamp-hub' ) }
							/>
						</div>
					</div>

					<ServerSideRender
						block="wpcamp-hub/feed"
						attributes={ {
							eventsCount,
							tweetsPerEvent,
							columns,
							showEmpty,
							// Header is rendered above; skip it in the preview.
							eyebrow: '',
							heading: '',
							linkLabel: '',
						} }
					/>
				</div>
			</section>
		</>
	);
}
