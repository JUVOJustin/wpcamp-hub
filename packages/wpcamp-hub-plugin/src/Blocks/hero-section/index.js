/**
 * Hero Section block registration.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';

import metadata from './block.json';
import Edit from './edit';
import './style.scss';
import './editor.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	// Dynamic block — render.php builds the hero. It only consumes the saved
	// InnerBlocks (the manual CTA buttons) when no event is linked, so persist
	// them here.
	save: () => <InnerBlocks.Content />,
} );
