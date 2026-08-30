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
		[ 'Настройки', 'page=fforms-settings' ],
		[ 'Экспорт CSV', 'page=fforms-export' ],
	] ) {
		test( `"${ label }" submenu link resolves without a 404`, async ( { page } ) => {
			await page.goto( '/wp-admin/edit.php?post_type=fform_entry' );

			const link = page.locator( '#adminmenu' ).getByRole( 'link', { name: label, exact: true } );
			await expect( link ).toHaveAttribute( 'href', new RegExp( `admin\\.php\\?${ expectedQuery }` ) );

			await link.click();
			await expect( page.locator( 'body' ) ).not.toContainText( 'Page not found' );
			await expect( page.locator( '.wrap h1' ) ).toBeVisible();
		} );
	}
} );
