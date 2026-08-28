import { getContext, store } from '@wordpress/interactivity';

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
			resetErrors( form );
			form.querySelectorAll( '[data-fforms-error]' ).forEach(
				( node ) => {
					node.textContent = '';
				}
			);
			try {
				const response = await fetch( context.endpoint, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( {
						form_id: context.formId,
						fields: fieldPayload( form ),
						website: new FormData( form ).get( 'website' ) || '',
						source: window.location.href,
					} ),
				} );
				const body = await response.json().catch( () => ( {} ) );
				if ( ! response.ok ) {
					throw body;
				}
				form.reset();
				context.message = body.message || 'Спасибо! Форма отправлена.';
			} catch ( error ) {
				context.isError = true;
				context.message =
					error?.message ||
					'Не удалось отправить форму. Попробуйте ещё раз.';
				showFieldErrors( form, error?.data?.fields );
			} finally {
				context.isSubmitting = false;
			}
		},
	},
} );
