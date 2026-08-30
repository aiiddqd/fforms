const { expect, test } = require( '@wordpress/e2e-test-utils-playwright' );

const { login } = require( './support/login' );

const HEADLESS_SCHEMA_CONTENT = [
	'<!-- wp:fforms/headless-schema -->',
	'<!-- wp:fforms/field-text {"fieldId":"name","name":"name","label":"Name","required":true} /-->',
	'<!-- wp:fforms/field-email {"fieldId":"email","name":"email","label":"Email","required":true} /-->',
	'<!-- /wp:fforms/headless-schema -->',
].join( '\n' );

test.describe( 'form creation mode', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	test( 'creates a Block editor form and converts it to Headless API', async ( {
		editor,
		page,
	} ) => {
		await page.goto( '/wp-admin/post-new.php?post_type=fform' );
		await expect(
			editor.canvas.locator( '.wp-block-fforms-form' )
		).toBeVisible();

		await page.getByLabel( 'Режим формы' ).selectOption( 'headless' );
		await expect(
			editor.canvas.locator( '.wp-block-fforms-form' )
		).toHaveCount( 0 );
		await expect(
			editor.canvas.locator( '.wp-block-fforms-headless-schema' )
		).toBeVisible();
		await expect(
			editor.canvas.locator( '.wp-block-fforms-submit' )
		).toHaveCount( 0 );

		await page.getByLabel( 'Режим формы' ).selectOption( 'block' );
		await expect(
			editor.canvas.locator( '.wp-block-fforms-form' )
		).toBeVisible();
	} );
} );

test( 'publishes a Headless API form with protected form meta', async ( {
	request,
	requestUtils,
} ) => {
	const headers = { 'X-WP-Nonce': await requestUtils.login() };
	const title = `headless-meta-${ Date.now() }`;
	const created = await requestUtils.request.post( '/wp-json/wp/v2/fforms', {
		headers,
		data: { status: 'draft', title },
	} );
	expect( created.status() ).toBe( 201 );
	const form = await created.json();

	const published = await requestUtils.request.post(
		`/wp-json/wp/v2/fforms/${ form.id }`,
		{
			headers,
			data: {
				content: HEADLESS_SCHEMA_CONTENT,
				meta: {
					_fforms_type: 'contact',
					_fforms_mode: 'headless',
					_fforms_schema: JSON.stringify( {
						fields: [
							{
								name: 'name',
								label: 'Name',
								type: 'text',
								required: true,
							},
						],
					} ),
				},
				status: 'publish',
			},
		}
	);
	expect( published.status() ).toBe( 200 );
	const publishedForm = await published.json();
	expect( publishedForm.meta._fforms_type ).toBe( 'contact' );
	expect( publishedForm.meta._fforms_mode ).toBe( 'headless' );

	const publicForm = await request.get(
		`/wp-json/fforms/v1/forms/${ form.id }`
	);
	expect( publicForm.ok() ).toBeTruthy();
	expect( ( await publicForm.json() ).mode ).toBe( 'headless' );
} );
