( function () {
	'use strict';

	const POLL_INTERVAL = 50;
	const PROVIDER_LOAD_TIMEOUT = 5000;
	const TOKEN_TIMEOUT = 120000;
	const WIDGET_ID_KEY = 'fformsCaptchaWidgetId';

	const PROVIDERS = {
		recaptcha: { global: 'grecaptcha' },
		turnstile: { global: 'turnstile' },
		smartcaptcha: { global: 'smartCaptcha' },
	};

	function getConfig() {
		const config = window.fformsCaptchaConfig;
		if ( ! config || ! PROVIDERS[ config.provider ] ) {
			throw new Error(
				'FForms captcha: missing or unknown provider config.'
			);
		}
		return config;
	}

	function findContainer( formElement ) {
		if ( ! formElement || ! formElement.querySelector ) {
			return null;
		}
		if (
			formElement.matches &&
			formElement.matches( '.fforms-captcha-widget' )
		) {
			return formElement;
		}
		return formElement.querySelector( '.fforms-captcha-widget' );
	}

	function resolveSitekey( config, container ) {
		return config.sitekey || container.dataset.sitekey || '';
	}

	function poll( check, timeout ) {
		return new Promise( function ( resolve, reject ) {
			const started = Date.now();
			const timer = setInterval( function () {
				let result;
				try {
					result = check();
				} catch ( error ) {
					clearInterval( timer );
					reject( error );
					return;
				}
				if ( result ) {
					clearInterval( timer );
					resolve( result );
					return;
				}
				if ( Date.now() - started >= timeout ) {
					clearInterval( timer );
					reject( new Error( 'FForms captcha: timed out.' ) );
				}
			}, POLL_INTERVAL );
		} );
	}

	function waitForGlobal( name, timeout ) {
		return poll( function () {
			return window[ name ];
		}, timeout );
	}

	function ensureWidget( api, container, config ) {
		if ( container[ WIDGET_ID_KEY ] !== undefined ) {
			return container[ WIDGET_ID_KEY ];
		}
		const widgetId = api.render( container, {
			sitekey: resolveSitekey( config, container ),
		} );
		container[ WIDGET_ID_KEY ] = widgetId;
		return widgetId;
	}

	function getToken( formElement ) {
		const config = getConfig();
		const provider = PROVIDERS[ config.provider ];
		const container = findContainer( formElement );
		if ( ! container ) {
			return Promise.reject(
				new Error( 'FForms captcha: no container in form.' )
			);
		}
		return waitForGlobal( provider.global, PROVIDER_LOAD_TIMEOUT ).then(
			function ( api ) {
				const widgetId = ensureWidget( api, container, config );
				return poll( function () {
					return api.getResponse( widgetId );
				}, TOKEN_TIMEOUT );
			}
		);
	}

	function reset( formElement ) {
		try {
			const config = getConfig();
			const provider = PROVIDERS[ config.provider ];
			const api = window[ provider.global ];
			const container = findContainer( formElement );
			if (
				! api ||
				! container ||
				container[ WIDGET_ID_KEY ] === undefined
			) {
				return;
			}
			api.reset( container[ WIDGET_ID_KEY ] );
		} catch {
			// Reset is best-effort: a missing config or global must not break submit.
		}
	}

	function maybeInit() {
		let config;
		try {
			config = getConfig();
		} catch {
			return;
		}
		const api = window[ PROVIDERS[ config.provider ].global ];
		if ( ! api ) {
			return;
		}
		document
			.querySelectorAll( '.fforms-captcha-widget' )
			.forEach( function ( container ) {
				try {
					ensureWidget( api, container, config );
				} catch {
					// Rendering stays lazy: getToken will retry on first use.
				}
			} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', maybeInit );
	} else {
		maybeInit();
	}

	window.fformsCaptchaProvider = { getToken, reset };
} )();
