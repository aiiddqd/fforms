const { expect, test } = require( '@wordpress/e2e-test-utils-playwright' );

const { login } = require( './support/login' );

test( 'new form editor loads styles and stacks field controls', async ( {
	editor,
	page,
} ) => {
	await login( page );
	await page.goto( '/wp-admin/post-new.php?post_type=fform' );
	await page.getByRole( 'link', { name: /^Block editor/ } ).click();

	const form = editor.canvas.locator( '.wp-block-fforms-form' );
	await expect( form ).toBeVisible();

	const fields = form.locator( '.fforms-field-preview' );
	await expect( fields ).toHaveCount( 3 );

	for ( let index = 0; index < 3; index++ ) {
		const field = fields.nth( index );
		const label = field.locator( '.fforms-label' );
		const control = field.locator( '.fforms-control' );

		await expect( field ).toHaveCSS( 'display', 'grid' );
		await expect( label ).toBeVisible();
		await expect( control ).toBeVisible();

		const labelBox = await label.boundingBox();
		const controlBox = await control.boundingBox();
		expect( controlBox.y ).toBeGreaterThan( labelBox.y );
	}

	await expect( form.locator( '.fforms-submit' ) ).toBeEnabled();
} );
