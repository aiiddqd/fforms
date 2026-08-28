( function ( components, data, editPost, element, i18n, plugins ) {
	'use strict';

	var el = element.createElement;
	var useEffect = element.useEffect;
	var __ = i18n.__;
	var FORM_SETTINGS_PANEL_NAME = 'fforms-form-settings/form-settings';
	var PANEL_DEFAULTED_KEY = 'fforms-form-settings-panel-defaulted-v2';
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var ToggleControl = components.ToggleControl;
	var PluginDocumentSettingPanel = editPost.PluginDocumentSettingPanel;
	var META = {
		type: '_fforms_type',
		notificationTo: '_fforms_notification_to',
		notificationSubject: '_fforms_notification_subject',
		successMessage: '_fforms_success_message',
		publicForm: '_fforms_public',
		autoreplyEnabled: '_fforms_autoreply_enabled',
		autoreplyEmailField: '_fforms_autoreply_email_field',
		autoreplySubject: '_fforms_autoreply_subject',
		autoreplyMessage: '_fforms_autoreply_message'
	};

	function FormSettings() {
		var editor = data.useSelect( function ( select ) {
			var store = select( 'core/editor' );
			return {
				id: store.getCurrentPostId(),
				meta: store.getEditedPostAttribute( 'meta' ) || {},
				status: store.getEditedPostAttribute( 'status' ),
				isPanelOpened: store.isEditorPanelOpened( FORM_SETTINGS_PANEL_NAME )
			};
		}, [] );
		var togglePanelOpened = data.useDispatch( 'core/editor' ).toggleEditorPanelOpened;
		useEffect( function () {
			if ( editor.isPanelOpened || window.localStorage.getItem( PANEL_DEFAULTED_KEY ) ) {
				return;
			}
			window.localStorage.setItem( PANEL_DEFAULTED_KEY, '1' );
			togglePanelOpened( FORM_SETTINGS_PANEL_NAME );
		}, [] );
		var editPostMeta = data.useDispatch( 'core/editor' ).editPost;
		var meta = editor.meta;
		var updateMeta = function ( key, value ) {
			editPostMeta( { meta: Object.assign( {}, meta, ( function () {
				var next = {};
				next[ key ] = value;
				return next;
			} )() ) } );
		};
		var publicUrl = window.fformsFormSettings && window.fformsFormSettings.publicFormUrl
			? window.fformsFormSettings.publicFormUrl.replace( /0\/?$/, String( editor.id ) + '/' )
			: '';

		return el(
			element.Fragment,
			null,
			el(
				PluginDocumentSettingPanel,
				{
					name: 'form-settings',
					title: __( 'Настройки формы', 'fforms' ),
					className: 'fforms-form-settings'
				},
				el( SelectControl, {
					label: __( 'Тип формы', 'fforms' ),
					value: meta[ META.type ] || 'contact',
					options: [
						{ label: __( 'Контактная', 'fforms' ), value: 'contact' },
						{ label: __( 'Лид', 'fforms' ), value: 'lead' }
					],
					onChange: function ( value ) { updateMeta( META.type, value ); }
				} ),
				el( TextControl, {
					label: __( 'Получатели', 'fforms' ),
					value: meta[ META.notificationTo ] || '',
					help: __( 'Email через запятую; если пусто — email администратора.', 'fforms' ),
					onChange: function ( value ) { updateMeta( META.notificationTo, value ); }
				} ),
				el( TextControl, {
					label: __( 'Тема уведомления', 'fforms' ),
					value: meta[ META.notificationSubject ] || '',
					onChange: function ( value ) { updateMeta( META.notificationSubject, value ); }
				} ),
				el( TextControl, {
					label: __( 'Сообщение об успехе', 'fforms' ),
					value: meta[ META.successMessage ] || '',
					placeholder: __( 'Спасибо! Форма отправлена.', 'fforms' ),
					onChange: function ( value ) { updateMeta( META.successMessage, value ); }
				} )
			),
			el(
				PluginDocumentSettingPanel,
				{ name: 'public-form', title: __( 'Публичная форма', 'fforms' ) },
				el( ToggleControl, {
					label: __( 'Открыть форму по публичной ссылке', 'fforms' ),
					checked: Boolean( meta[ META.publicForm ] ),
					help: __( 'Любой, у кого есть ссылка, сможет заполнить и отправить форму.', 'fforms' ),
					onChange: function ( value ) { updateMeta( META.publicForm, value ); }
				} ),
				meta[ META.publicForm ] && 'publish' === editor.status && publicUrl
					? el( 'p', null, el( 'a', { href: publicUrl, target: '_blank', rel: 'noopener noreferrer' }, __( 'Открыть публичную форму', 'fforms' ) ) )
					: null,
				meta[ META.publicForm ] && 'publish' !== editor.status
					? el( 'p', { className: 'components-base-control__help' }, __( 'Ссылка станет доступна после публикации формы.', 'fforms' ) )
					: null
			),
			el(
				PluginDocumentSettingPanel,
				{ name: 'autoreply', title: __( 'Автоответ', 'fforms' ) },
				el( ToggleControl, {
					label: __( 'Отправлять автоответ пользователю', 'fforms' ),
					checked: Boolean( meta[ META.autoreplyEnabled ] ),
					onChange: function ( value ) { updateMeta( META.autoreplyEnabled, value ); }
				} ),
				meta[ META.autoreplyEnabled ]
					? el( element.Fragment, null,
						el( TextControl, {
							label: __( 'Имя email-поля', 'fforms' ),
							value: meta[ META.autoreplyEmailField ] || 'email',
							onChange: function ( value ) { updateMeta( META.autoreplyEmailField, value ); }
						} ),
						el( TextControl, {
							label: __( 'Тема автоответа', 'fforms' ),
							value: meta[ META.autoreplySubject ] || '',
							onChange: function ( value ) { updateMeta( META.autoreplySubject, value ); }
						} ),
						el( TextareaControl, {
							label: __( 'Текст автоответа', 'fforms' ),
							value: meta[ META.autoreplyMessage ] || '',
							onChange: function ( value ) { updateMeta( META.autoreplyMessage, value ); }
						} )
					)
					: null
			)
		);
	}

	plugins.registerPlugin( 'fforms-form-settings', { render: FormSettings } );
} )( window.wp.components, window.wp.data, window.wp.editPost, window.wp.element, window.wp.i18n, window.wp.plugins );
