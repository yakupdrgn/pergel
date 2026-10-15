/**
 * Every pattern in every style variation: axe (WCAG 2.2 A/AA) and sideways
 * overflow; full-page patterns also against committed screenshots (@visual).
 *
 * Patterns are rendered on the seeded sample page through the perf probe's
 * X-Pergel-Pattern header (tools/budget/perf-probe.php), so nothing is saved.
 * Section patterns are checked one category per page; header and footer
 * patterns are covered by the performance budget.
 */
import { readFileSync, readdirSync } from 'node:fs';
import { basename, dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect } from 'playwright/test';
import { AxeBuilder } from '@axe-core/playwright';

const root = resolve( dirname( fileURLToPath( import.meta.url ) ), '../..' );
const base = ( process.env.PERGEL_BASE || 'http://127.0.0.1:8080' ).replace( /\/$/, '' );
const AXE_TAGS = [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ];
const WIDTHS = { desktop: 1280, tablet: 800, phone: 360 };

const variations = readdirSync( join( root, 'theme/styles' ) )
	.filter( ( f ) => f.endsWith( '.json' ) )
	.map( ( f ) => basename( f, '.json' ) )
	.sort();

/** Section patterns by their first category, and the full pages. */
const sheets = {};
const pages = [];
for ( const file of readdirSync( join( root, 'theme/patterns' ) ).sort() ) {
	const head = readFileSync( join( root, 'theme/patterns', file ), 'utf8' ).slice( 0, 1500 );
	const field = ( name ) => ( head.match( new RegExp( `^ [*] ${ name }: (.+)$`, 'm' ) ) || [] )[ 1 ]?.trim();
	const name = basename( file, '.php' );
	const category = field( 'Categories' )?.split( ',' )[ 0 ].trim();
	if ( ! category || 'no' === field( 'Inserter' ) || [ 'header', 'footer' ].includes( category ) ) {
		continue;
	}
	if ( 'pergel-pages' === category ) {
		pages.push( name );
	} else {
		( sheets[ category ] ||= [] ).push( name );
	}
}

async function open( page, patterns, variation, width ) {
	await page.setViewportSize( { width, height: 900 } );
	await page.setExtraHTTPHeaders( {
		'X-Pergel-Pattern': patterns.join( ',' ),
		...( 'default' === variation ? {} : { 'X-Pergel-Variation': variation } ),
	} );
	const res = await page.goto( `${ base }/pergel-sample-page/`, { waitUntil: 'networkidle' } );
	expect( res.status() ).toBe( 200 );
	// Full-page screenshots and axe see every image, not only those near the top.
	await page.evaluate( async () => {
		document.querySelectorAll( 'img[loading="lazy"]' ).forEach( ( img ) => ( img.loading = 'eager' ) );
		await Promise.all( [ ...document.images ].map( ( img ) => img.complete || new Promise( ( done ) => ( img.onload = img.onerror = done ) ) ) );
		await document.fonts.ready;
	} );
}

async function audit( page, label ) {
	const { violations } = await new AxeBuilder( { page } ).withTags( AXE_TAGS ).analyze();
	const lines = violations.flatMap( ( v ) => v.nodes.map( ( n ) => `${ v.id }: ${ n.target.join( ' ' ) }` ) );
	expect( lines, `axe ${ label }` ).toEqual( [] );
	const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
	expect( overflow, `sideways scroll ${ label }` ).toBe( 0 );
}

for ( const variation of [ 'default', ...variations ] ) {
	test.describe( variation, () => {
		for ( const [ category, patterns ] of Object.entries( sheets ) ) {
			test( `${ category } sections`, async ( { page } ) => {
				for ( const width of [ WIDTHS.desktop, WIDTHS.phone ] ) {
					await open( page, patterns, variation, width );
					await audit( page, `${ width }px` );
				}
			} );
		}
		for ( const name of pages ) {
			test( `${ name } page`, async ( { page } ) => {
				for ( const width of [ WIDTHS.desktop, WIDTHS.phone ] ) {
					await open( page, [ name ], variation, width );
					await audit( page, `${ width }px` );
				}
			} );
			if ( 'default' === variation ) {
				continue;
			}
			test( `${ name } page looks the same @visual`, async ( { page } ) => {
				for ( const width of Object.values( WIDTHS ) ) {
					await open( page, [ name ], variation, width );
					await expect( page ).toHaveScreenshot( `${ name }@${ variation }-${ width }.png`, { fullPage: true } );
				}
			} );
		}
	} );
}
