const { expect, test } = require( '@wordpress/e2e-test-utils-playwright' );

const FORM_KEY = 'e2e_contact';
const ALLOWED_ORIGIN = 'https://e2e.example.com';

/**
 * Logs requestUtils' shared request context into wp-admin and returns a
 * REST nonce, so an authenticated GET can be made without going through
 * requestUtils.rest() (which throws on non-2xx instead of returning it).
 */
const authHeaders = async ( requestUtils ) => ( { 'X-WP-Nonce': await requestUtils.login() } );

test.describe( 'headless code-form REST contract', () => {
	test( 'schema is reachable by key', async ( { request } ) => {
		const response = await request.get( `/wp-json/fforms/v1/forms/${ FORM_KEY }` );
		expect( response.ok() ).toBeTruthy();

		const body = await response.json();
		expect( body.key ).toBe( FORM_KEY );
		expect( body.source ).toBe( 'code' );
		expect( body.schema.fields.map( ( field ) => field.name ) ).toEqual( [ 'email', 'message' ] );
	} );

	test( 'submit without form_id or form_key is rejected', async ( { request } ) => {
		const response = await request.post( '/wp-json/fforms/v1/submit', {
			data: { fields: { email: 'a@b.com' } },
		} );
		expect( response.status() ).toBe( 400 );
	} );

	test( 'submit with an unknown form_key is a 404', async ( { request } ) => {
		const response = await request.post( '/wp-json/fforms/v1/submit', {
			data: { form_key: 'does_not_exist', fields: { email: 'a@b.com' } },
		} );
		expect( response.status() ).toBe( 404 );
	} );

	test( 'submit with a filled honeypot pretends success without saving', async ( { request, requestUtils } ) => {
		const message = `honeypot-${ Date.now() }`;
		const response = await request.post( '/wp-json/fforms/v1/submit', {
			data: { form_key: FORM_KEY, fields: { email: 'a@b.com', message }, website: 'http://spam.example' },
		} );
		expect( response.status() ).toBe( 200 );

		const headers = await authHeaders( requestUtils );
		const entries = await ( await requestUtils.request.get(
			`/wp-json/fforms/v1/entries?form_key=${ FORM_KEY }`,
			{ headers }
		) ).json();
		expect( entries.some( ( entry ) => entry.data.message === message ) ).toBe( false );
	} );

	test( 'a valid submit creates an entry visible through /entries?form_key', async ( { request, requestUtils } ) => {
		const message = `e2e-${ Date.now() }`;
		const submit = await request.post( '/wp-json/fforms/v1/submit', {
			data: { form_key: FORM_KEY, fields: { email: 'a@b.com', message } },
		} );
		expect( submit.status() ).toBe( 201 );
		const submitBody = await submit.json();
		expect( submitBody.success ).toBe( true );
		expect( submitBody.entry_id ).toBeGreaterThan( 0 );

		const headers = await authHeaders( requestUtils );
		const entriesResponse = await requestUtils.request.get( `/wp-json/fforms/v1/entries?form_key=${ FORM_KEY }`, { headers } );
		expect( entriesResponse.ok() ).toBeTruthy();
		const entries = await entriesResponse.json();
		const created = entries.find( ( entry ) => entry.id === submitBody.entry_id );
		expect( created ).toBeTruthy();
		expect( created.form_id ).toBe( 0 );
		expect( created.form_key ).toBe( FORM_KEY );
		expect( created.data.message ).toBe( message );
	} );

	test( 'CORS reflects the allowed origin and rejects a foreign one', async ( { request } ) => {
		const allowed = await request.post( '/wp-json/fforms/v1/submit', {
			data: { form_key: FORM_KEY, fields: { email: 'a@b.com', message: 'cors-allowed' } },
			headers: { Origin: ALLOWED_ORIGIN },
		} );
		expect( allowed.headers()[ 'access-control-allow-origin' ] ).toBe( ALLOWED_ORIGIN );
		expect( allowed.headers()[ 'access-control-allow-credentials' ] ).toBeUndefined();

		const foreign = await request.post( '/wp-json/fforms/v1/submit', {
			data: { form_key: FORM_KEY, fields: { email: 'a@b.com', message: 'cors-foreign' } },
			headers: { Origin: 'https://not-allowed.example' },
		} );
		expect( foreign.headers()[ 'access-control-allow-origin' ] ).toBeUndefined();
	} );

	test( 'preflight OPTIONS answers 204 with the expected headers', async ( { request } ) => {
		const response = await request.fetch( '/wp-json/fforms/v1/submit', {
			method: 'OPTIONS',
			headers: { Origin: ALLOWED_ORIGIN, 'Access-Control-Request-Method': 'POST' },
		} );
		expect( response.status() ).toBe( 204 );
		expect( response.headers()[ 'access-control-allow-origin' ] ).toBe( ALLOWED_ORIGIN );
		expect( response.headers()[ 'access-control-allow-methods' ] ).toBe( 'GET, POST, OPTIONS' );
		expect( response.headers()[ 'access-control-allow-headers' ] ).toBe( 'Content-Type' );
	} );
} );
