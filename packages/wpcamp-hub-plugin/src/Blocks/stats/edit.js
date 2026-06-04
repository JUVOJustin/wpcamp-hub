/**
 * Stats Band — editor component.
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';

const ALLOWED_BLOCKS = [ 'wpcamp-hub/stat' ];

/** Default stats, mirroring the WPCAMP-HUB design. */
const TEMPLATE = [
	[
		'wpcamp-hub/stat',
		{
			number: 3,
			label: __( 'days', 'wpcamp-hub' ),
			accent: 'fest-gold',
			thousandsSeparator: false,
		},
	],
	[
		'wpcamp-hub/stat',
		{
			number: 60,
			suffix: '+',
			label: __( 'sessions', 'wpcamp-hub' ),
			accent: 'fest-coral',
			thousandsSeparator: false,
		},
	],
	[
		'wpcamp-hub/stat',
		{
			number: 2500,
			label: __( 'attendees', 'wpcamp-hub' ),
			accent: 'fest-teal',
		},
	],
	[
		'wpcamp-hub/stat',
		{
			number: 40,
			label: __( 'countries', 'wpcamp-hub' ),
			accent: 'fest-violet',
			thousandsSeparator: false,
		},
	],
];

export default function Edit( { attributes, setAttributes } ) {
	const { columns } = attributes;

	const blockProps = useBlockProps( {
		className: 'wpch-stats',
		style: { '--wpch-stats-cols': columns },
	} );

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'wpch-stats__grid' },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			orientation: 'horizontal',
			renderAppender: undefined,
		}
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'wpcamp-hub' ) }>
					<RangeControl
						label={ __( 'Columns', 'wpcamp-hub' ) }
						value={ columns }
						min={ 1 }
						max={ 6 }
						onChange={ ( v ) =>
							setAttributes( { columns: v || 1 } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<section { ...blockProps }>
				<div className="wpch-stats__inner">
					<div { ...innerBlocksProps } />
				</div>
			</section>
		</>
	);
}
