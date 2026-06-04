/**
 * Hero Section — editor component.
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
	InspectorControls,
	BlockControls,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	Button,
	ToolbarGroup,
	ToolbarButton,
} from '@wordpress/components';

import HeroCollage from './collage';

/**
 * Nested buttons: a primary "Get tickets" and an outline "Explore events".
 * Editors can change labels, links and styles via the core button UI.
 */
const TEMPLATE = [
	[
		'core/buttons',
		{},
		[
			[ 'core/button', { text: __( 'Get tickets', 'wpcamp-hub' ) } ],
			[
				'core/button',
				{
					text: __( 'Explore events', 'wpcamp-hub' ),
					className: 'is-style-outline',
				},
			],
		],
	],
];

const ALLOWED_BLOCKS = [ 'core/buttons' ];
const ALLOWED_MEDIA = [ 'image' ];

export default function Edit( { attributes, setAttributes } ) {
	const {
		eyebrow,
		location,
		heading,
		lead,
		whenText,
		dateLabel,
		showBadge,
		goingText,
		goingSubtext,
		imageId,
		imageUrl,
		imageAlt,
		avatars,
	} = attributes;

	const blockProps = useBlockProps( { className: 'wpch-hero' } );

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'wpch-hero__actions' },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			templateLock: false,
		}
	);

	const onSelectImage = ( media ) => {
		if ( ! media || ! media.url ) {
			return;
		}
		setAttributes( {
			imageId: media.id,
			imageUrl: media.url,
			imageAlt: media.alt || '',
		} );
	};

	const clearImage = () =>
		setAttributes( {
			imageId: undefined,
			imageUrl: undefined,
			imageAlt: '',
		} );

	const onSelectAvatars = ( media ) => {
		const list = ( Array.isArray( media ) ? media : [ media ] )
			.filter( ( m ) => m && m.url )
			.map( ( m ) => ( {
				id: m.id,
				url: m.url,
				alt: m.alt || '',
			} ) );
		setAttributes( { avatars: list } );
	};

	const clearAvatars = () => setAttributes( { avatars: [] } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Hero settings', 'wpcamp-hub' ) }>
					<ToggleControl
						label={ __( 'Show attendee badge', 'wpcamp-hub' ) }
						help={ __(
							'Toggle the floating "going" badge on the image.',
							'wpcamp-hub'
						) }
						checked={ showBadge }
						onChange={ ( v ) => setAttributes( { showBadge: v } ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Hero image', 'wpcamp-hub' ) }>
					<MediaUploadCheck>
						<MediaUpload
							onSelect={ onSelectImage }
							allowedTypes={ ALLOWED_MEDIA }
							value={ imageId }
							render={ ( { open } ) => (
								<Button
									variant="secondary"
									onClick={ open }
									style={ { marginBottom: '8px' } }
								>
									{ imageUrl
										? __( 'Replace image', 'wpcamp-hub' )
										: __( 'Select image', 'wpcamp-hub' ) }
								</Button>
							) }
						/>
					</MediaUploadCheck>
					{ imageUrl && (
						<Button
							variant="link"
							isDestructive
							onClick={ clearImage }
						>
							{ __( 'Use default graphic', 'wpcamp-hub' ) }
						</Button>
					) }
				</PanelBody>
				<PanelBody
					title={ __( 'Attendee avatars', 'wpcamp-hub' ) }
					initialOpen={ false }
				>
					<p style={ { marginTop: 0 } }>
						{ avatars && avatars.length
							? __(
									'Avatar images shown in the badge.',
									'wpcamp-hub'
							  )
							: __(
									'Using the default coloured circles. Add images to show attendee photos.',
									'wpcamp-hub'
							  ) }
					</p>
					<MediaUploadCheck>
						<MediaUpload
							multiple
							gallery
							onSelect={ onSelectAvatars }
							allowedTypes={ ALLOWED_MEDIA }
							value={ ( avatars || [] )
								.map( ( a ) => a.id )
								.filter( Boolean ) }
							render={ ( { open } ) => (
								<Button
									variant="secondary"
									onClick={ open }
									style={ { marginBottom: '8px' } }
								>
									{ avatars && avatars.length
										? __( 'Edit avatars', 'wpcamp-hub' )
										: __( 'Add avatars', 'wpcamp-hub' ) }
								</Button>
							) }
						/>
					</MediaUploadCheck>
					{ avatars && avatars.length > 0 && (
						<Button
							variant="link"
							isDestructive
							onClick={ clearAvatars }
						>
							{ __( 'Use default circles', 'wpcamp-hub' ) }
						</Button>
					) }
				</PanelBody>
			</InspectorControls>

			{ imageUrl && (
				<BlockControls group="other">
					<ToolbarGroup>
						<MediaUploadCheck>
							<MediaUpload
								onSelect={ onSelectImage }
								allowedTypes={ ALLOWED_MEDIA }
								value={ imageId }
								render={ ( { open } ) => (
									<ToolbarButton onClick={ open }>
										{ __( 'Replace', 'wpcamp-hub' ) }
									</ToolbarButton>
								) }
							/>
						</MediaUploadCheck>
					</ToolbarGroup>
				</BlockControls>
			) }

			<section { ...blockProps }>
				<div className="wpch-hero__inner">
					<div className="wpch-hero__content">
						<div className="wpch-hero__eyebrow-row">
							<RichText
								tagName="span"
								className="wpch-hero__eyebrow"
								value={ eyebrow }
								allowedFormats={ [] }
								onChange={ ( v ) =>
									setAttributes( { eyebrow: v } )
								}
								placeholder={ __( 'Eyebrow…', 'wpcamp-hub' ) }
							/>
							<span className="wpch-hero__pill wpch-hero__pill--location">
								<svg
									className="wpch-hero__pin"
									width="14"
									height="14"
									viewBox="0 0 24 24"
									fill="none"
									stroke="currentColor"
									strokeWidth="2"
									strokeLinecap="round"
									strokeLinejoin="round"
									aria-hidden="true"
								>
									<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
									<circle cx="12" cy="10" r="3" />
								</svg>
								<RichText
									tagName="span"
									className="wpch-hero__pill-label"
									value={ location }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { location: v } )
									}
									placeholder={ __(
										'Location…',
										'wpcamp-hub'
									) }
								/>
							</span>
						</div>

						<RichText
							tagName="h1"
							className="wpch-hero__title"
							value={ heading }
							onChange={ ( v ) =>
								setAttributes( { heading: v } )
							}
							placeholder={ __( 'Headline…', 'wpcamp-hub' ) }
						/>

						<RichText
							tagName="p"
							className="wpch-hero__lead"
							value={ lead }
							onChange={ ( v ) => setAttributes( { lead: v } ) }
							placeholder={ __( 'Lead text…', 'wpcamp-hub' ) }
						/>

						<div className="wpch-hero__actions-row">
							<div { ...innerBlocksProps } />
							<RichText
								tagName="span"
								className="wpch-hero__when"
								value={ whenText }
								allowedFormats={ [] }
								onChange={ ( v ) =>
									setAttributes( { whenText: v } )
								}
								placeholder={ __(
									'Date range…',
									'wpcamp-hub'
								) }
							/>
						</div>
					</div>

					<HeroCollage
						imageUrl={ imageUrl }
						imageAlt={ imageAlt }
						showBadge={ showBadge }
						avatars={ avatars }
						dateNode={ renderDate() }
						goingCountNode={ renderGoing() }
						goingSubNode={ renderSub() }
					/>
				</div>
			</section>
		</>
	);

	function renderDate() {
		return (
			<RichText
				tagName="span"
				className="wpch-hero__date"
				value={ dateLabel }
				allowedFormats={ [] }
				onChange={ ( v ) => setAttributes( { dateLabel: v } ) }
				placeholder={ __( 'Date…', 'wpcamp-hub' ) }
			/>
		);
	}
	function renderGoing() {
		return (
			<RichText
				tagName="div"
				className="wpch-hero__going-count"
				value={ goingText }
				allowedFormats={ [] }
				onChange={ ( v ) => setAttributes( { goingText: v } ) }
				placeholder={ __( 'Going…', 'wpcamp-hub' ) }
			/>
		);
	}
	function renderSub() {
		return (
			<RichText
				tagName="div"
				className="wpch-hero__going-sub"
				value={ goingSubtext }
				allowedFormats={ [] }
				onChange={ ( v ) => setAttributes( { goingSubtext: v } ) }
				placeholder={ __( 'Subtext…', 'wpcamp-hub' ) }
			/>
		);
	}
}
