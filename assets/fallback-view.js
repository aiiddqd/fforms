( function () {
	document.addEventListener( 'submit', function ( event ) {
		const form = event.target.closest( '.fforms-form' );
		if ( ! form ) {
			return;
		}
		event.preventDefault();
		if ( form.dataset.isSubmitting ) {
			return;
		}
		form.dataset.isSubmitting = '1';
		const submit = form.querySelector( '[type="submit"]' );
		if ( submit ) {
			submit.disabled = true;
		}
		const context = JSON.parse(
			form.getAttribute( 'data-wp-context' ) || '{}'
		);
		const fields = {};
		new FormData( form ).forEach( function ( value, name ) {
			const match = name.match( /^fields\[([^\]]+)\](\[\])?$/ );
			if ( ! match ) {
				return;
			}
			if ( match[ 2 ] ) {
				fields[ match[ 1 ] ] = fields[ match[ 1 ] ] || [];
				fields[ match[ 1 ] ].push( value );
			} else {
				fields[ match[ 1 ] ] = value;
			}
		} );
		let receivedResponse = false;
		let fieldValidationError = false;
		const captchaSlot = form.querySelector( '[data-fforms-captcha]' );
		Promise.resolve(
			captchaSlot && window.fformsCaptchaProvider
				? window.fformsCaptchaProvider.getToken( form )
				: ''
		)
			.catch( function () {
				return '';
			} )
			.then( function ( captchaToken ) {
				return fetch( context.endpoint, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					credentials: 'same-origin',
					body: JSON.stringify( {
						form_id: context.formId,
						fields,
						website: new FormData( form ).get( 'website' ) || '',
						captcha_token: captchaToken,
						source: window.location.href,
					} ),
				} );
			} )
			.then( function ( response ) {
				receivedResponse = true;
				return response.json().then( function ( body ) {
					if ( ! response.ok ) {
						throw body;
					}
					return body;
				} );
			} )
			.then( function ( body ) {
				form.reset();
				form.querySelector( '.fforms-response' ).textContent =
					body.message || 'Thank you! The form has been sent.';
			} )
			.catch( function ( error ) {
				fieldValidationError =
					'fforms_validation_failed' === error.code &&
					!! error.data?.fields;
				form.querySelector( '.fforms-response' ).textContent =
					error.message || 'Could not submit the form.';
				if (
					/^fforms_captcha_/.test( error.code || '' ) &&
					captchaSlot
				) {
					captchaSlot.setAttribute( 'tabindex', '-1' );
					captchaSlot.focus();
				}
			} )
			.finally( function () {
				if ( receivedResponse && ! fieldValidationError ) {
					window.fformsCaptchaProvider?.reset( form );
				}
				delete form.dataset.isSubmitting;
				if ( submit ) {
					submit.disabled = false;
				}
			} );
	} );
} )();
