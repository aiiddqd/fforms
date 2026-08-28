const config = require( '@wordpress/scripts/config/eslint.config.cjs' );

config[ 0 ].ignores.push( 'docs/rfc/jetpack/**' );

config.push( {
	files: [ 'assets/fallback-editor.js' ],
	rules: {
		// The pre-build fallback uses WordPress globals and dynamic block factories.
		'@wordpress/i18n-no-variables': 'off',
		'react-hooks/rules-of-hooks': 'off',
	},
} );

module.exports = config;
