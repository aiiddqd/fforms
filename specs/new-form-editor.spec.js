const { expect, test } = require( '@wordpress/e2e-test-utils-playwright' );

const login = async ( page ) => {
	await page.goto( '/wp-login.php' );

	if ( ! page.url().includes( 'wp-login.php' ) ) {
		return;
	}

	await page.getByLabel( /username or email address/i ).fill( 'admin' );
	await page.getByLabel( /^password$/i ).fill( 'password' );
	await page.getByRole( 'button', { name: /log in/i } ).click();
	await expect( page ).toHaveURL( /wp-admin/ );
};

test( 'new form editor loads styles and stacks field controls', async ( {
	admin,
	editor,
	page,
} ) => {
	await login( page );
	await admin.createNewPost( { postType: 'fform' } );

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
