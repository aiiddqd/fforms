const { defineConfig } = require( '@playwright/test' );

require( 'dotenv' ).config( { path: __dirname + '/.env' } );

module.exports = defineConfig( {
	testDir: './specs',
	testMatch: '**/*.spec.js',
	fullyParallel: false,
	timeout: 60_000,
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8890',
	},
	workers: 1,
} );
