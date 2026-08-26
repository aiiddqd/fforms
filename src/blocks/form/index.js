import metadata from './block.json';
import Edit from './edit';
import save from './save';
import { registerBlockType } from '@wordpress/blocks';

registerBlockType( metadata, { edit: Edit, save } );
