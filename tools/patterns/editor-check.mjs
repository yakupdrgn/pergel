#!/usr/bin/env node
/**
 * Checks the theme's patterns in the block editor of a running site: every
 * block is valid (the saved markup matches what the block would save now),
 * and an inserted section opens for content editing only, with its text and
 * images editable and its layout locked.
 *
 *   PERGEL_QA_PASSWORD=… node tools/patterns/editor-check.mjs \
 *        --base http://localhost/pergel-tema [--user pergel-qa] [--channel chrome]
 *
 * The user must be an administrator. Exit code 1 = a pattern failed.
 * The hidden shop patterns (pergel/hidden-product-*) hold WooCommerce blocks
 * that WooCommerce offers in the Site Editor only, so they are checked there,
 * in the product template. Without WooCommerce they are skipped; run it on a
 * shop site too.
 */
import { parseArgs } from 'node:util';
import { chromium } from 'playwright';

const { values: opt } = parseArgs( {
	options: {
		base: { type: 'string' },
		user: { type: 'string', default: 'pergel-qa' },
		channel: { type: 'string' },
	},
} );
const password = process.env.PERGEL_QA_PASSWORD;
if ( ! opt.base || ! password ) {
	console.error( 'Usage: PERGEL_QA_PASSWORD=… editor-check.mjs --base <site url> [--user <login>] [--channel chrome]' );
	process.exit( 2 );
}
const base = opt.base.replace( /\/$/, '' );

const browser = await chromium.launch( opt.channel ? { channel: opt.channel } : {} );
const page = await ( await browser.newContext( { viewport: { width: 1440, height: 900 } } ) ).newPage();
const consoleErrors = [];
page.on( 'console', ( message ) => {
	// Core's own warning about its global styles in the editor iframe, and
	// block validation messages, which are reported per pattern below.
	if ( 'error' === message.type() && ! /was added to the iframe incorrectly|^Block validation/.test( message.text() ) ) {
		consoleErrors.push( message.text() );
	}
} );
page.on( 'pageerror', ( error ) => consoleErrors.push( String( error ) ) );

// Straight to the editor: the dashboard's news widget fetches feeds from
// wordpress.org over admin-ajax, which can hold up the single-threaded CI server.
await page.goto( `${ base }/wp-login.php?redirect_to=${ encodeURIComponent( `${ base }/wp-admin/post-new.php?post_type=page` ) }` );
await page.fill( '#user_login', opt.user );
await page.fill( '#user_pass', password );
await page.click( '#wp-submit' );
await page.waitForURL( /post-new\.php/, { waitUntil: 'domcontentloaded', timeout: 90000 } );
await page.waitForFunction( () => window.wp?.data?.select( 'core/block-editor' ) && window.wp.blocks?.getBlockTypes().length > 50, null, { timeout: 90000 } );
await page.evaluate( () => {
	wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false );
	wp.data.dispatch( 'core/preferences' ).set( 'core', 'enableChoosePatternModal', false );
} );
await page.keyboard.press( 'Escape' );

const patterns = await page.evaluate( async () => {
	const all = await wp.apiFetch( { path: '/wp/v2/block-patterns/patterns' } );
	await wp.data.resolveSelect( 'core' ).getBlockPatterns();
	return all.filter( ( p ) => p.name.startsWith( 'pergel/' ) ).map( ( p ) => ( {
		name: p.name,
		content: p.content,
		inserter: p.inserter !== false,
		// Header and footer patterns are offered in the Site Editor only.
		templatePart: ( p.block_types || [] ).some( ( t ) => t.startsWith( 'core/template-part' ) ),
	} ) );
} );

/**
 * Defines window.pergelBlockProblems( content ) in the editor: unknown and
 * invalid blocks of a pattern.
 */
const defineChecks = () => page.evaluate( () => {
	window.pergelBlockProblems = ( content ) => {
		const problems = [];
		const walk = ( blocks ) => blocks.forEach( ( block ) => {
			if ( 'core/missing' === block.name ) {
				problems.push( `unknown block ${ block.attributes.originalName }` );
			} else {
				// Strict: the parser quietly accepts old markup through a block's
				// deprecations or by moving unknown classes into className, but the
				// pattern must already be what the block saves today.
				const [ valid, issues ] = wp.blocks.validateBlock( block );
				if ( ! valid || false === block.isValid ) {
					const issue = ( issues || block.validationIssues || [] )
						.map( ( i ) => ( i.args || [] ).slice( 1 ).filter( ( a ) => 'string' === typeof a ).join( ' | ' ) )
						.filter( Boolean )
						.join( '; ' );
					problems.push( `invalid ${ block.name }: ${ issue.slice( 0, 300 ) }` );
				}
			}
			walk( block.innerBlocks || [] );
		} );
		walk( wp.blocks.parse( content ) );
		return problems;
	};
} );
await defineChecks();

const SHOP_PREFIX = 'pergel/hidden-product-';
const shop = await page.evaluate( () => wp.blocks.getBlockTypes().some( ( t ) => t.name.startsWith( 'woocommerce/' ) ) );
const shopPatterns = patterns.filter( ( p ) => p.name.startsWith( SHOP_PREFIX ) && p.content.includes( '<!-- wp:woocommerce/' ) );

const results = [];
const skipped = shop ? [] : shopPatterns.map( ( p ) => p.name );
for ( const pattern of patterns ) {
	if ( shopPatterns.includes( pattern ) ) {
		continue;
	}
	const result = await page.evaluate( async ( { name, content, inserter, templatePart } ) => {
		const problems = window.pergelBlockProblems( content );
		if ( ! inserter || templatePart || problems.length ) {
			return { problems };
		}

		// Insert it the way the inserter does.
		const editor = wp.data.select( 'core/block-editor' );
		wp.data.dispatch( 'core/block-editor' ).resetBlocks( [] );
		const parsed = editor.__experimentalGetParsedPattern( name );
		if ( ! parsed ) {
			return { problems: [ 'not offered in the page editor' ] };
		}
		await wp.data.dispatch( 'core/block-editor' ).insertBlocks( parsed.blocks.map( ( b ) => wp.blocks.cloneBlock( b ) ) );
		// Full pages are pattern blocks that replace themselves once loaded.
		for ( let i = 0; i < 50 && editor.getBlocks().some( ( b ) => 'core/pattern' === b.name ); i++ ) {
			await new Promise( ( r ) => setTimeout( r, 100 ) );
		}
		const roots = editor.getBlocks();
		const modes = {};
		const count = ( blocks ) => blocks.forEach( ( block ) => {
			const mode = editor.getBlockEditingMode( block.clientId );
			modes[ mode ] = ( modes[ mode ] || 0 ) + 1;
			count( block.innerBlocks );
		} );
		count( roots );
		roots.forEach( ( root ) => {
			if ( ! root.attributes.metadata?.patternName ) {
				problems.push( `root ${ root.name } is not a section (no patternName)` );
			}
		} );
		if ( ! modes.contentOnly ) {
			problems.push( 'nothing to edit in content-only mode' );
		}
		return { problems, roots: roots.length, modes };
	}, pattern );
	results.push( { name: pattern.name, ...result } );
}

if ( shop && shopPatterns.length ) {
	await page.goto( `${ base }/wp-admin/site-editor.php?p=${ encodeURIComponent( '/wp_template/pergel//single-product' ) }&canvas=edit` );
	await page.waitForFunction( () => window.wp?.blocks?.getBlockType( 'core/template-part' ) && window.wp.blocks.getBlockTypes().some( ( t ) => t.name.startsWith( 'woocommerce/' ) ), null, { timeout: 90000 } );
	await defineChecks();
	for ( const pattern of shopPatterns ) {
		results.push( { name: pattern.name, problems: await page.evaluate( ( content ) => window.pergelBlockProblems( content ), pattern.content ) } );
	}
}
await browser.close();

const failed = results.filter( ( r ) => r.problems.length );
for ( const r of failed ) {
	console.error( `${ r.name }:\n  ${ r.problems.join( '\n  ' ) }` );
}
if ( consoleErrors.length ) {
	console.error( `Console errors:\n  ${ [ ...new Set( consoleErrors ) ].join( '\n  ' ) }` );
}
console.log( `Patterns in the editor: ${ results.length } checked, ${ failed.length } failed${ skipped.length ? `, ${ skipped.length } skipped without WooCommerce (${ skipped.join( ', ' ) })` : '' }.` );
process.exit( failed.length || consoleErrors.length ? 1 : 0 );
