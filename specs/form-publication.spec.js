const { expect, test } = require( '@wordpress/e2e-test-utils-playwright' );

const { login } = require( './support/login' );

test.describe( 'form publication', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	test( 'offers the share link toggle instead of a form mode', async ( {
		editor,
		page,
	} ) => {
		await page.goto( '/wp-admin/post-new.php?post_type=fform' );
		await expect(
			editor.canvas.locator( '.wp-block-fforms-form' )
		).toBeVisible();

		// A form has one shape now: the builder. Publication is a property of
		// how it is shared, not of the form itself.
		await expect( page.getByLabel( 'Form mode' ) ).toHaveCount( 0 );
		await expect( page.getByLabel( 'Share via link' ) ).toBeVisible();
		await expect(
			page.getByText( 'Publish the form to get its shortcode and link.' )
		).toBeVisible();
	} );
} );
