#!/usr/bin/env node
/**
 * Pergel performance budget (plan K11).
 *
 * Measures every page in tests/budget/budget.json on a running site and
 * fails when a value is outside its limit. Server-side numbers come from the perf
 * probe (tools/budget/perf-probe.php) through its JSON-lines log; browser
 * numbers come from Playwright, accessibility from axe-core (WCAG 2.2 A/AA).
 *
 *   node tools/budget/measure.mjs --base http://localhost/pergel-tema \
 *        --log C:/xampp/htdocs/pergel-tema/wp-content/pergel-perf.jsonl [--channel chrome]
 *
 * Writes reports/budget-<stamp>.json and .md. Exit code 1 = over budget.
 */
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { parseArgs } from 'node:util';
import { chromium } from 'playwright';
import { AxeBuilder } from '@axe-core/playwright';

const root = resolve( dirname( fileURLToPath( import.meta.url ) ), '../..' );
const { values: opt } = parseArgs( {
	options: {
		base: { type: 'string' },
		log: { type: 'string' },
		budget: { type: 'string', default: join( root, 'tests/budget/budget.json' ) },
		out: { type: 'string', default: join( root, 'reports' ) },
		channel: { type: 'string' },
		label: { type: 'string', default: 'local' },
	},
} );
if ( ! opt.base || ! opt.log ) {
	console.error( 'Usage: measure.mjs --base <site url> --log <perf log path> [--channel chrome]' );
	process.exit( 2 );
}

const base = opt.base.replace( /\/$/, '' );
const budget = JSON.parse( readFileSync( opt.budget, 'utf8' ) );
const runId = Date.now().toString( 36 );
const median = ( a ) => {
	const s = [ ...a ].sort( ( x, y ) => x - y );
	return s.length ? s[ Math.floor( ( s.length - 1 ) / 2 ) ] : null;
};

/* ---- Server side: probe log, median of N warm runs ---------------------- */
async function server( page ) {
	const url = base + page.path;
	await fetch( url ); // Warm OPcache and object caches.
	for ( let i = 0; i < budget.runs; i++ ) {
		const res = await fetch( url, { headers: { 'X-Pergel-Perf': `${ runId }:${ page.name }:${ i }` } } );
		await res.arrayBuffer();
		const expected = page.status ?? 200;
		if ( res.status !== expected ) {
			throw new Error( `${ page.name }: HTTP ${ res.status }, expected ${ expected } (${ url })` );
		}
	}
	const rows = existsSync( opt.log )
		? readFileSync( opt.log, 'utf8' )
				.split( '\n' )
				.filter( ( l ) => l.includes( `"${ runId }:${ page.name }:` ) )
				.map( ( l ) => JSON.parse( l ) )
		: [];
	if ( rows.length !== budget.runs ) {
		throw new Error( `${ page.name }: probe logged ${ rows.length }/${ budget.runs } requests. Is perf-probe.php loaded and ${ opt.log } writable?` );
	}
	return {
		ms: median( rows.map( ( r ) => r.ms ) ),
		queries: median( rows.map( ( r ) => r.queries ) ),
		writes: [ ...new Set( rows.flatMap( ( r ) => r.writes ) ) ],
		pergel_files: Math.max( ...rows.map( ( r ) => r.pergel_files ) ),
		files: median( rows.map( ( r ) => r.files ) ),
		peak_mb: Math.max( ...rows.map( ( r ) => r.peak_mb ) ),
	};
}

/* ---- Browser side ------------------------------------------------------- */
const COLLECT = () => {
	const ld = [ ...document.querySelectorAll( 'script[type="application/ld+json"]' ) ];
	return {
		dom: document.getElementsByTagName( '*' ).length,
		bodyCssLinks: document.body.querySelectorAll( 'link[rel="stylesheet"]' ).length,
		h1: document.querySelectorAll( 'h1' ).length,
		inlineCss: [ ...document.querySelectorAll( 'style' ) ].reduce( ( n, el ) => n + new TextEncoder().encode( el.textContent ).length, 0 ),
		jsonld: ld.length,
		jquery: !! window.jQuery,
		lcp: Math.round( window.__lcp || 0 ),
		cls: +( window.__cls || 0 ).toFixed( 3 ),
	};
};
const INIT = `window.__lcp=0;window.__cls=0;
new PerformanceObserver(l=>{for(const e of l.getEntries())window.__lcp=e.startTime}).observe({type:'largest-contentful-paint',buffered:true});
new PerformanceObserver(l=>{for(const e of l.getEntries())if(!e.hadRecentInput)window.__cls+=e.value}).observe({type:'layout-shift',buffered:true});`;

// axe-core rule tags: WCAG 2.0, 2.1 and 2.2, levels A and AA.
const AXE_TAGS = [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ];

async function browserMetrics( browser, page ) {
	const ctx = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
	await ctx.addInitScript( INIT );
	const tab = await ctx.newPage();
	const finished = [];
	tab.on( 'requestfinished', ( r ) => finished.push( r ) );
	const res = await tab.goto( base + page.path, { waitUntil: 'load' } );
	await tab.waitForTimeout( 1500 );
	const data = await tab.evaluate( COLLECT );
	const bytes = { script: 0, stylesheet: 0, font: 0, total: 0 };
	for ( const r of finished ) {
		const size = ( await r.sizes().catch( () => ( { responseBodySize: 0 } ) ) ).responseBodySize;
		bytes.total += size;
		if ( r.resourceType() in bytes ) {
			bytes[ r.resourceType() ] += size;
		}
	}
	const html = await res.body();
	const axe = await new AxeBuilder( { page: tab } ).withTags( AXE_TAGS ).analyze();
	await ctx.close();
	const kb = ( n ) => +( n / 1024 ).toFixed( 1 );
	const { inlineCss, ...rest } = data;
	return {
		...rest,
		htmlKB: kb( html.length ),
		requests: finished.length,
		jsKB: kb( bytes.script ),
		cssKB: kb( bytes.stylesheet + inlineCss ),
		fontKB: kb( bytes.font ),
		totalKB: kb( bytes.total ),
		axe: axe.violations.length,
		axeViolations: axe.violations.map( ( v ) => ( {
			id: v.id,
			impact: v.impact,
			nodes: v.nodes.length,
			target: v.nodes[ 0 ]?.target.join( ' ' ),
			help: v.help,
		} ) ),
	};
}

/* ---- Run, compare, report ----------------------------------------------- */
const browser = await chromium.launch( opt.channel ? { channel: opt.channel } : {} );
const results = {};
const limitText = ( limit ) => {
	if ( ! Array.isArray( limit ) ) {
		return `≤ ${ limit }`;
	}
	return limit[ 0 ] === limit[ 1 ] ? `= ${ limit[ 0 ] }` : `${ limit[ 0 ] }–${ limit[ 1 ] }`;
};
const failures = [];
for ( const page of budget.pages ) {
	const m = { ...( await server( page ) ), ...( await browserMetrics( browser, page ) ) };
	const limits = { ...budget.limits[ '*' ], ...( budget.limits[ page.name ] ?? {} ) };
	for ( const [ key, limit ] of Object.entries( limits ) ) {
		const value = key === 'writes' ? m.writes.length : m[ key ];
		const [ min, max ] = Array.isArray( limit ) ? limit : [ -Infinity, limit ];
		if ( value > max || value < min ) {
			failures.push( `${ page.name }: ${ key } ${ value } not ${ limitText( limit ) }` );
		}
	}
	results[ page.name ] = m;
}
await browser.close();

const ordered = budget.pages.map( ( p ) => [ p.name, results[ p.name ] ] );
const cols = [ 'pergel_files', 'queries', 'writes', 'ms', 'htmlKB', 'requests', 'jsKB', 'cssKB', 'fontKB', 'totalKB', 'bodyCssLinks', 'dom', 'h1', 'axe', 'lcp', 'cls' ];
const cell = ( m, c ) => ( c === 'writes' ? m.writes.length : m[ c ] );
const limitRow = budget.limits[ '*' ];
const md = [
	`# Pergel budget report`,
	'',
	`- Site: ${ base } (${ opt.label })`,
	`- Date: ${ new Date().toISOString() }`,
	`- Runs per page: ${ budget.runs } (server numbers are medians of warm runs)`,
	`- Result: ${ failures.length ? `**OVER BUDGET** (${ failures.length })` : '**within budget**' }`,
	'',
	`| page | ${ cols.join( ' | ' ) } |`,
	`|---|${ cols.map( () => '--:' ).join( '|' ) }|`,
	`| *limit* | ${ cols.map( ( c ) => ( c in limitRow ? limitText( limitRow[ c ] ) : '' ) ).join( ' | ' ) } |`,
	...ordered.map( ( [ n, m ] ) => `| ${ n } | ${ cols.map( ( c ) => cell( m, c ) ).join( ' | ' ) } |` ),
	'',
	...( failures.length ? [ '## Over budget', '', ...failures.map( ( f ) => `- ${ f }` ), '' ] : [] ),
	...ordered
		.filter( ( [ , m ] ) => m.axeViolations.length )
		.flatMap( ( [ n, m ] ) => [
			`## Accessibility on ${ n }`,
			'',
			...m.axeViolations.map( ( v ) => `- \`${ v.id }\` (${ v.impact }, ${ v.nodes } nodes): ${ v.help }. First: \`${ v.target }\`` ),
			'',
		] ),
	...ordered
		.filter( ( [ , m ] ) => m.writes.length )
		.flatMap( ( [ n, m ] ) => [ `## DB writes on ${ n }`, '', ...m.writes.map( ( w ) => `- \`${ w }\`` ), '' ] ),
].join( '\n' );

mkdirSync( opt.out, { recursive: true } );
const stamp = new Date().toISOString().replace( /[-:]/g, '' ).slice( 0, 13 );
writeFileSync( join( opt.out, `budget-${ stamp }.json` ), JSON.stringify( { base, label: opt.label, results, failures }, null, '\t' ) );
writeFileSync( join( opt.out, `budget-${ stamp }.md` ), md + '\n' );
writeFileSync( join( opt.out, 'budget-latest.md' ), md + '\n' );
console.log( md );
process.exit( failures.length ? 1 : 0 );
