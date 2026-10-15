#!/usr/bin/env node
/**
 * Validates theme.json, style variations and block.json files against the
 * official WordPress schemas for the minimum supported WP version.
 *
 *   node tools/validate-json.mjs
 */
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import Ajv from 'ajv';

const WP = '7.1';
const root = resolve( dirname( fileURLToPath( import.meta.url ) ), '..' );
const schemas = {
	theme: `https://schemas.wp.org/wp/${ WP }/theme.json`,
	block: `https://schemas.wp.org/wp/${ WP }/block.json`,
};

function walk( dir, out = [] ) {
	for ( const name of readdirSync( dir ) ) {
		if ( name === 'node_modules' || name === 'vendor' || name.startsWith( '.' ) ) {
			continue;
		}
		const path = join( dir, name );
		if ( statSync( path ).isDirectory() ) {
			walk( path, out );
		} else if ( name.endsWith( '.json' ) ) {
			out.push( path );
		}
	}
	return out;
}

const files = [
	...walk( join( root, 'theme' ) ).filter( ( f ) => f.endsWith( 'theme.json' ) || f.includes( `${ join( 'theme', 'styles' ) }` ) ),
	...walk( join( root, 'engine', 'blocks' ) ).filter( ( f ) => f.endsWith( 'block.json' ) ),
].map( ( f ) => ( { path: f, kind: f.endsWith( 'block.json' ) ? 'block' : 'theme' } ) );

const ajv = new Ajv( { strict: false, allErrors: true } );
const validators = {};
for ( const [ kind, url ] of Object.entries( schemas ) ) {
	const res = await fetch( url );
	if ( ! res.ok ) {
		console.error( `Cannot fetch ${ url }: HTTP ${ res.status }` );
		process.exit( 2 );
	}
	validators[ kind ] = ajv.compile( await res.json() );
}

let failed = 0;
for ( const { path, kind } of files ) {
	const data = JSON.parse( readFileSync( path, 'utf8' ) );
	const ok = validators[ kind ]( data );
	console.log( `${ ok ? 'ok  ' : 'FAIL' } ${ relative( root, path ) } (${ kind }.json, WP ${ WP })` );
	if ( ! ok ) {
		failed++;
		for ( const e of validators[ kind ].errors.slice( 0, 20 ) ) {
			console.log( `     ${ e.instancePath || '/' } ${ e.message }` );
		}
	}
}
process.exit( failed ? 1 : 0 );
