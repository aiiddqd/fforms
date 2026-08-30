const { existsSync, readFileSync, writeFileSync } = require( 'node:fs' );
const { resolve } = require( 'node:path' );
const { parseEnv } = require( 'node:util' );

const projectRoot = resolve( __dirname, '..' );
const envPath = resolve( projectRoot, '.env' );
const overridePath = resolve( projectRoot, '.wp-env.override.json' );
const debugVariables = [
	'WP_DEBUG',
	'WP_DEBUG_LOG',
	'WP_DEBUG_DISPLAY',
	'SCRIPT_DEBUG',
];

const parseBoolean = ( name, value ) => {
	const normalized = value.trim().toLowerCase();

	if ( [ 'true', '1' ].includes( normalized ) ) {
		return true;
	}

	if ( [ 'false', '0' ].includes( normalized ) ) {
		return false;
	}

	throw new Error(
		`${ name } in .env must be true, false, 1, or 0; received "${ value }".`
	);
};

if ( ! existsSync( envPath ) ) {
	process.exit( 0 );
}

const env = parseEnv( readFileSync( envPath, 'utf8' ) );

const override = existsSync( overridePath )
	? JSON.parse( readFileSync( overridePath, 'utf8' ) )
	: {};
const config = { ...override.config };
let hasDebugValue = false;

for ( const name of debugVariables ) {
	if ( undefined === env[ name ] ) {
		continue;
	}

	config[ name ] = parseBoolean( name, env[ name ] );
	hasDebugValue = true;
}

if ( ! hasDebugValue ) {
	process.exit( 0 );
}

writeFileSync(
	overridePath,
	`${ JSON.stringify( { ...override, config }, null, '\t' ) }\n`
);
