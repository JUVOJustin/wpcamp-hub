/**
 * Community Feed block registration.
 *
 * Dynamic, server-rendered block: tweets grouped by their event hashtag.
 */
import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	// Dynamic block — markup comes from render.php.
	save: () => null,
} );
