import metadata from './block.json';
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
registerBlockType( metadata, { edit: ( { attributes, setAttributes } ) => <><InspectorControls><PanelBody title={ __( 'Кнопка отправки', 'fforms' ) }><TextControl label={ __( 'Текст кнопки', 'fforms' ) } value={ attributes.label } onChange={ ( label ) => setAttributes( { label } ) } /></PanelBody></InspectorControls><div { ...useBlockProps() }><button type="button" disabled>{ attributes.label || __( 'Отправить', 'fforms' ) }</button></div></>, save: () => null } );
