const { expect, test } = require( '@wordpress/e2e-test-utils-playwright' );

const { login } = require( './support/login' );

test.describe( 'form creation mode', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	test( 'creates a Block editor form and converts it to Headless API', async ( {
		editor,
		page,
		request,
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

		const formId = await editor.publishPost();
		expect( formId ).not.toBeNull();
		const schemaResponse = await request.get(
			`/wp-json/fforms/v1/forms/${ formId }/schema`
		);
		expect( schemaResponse.ok() ).toBeTruthy();
		expect(
			( await schemaResponse.json() ).fields.map(
				( field ) => field.name
			)
		).toEqual( [ 'name', 'email', 'message' ] );

		await page.getByLabel( 'Режим формы' ).selectOption( 'block' );
		await expect(
			editor.canvas.locator( '.wp-block-fforms-form' )
		).toBeVisible();
	} );
} );
