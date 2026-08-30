const { expect, test } = require( '@wordpress/e2e-test-utils-playwright' );

const { login } = require( './support/login' );

test.describe( 'form creation mode', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	test( 'creates and publishes a Headless API form through wp-admin', async ( {
		editor,
		page,
		request,
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

		await page.getByLabel( 'Режим формы' ).selectOption( 'headless' );
		await page.locator( '.editor-post-publish-button' ).click();
		await page
			.locator( '.editor-post-publish-panel__publish-button' )
			.click();
		await expect
			.poll( () =>
				page.evaluate( () =>
					window.wp.data
						.select( 'core/editor' )
						.getCurrentPostAttribute( 'status' )
				)
			)
			.toBe( 'publish' );

		const formId = await page.evaluate( () =>
			window.wp.data.select( 'core/editor' ).getCurrentPostId()
		);
		const publicForm = await request.get(
			`/wp-json/fforms/v1/forms/${ formId }`
		);
		expect( publicForm.ok() ).toBeTruthy();
		expect( ( await publicForm.json() ).mode ).toBe( 'headless' );
	} );
} );
