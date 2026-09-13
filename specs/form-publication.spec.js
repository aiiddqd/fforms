const { expect, test } = require( '@wordpress/e2e-test-utils-playwright' );

const { login } = require( './support/login' );

test.describe( 'form publication', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	test( 'keeps the share link in a section of its own', async ( {
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

		// "Publication" is about putting the form on this site; the link and
		// everything that configures it live next door.
		await expect(
			page.getByText( 'Publish the form to get its shortcode.' )
		).toBeVisible();
		await expect(
			page
				.locator( '.fforms-form-publication' )
				.getByLabel( 'Share via link' )
		).toHaveCount( 0 );

		// The editor remembers which panels a user left open, so the panel may
		// already be expanded from an earlier run: clicking it blindly would
		// close it.
		const sharePanel = page.getByRole( 'button', {
			name: 'Share via link',
		} );
		if ( 'true' !== ( await sharePanel.getAttribute( 'aria-expanded' ) ) ) {
			await sharePanel.click();
		}
		const shareLink = page.getByLabel( 'Share via link' );
		await expect( shareLink ).toBeVisible();

		// The layout only makes sense once the link exists.
		await expect( page.getByLabel( 'With site header' ) ).toHaveCount( 0 );
		await shareLink.click();
		await expect( page.getByLabel( 'With site header' ) ).toBeChecked();
		await expect( page.getByLabel( 'Form only' ) ).not.toBeChecked();
		await expect(
			page.getByText(
				'The link becomes available once the form is published.'
			)
		).toBeVisible();
	} );
} );
