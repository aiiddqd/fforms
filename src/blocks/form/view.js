import { getContext, store } from '@wordpress/interactivity';
import './view.scss';

const fieldPayload = ( form ) => {
	const fields = {};
	new FormData( form ).forEach( ( value, name ) => {
		const match = name.match( /^fields\[([^\]]+)\](\[\])?$/ );
		if ( ! match ) {
			return;
		}
		if ( match[ 2 ] ) {
			fields[ match[ 1 ] ] = [ ...( fields[ match[ 1 ] ] || [] ), value ];
		} else {
			fields[ match[ 1 ] ] = value;
		}
	} );
	return fields;
};

const honeypotPayload = ( form ) => {
	const data = {};
	const values = new FormData( form );
	form.querySelectorAll( '[data-fforms-honeypot]' ).forEach( ( input ) => {
		data[ input.name ] = values.get( input.name ) || '';
	} );
	return data;
};

const resetErrors = ( form ) =>
	form
		.querySelectorAll( '[aria-invalid="true"]' )
		.forEach( ( input ) => input.removeAttribute( 'aria-invalid' ) );
const escapeSelector = ( value ) =>
	window.CSS?.escape
		? window.CSS.escape( value )
		: value.replace( /[^a-zA-Z0-9_-]/g, '\\$&' );
const showFieldErrors = ( form, fields ) => {
	Object.entries( fields || {} ).forEach( ( [ name, message ] ) => {
		const input = form.querySelector(
			`[data-fforms-field="${ escapeSelector( name ) }"]`
		);
		const target = form.querySelector(
			`[data-fforms-error="${ escapeSelector( name ) }"]`
		);
		if ( input ) {
			input.setAttribute( 'aria-invalid', 'true' );
		}
		if ( target ) {
			target.textContent = message;
		}
	} );
	form.querySelector( '[aria-invalid="true"]' )?.focus();
};

store( 'fforms/form', {
	actions: {
		async submit( event ) {
			event.preventDefault();
			const form = event.currentTarget;
			const context = getContext();
			context.isSubmitting = true;
			context.isError = false;
			context.message = '';
			let fieldValidationError = false;
			let receivedResponse = false;
			resetErrors( form );
			form.querySelectorAll( '[data-fforms-error]' ).forEach(
				( node ) => {
					node.textContent = '';
				}
			);
			try {
				const captchaSlot = form.querySelector(
					'[data-fforms-captcha]'
				);
				let captchaToken = '';
				if ( captchaSlot && window.fformsCaptchaProvider ) {
					captchaToken = await window.fformsCaptchaProvider
						.getToken( form )
						.catch( () => '' );
				}
				const response = await fetch( context.endpoint, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( {
						form_id: context.formId,
						fields: fieldPayload( form ),
						...honeypotPayload( form ),
						captcha_token: captchaToken,
						source: window.location.href,
					} ),
				} );
				receivedResponse = true;
				const body = await response.json().catch( () => ( {} ) );
				if ( ! response.ok ) {
					throw body;
				}
				form.reset();
				context.message =
					body.message || 'Thank you! The form has been sent.';
			} catch ( error ) {
				fieldValidationError =
					'fforms_validation_failed' === error?.code &&
					!! error?.data?.fields;
				context.isError = true;
				context.message =
					error?.message ||
					'Could not submit the form. Please try again.';
				showFieldErrors( form, error?.data?.fields );
				if ( /^fforms_captcha_/.test( error?.code || '' ) ) {
					const slot = form.querySelector( '[data-fforms-captcha]' );
					if ( slot ) {
						slot.setAttribute( 'tabindex', '-1' );
						slot.focus();
					}
				}
			} finally {
				if ( receivedResponse && ! fieldValidationError ) {
					window.fformsCaptchaProvider?.reset( form );
				}
				context.isSubmitting = false;
			}
		},
	},
} );
