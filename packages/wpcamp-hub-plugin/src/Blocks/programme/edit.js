/**
 * Programme Excerpt — editor component.
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	ToggleControl,
	TextControl,
	Button,
	Flex,
	FlexItem,
	FlexBlock,
} from '@wordpress/components';

import Legend from './legend';

const ALLOWED_BLOCKS = [ 'wpcamp-hub/session-card' ];

const TEMPLATE = [
	[
		'wpcamp-hub/session-card',
		{
			type: 'Keynote',
			track: 'Building',
			accent: '#3858E9',
			title: __( 'Opening keynote', 'wpcamp-hub' ),
			blurb: __(
				'Where the open web is heading, and how the community gets there together.',
				'wpcamp-hub'
			),
			speaker: __( 'Mei Tanaka', 'wpcamp-hub' ),
			meta: '09:30 · Main hall',
		},
	],
	[
		'wpcamp-hub/session-card',
		{
			type: 'Talk',
			track: 'Designing',
			accent: '#FF5A4D',
			title: __( 'Designing for everyone', 'wpcamp-hub' ),
			blurb: __(
				'Practical accessibility patterns you can ship the day you get home.',
				'wpcamp-hub'
			),
			speaker: __( 'Ana Costa', 'wpcamp-hub' ),
			meta: '11:00 · Track A',
		},
	],
	[
		'wpcamp-hub/session-card',
		{
			type: 'Workshop',
			track: 'Community',
			accent: '#FFB020',
			title: __( 'Grow your local meetup', 'wpcamp-hub' ),
			blurb: __(
				'From five people in a café to a thriving regional community.',
				'wpcamp-hub'
			),
			speaker: __( 'Diego Hernández', 'wpcamp-hub' ),
			meta: '14:00 · Workshop',
		},
	],
];

export default function Edit( { attributes, setAttributes } ) {
	const {
		eyebrow,
		heading,
		linkLabel,
		linkUrl,
		showLegend,
		legend,
		columns,
	} = attributes;

	const blockProps = useBlockProps( { className: 'wpch-programme' } );

	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'wpch-programme__grid',
			style: { '--wpch-programme-cols': columns },
		},
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			orientation: 'horizontal',
		}
	);

	const updateTrack = ( index, key, value ) => {
		const next = legend.map( ( t, i ) =>
			i === index ? { ...t, [ key ]: value } : t
		);
		setAttributes( { legend: next } );
	};

	const removeTrack = ( index ) =>
		setAttributes( {
			legend: legend.filter( ( _, i ) => i !== index ),
		} );

	const addTrack = () =>
		setAttributes( {
			legend: [
				...legend,
				{ name: __( 'New track', 'wpcamp-hub' ), color: '#3858E9' },
			],
		} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Section', 'wpcamp-hub' ) }>
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
					<RangeControl
						label={ __( 'Columns', 'wpcamp-hub' ) }
						value={ columns }
						min={ 1 }
						max={ 4 }
						onChange={ ( v ) =>
							setAttributes( { columns: v || 1 } )
						}
					/>
				</PanelBody>
				<PanelBody title={ __( 'Track legend', 'wpcamp-hub' ) }>
					<ToggleControl
						label={ __( 'Show legend', 'wpcamp-hub' ) }
						checked={ showLegend }
						onChange={ ( v ) => setAttributes( { showLegend: v } ) }
					/>
					{ legend.map( ( t, i ) => (
						<Flex
							key={ i }
							align="flex-end"
							style={ { marginBottom: 8 } }
						>
							<FlexItem>
								<input
									type="color"
									value={ t.color }
									aria-label={ __(
										'Track colour',
										'wpcamp-hub'
									) }
									onChange={ ( e ) =>
										updateTrack(
											i,
											'color',
											e.target.value
										)
									}
								/>
							</FlexItem>
							<FlexBlock>
								<TextControl
									value={ t.name }
									onChange={ ( v ) =>
										updateTrack( i, 'name', v )
									}
								/>
							</FlexBlock>
							<FlexItem>
								<Button
									isDestructive
									variant="tertiary"
									onClick={ () => removeTrack( i ) }
								>
									{ __( 'Remove', 'wpcamp-hub' ) }
								</Button>
							</FlexItem>
						</Flex>
					) ) }
					<Button variant="secondary" onClick={ addTrack }>
						{ __( 'Add track', 'wpcamp-hub' ) }
					</Button>
				</PanelBody>
			</InspectorControls>

			<section { ...blockProps }>
				<div className="wpch-programme__inner">
					<div className="wpch-programme__header">
						<div className="wpch-programme__heading-group">
							<RichText
								tagName="div"
								className="wpch-programme__eyebrow"
								value={ eyebrow }
								allowedFormats={ [] }
								onChange={ ( v ) =>
									setAttributes( { eyebrow: v } )
								}
								placeholder={ __( 'Eyebrow…', 'wpcamp-hub' ) }
							/>
							<RichText
								tagName="h2"
								className="wpch-programme__heading"
								value={ heading }
								onChange={ ( v ) =>
									setAttributes( { heading: v } )
								}
								placeholder={ __( 'Heading…', 'wpcamp-hub' ) }
							/>
						</div>
						<span className="wpch-programme__link">
							{ linkLabel }
							<span aria-hidden="true"> →</span>
						</span>
					</div>

					{ showLegend && <Legend items={ legend } /> }

					<div { ...innerBlocksProps } />
				</div>
			</section>
		</>
	);
}
