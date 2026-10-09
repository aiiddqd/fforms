const { expect, test } = require( '@wordpress/e2e-test-utils-playwright' );

const { login } = require( './support/login' );

/**
 * Regression test: add_submenu_page() for 'fforms-settings'/'fforms-export' must
 * run after add_menu_page() registers the 'fforms' top-level menu (Plugin::boot()
 * order), otherwise WP renders their sidebar links as the bare slug instead of
 * admin.php?page=..., 404ing.
 */
test.describe( 'FForms admin menu links', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	for ( const [ label, expectedQuery ] of [
		[ 'Settings', 'page=fforms-settings' ],
		[ 'CSV export', 'page=fforms-export' ],
	] ) {
		test( `"${ label }" submenu link resolves without a 404`, async ( {
			page,
		} ) => {
			await page.goto( '/wp-admin/edit.php?post_type=fform_entry' );

			// Scoped to the plugin's own top-level menu: WordPress has a
			// "Settings" menu of its own further down the sidebar.
			const link = page
				.locator( '#toplevel_page_fforms' )
				.getByRole( 'link', { name: label, exact: true } );
			await expect( link ).toHaveAttribute(
				'href',
				new RegExp( `admin\\.php\\?${ expectedQuery }` )
			);

			await link.click();
			await expect( page.locator( 'body' ) ).not.toContainText(
				'Page not found'
			);
			await expect( page.locator( '.wrap h1' ) ).toBeVisible();
		} );
	}
} );
