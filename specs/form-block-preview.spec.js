const { expect, test } = require( '@wordpress/e2e-test-utils-playwright' );

const { login } = require( './support/login' );

/**
 * The `fforms/form` block on a page is a reference: until a form is picked it is
 * a placeholder, after that the canvas shows the very markup the front end will
 * render, and the page itself stores nothing but the reference.
 */
test.describe( 'form block preview in the editor', () => {
	test( 'previews the selected form and keeps the page free of field blocks', async ( {
		admin,
		editor,
		page,
	} ) => {
		await login( page );

		const formTitle = `Preview form ${ Date.now() }`;
		await admin.createNewPost( { postType: 'fform', title: formTitle } );
		await expect(
			editor.canvas.locator( '.wp-block-fforms-form' )
		).toBeVisible();
		const formId = await editor.publishPost();
		expect( formId ).toBeTruthy();

		await admin.createNewPost( {
			postType: 'page',
			title: `Preview page ${ Date.now() }`,
		} );
		await editor.insertBlock( { name: 'fforms/form' } );

		// Nothing picked yet: the placeholder with the picker.
		const block = editor.canvas.locator( '.wp-block-fforms-form' );
		await expect( block.getByText( 'FForms' ) ).toBeVisible();
		await expect( block.locator( '.fforms-form' ) ).toHaveCount( 0 );

		await editor.canvas
			.getByLabel( 'Form', { exact: true } )
			.selectOption( { label: formTitle } );

		// Picked: the server-rendered form, not a grey box.
		const preview = block.locator( '.fforms-block-preview .fforms-form' );
		await expect( preview ).toBeVisible();
		await expect( preview.locator( '.fforms-field' ) ).toHaveCount( 3 );
		await expect( preview.locator( '.fforms-submit' ) ).toBeVisible();
		await expect( preview.locator( '.fforms-hp input' ) ).not.toBeVisible();

		// From the preview straight to the form itself.
		await editor.openDocumentSettingsSidebar();
		await page.getByRole( 'tab', { name: 'Block' } ).click();
		await expect(
			page.getByRole( 'link', { name: 'Edit form' } )
		).toHaveAttribute( 'href', `post.php?post=${ formId }&action=edit` );

		// The preview is not a text field: a click lands on the block.
		const control = preview.locator( '.fforms-control' ).first();
		const box = await control.boundingBox();
		await page.mouse.click( box.x + box.width / 2, box.y + box.height / 2 );
		await expect( block ).toHaveClass( /is-selected/ );
		await expect( control ).not.toBeFocused();

		// The page stores the reference only — the fields live in the form.
		const content = await editor.getEditedPostContent();
		expect( content ).toContain( `"ref":${ formId }` );
		expect( content ).not.toContain( 'fforms/field-' );

		const pageId = await editor.publishPost();
		await page.goto( `/?page_id=${ pageId }` );

		const frontend = page.locator( '.fforms-form' );
		await expect( frontend ).toBeVisible();
		await frontend.locator( 'input[name="fields[name]"]' ).fill( 'Ada' );
		await frontend
			.locator( 'input[name="fields[email]"]' )
			.fill( 'ada@example.com' );
		await frontend
			.locator( 'textarea[name="fields[message]"]' )
			.fill( 'Sent from the e2e spec.' );
		await frontend.locator( '.fforms-submit' ).click();

		const response = page.locator( '.fforms-response' );
		await expect( response ).not.toBeEmpty();
		await expect( response ).not.toHaveClass( /is-error/ );
	} );
} );
