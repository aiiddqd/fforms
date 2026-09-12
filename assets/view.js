( function () {
	'use strict';
	function payloadFields( form ) {
		var fields = {};
		new FormData( form ).forEach( function ( value, name ) {
			var match = name.match( /^fields\[([^\]]+)\](\[\])?$/ );
			if ( ! match ) { return; }
			if ( match[ 2 ] ) {
				fields[ match[ 1 ] ] = fields[ match[ 1 ] ] || [];
				fields[ match[ 1 ] ].push( value );
			} else {
				fields[ match[ 1 ] ] = value;
			}
		} );
		return fields;
	}
	function showMessage( form, text, isError ) {
		var target = form.querySelector( '.fforms-response' );
		target.textContent = text || '';
		target.classList.toggle( 'is-error', Boolean( isError ) );
	}
	document.addEventListener( 'submit', function ( event ) {
		var form = event.target.closest( '.fforms-form' );
		if ( ! form ) { return; }
		event.preventDefault();
		var button = form.querySelector( '.fforms-submit' );
		button.disabled = true;
		form.setAttribute( 'aria-busy', 'true' );
		showMessage( form, '', false );
		fetch( form.dataset.endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify( { form_id: Number( form.dataset.formId ), fields: payloadFields( form ), website: new FormData( form ).get( 'website' ) || '', source: window.location.href } ) } )
			.then( function ( response ) { return response.json().catch( function () { return {}; } ).then( function ( body ) { if ( ! response.ok ) { throw body; } return body; } ); } )
			.then( function ( body ) { form.reset(); showMessage( form, body.message || 'Спасибо! Форма отправлена.', false ); } )
			.catch( function ( error ) { showMessage( form, error && error.message ? error.message : 'Не удалось отправить форму. Попробуйте ещё раз.', true ); } )
			.finally( function () { button.disabled = false; form.removeAttribute( 'aria-busy' ); } );
	} );
} )();
