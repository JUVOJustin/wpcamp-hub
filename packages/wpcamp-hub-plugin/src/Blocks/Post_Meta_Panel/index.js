/* eslint-disable import/no-unresolved, import/no-extraneous-dependencies, @wordpress/no-unsafe-wp-apis */
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import {
	BaseControl,
	Button,
	ComboboxControl,
	__experimentalInputControl as InputControl,
	__experimentalInputControlPrefixWrapper as InputControlPrefixWrapper,
	TextControl,
	TextareaControl,
	TimePicker,
	ToggleControl,
} from '@wordpress/components';
import { globe, Icon } from '@wordpress/icons';

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
const relationQueryLimit = 20;
const selectedRelationQueryLimit = 100;

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

const getRelationEntity = ( target ) =>
	target === 'user'
		? { kind: 'root', name: 'user' }
		: { kind: 'postType', name: target };

const getRelationQuery = ( target, query ) => ( {
	per_page: relationQueryLimit,
	...( target === 'user' ? {} : { status: 'any' } ),
	...( query.length > 0 ? { search: query } : {} ),
} );

const getSelectedRelationQuery = ( target, selectedIds ) => {
	const includedIds = selectedIds.slice( 0, selectedRelationQueryLimit );

	return {
		per_page: includedIds.length,
		include: includedIds,
		...( target === 'user' ? {} : { status: 'any' } ),
	};
};

const getRelationOption = ( target, record ) => ( {
	label:
		target === 'user'
			? record.name || record.slug || `#${ record.id }`
			: record.title?.rendered || record.title?.raw || `#${ record.id }`,
	value: String( record.id ),
} );

const getFallbackRelationOption = ( id ) => ( {
	label: `#${ id }`,
	value: String( id ),
} );

const mergeRelationOptions = ( ...optionGroups ) => {
	const optionsByValue = new Map();

	optionGroups.flat().forEach( ( option ) => {
		optionsByValue.set( option.value, option );
	} );

	return [ ...optionsByValue.values() ];
};

const toRelationIds = ( value, multiple ) => {
	const values = multiple && Array.isArray( value ) ? value : [ value ];

	return values
		.map( ( item ) => Number.parseInt( item, 10 ) )
		.filter( ( id ) => Number.isInteger( id ) && id > 0 );
};

function AsyncRelationControl( {
	help,
	label,
	metaKey,
	multiple,
	onChange,
	target,
	value,
} ) {
	const [ query, setQuery ] = useState( '' );
	const selectedIds = toRelationIds( value, multiple );
	const selectedIdsKey = selectedIds.join( ',' );
	const trimmedQuery = query.trim();
	const { records, selectedRecords, isLoading } = useSelect(
		( select ) => {
			const { kind, name } = getRelationEntity( target );
			const core = select( 'core' );
			const selectedIdsForQuery =
				selectedIdsKey.length > 0
					? selectedIdsKey
							.split( ',' )
							.map( ( id ) => Number.parseInt( id, 10 ) )
							.filter( Number.isInteger )
					: [];
			const recordsQuery = getRelationQuery( target, trimmedQuery );
			const selectedQuery =
				selectedIdsForQuery.length > 0
					? getSelectedRelationQuery( target, selectedIdsForQuery )
					: null;
			const relationRecords =
				core.getEntityRecords( kind, name, recordsQuery ) || [];

			return {
				records: relationRecords,
				selectedRecords: selectedQuery
					? core.getEntityRecords( kind, name, selectedQuery ) || []
					: [],
				isLoading: Boolean(
					core.isResolving?.( 'getEntityRecords', [
						kind,
						name,
						recordsQuery,
					] )
				),
			};
		},
		[ target, trimmedQuery, selectedIdsKey ]
	);
	const options = mergeRelationOptions(
		selectedIds.map( getFallbackRelationOption ),
		selectedRecords.map( ( record ) =>
			getRelationOption( target, record )
		),
		records.map( ( record ) => getRelationOption( target, record ) )
	);
	const selectedOptions = selectedIds.map(
		( id ) =>
			options.find( ( option ) => option.value === String( id ) ) ||
			getFallbackRelationOption( id )
	);
	const updateSelection = ( nextValue ) => {
		const nextId = Number.parseInt( nextValue, 10 );

		if ( ! Number.isInteger( nextId ) ) {
			if ( ! multiple ) {
				onChange( 0 );
			}
			return;
		}

		if ( multiple ) {
			onChange( [ ...new Set( [ ...selectedIds, nextId ] ) ] );
		} else {
			onChange( nextId );
		}

		setQuery( '' );
	};

	return (
		<BaseControl
			__nextHasNoMarginBottom
			id={ `wpcamp-hub-editor-${ metaKey }` }
			label={ label }
			help={ help }
		>
			<div className="wpcamp-hub-editor-control-list">
				<ComboboxControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					allowReset={ ! multiple }
					hideLabelFromVision
					isLoading={ isLoading }
					label={ label }
					onChange={ updateSelection }
					onFilterValueChange={ setQuery }
					options={ options }
					placeholder={ __( 'Search relations', 'wpcamp-hub' ) }
					value={ multiple ? '' : selectedOptions[ 0 ]?.value || '' }
				/>
				{ multiple && selectedOptions.length > 0 && (
					<div className="wpcamp-hub-editor-relation-list">
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
					</div>
				) }
			</div>
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
				prefix={
					<InputControlPrefixWrapper variant="icon">
						<Icon icon={ globe } />
					</InputControlPrefixWrapper>
				}
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
			<TimePicker
				currentTime={ getDateValue( value ) }
				onChange={ onChange }
				is12Hour={ false }
				hideLabelFromVision
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

function FieldControl( { field, metaKey, relationTarget, value, onChange } ) {
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
		if ( relationTarget ) {
			return (
				<AsyncRelationControl
					help={ help }
					label={ label }
					metaKey={ metaKey }
					multiple={ false }
					onChange={ onChange }
					target={ relationTarget }
					value={ value }
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
		if ( relationTarget ) {
			return (
				<AsyncRelationControl
					help={ help }
					label={ label }
					metaKey={ metaKey }
					multiple
					onChange={ onChange }
					target={ relationTarget }
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
				<div className="wpcamp-hub-editor-inline-blocks">
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
				</div>
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

function MetaPanel() {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);
	const fields = fieldsByPostType[ postType ] || {};
	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

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
				relationTarget={ relationTargets[ metaKey ] }
				value={ meta?.[ metaKey ] }
				onChange={ ( nextValue ) => updateMeta( metaKey, nextValue ) }
			/>
		);
	};

	if ( Object.keys( fields ).length === 0 ) {
		return (
			<PluginDocumentSettingPanel
				name="wpcamp-hub-details"
				title={ __( 'WPCamp Hub Details', 'wpcamp-hub' ) }
				className="wpcamp-hub-editor-meta-panel"
			>
				<p className="wpcamp-hub-editor-empty-state">
					{ __(
						'No editable WPCamp Hub fields are registered for this post type.',
						'wpcamp-hub'
					) }
				</p>
			</PluginDocumentSettingPanel>
		);
	}

	return (
		<PluginDocumentSettingPanel
			name="wpcamp-hub-details"
			title={ __( 'WPCamp Hub Details', 'wpcamp-hub' ) }
			className="wpcamp-hub-editor-meta-panel"
		>
			<div className="wpcamp-hub-editor-control-list">
				{ Object.entries( fields ).map( ( [ metaKey, field ] ) =>
					renderFieldControl( metaKey, field )
				) }
			</div>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'wpcamp-hub-details', {
	render: MetaPanel,
} );
