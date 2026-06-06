/**
 * Hero Section — editor component.
 *
 * Link an event to pull its date, location, attendees and ticket link; any
 * field typed manually overrides the event-derived value. Front-end markup is
 * produced by render.php — this component is a faithful editor preview.
 */
import { __, sprintf } from '@wordpress/i18n';
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
	ComboboxControl,
	ToolbarGroup,
	ToolbarButton,
	Notice,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useState } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';

import HeroCollage from './collage';

const ALLOWED_MEDIA = [ 'image' ];

// Manual CTA buttons (used when no event is linked): a primary "Get tickets"
// and an outline "Explore events". Editors set labels, links and styles via the
// core button UI.
const BUTTONS_TEMPLATE = [
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

export default function Edit( { attributes, setAttributes } ) {
	const {
		eventId,
		eyebrow,
		location,
		heading,
		lead,
		dateLabel,
		showBadge,
		goingSubtext,
		imageId,
		imageUrl,
		imageAlt,
	} = attributes;

	const [ eventSearch, setEventSearch ] = useState( '' );

	// Event options for the selector + the currently-linked event record.
	const { eventOptions, linkedEvent, attendeeCount } = useSelect(
		( select ) => {
			const core = select( coreStore );
			const query = {
				per_page: 20,
				orderby: 'date',
				order: 'desc',
				...( eventSearch ? { search: eventSearch } : {} ),
			};
			const events =
				core.getEntityRecords( 'postType', 'wpcamp_event', query ) ||
				[];
			const linked = eventId
				? core.getEntityRecord( 'postType', 'wpcamp_event', eventId )
				: null;

			// Attendee count: read the event's relationship meta if exposed.
			let count = 0;
			if ( linked && linked.meta ) {
				const related = linked.meta.wpcamp_related_attendees;
				if ( Array.isArray( related ) ) {
					count = related.length;
				}
			}

			return {
				eventOptions: events.map( ( e ) => ( {
					value: e.id,
					label: decodeEntities(
						e.title?.rendered || __( '(no title)', 'wpcamp-hub' )
					),
				} ) ),
				linkedEvent: linked,
				attendeeCount: count,
			};
		},
		[ eventId, eventSearch ]
	);

	// Event-derived values (used as preview fallbacks when no manual value).
	const evTitle = linkedEvent
		? decodeEntities( linkedEvent.title?.rendered || '' )
		: '';
	const evLocation = linkedEvent?.meta?.wpcamp_location || '';
	const evMeta = linkedEvent?.meta || {};
	const evDate = formatEventDate(
		evMeta.wpcamp_date_start,
		evMeta.wpcamp_date_end
	);

	const shownEyebrow = ( eyebrow || '' ).trim() || evTitle;
	const shownLocation = ( location || '' ).trim() || evLocation;
	const shownDate = ( dateLabel || '' ).trim() || evDate;
	const goingText =
		linkedEvent && attendeeCount >= 0
			? sprintf(
					/* translators: %s: attendee count. */
					__( '%s going', 'wpcamp-hub' ),
					String( attendeeCount )
			  )
			: '';

	const blockProps = useBlockProps( { className: 'wpch-hero' } );

	// Manual CTA buttons via InnerBlocks (shown only when no event is linked).
	const innerBlocksProps = useInnerBlocksProps(
		{
			className:
				'wpch-hero__actions wp-block-buttons is-layout-flex wp-block-buttons-is-layout-flex',
		},
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: BUTTONS_TEMPLATE,
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

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Linked event', 'wpcamp-hub' ) }>
					<ComboboxControl
						label={ __( 'Event', 'wpcamp-hub' ) }
						help={ __(
							'Pull date, location, attendees and the ticket link from an event. Leave empty to set fields manually.',
							'wpcamp-hub'
						) }
						value={ eventId || null }
						options={ eventOptions }
						onFilterValueChange={ ( v ) => setEventSearch( v ) }
						onChange={ ( v ) =>
							setAttributes( { eventId: v || undefined } )
						}
						allowReset
					/>
					{ eventId && ! linkedEvent && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'Linked event not found — it may have been deleted.',
								'wpcamp-hub'
							) }
						</Notice>
					) }
				</PanelBody>
				<PanelBody
					title={ __( 'Display', 'wpcamp-hub' ) }
					initialOpen={ false }
				>
					{ linkedEvent && (
						<Notice status="info" isDismissible={ false }>
							{ __(
								'The buttons are set automatically from the linked event (Get tickets → official URL, Explore events → sessions page).',
								'wpcamp-hub'
							) }
						</Notice>
					) }
					<ToggleControl
						label={ __( 'Show attendee badge', 'wpcamp-hub' ) }
						checked={ showBadge }
						onChange={ ( v ) => setAttributes( { showBadge: v } ) }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Hero image', 'wpcamp-hub' ) }
					initialOpen={ false }
				>
					<MediaUploadCheck>
						<MediaUpload
							onSelect={ onSelectImage }
							allowedTypes={ ALLOWED_MEDIA }
							value={ imageId }
							render={ ( { open } ) => (
								<Button variant="secondary" onClick={ open }>
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
							{ linkedEvent ? (
								<span className="wpch-hero__eyebrow">
									{ shownEyebrow }
								</span>
							) : (
								<RichText
									tagName="span"
									className="wpch-hero__eyebrow"
									value={ eyebrow }
									allowedFormats={ [] }
									onChange={ ( v ) =>
										setAttributes( { eyebrow: v } )
									}
									placeholder={ __(
										'Eyebrow…',
										'wpcamp-hub'
									) }
								/>
							) }
							{ shownLocation && (
								<span className="wpch-hero__pill wpch-hero__pill--location">
									<LocationPin />
									<span className="wpch-hero__pill-label">
										{ shownLocation }
									</span>
								</span>
							) }
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
							{ linkedEvent ? (
								// Event linked: preview the auto-wired CTAs (render.php
								// outputs these on the front end).
								<div className="wpch-hero__actions wp-block-buttons is-layout-flex wp-block-buttons-is-layout-flex">
									<div className="wp-block-button">
										<span className="wp-block-button__link wp-element-button">
											{ __(
												'Get tickets',
												'wpcamp-hub'
											) }
										</span>
									</div>
									<div className="wp-block-button is-style-outline">
										<span className="wp-block-button__link wp-element-button">
											{ __(
												'Explore events',
												'wpcamp-hub'
											) }
										</span>
									</div>
								</div>
							) : (
								// No event: real, editable core/buttons InnerBlocks.
								<div { ...innerBlocksProps } />
							) }
							{ shownDate && (
								<span className="wpch-hero__when">
									{ shownDate }
								</span>
							) }
						</div>
					</div>

					<HeroCollage
						imageUrl={ imageUrl }
						imageAlt={ imageAlt }
						showBadge={ showBadge }
						avatars={ [] }
						dateNode={
							<span className="wpch-hero__date">
								{ shownDate || __( 'Date…', 'wpcamp-hub' ) }
							</span>
						}
						goingCountNode={
							<div className="wpch-hero__going-count">
								{ goingText ||
									__( 'Link an event', 'wpcamp-hub' ) }
							</div>
						}
						goingSubNode={
							<RichText
								tagName="div"
								className="wpch-hero__going-sub"
								value={ goingSubtext }
								allowedFormats={ [] }
								onChange={ ( v ) =>
									setAttributes( { goingSubtext: v } )
								}
								placeholder={ __( 'Subtext…', 'wpcamp-hub' ) }
							/>
						}
					/>
				</div>
			</section>
		</>
	);
}

function LocationPin() {
	return (
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
	);
}

/**
 * Format an event date range from ISO start/end meta into a compact label.
 *
 * @param {string} start ISO start.
 * @param {string} end   ISO end.
 * @return {string} Formatted range, or ''.
 */
function formatEventDate( start, end ) {
	if ( ! start ) {
		return '';
	}
	const s = new Date( start );
	if ( isNaN( s.getTime() ) ) {
		return '';
	}
	const opts = { day: 'numeric', month: 'short', year: 'numeric' };
	if ( end ) {
		const e = new Date( end );
		if ( ! isNaN( e.getTime() ) ) {
			return `${ s.getDate() }–${ e.toLocaleDateString(
				undefined,
				opts
			) }`;
		}
	}
	return s.toLocaleDateString( undefined, opts );
}
