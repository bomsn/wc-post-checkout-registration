import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import metadata from './block.json';
import './style.scss';

registerBlockType(metadata.name, {
	edit: Edit,
	// Dynamic block - no save function needed
	save: () => null,
});
