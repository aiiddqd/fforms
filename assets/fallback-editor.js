( function ( blocks, blockEditor, components, data, element, i18n ) {
	'use strict';
	const el = element.createElement;
	const Fragment = element.Fragment;
	const __ = i18n.__;
	const InnerBlocks = blockEditor.InnerBlocks;
	const InspectorControls = blockEditor.InspectorControls;
	const useBlockProps = blockEditor.useBlockProps;
	const SelectControl = components.SelectControl;
	const TextControl = components.TextControl;
	const ToggleControl = components.ToggleControl;
	const PanelBody = components.PanelBody;
	const Placeholder = components.Placeholder;
	const TEMPLATE = [
		[
			'fforms/field-text',
			{
				fieldId: 'name',
				name: 'name',
				label: __( 'Имя', 'fforms' ),
				required: true,
			},
		],
		[
			'fforms/field-email',
			{
				fieldId: 'email',
				name: 'email',
				label: __( 'Email', 'fforms' ),
				required: true,
			},
		],
		[
			'fforms/field-textarea',
			{
				fieldId: 'message',
				name: 'message',
				label: __( 'Сообщение', 'fforms' ),
				required: true,
			},
		],
		[ 'fforms/submit', { label: __( 'Отправить', 'fforms' ) } ],
	];
	const FIELDS = [
		'text',
		'textarea',
		'email',
		'tel',
		'url',
		'number',
		'select',
		'radio',
		'checkbox',
		'hidden',
	];

	blocks.registerBlockType( 'fforms/form', {
		title: __( 'FForms Form', 'fforms' ),
		icon: 'feedback',
		category: 'widgets',
		attributes: {
			ref: { type: 'integer', default: 0 },
			formId: { type: 'integer', default: 0 },
			showTitle: { type: 'boolean', default: false },
			submitLabel: { type: 'string', default: '' },
		},
		edit( props ) {
			const isFormEditor = data.useSelect( function ( select ) {
				return select( 'core/editor' ).getCurrentPostType() === 'fform';
			}, [] );
			if ( isFormEditor ) {
				return el(
					'div',
					useBlockProps(),
					el( InnerBlocks, {
						template: TEMPLATE,
						allowedBlocks: FIELDS.map( function ( type ) {
							return 'fforms/field-' + type;
						} ).concat( [
							'fforms/submit',
							'core/paragraph',
							'core/heading',
							'core/group',
							'core/columns',
							'core/column',
							'core/spacer',
							'core/separator',
						] ),
					} )
				);
			}
			const forms = data.useSelect( function ( select ) {
				return select( 'core' ).getEntityRecords( 'postType', 'fform', {
					per_page: 100,
					status: 'publish',
					orderby: 'title',
					order: 'asc',
				} );
			}, [] );
			const options = [
				{
					label: __( 'Выберите опубликованную форму', 'fforms' ),
					value: 0,
				},
			].concat(
				( forms || [] ).map( function ( form ) {
					return {
						label: form.title.rendered || '#' + form.id,
						value: form.id,
					};
				} )
			);
			return el(
				'div',
				useBlockProps(),
				el(
					Placeholder,
					{ icon: 'feedback', label: __( 'FForms Form', 'fforms' ) },
					el( SelectControl, {
						label: __( 'Форма', 'fforms' ),
						value: props.attributes.ref || props.attributes.formId,
						options,
						onChange( value ) {
							props.setAttributes( {
								ref: Number( value ),
								formId: Number( value ),
							} );
						},
					} )
				)
			);
		},
		save() {
			return el( InnerBlocks.Content );
		},
	} );

	FIELDS.forEach( function ( type ) {
		blocks.registerBlockType( 'fforms/field-' + type, {
			title: __( type + ' field', 'fforms' ),
			category: 'widgets',
			parent: [ 'fforms/form' ],
			attributes: {
				fieldId: { type: 'string' },
				name: { type: 'string' },
				label: { type: 'string' },
				required: { type: 'boolean', default: false },
				placeholder: { type: 'string' },
				maxLength: { type: 'number', default: 0 },
				options: { type: 'array', default: [] },
			},
			edit( props ) {
				const a = props.attributes;
				const control = el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Настройки поля', 'fforms' ) },
						el( TextControl, {
							label: __( 'Имя', 'fforms' ),
							value: a.name,
							onChange( name ) {
								props.setAttributes( {
									name,
									fieldId: a.fieldId || name,
								} );
							},
						} ),
						el( TextControl, {
							label: __( 'Подпись', 'fforms' ),
							value: a.label,
							onChange( label ) {
								props.setAttributes( { label } );
							},
						} ),
						el( TextControl, {
							label: __( 'Placeholder', 'fforms' ),
							value: a.placeholder,
							onChange( placeholder ) {
								props.setAttributes( {
									placeholder,
								} );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Обязательное поле', 'fforms' ),
							checked: a.required,
							onChange( required ) {
								props.setAttributes( { required } );
							},
						} )
					)
				);
				const input =
					type === 'textarea'
						? el( 'textarea', {
								disabled: true,
								placeholder: a.placeholder,
						  } )
						: el( 'input', {
								disabled: true,
								type: type === 'hidden' ? 'hidden' : type,
								placeholder: a.placeholder,
						  } );
				return el(
					Fragment,
					null,
					control,
					el(
						'div',
						useBlockProps( { className: 'fforms-field' } ),
						type !== 'hidden'
							? el(
									'label',
									null,
									a.label ||
										a.name ||
										__( 'Новое поле', 'fforms' )
							  )
							: null,
						input
					)
				);
			},
			save() {
				return null;
			},
		} );
	} );
	blocks.registerBlockType( 'fforms/submit', {
		title: __( 'Submit button', 'fforms' ),
		category: 'widgets',
		parent: [ 'fforms/form' ],
		attributes: { label: { type: 'string', default: '' } },
		edit( props ) {
			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Кнопка отправки', 'fforms' ) },
						el( TextControl, {
							label: __( 'Текст', 'fforms' ),
							value: props.attributes.label,
							onChange( label ) {
								props.setAttributes( { label } );
							},
						} )
					)
				),
				el(
					'div',
					useBlockProps(),
					el(
						'button',
						{ type: 'button', disabled: true },
						props.attributes.label || __( 'Отправить', 'fforms' )
					)
				)
			);
		},
		save() {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.data,
	window.wp.element,
	window.wp.i18n
);
