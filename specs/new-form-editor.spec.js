const { expect, test } = require( '@wordpress/e2e-test-utils-playwright' );

const { login } = require( './support/login' );

const gapBetween = ( above, below ) => below.y - ( above.y + above.height );

test( 'new form editor loads styles and stacks field controls', async ( {
	admin,
	editor,
	page,
} ) => {
	await login( page );
	await admin.createNewPost( { postType: 'fform' } );

	const form = editor.canvas.locator( '.wp-block-fforms-form' );
	await expect( form ).toBeVisible();

	// The inner blocks container carries the same class and the same grid as
	// the server shell, so the editor spacing is the frontend spacing.
	await expect( form ).toHaveClass( /fforms-fields/ );
	await expect( form ).toHaveCSS( 'display', 'grid' );

	const fields = form.locator( '.fforms-field' );
	await expect( fields ).toHaveCount( 3 );

	let labelGap = 0;

	for ( let index = 0; index < 3; index++ ) {
		const field = fields.nth( index );
		// The tree matches `field_markup()`: no wrapper of its own between the
		// field and its label/control.
		const label = field.locator( '> .fforms-label' );
		const control = field.locator( '> .fforms-control' );

		await expect( field ).toHaveCSS( 'display', 'grid' );
		await expect( label ).toBeVisible();
		await expect( control ).toBeVisible();

		const labelBox = await label.boundingBox();
		const controlBox = await control.boundingBox();
		expect( controlBox.y ).toBeGreaterThan( labelBox.y );

		labelGap = gapBetween( labelBox, controlBox );
	}

	// Three steps: label ↔ control < field ↔ field < last field ↔ button.
	const firstControl = await fields
		.nth( 0 )
		.locator( '> .fforms-control' )
		.boundingBox();
	const secondLabel = await fields
		.nth( 1 )
		.locator( '> .fforms-label' )
		.boundingBox();
	const lastControl = await fields
		.nth( 2 )
		.locator( '> .fforms-control' )
		.boundingBox();

	const submit = form.locator( '.fforms-submit' );
	await expect( submit ).toBeEnabled();
	const submitBox = await submit.boundingBox();

	const fieldGap = gapBetween( firstControl, secondLabel );
	const submitGap = gapBetween( lastControl, submitBox );

	expect( labelGap ).toBeLessThan( fieldGap );
	expect( fieldGap ).toBeLessThan( submitGap );

	await form.screenshot( {
		path: 'artifacts/new-form-editor.png',
	} );
} );
