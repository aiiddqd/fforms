( function ( components, data, editPost, element, i18n, plugins, blocks ) {
	'use strict';

	const el = element.createElement;
	const useEffect = element.useEffect;
	const __ = i18n.__;
	const FORM_SETTINGS_PANEL_NAME = 'fforms-form-settings/form-settings';
	const PANEL_DEFAULTED_KEY = 'fforms-form-settings-panel-defaulted-v2';
	const SelectControl = components.SelectControl;
	const TextControl = components.TextControl;
	const TextareaControl = components.TextareaControl;
	const ToggleControl = components.ToggleControl;
	const PluginDocumentSettingPanel = editPost.PluginDocumentSettingPanel;
	const FIELD_TYPES = [
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
	const DEFAULT_SCHEMA = {
		fields: [
			{
				name: 'name',
				label: __( 'Имя', 'fforms' ),
				type: 'text',
				required: true,
			},
			{
				name: 'email',
				label: __( 'Email', 'fforms' ),
				type: 'email',
				required: true,
			},
			{
				name: 'message',
				label: __( 'Сообщение', 'fforms' ),
				type: 'textarea',
				required: true,
			},
		],
	};
	const notificationSettingsEnabled = Boolean(
		window.fformsFormSettings &&
			window.fformsFormSettings.notificationSettingsEnabled
	);
	const META = {
		type: '_fforms_type',
		mode: '_fforms_mode',
		schema: '_fforms_schema',
		notificationTo: '_fforms_notification_to',
		notificationSubject: '_fforms_notification_subject',
		notificationsEnabled: '_fforms_notifications_enabled',
		successMessage: '_fforms_success_message',
		publicForm: '_fforms_public',
		autoreplyEnabled: '_fforms_autoreply_enabled',
		autoreplyEmailField: '_fforms_autoreply_email_field',
		autoreplySubject: '_fforms_autoreply_subject',
		autoreplyMessage: '_fforms_autoreply_message',
	};

	function findSchemaBlock( blockList ) {
		for ( const block of blockList ) {
			if (
				'fforms/form' === block.name ||
				'fforms/headless-schema' === block.name
			) {
				return block;
			}
			const nested = findSchemaBlock( block.innerBlocks || [] );
			if ( nested ) {
				return nested;
			}
		}
		return null;
	}

	function schemaFromBlockEditor() {
		const blockEditor = data.select( 'core/block-editor' );
		const form = blockEditor
			? findSchemaBlock( blockEditor.getBlocks() )
			: null;
		if ( ! form ) {
			return DEFAULT_SCHEMA;
		}

		const fields = ( form.innerBlocks || [] )
			.filter( ( block ) => block.name.startsWith( 'fforms/field-' ) )
			.map( ( block ) => {
				const attributes = block.attributes || {};
				const type = block.name.replace( 'fforms/field-', '' );
				const field = {
					name: attributes.name || '',
					label: attributes.label || attributes.name || '',
					type,
					required: Boolean( attributes.required ),
					placeholder: attributes.placeholder || '',
				};
				if ( attributes.maxLength ) {
					field.max_length = attributes.maxLength;
				}
				if ( attributes.options ) {
					field.options = attributes.options;
				}
				return field;
			} )
			.filter(
				( field ) => field.name && FIELD_TYPES.includes( field.type )
			);

		return fields.length ? { fields } : DEFAULT_SCHEMA;
	}

	function fieldBlocksFromSchema( schema ) {
		return schema.fields
			.filter( ( field ) => FIELD_TYPES.includes( field.type ) )
			.map( ( field ) =>
				blocks.createBlock( `fforms/field-${ field.type }`, {
					fieldId: field.name,
					name: field.name,
					label: field.label,
					required: Boolean( field.required ),
					placeholder: field.placeholder || '',
					maxLength: field.max_length || undefined,
					options: field.options || undefined,
				} )
			);
	}

	function blocksFromSchema( schema ) {
		const innerBlocks = fieldBlocksFromSchema( schema );
		innerBlocks.push(
			blocks.createBlock( 'fforms/submit', {
				label: __( 'Отправить', 'fforms' ),
			} )
		);
		return [ blocks.createBlock( 'fforms/form', {}, innerBlocks ) ];
	}

	function headlessBlocksFromSchema( schema ) {
		return [
			blocks.createBlock(
				'fforms/headless-schema',
				{ lock: { move: true, remove: true } },
				fieldBlocksFromSchema( schema )
			),
		];
	}

	function FormSettings() {
		const editor = data.useSelect( function ( select ) {
			const store = select( 'core/editor' );
			return {
				id: store.getCurrentPostId(),
				meta: store.getEditedPostAttribute( 'meta' ) || {},
				status: store.getEditedPostAttribute( 'status' ),
				isPanelOpened: store.isEditorPanelOpened(
					FORM_SETTINGS_PANEL_NAME
				),
			};
		}, [] );
		const togglePanelOpened =
			data.useDispatch( 'core/editor' ).toggleEditorPanelOpened;
		useEffect(
			function () {
				if (
					editor.isPanelOpened ||
					window.localStorage.getItem( PANEL_DEFAULTED_KEY )
				) {
					return;
				}
				window.localStorage.setItem( PANEL_DEFAULTED_KEY, '1' );
				togglePanelOpened( FORM_SETTINGS_PANEL_NAME );
			},
			[ editor.isPanelOpened, togglePanelOpened ]
		);
		const editPostMeta = data.useDispatch( 'core/editor' ).editPost;
		const meta = editor.meta;
		const updateMeta = function ( key, value ) {
			editPostMeta( {
				meta: Object.assign(
					{},
					meta,
					( function () {
						const next = {};
						next[ key ] = value;
						return next;
					} )()
				),
			} );
		};
		const updateMode = function ( value ) {
			const mode = 'headless' === value ? 'headless' : 'block';
			const nextMeta = Object.assign( {}, meta, { [ META.mode ]: mode } );
			if ( 'headless' === mode ) {
				const schema = schemaFromBlockEditor();
				nextMeta[ META.schema ] = JSON.stringify( schema, null, 2 );
				data.dispatch( 'core/block-editor' ).resetBlocks(
					headlessBlocksFromSchema( schema )
				);
				editPostMeta( { meta: nextMeta } );
				return;
			}
			data.dispatch( 'core/block-editor' ).resetBlocks(
				blocksFromSchema( schemaFromBlockEditor() )
			);
			editPostMeta( {
				meta: nextMeta,
			} );
		};
		const publicUrl =
			window.fformsFormSettings && window.fformsFormSettings.publicFormUrl
				? window.fformsFormSettings.publicFormUrl.replace(
						/0\/?$/,
						String( editor.id ) + '/'
				  )
				: '';
		return el(
			element.Fragment,
			null,
			el(
				PluginDocumentSettingPanel,
				{
					name: 'form-settings',
					title: __( 'Настройки формы', 'fforms' ),
					className: 'fforms-form-settings',
				},
				el( SelectControl, {
					label: __( 'Режим формы', 'fforms' ),
					value: meta[ META.mode ] || 'block',
					options: [
						{
							label: __( 'Block editor', 'fforms' ),
							value: 'block',
						},
						{
							label: __( 'Headless API', 'fforms' ),
							value: 'headless',
						},
					],
					help: __(
						'При смене режима поля формы сохраняются и преобразуются в нужный формат.',
						'fforms'
					),
					onChange: updateMode,
				} ),
				el( SelectControl, {
					label: __( 'Тип формы', 'fforms' ),
					value: meta[ META.type ] || 'contact',
					options: [
						{
							label: __( 'Контактная', 'fforms' ),
							value: 'contact',
						},
						{ label: __( 'Лид', 'fforms' ), value: 'lead' },
					],
					onChange( value ) {
						updateMeta( META.type, value );
					},
				} ),
				notificationSettingsEnabled
					? el(
							element.Fragment,
							null,
							el( ToggleControl, {
								label: __(
									'Отправлять уведомления о заявках',
									'fforms'
								),
								checked: Boolean(
									meta[ META.notificationsEnabled ]
								),
								onChange( value ) {
									updateMeta(
										META.notificationsEnabled,
										value
									);
								},
							} ),
							meta[ META.notificationsEnabled ]
								? el(
										element.Fragment,
										null,
										el( TextControl, {
											label: __( 'Получатели', 'fforms' ),
											value:
												meta[ META.notificationTo ] ||
												'',
											help: __(
												'Email через запятую; если пусто — email администратора.',
												'fforms'
											),
											onChange( value ) {
												updateMeta(
													META.notificationTo,
													value
												);
											},
										} ),
										el( TextControl, {
											label: __(
												'Тема уведомления',
												'fforms'
											),
											value:
												meta[
													META.notificationSubject
												] || '',
											onChange( value ) {
												updateMeta(
													META.notificationSubject,
													value
												);
											},
										} )
								  )
								: null
					  )
					: null,
				el( TextControl, {
					label: __( 'Сообщение об успехе', 'fforms' ),
					value: meta[ META.successMessage ] || '',
					placeholder: __( 'Спасибо! Форма отправлена.', 'fforms' ),
					onChange( value ) {
						updateMeta( META.successMessage, value );
					},
				} )
			),
			el(
				PluginDocumentSettingPanel,
				{
					name: 'public-form',
					title: __( 'Публичная форма', 'fforms' ),
				},
				el( ToggleControl, {
					label: __( 'Открыть форму по публичной ссылке', 'fforms' ),
					checked: Boolean( meta[ META.publicForm ] ),
					help: __(
						'Любой, у кого есть ссылка, сможет заполнить и отправить форму.',
						'fforms'
					),
					onChange( value ) {
						updateMeta( META.publicForm, value );
					},
				} ),
				meta[ META.publicForm ] &&
					'publish' === editor.status &&
					publicUrl
					? el(
							'p',
							null,
							el(
								'a',
								{
									href: publicUrl,
									target: '_blank',
									rel: 'noopener noreferrer',
								},
								__( 'Открыть публичную форму', 'fforms' )
							)
					  )
					: null,
				meta[ META.publicForm ] && 'publish' !== editor.status
					? el(
							'p',
							{ className: 'components-base-control__help' },
							__(
								'Ссылка станет доступна после публикации формы.',
								'fforms'
							)
					  )
					: null
			),
			notificationSettingsEnabled
				? el(
						PluginDocumentSettingPanel,
						{
							name: 'autoreply',
							title: __( 'Автоответ', 'fforms' ),
						},
						el( ToggleControl, {
							label: __(
								'Отправлять автоответ пользователю',
								'fforms'
							),
							checked: Boolean( meta[ META.autoreplyEnabled ] ),
							onChange( value ) {
								updateMeta( META.autoreplyEnabled, value );
							},
						} ),
						meta[ META.autoreplyEnabled ]
							? el(
									element.Fragment,
									null,
									el( TextControl, {
										label: __( 'Имя email-поля', 'fforms' ),
										value:
											meta[ META.autoreplyEmailField ] ||
											'email',
										onChange( value ) {
											updateMeta(
												META.autoreplyEmailField,
												value
											);
										},
									} ),
									el( TextControl, {
										label: __(
											'Тема автоответа',
											'fforms'
										),
										value:
											meta[ META.autoreplySubject ] || '',
										onChange( value ) {
											updateMeta(
												META.autoreplySubject,
												value
											);
										},
									} ),
									el( TextareaControl, {
										label: __(
											'Текст автоответа',
											'fforms'
										),
										value:
											meta[ META.autoreplyMessage ] || '',
										onChange( value ) {
											updateMeta(
												META.autoreplyMessage,
												value
											);
										},
									} )
							  )
							: null
				  )
				: null
		);
	}

	plugins.registerPlugin( 'fforms-form-settings', { render: FormSettings } );
} )(
	window.wp.components,
	window.wp.data,
	window.wp.editPost,
	window.wp.element,
	window.wp.i18n,
	window.wp.plugins,
	window.wp.blocks
);
