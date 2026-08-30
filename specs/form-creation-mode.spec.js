const { expect, test } = require( '@wordpress/e2e-test-utils-playwright' );

const { login } = require( './support/login' );

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
		await page.getByRole( 'button', { name: 'Поля Headless API' } ).click();
		await expect( page.getByLabel( 'JSON-схема' ) ).toHaveValue( /email/ );

		await page.getByLabel( 'Режим формы' ).selectOption( 'block' );
		await expect(
			editor.canvas.locator( '.wp-block-fforms-form' )
		).toBeVisible();
	} );
} );
