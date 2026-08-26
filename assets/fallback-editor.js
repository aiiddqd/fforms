( function ( blocks, blockEditor, components, data, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	var InnerBlocks = blockEditor.InnerBlocks;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var ToggleControl = components.ToggleControl;
	var PanelBody = components.PanelBody;
	var Placeholder = components.Placeholder;
	var TEMPLATE = [
		[ 'fforms/field-text', { fieldId: 'name', name: 'name', label: __( 'Имя', 'fforms' ), required: true } ],
		[ 'fforms/field-email', { fieldId: 'email', name: 'email', label: __( 'Email', 'fforms' ), required: true } ],
		[ 'fforms/field-textarea', { fieldId: 'message', name: 'message', label: __( 'Сообщение', 'fforms' ), required: true } ],
		[ 'fforms/submit', { label: __( 'Отправить', 'fforms' ) } ]
	];
	var FIELDS = [ 'text', 'textarea', 'email', 'tel', 'url', 'number', 'select', 'radio', 'checkbox', 'hidden' ];

	blocks.registerBlockType( 'fforms/form', {
		title: __( 'FForms Form', 'fforms' ), icon: 'feedback', category: 'widgets', attributes: { ref: { type: 'integer', default: 0 }, formId: { type: 'integer', default: 0 }, showTitle: { type: 'boolean', default: false }, submitLabel: { type: 'string', default: '' } },
		edit: function ( props ) {
			var isFormEditor = data.useSelect( function ( select ) { return select( 'core/editor' ).getCurrentPostType() === 'fform'; }, [] );
			if ( isFormEditor ) return el( 'div', useBlockProps(), el( InnerBlocks, { template: TEMPLATE, allowedBlocks: FIELDS.map( function ( type ) { return 'fforms/field-' + type; } ).concat( [ 'fforms/submit', 'core/paragraph', 'core/heading', 'core/group', 'core/columns', 'core/column', 'core/spacer', 'core/separator' ] ) } ) );
			var forms = data.useSelect( function ( select ) { return select( 'core' ).getEntityRecords( 'postType', 'fform', { per_page: 100, status: 'publish', orderby: 'title', order: 'asc' } ); }, [] );
			var options = [ { label: __( 'Выберите опубликованную форму', 'fforms' ), value: 0 } ].concat( ( forms || [] ).map( function ( form ) { return { label: form.title.rendered || '#' + form.id, value: form.id }; } ) );
			return el( 'div', useBlockProps(), el( Placeholder, { icon: 'feedback', label: __( 'FForms Form', 'fforms' ) }, el( SelectControl, { label: __( 'Форма', 'fforms' ), value: props.attributes.ref || props.attributes.formId, options: options, onChange: function ( value ) { props.setAttributes( { ref: Number( value ), formId: Number( value ) } ); } } ) ) );
		},
		save: function () { return el( InnerBlocks.Content ); }
	} );

	FIELDS.forEach( function ( type ) {
		blocks.registerBlockType( 'fforms/field-' + type, {
			title: __( type + ' field', 'fforms' ), category: 'widgets', parent: [ 'fforms/form' ], attributes: { fieldId: { type: 'string' }, name: { type: 'string' }, label: { type: 'string' }, required: { type: 'boolean', default: false }, placeholder: { type: 'string' }, maxLength: { type: 'number', default: 0 }, options: { type: 'array', default: [] } },
			edit: function ( props ) {
				var a = props.attributes;
				var control = el( InspectorControls, null, el( PanelBody, { title: __( 'Настройки поля', 'fforms' ) }, el( TextControl, { label: __( 'Имя', 'fforms' ), value: a.name, onChange: function ( name ) { props.setAttributes( { name: name, fieldId: a.fieldId || name } ); } } ), el( TextControl, { label: __( 'Подпись', 'fforms' ), value: a.label, onChange: function ( label ) { props.setAttributes( { label: label } ); } } ), el( TextControl, { label: __( 'Placeholder', 'fforms' ), value: a.placeholder, onChange: function ( placeholder ) { props.setAttributes( { placeholder: placeholder } ); } } ), el( ToggleControl, { label: __( 'Обязательное поле', 'fforms' ), checked: a.required, onChange: function ( required ) { props.setAttributes( { required: required } ); } } ) ) );
				var input = type === 'textarea' ? el( 'textarea', { disabled: true, placeholder: a.placeholder } ) : el( 'input', { disabled: true, type: type === 'hidden' ? 'hidden' : type, placeholder: a.placeholder } );
				return el( Fragment, null, control, el( 'div', useBlockProps( { className: 'fforms-field' } ), type !== 'hidden' ? el( 'label', null, a.label || a.name || __( 'Новое поле', 'fforms' ) ) : null, input ) );
			}, save: function () { return null; }
		} );
	} );
	blocks.registerBlockType( 'fforms/submit', { title: __( 'Submit button', 'fforms' ), category: 'widgets', parent: [ 'fforms/form' ], attributes: { label: { type: 'string', default: '' } }, edit: function ( props ) { return el( Fragment, null, el( InspectorControls, null, el( PanelBody, { title: __( 'Кнопка отправки', 'fforms' ) }, el( TextControl, { label: __( 'Текст', 'fforms' ), value: props.attributes.label, onChange: function ( label ) { props.setAttributes( { label: label } ); } } ) ) ), el( 'div', useBlockProps(), el( 'button', { type: 'button', disabled: true }, props.attributes.label || __( 'Отправить', 'fforms' ) ) ) ); }, save: function () { return null; } } );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.data, window.wp.element, window.wp.i18n );
