const { defineConfig } = require( '@playwright/test' );

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
