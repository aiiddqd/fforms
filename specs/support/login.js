const { expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Logs into wp-admin using credentials from .env (WP_LOGIN / WP_PASS), skipping
 * the form if the session is already authenticated.
 */
const login = async ( page ) => {
	await page.goto( '/wp-login.php' );

	if ( ! page.url().includes( 'wp-login.php' ) ) {
		return;
	}

	await page.getByLabel( /username or email address/i ).fill( process.env.WP_LOGIN );
	await page.getByLabel( /^password$/i ).fill( process.env.WP_PASS );
	await page.getByRole( 'button', { name: /log in/i } ).click();
	await expect( page ).toHaveURL( /wp-admin/ );
};

module.exports = { login };
