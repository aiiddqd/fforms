( function ( blocks, element, components, data, i18n, blockEditor ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'fforms/form', {
		title: __( 'FForms', 'fforms' ),
		description: __( 'Показывает форму FForms.', 'fforms' ),
		icon: 'feedback',
		category: 'widgets',
		attributes: { formId: { type: 'integer', default: 0 }, showTitle: { type: 'boolean', default: false }, submitLabel: { type: 'string', default: '' } },
		edit: function ( props ) {
			var forms = data.useSelect( function ( select ) {
				return select( 'core' ).getEntityRecords( 'postType', 'fform', { per_page: 100, status: 'publish', orderby: 'title', order: 'asc' } );
			}, [] );
			var options = [ { label: __( 'Выберите форму', 'fforms' ), value: 0 } ];
			if ( forms ) {
				options = options.concat( forms.map( function ( form ) { return { label: form.title.rendered || ( '#' + form.id ), value: form.id }; } ) );
			}
			var select = el( components.SelectControl, { label: __( 'Форма', 'fforms' ), value: props.attributes.formId, options: options, onChange: function ( value ) { props.setAttributes( { formId: Number( value ) } ); } } );
			var controls = el( blockEditor.InspectorControls, {}, el( components.PanelBody, { title: __( 'Настройки формы', 'fforms' ), initialOpen: true }, select, el( components.ToggleControl, { label: __( 'Показывать заголовок', 'fforms' ), checked: props.attributes.showTitle, onChange: function ( value ) { props.setAttributes( { showTitle: value } ); } } ), el( components.TextControl, { label: __( 'Текст кнопки', 'fforms' ), value: props.attributes.submitLabel, placeholder: __( 'Отправить', 'fforms' ), onChange: function ( value ) { props.setAttributes( { submitLabel: value } ); } } ) ) );
			return el( element.Fragment, {}, controls, el( components.Placeholder, { icon: 'feedback', label: __( 'FForms', 'fforms' ) }, ! forms ? el( components.Spinner ) : select ) );
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.element, window.wp.components, window.wp.data, window.wp.i18n, window.wp.blockEditor );
