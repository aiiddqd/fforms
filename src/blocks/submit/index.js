import metadata from './block.json';
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( {
		className: 'fforms-submit wp-element-button',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Кнопка отправки', 'fforms' ) }>
					<TextControl
						label={ __( 'Текст кнопки', 'fforms' ) }
						onChange={ ( label ) => setAttributes( { label } ) }
						value={ attributes.label }
					/>
				</PanelBody>
			</InspectorControls>
			<button
				{ ...blockProps }
				onClick={ ( event ) => event.preventDefault() }
				type="button"
			>
				{ attributes.label || __( 'Отправить', 'fforms' ) }
			</button>
		</>
	);
}

registerBlockType( metadata, {
	edit: Edit,
	save: () => null,
} );
