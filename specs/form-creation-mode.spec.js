const { expect, test } = require( '@wordpress/e2e-test-utils-playwright' );

const { login } = require( './support/login' );

test.describe( 'form creation mode', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	test( 'creates and configures a Headless API form through wp-admin', async ( {
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
