( function ( apiFetch, components, data, editPost, element, i18n, plugins ) {
	'use strict';

	const el = element.createElement;
	const useEffect = element.useEffect;
	const useState = element.useState;
	const __ = i18n.__;
	const sprintf = i18n.sprintf;
	const PUBLICATION_PANEL_NAME = 'fforms-form-settings/publication';
	const PANEL_DEFAULTED_KEY = 'fforms-form-settings-panel-defaulted-v3';
	const Button = components.Button;
	const RadioControl = components.RadioControl;
	const TextControl = components.TextControl;
	const TextareaControl = components.TextareaControl;
	const ToggleControl = components.ToggleControl;
	const PluginDocumentSettingPanel = editPost.PluginDocumentSettingPanel;
	const settings = window.fformsFormSettings || {};
	const notificationSettingsEnabled = Boolean(
		settings.notificationSettingsEnabled
	);
	const META = {
		shareLink: '_fforms_share_link',
		shareLayout: '_fforms_share_layout',
		shareToken: '_fforms_share_token',
		schema: '_fforms_schema',
		notificationTo: '_fforms_notification_to',
		notificationSubject: '_fforms_notification_subject',
		notificationsEnabled: '_fforms_notifications_enabled',
		successMessage: '_fforms_success_message',
		autoreplyEnabled: '_fforms_autoreply_enabled',
		autoreplyEmailField: '_fforms_autoreply_email_field',
		autoreplySubject: '_fforms_autoreply_subject',
		autoreplyMessage: '_fforms_autoreply_message',
	};

	function shareUrl( token ) {
		if ( ! token || ! settings.shareUrlTemplate ) {
			return '';
		}
		return settings.shareUrlTemplate.replace( '{token}', token );
	}

	function embedUrl( token ) {
		const url = shareUrl( token );
		if ( ! url ) {
			return '';
		}
		return (
			url + ( url.indexOf( '?' ) === -1 ? '?' : '&' ) + 'fforms_embed=1'
		);
	}

	function iframeSnippet( token, title ) {
		const url = embedUrl( token );
		return url
			? '<iframe src="' +
					url +
					'" title="' +
					( title || 'Form' ) +
					'" style="width:100%;border:0" height="600" loading="lazy"></iframe>'
			: '';
	}

	function scriptSnippet( token, formId ) {
		const url = embedUrl( token );
		return url && settings.embedScriptUrl
			? '<script src="' +
					settings.embedScriptUrl +
					'" data-fforms-form="' +
					String( formId ) +
					'" data-fforms-origin="' +
					( settings.homeUrl || '' ) +
					'" data-fforms-src="' +
					url +
					'"></script>'
			: '';
	}

	function snippetField( label, value, help ) {
		return el( TextControl, {
			label,
			value,
			readOnly: true,
			onChange() {},
			help,
		} );
	}

	function helpText( text ) {
		return el( 'p', { className: 'components-base-control__help' }, text );
	}

	function FormSettings() {
		const editor = data.useSelect( function ( select ) {
			const store = select( 'core/editor' );
			return {
				id: store.getCurrentPostId(),
				title: store.getEditedPostAttribute( 'title' ) || '',
				meta: store.getEditedPostAttribute( 'meta' ) || {},
				status: store.getEditedPostAttribute( 'status' ),
				isPanelOpened: store.isEditorPanelOpened(
					PUBLICATION_PANEL_NAME
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
				togglePanelOpened( PUBLICATION_PANEL_NAME );
			},
			[ editor.isPanelOpened, togglePanelOpened ]
		);
		const editPostMeta = data.useDispatch( 'core/editor' ).editPost;
		const meta = editor.meta;
		const updateMeta = function ( key, value ) {
			const next = {};
			next[ key ] = value;
			editPostMeta( { meta: Object.assign( {}, meta, next ) } );
		};

		// A reissued token is already stored server-side; keep it locally so the
		// panel shows the new link without waiting for the next save.
		const [ reissuedToken, setReissuedToken ] = useState( '' );
		const [ isReissuing, setIsReissuing ] = useState( false );
		const token = reissuedToken || meta[ META.shareToken ] || '';
		const isPublished = 'publish' === editor.status;
		const shareEnabled = Boolean( meta[ META.shareLink ] );
		const url = shareUrl( token );

		const reissue = function () {
			setIsReissuing( true );
			apiFetch( {
				path:
					'/fforms/v1/forms/' + String( editor.id ) + '/share-token',
				method: 'POST',
			} )
				.then( function ( response ) {
					setReissuedToken( response.token || '' );
				} )
				.finally( function () {
					setIsReissuing( false );
				} );
		};

		return el(
			element.Fragment,
			null,
			el(
				PluginDocumentSettingPanel,
				{
					name: 'overview',
					title: __( 'Overview', 'fforms' ),
					className: 'fforms-form-overview',
				},
				// The form is its own type: its submissions are the entries
				// carrying this form's type term.
				el(
					'p',
					null,
					el(
						'a',
						{ href: settings.entriesUrl || '' },
						__( 'View submissions', 'fforms' )
					)
				),
				helpText(
					sprintf(
						/* translators: %d: number of stored submissions. */
						__( 'Stored submissions: %d', 'fforms' ),
						settings.entriesCount || 0
					)
				)
			),
			el(
				PluginDocumentSettingPanel,
				{
					name: 'publication',
					title: __( 'Publication', 'fforms' ),
					className: 'fforms-form-publication',
				},
				isPublished && editor.id
					? snippetField(
							__( 'Shortcode', 'fforms' ),
							'[fform id=' + String( editor.id ) + ']',
							__(
								'Insert it into any page or widget of this site. The block “FForms Form” inserts the same form.',
								'fforms'
							)
					  )
					: helpText(
							__(
								'Publish the form to get its shortcode.',
								'fforms'
							)
					  ),
				shareEnabled && isPublished && url
					? el(
							element.Fragment,
							null,
							snippetField(
								__( 'Iframe', 'fforms' ),
								iframeSnippet( token, editor.title ),
								__(
									'Embed on an external site with a fixed height.',
									'fforms'
								)
							),
							snippetField(
								__( 'Js-script', 'fforms' ),
								scriptSnippet( token, editor.id ),
								__(
									'Embed on an external site: the script inserts the iframe and adjusts its height.',
									'fforms'
								)
							),
							el(
								Button,
								{
									variant: 'secondary',
									isDestructive: true,
									isBusy: isReissuing,
									disabled: isReissuing,
									onClick: reissue,
								},
								__( 'Reissue link', 'fforms' )
							),
							helpText(
								__(
									'The current link stops working immediately.',
									'fforms'
								)
							)
					  )
					: null
			),
			el(
				PluginDocumentSettingPanel,
				{
					name: 'share-link',
					title: __( 'Share via link', 'fforms' ),
					className: 'fforms-form-share-link',
				},
				el( ToggleControl, {
					label: __( 'Share via link', 'fforms' ),
					checked: shareEnabled,
					help: __(
						'Anyone with the link can open and submit the form. The page is not indexed by search engines.',
						'fforms'
					),
					onChange( value ) {
						updateMeta( META.shareLink, value );
					},
				} ),
				shareEnabled && ! isPublished
					? helpText(
							__(
								'The link becomes available once the form is published.',
								'fforms'
							)
					  )
					: null,
				shareEnabled && isPublished && url
					? el(
							element.Fragment,
							null,
							snippetField(
								__( 'Link', 'fforms' ),
								url,
								__(
									'A secret address: it cannot be guessed from the form id.',
									'fforms'
								)
							),
							el(
								'p',
								null,
								el(
									'a',
									{
										href: url,
										target: '_blank',
										rel: 'noopener noreferrer',
									},
									__( 'Open the form', 'fforms' )
								)
							)
					  )
					: null,
				// The layout is the link's own business: a frame on someone else's
				// page never wants this site's header, so the snippets ignore it.
				shareEnabled
					? el( RadioControl, {
							label: __( 'Page layout', 'fforms' ),
							selected: meta[ META.shareLayout ] || 'site',
							options: [
								{
									label: __( 'With site header', 'fforms' ),
									value: 'site',
								},
								{
									label: __( 'Form only', 'fforms' ),
									value: 'standalone',
								},
							],
							help: __(
								'Applies to the link only. The iframe and js-script always embed the form on its own.',
								'fforms'
							),
							onChange( value ) {
								updateMeta( META.shareLayout, value );
							},
					  } )
					: null
			),
			el(
				PluginDocumentSettingPanel,
				{
					name: 'form-settings',
					title: __( 'Form settings', 'fforms' ),
					className: 'fforms-form-settings',
				},
				notificationSettingsEnabled
					? el(
							element.Fragment,
							null,
							el( ToggleControl, {
								label: __(
									'Send notifications about submissions',
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
											label: __( 'Recipients', 'fforms' ),
											value:
												meta[ META.notificationTo ] ||
												'',
											help: __(
												'Comma-separated emails; the administrator email when empty.',
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
												'Notification subject',
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
					label: __( 'Success message', 'fforms' ),
					value: meta[ META.successMessage ] || '',
					placeholder: __(
						'Thank you! The form has been sent.',
						'fforms'
					),
					onChange( value ) {
						updateMeta( META.successMessage, value );
					},
				} )
			),
			notificationSettingsEnabled
				? el(
						PluginDocumentSettingPanel,
						{
							name: 'autoreply',
							title: __( 'Auto-reply', 'fforms' ),
						},
						el( ToggleControl, {
							label: __(
								'Send an auto-reply to the user',
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
										label: __(
											'Email field name',
											'fforms'
										),
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
											'Auto-reply subject',
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
											'Auto-reply body',
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
	window.wp.apiFetch,
	window.wp.components,
	window.wp.data,
	window.wp.editPost,
	window.wp.element,
	window.wp.i18n,
	window.wp.plugins
);
