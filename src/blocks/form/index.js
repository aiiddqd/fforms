import metadata from './block.json';
import Edit from './edit';
import save from './save';
import { registerBlockType } from '@wordpress/blocks';
import './style.scss';
import './editor.scss';

registerBlockType( metadata, { edit: Edit, save } );
