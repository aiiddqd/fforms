( function ( components, data, editPost, element, i18n, plugins ) {
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
	const META = {
		type: '_fforms_type',
		notificationTo: '_fforms_notification_to',
		notificationSubject: '_fforms_notification_subject',
		successMessage: '_fforms_success_message',
		publicForm: '_fforms_public',
		autoreplyEnabled: '_fforms_autoreply_enabled',
		autoreplyEmailField: '_fforms_autoreply_email_field',
		autoreplySubject: '_fforms_autoreply_subject',
		autoreplyMessage: '_fforms_autoreply_message',
	};

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
				el( TextControl, {
					label: __( 'Получатели', 'fforms' ),
					value: meta[ META.notificationTo ] || '',
					help: __(
						'Email через запятую; если пусто — email администратора.',
						'fforms'
					),
					onChange( value ) {
						updateMeta( META.notificationTo, value );
					},
				} ),
				el( TextControl, {
					label: __( 'Тема уведомления', 'fforms' ),
					value: meta[ META.notificationSubject ] || '',
					onChange( value ) {
						updateMeta( META.notificationSubject, value );
					},
				} ),
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
			el(
				PluginDocumentSettingPanel,
				{ name: 'autoreply', title: __( 'Автоответ', 'fforms' ) },
				el( ToggleControl, {
					label: __( 'Отправлять автоответ пользователю', 'fforms' ),
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
									meta[ META.autoreplyEmailField ] || 'email',
								onChange( value ) {
									updateMeta(
										META.autoreplyEmailField,
										value
									);
								},
							} ),
							el( TextControl, {
								label: __( 'Тема автоответа', 'fforms' ),
								value: meta[ META.autoreplySubject ] || '',
								onChange( value ) {
									updateMeta( META.autoreplySubject, value );
								},
							} ),
							el( TextareaControl, {
								label: __( 'Текст автоответа', 'fforms' ),
								value: meta[ META.autoreplyMessage ] || '',
								onChange( value ) {
									updateMeta( META.autoreplyMessage, value );
								},
							} )
					  )
					: null
			)
		);
	}

	plugins.registerPlugin( 'fforms-form-settings', { render: FormSettings } );
} )(
	window.wp.components,
	window.wp.data,
	window.wp.editPost,
	window.wp.element,
	window.wp.i18n,
	window.wp.plugins
);
