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

		// Nothing picked yet: the placeholder with the picker. The block is
		// addressed by `data-type`: once the preview renders, the form's own
		// wrapper carries `wp-block-fforms-form` inside the editor's wrapper.
		const block = editor.canvas.locator( '[data-type="fforms/form"]' );
		await expect( block.getByText( 'FForms' ) ).toBeVisible();
		await expect( block.locator( '.fforms-form' ) ).toHaveCount( 0 );

		// Nothing to edit yet, so the link to the form is not offered.
		await editor.openDocumentSettingsSidebar();
		await page.getByRole( 'tab', { name: 'Block' } ).click();
		await expect(
			page.getByRole( 'link', { name: 'Edit form' } )
		).toHaveCount( 0 );

		await editor.canvas
			.getByLabel( 'Form', { exact: true } )
			.selectOption( { label: formTitle } );

		// Picked: the server-rendered form, not a grey box.
		const preview = block.locator( '.fforms-block-preview .fforms-form' );
		await expect( preview ).toBeVisible();
		await expect( preview.locator( '.fforms-field' ) ).toHaveCount( 3 );
		await expect( preview.locator( '.fforms-submit' ) ).toBeVisible();
		// The honeypot is hidden by clipping, not by `display`, so measure the
		// container: `view.css` is not enqueued in the editor and the rules are
		// repeated in `editor.scss` — without them the field would sit in the flow.
		const honeypot = preview.locator( '.fforms-hp' );
		await expect( honeypot ).toHaveCSS( 'position', 'absolute' );
		const honeypotBox = await honeypot.boundingBox();
		expect( honeypotBox.height ).toBeLessThanOrEqual( 1 );
		expect( honeypotBox.width ).toBeLessThanOrEqual( 1 );

		// From the preview straight to the form itself, in a tab of its own so
		// the unsaved page survives.
		const editLink = page.getByRole( 'link', { name: 'Edit form' } );
		await expect( editLink ).toHaveAttribute(
			'href',
			`post.php?post=${ formId }&action=edit`
		);
		await expect( editLink ).toHaveAttribute( 'target', '_blank' );

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

	test( 'the picker in the block sidebar repoints the preview', async ( {
		admin,
		editor,
		page,
	} ) => {
		await login( page );

		const stamp = Date.now();
		const ids = [];
		for ( const suffix of [ 'A', 'B' ] ) {
			await admin.createNewPost( {
				postType: 'fform',
				title: `Preview form ${ suffix } ${ stamp }`,
			} );
			ids.push( await editor.publishPost() );
		}
		const [ first, second ] = ids;

		await admin.createNewPost( {
			postType: 'page',
			title: `Repoint page ${ stamp }`,
		} );
		await editor.insertBlock( {
			name: 'fforms/form',
			attributes: { ref: first, formId: first },
		} );

		// The rendered form carries its own id in the Interactivity context, so
		// the preview can be told apart from the previous one even though both
		// forms were created from the same template.
		const form = editor.canvas.locator(
			'.fforms-block-preview .fforms-form'
		);
		await expect( form ).toHaveAttribute(
			'data-wp-context',
			new RegExp( `"formId":${ first }\\b` )
		);

		await editor.openDocumentSettingsSidebar();
		await page.getByRole( 'tab', { name: 'Block' } ).click();
		await page
			.getByRole( 'region', { name: 'Editor settings' } )
			.getByLabel( 'Form', { exact: true } )
			.selectOption( String( second ) );

		await expect( form ).toHaveAttribute(
			'data-wp-context',
			new RegExp( `"formId":${ second }\\b` )
		);
	} );

	test( 'a reference to a form that is gone stays fixable', async ( {
		admin,
		editor,
		page,
	} ) => {
		await login( page );

		await admin.createNewPost( {
			postType: 'page',
			title: `Missing form page ${ Date.now() }`,
		} );
		await editor.insertBlock( {
			name: 'fforms/form',
			attributes: { ref: 999999, formId: 999999 },
		} );

		await expect(
			editor.canvas.getByText(
				'Select a published form in the block settings.'
			)
		).toBeVisible();

		// The picker is still there, so the block is not a dead end.
		await editor.openDocumentSettingsSidebar();
		await page.getByRole( 'tab', { name: 'Block' } ).click();
		await expect(
			page
				.getByRole( 'region', { name: 'Editor settings' } )
				.getByLabel( 'Form', { exact: true } )
		).toBeVisible();
	} );
} );
