const { expect, test } = require( '@wordpress/e2e-test-utils-playwright' );

const { login } = require( './support/login' );

test.describe( 'form creation mode', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	test( 'shows a mode selector before making a form draft', async ( { page } ) => {
		await page.goto( '/wp-admin/post-new.php?post_type=fform' );

		await expect(
			page.getByRole( 'heading', { name: 'Какую форму создать?' } )
		).toBeVisible();
		await expect(
			page.getByRole( 'link', { name: /^Block editor/ } )
		).toHaveAttribute( 'href', /fforms_mode=block/ );
		await expect(
			page.getByRole( 'link', { name: /^Headless API/ } )
		).toHaveAttribute( 'href', /fforms_mode=headless/ );
	} );

	test( 'headless mode opens an empty locked editor', async ( { editor, page } ) => {
		await page.goto( '/wp-admin/post-new.php?post_type=fform' );
		await page
			.getByRole( 'link', { name: /^Headless API/ } )
			.click();

		await expect( page ).toHaveURL( /post\.php\?post=\d+&action=edit/ );
		await expect( editor.canvas.locator( '.wp-block-fforms-form' ) ).toHaveCount(
			0
		);
	} );
} );
