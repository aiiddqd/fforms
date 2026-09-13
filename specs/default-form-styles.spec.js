const { expect, test } = require( '@wordpress/e2e-test-utils-playwright' );

const { login } = require( './support/login' );

const gapBetween = ( above, below ) => below.y - ( above.y + above.height );

/**
 * Out of the box, with no theme.json and no custom CSS, the rendered form has to
 * read as three spacing steps: a label sits close to its control, fields are
 * further apart, and the button is detached from the last field.
 */
test( 'a form on a page is tidy without any theme setup', async ( {
	admin,
	editor,
	page,
} ) => {
	await login( page );

	const formTitle = `Styles form ${ Date.now() }`;
	await admin.createNewPost( { postType: 'fform', title: formTitle } );
	await expect(
		editor.canvas.locator( '.wp-block-fforms-form' )
	).toBeVisible();
	await editor.publishPost();

	await admin.createNewPost( {
		postType: 'page',
		title: `Styles page ${ Date.now() }`,
	} );
	await editor.insertBlock( { name: 'fforms/form' } );
	await editor.canvas
		.getByLabel( 'Form', { exact: true } )
		.selectOption( { label: formTitle } );
	await expect(
		editor.canvas.locator( '.fforms-block-preview .fforms-form' )
	).toBeVisible();
	const pageId = await editor.publishPost();

	await page.goto( `/?page_id=${ pageId }` );

	const form = page.locator( '.fforms-form' );
	await expect( form ).toBeVisible();
	await expect( form.locator( '.fforms-fields' ) ).toHaveCSS(
		'display',
		'grid'
	);

	const fields = form.locator( '.fforms-field' );
	await expect( fields ).toHaveCount( 3 );

	// The error paragraph is in the DOM under every control; while it is empty
	// it must not reserve a row of its own.
	const errors = form.locator( '.fforms-field-error' );
	await expect( errors ).toHaveCount( 3 );
	await expect( errors.first() ).toHaveCSS( 'display', 'none' );

	const label = await fields
		.nth( 0 )
		.locator( '.fforms-label' )
		.boundingBox();
	const control = await fields
		.nth( 0 )
		.locator( '.fforms-control' )
		.boundingBox();
	const nextLabel = await fields
		.nth( 1 )
		.locator( '.fforms-label' )
		.boundingBox();
	const lastControl = await fields
		.nth( 2 )
		.locator( '.fforms-control' )
		.boundingBox();
	const submit = await form.locator( '.fforms-submit' ).boundingBox();

	const labelGap = gapBetween( label, control );
	const fieldGap = gapBetween( control, nextLabel );
	const submitGap = gapBetween( lastControl, submit );

	expect( labelGap ).toBeLessThan( fieldGap );
	expect( fieldGap ).toBeLessThan( submitGap );

	// The control border is a soft hairline, not pure text colour.
	await expect( fields.nth( 0 ).locator( '.fforms-control' ) ).toHaveCSS(
		'border-top-left-radius',
		'4px'
	);

	await form.screenshot( {
		path: 'artifacts/default-form-styles-front.png',
	} );
} );
