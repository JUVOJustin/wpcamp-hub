/* eslint-disable import/no-unresolved, import/no-extraneous-dependencies */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import {
	BaseControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { Stack, Text } from '@wordpress/ui';

import metadata from './block.json';
import './editor.scss';

const config = window.wpcamp_hub || {};
const fieldsByPostType = config.postMetaFields || {};

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

function FieldControl( { field, metaKey, value, onChange } ) {
	const label = getFieldLabel( metaKey );
	const help = field.description;

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

	return (
		<TextControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			type={ field.format === 'uri' ? 'url' : 'text' }
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

	const updateMeta = ( metaKey, nextValue ) => {
		setMeta( {
			...meta,
			[ metaKey ]: nextValue,
		} );
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
				{ Object.entries( fields ).map( ( [ metaKey, field ] ) => (
					<FieldControl
						key={ metaKey }
						field={ field }
						metaKey={ metaKey }
						value={ meta?.[ metaKey ] }
						onChange={ ( nextValue ) =>
							updateMeta( metaKey, nextValue )
						}
					/>
				) ) }
			</Stack>
		</div>
	);
}

registerBlockType( metadata.name, {
	...metadata,
	edit: Edit,
	save: () => null,
} );
