/* eslint-disable import/no-unresolved, import/no-extraneous-dependencies, @wordpress/no-unsafe-wp-apis */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import {
	BaseControl,
	Button,
	ComboboxControl,
	DateTimePicker,
	__experimentalInputControl as InputControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { globe, Icon } from '@wordpress/icons';
import { Stack, Text } from '@wordpress/ui';

import metadata from './block.json';
import './editor.scss';

const config = window.wpcamp_hub || {};
const fieldsByPostType = config.postMetaFields || {};
const relationTargets = {
	wpcamp_event: 'wpcamp_event',
	wpcamp_related_event: 'wpcamp_event',
	wpcamp_related_events: 'wpcamp_event',
	wpcamp_person: 'user',
	wpcamp_related_attendee: 'user',
	wpcamp_related_attendees: 'user',
	wpcamp_speakers: 'user',
	wpcamp_related_tweets: 'wpcamp_tweet',
	wpcamp_source_tweet: 'wpcamp_tweet',
};
const comboboxOptions = {
	wpcamp_source: [
		{ label: __( 'Curated', 'wpcamp-hub' ), value: 'curated' },
		{ label: __( 'WordCamp', 'wpcamp-hub' ), value: 'WordCamp' },
		{ label: __( 'Twitter/X', 'wpcamp-hub' ), value: 'Twitter/X' },
		{ label: __( 'Official', 'wpcamp-hub' ), value: 'official' },
	],
	wpcamp_processing_status: [
		{ label: __( 'Fetched', 'wpcamp-hub' ), value: 'fetched' },
		{ label: __( 'Queued', 'wpcamp-hub' ), value: 'queued' },
		{ label: __( 'Processed', 'wpcamp-hub' ), value: 'processed' },
		{ label: __( 'Curated', 'wpcamp-hub' ), value: 'curated' },
		{ label: __( 'Rejected', 'wpcamp-hub' ), value: 'rejected' },
	],
	wpcamp_day: [
		{ label: __( 'Thursday', 'wpcamp-hub' ), value: 'Thursday' },
		{ label: __( 'Friday', 'wpcamp-hub' ), value: 'Friday' },
		{ label: __( 'Saturday', 'wpcamp-hub' ), value: 'Saturday' },
		{ label: __( 'Sunday', 'wpcamp-hub' ), value: 'Sunday' },
	],
	wpcamp_room: [
		{ label: __( 'Track 1', 'wpcamp-hub' ), value: 'Track 1' },
		{ label: __( 'Track 2', 'wpcamp-hub' ), value: 'Track 2' },
	],
};
const dateRangeFields = {
	wpcamp_date_start: {
		endKey: 'wpcamp_date_end',
		label: __( 'Event Dates', 'wpcamp-hub' ),
		startLabel: __( 'Start', 'wpcamp-hub' ),
		endLabel: __( 'End', 'wpcamp-hub' ),
	},
	wpcamp_start_time: {
		endKey: 'wpcamp_end_time',
		label: __( 'Session Time', 'wpcamp-hub' ),
		startLabel: __( 'Start', 'wpcamp-hub' ),
		endLabel: __( 'End', 'wpcamp-hub' ),
	},
};
const dateRangeEndFields = Object.values( dateRangeFields ).map(
	( range ) => range.endKey
);

const getFieldLabel = ( metaKey ) =>
	metaKey
		.replace( /^wpcamp_/, '' )
		.replace( /_/g, ' ' )
		.replace( /\b\w/g, ( letter ) => letter.toUpperCase() );

const toIntegerList = ( value ) =>
	String( value )
		.split( ',' )
		.map( ( item ) => Number.parseInt( item.trim(), 10 ) )
		.filter( Number.isInteger );

const toListValue = ( value ) =>
	Array.isArray( value ) ? value.join( ', ' ) : '';

const getDateValue = ( value ) =>
	typeof value === 'string' && value.length > 0
		? value
		: new Date().toISOString();

const isDateField = ( metaKey ) =>
	metaKey.includes( 'date' ) ||
	metaKey.includes( 'time' ) ||
	metaKey.includes( 'timestamp' );

const buildPostOptions = ( records ) =>
	( records || [] ).map( ( record ) => ( {
		label: record.title?.rendered || record.title?.raw || `#${ record.id }`,
		value: String( record.id ),
	} ) );

const buildUserOptions = ( records ) =>
	( records || [] ).map( ( record ) => ( {
		label: record.name || record.slug || `#${ record.id }`,
		value: String( record.id ),
	} ) );

function RelationArrayControl( {
	help,
	label,
	metaKey,
	onChange,
	options,
	value,
} ) {
	const selectedIds = Array.isArray( value )
		? value
				.map( ( item ) => Number.parseInt( item, 10 ) )
				.filter( Number.isInteger )
		: [];
	const selectedOptions = options.filter( ( option ) =>
		selectedIds.includes( Number.parseInt( option.value, 10 ) )
	);

	return (
		<BaseControl
			__nextHasNoMarginBottom
			id={ `wpcamp-hub-editor-${ metaKey }` }
			label={ label }
			help={ help }
		>
			<Stack spacing={ 2 }>
				<ComboboxControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ label }
					hideLabelFromVision
					options={ options }
					value=""
					onChange={ ( nextValue ) => {
						const nextId = Number.parseInt( nextValue, 10 );
						if ( ! Number.isInteger( nextId ) ) {
							return;
						}

						onChange( [
							...new Set( [ ...selectedIds, nextId ] ),
						] );
					} }
				/>
				{ selectedOptions.length > 0 && (
					<Stack
						className="wpcamp-hub-editor-relation-list"
						direction="row"
						spacing={ 2 }
					>
						{ selectedOptions.map( ( option ) => (
							<Button
								key={ option.value }
								size="small"
								variant="secondary"
								onClick={ () =>
									onChange(
										selectedIds.filter(
											( selectedId ) =>
												selectedId !==
												Number.parseInt(
													option.value,
													10
												)
										)
									)
								}
							>
								{ option.label }
							</Button>
						) ) }
					</Stack>
				) }
			</Stack>
		</BaseControl>
	);
}

function UrlControl( { help, label, metaKey, onChange, value } ) {
	return (
		<BaseControl
			__nextHasNoMarginBottom
			id={ `wpcamp-hub-editor-${ metaKey }` }
			label={ label }
			help={ help }
		>
			<InputControl
				__next40pxDefaultSize
				hideLabelFromVision
				label={ label }
				prefix={ <Icon icon={ globe } size={ 20 } /> }
				type="url"
				value={ value || '' }
				onChange={ ( nextValue ) => onChange( nextValue ?? '' ) }
			/>
		</BaseControl>
	);
}

function DateFieldControl( { help, label, metaKey, onChange, value } ) {
	return (
		<BaseControl
			__nextHasNoMarginBottom
			id={ `wpcamp-hub-editor-${ metaKey }` }
			label={ label }
			help={ help }
		>
			<DateTimePicker
				currentDate={ getDateValue( value ) }
				onChange={ onChange }
				is12Hour={ false }
			/>
		</BaseControl>
	);
}

function DateRangeControl( {
	endField,
	endKey,
	endValue,
	label,
	onChange,
	startField,
	startKey,
	startValue,
	startLabel,
	endLabel,
} ) {
	return (
		<BaseControl
			__nextHasNoMarginBottom
			id={ `wpcamp-hub-editor-${ startKey }-${ endKey }` }
			label={ label }
		>
			<div className="wpcamp-hub-editor-inline-blocks">
				<DateFieldControl
					help={ startField.description }
					label={ startLabel }
					metaKey={ startKey }
					onChange={ ( nextValue ) =>
						onChange( startKey, nextValue )
					}
					value={ startValue }
				/>
				<DateFieldControl
					help={ endField.description }
					label={ endLabel }
					metaKey={ endKey }
					onChange={ ( nextValue ) => onChange( endKey, nextValue ) }
					value={ endValue }
				/>
			</div>
		</BaseControl>
	);
}

function FieldControl( { field, metaKey, options, value, onChange } ) {
	const label = getFieldLabel( metaKey );
	const help = field.description;

	if ( isDateField( metaKey ) ) {
		return (
			<DateFieldControl
				help={ help }
				label={ label }
				metaKey={ metaKey }
				onChange={ onChange }
				value={ value }
			/>
		);
	}

	if ( field.type === 'boolean' ) {
		return (
			<ToggleControl
				__nextHasNoMarginBottom
				label={ label }
				help={ help }
				checked={ Boolean( value ) }
				onChange={ onChange }
			/>
		);
	}

	if ( field.type === 'integer' ) {
		if ( options.length > 0 ) {
			return (
				<ComboboxControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ label }
					help={ help }
					options={ options }
					value={ value ? String( value ) : '' }
					onChange={ ( nextValue ) =>
						onChange( Number.parseInt( nextValue, 10 ) || 0 )
					}
				/>
			);
		}

		return (
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				type="number"
				label={ label }
				help={ help }
				value={ value || '' }
				onChange={ ( nextValue ) =>
					onChange(
						nextValue === ''
							? 0
							: Number.parseInt( nextValue, 10 ) || 0
					)
				}
			/>
		);
	}

	if ( field.type === 'array' ) {
		if ( options.length > 0 ) {
			return (
				<RelationArrayControl
					help={ help }
					label={ label }
					metaKey={ metaKey }
					onChange={ onChange }
					options={ options }
					value={ value }
				/>
			);
		}

		return (
			<TextareaControl
				__nextHasNoMarginBottom
				label={ label }
				help={ help }
				value={ toListValue( value ) }
				onChange={ ( nextValue ) =>
					onChange( toIntegerList( nextValue ) )
				}
			/>
		);
	}

	if ( field.type === 'object' ) {
		const objectValue = value && typeof value === 'object' ? value : {};
		const properties = field.properties || {};
		const controlId = `wpcamp-hub-editor-${ metaKey }`;

		return (
			<BaseControl
				__nextHasNoMarginBottom
				id={ controlId }
				label={ label }
				help={ help }
			>
				<Stack direction="row" spacing={ 3 }>
					{ Object.entries( properties ).map(
						( [ property, propertyType ] ) => (
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								id={ `${ controlId }-${ property }` }
								key={ property }
								type={
									propertyType === 'number'
										? 'number'
										: 'text'
								}
								label={ getFieldLabel( property ) }
								value={ objectValue[ property ] ?? '' }
								onChange={ ( nextValue ) =>
									onChange( {
										...objectValue,
										[ property ]:
											propertyType === 'number'
												? Number.parseFloat(
														nextValue
												  ) || 0
												: nextValue,
									} )
								}
							/>
						)
					) }
				</Stack>
			</BaseControl>
		);
	}

	if ( comboboxOptions[ metaKey ] ) {
		return (
			<ComboboxControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ label }
				help={ help }
				options={ comboboxOptions[ metaKey ] }
				value={ value || '' }
				onChange={ onChange }
			/>
		);
	}

	if ( field.format === 'uri' ) {
		return (
			<UrlControl
				help={ help }
				label={ label }
				metaKey={ metaKey }
				onChange={ onChange }
				value={ value }
			/>
		);
	}

	return (
		<TextControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			type="text"
			label={ label }
			help={ help }
			value={ value || '' }
			onChange={ onChange }
		/>
	);
}

function Edit() {
	const blockProps = useBlockProps( {
		className: 'wpcamp-hub-editor-meta-panel',
	} );
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);
	const fields = fieldsByPostType[ postType ] || {};
	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );
	const relationRecords = useSelect(
		( select ) => ( {
			events: select( 'core' ).getEntityRecords(
				'postType',
				'wpcamp_event',
				{ per_page: 100, status: 'any' }
			),
			tweets: select( 'core' ).getEntityRecords(
				'postType',
				'wpcamp_tweet',
				{ per_page: 100, status: 'any' }
			),
			users: select( 'core' ).getEntityRecords( 'root', 'user', {
				per_page: 100,
			} ),
		} ),
		[]
	);

	const getOptions = ( metaKey ) => {
		const target = relationTargets[ metaKey ];

		if ( target === 'wpcamp_event' ) {
			return buildPostOptions( relationRecords.events );
		}

		if ( target === 'wpcamp_tweet' ) {
			return buildPostOptions( relationRecords.tweets );
		}

		if ( target === 'user' ) {
			return buildUserOptions( relationRecords.users );
		}

		return [];
	};

	const updateMeta = ( metaKey, nextValue ) => {
		setMeta( {
			...meta,
			[ metaKey ]: nextValue,
		} );
	};
	const renderFieldControl = ( metaKey, field ) => {
		const dateRange = dateRangeFields[ metaKey ];

		if ( dateRange && fields[ dateRange.endKey ] ) {
			return (
				<DateRangeControl
					key={ metaKey }
					endField={ fields[ dateRange.endKey ] }
					endKey={ dateRange.endKey }
					endValue={ meta?.[ dateRange.endKey ] }
					label={ dateRange.label }
					onChange={ updateMeta }
					startField={ field }
					startKey={ metaKey }
					startValue={ meta?.[ metaKey ] }
					startLabel={ dateRange.startLabel }
					endLabel={ dateRange.endLabel }
				/>
			);
		}

		if ( dateRangeEndFields.includes( metaKey ) ) {
			const hasStartField = Object.entries( dateRangeFields ).some(
				( [ startKey, range ] ) =>
					range.endKey === metaKey && Boolean( fields[ startKey ] )
			);

			if ( hasStartField ) {
				return null;
			}
		}

		return (
			<FieldControl
				key={ metaKey }
				field={ field }
				metaKey={ metaKey }
				options={ getOptions( metaKey ) }
				value={ meta?.[ metaKey ] }
				onChange={ ( nextValue ) => updateMeta( metaKey, nextValue ) }
			/>
		);
	};

	if ( Object.keys( fields ).length === 0 ) {
		return (
			<div { ...blockProps }>
				<Text>
					{ __(
						'No editable WPCamp Hub fields are registered for this post type.',
						'wpcamp-hub'
					) }
				</Text>
			</div>
		);
	}

	return (
		<div { ...blockProps }>
			<Stack spacing={ 4 }>
				<Text weight={ 600 }>
					{ __( 'WPCamp Hub Details', 'wpcamp-hub' ) }
				</Text>
				{ Object.entries( fields ).map( ( [ metaKey, field ] ) =>
					renderFieldControl( metaKey, field )
				) }
			</Stack>
		</div>
	);
}

registerBlockType( metadata.name, {
	...metadata,
	edit: Edit,
	save: () => null,
} );
