#!/usr/bin/env node
/**
 * SEO matrix check for one scenario: Pergel alone or with one SEO plugin.
 *
 *   node tools/seo/check.mjs --base http://127.0.0.1:8080 --scenario yoast \
 *     --phone "+90 212 555 00 00" --social https://www.linkedin.com/company/pergel-matrix
 *
 * Reads the head of a fixed set of pages (tools/fixtures/seed.php) and the
 * sitemaps, and fails when a tag is printed twice, a page has two
 * Organization or WebSite nodes, Pergel prints its fallback next to a
 * plugin, the Organization node lacks the brand details, or the site serves
 * more than one sitemap. tools/seo/matrix.sh runs it for every scenario.
 */
import { parseArgs } from 'node:util';

const { values: args } = parseArgs( {
	options: {
		base: { type: 'string' },
		scenario: { type: 'string', default: 'none' },
		phone: { type: 'string', default: '' },
		social: { type: 'string', default: '' },
	},
} );
if ( ! args.base ) {
	console.error( 'Usage: check.mjs --base <site url> [--scenario none|rank-math|yoast|aioseo|seopress|tsf] [--phone <brand phone>] [--social <profile url>]' );
	process.exit( 2 );
}
const BASE = args.base.replace( /\/$/, '' );
const FALLBACK = 'none' === args.scenario;

// Pages from tools/fixtures/seed.php. "indexable" pages need exactly one canonical,
// "described" ones (content to describe) one description in the fallback.
const PAGES = [
	{ path: '/', indexable: true, described: true },
	{ path: '/blog/', indexable: true },
	{ path: '/pergel-sample-post/', indexable: true, described: true },
	{ path: '/pergel-sample-page/', indexable: true, described: true },
	{ path: '/category/pergel-test/', indexable: true },
	{ path: '/?s=pergel', indexable: false },
	{ path: '/pergel-seo-missing/', indexable: false, status: 404 },
];
// Tags that must not appear twice in the head.
const SINGLE = [
	[ 'meta', 'name', 'description' ],
	[ 'meta', 'name', 'robots' ],
	[ 'meta', 'property', 'og:title' ],
	[ 'meta', 'property', 'og:description' ],
	[ 'meta', 'property', 'og:url' ],
	[ 'meta', 'property', 'og:type' ],
	[ 'meta', 'name', 'twitter:card' ],
	[ 'meta', 'name', 'twitter:title' ],
];
// Mirrors Pergel\Seo\Graph::is_organization().
const ORG_TYPES = new Set( [ 'Organization', 'Corporation', 'LocalBusiness', 'OnlineBusiness', 'OnlineStore', 'NGO' ] );
const SITEMAPS = [ '/wp-sitemap.xml', '/sitemap_index.xml', '/sitemap.xml' ];

const failures = [];
const fail = ( where, message ) => failures.push( `${ where }: ${ message }` );

/* ---- HTML ---------------------------------------------------------------- */
const attrs = ( tag ) => {
	const out = {};
	for ( const m of tag.matchAll( /([\w:-]+)\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+))/g ) ) {
		out[ m[ 1 ].toLowerCase() ] = m[ 2 ] ?? m[ 3 ] ?? m[ 4 ];
	}
	return out;
};
const tags = ( html, name ) => [ ...html.matchAll( new RegExp( `<${ name }\\b[^>]*>`, 'gi' ) ) ].map( ( m ) => attrs( m[ 0 ] ) );
const count = ( html, el, attr, value ) => tags( html, el ).filter( ( a ) => ( a[ attr ] ?? '' ).toLowerCase() === value ).length;

/** Every typed node in the JSON-LD scripts, @graph lists and nested values flattened. */
const nodes = ( value, out = [] ) => {
	if ( Array.isArray( value ) ) {
		value.forEach( ( v ) => nodes( v, out ) );
	} else if ( value && 'object' === typeof value ) {
		if ( value[ '@type' ] ) {
			out.push( value );
		}
		Object.values( value ).forEach( ( v ) => nodes( v, out ) );
	}
	return out;
};
const types = ( node ) => [ node[ '@type' ] ].flat();
const isOrganization = ( node ) => {
	const t = types( node );
	if ( t.includes( 'Person' ) ) {
		return false;
	}
	return t.some( ( x ) => ORG_TYPES.has( x ) ) || String( node[ '@id' ] ?? '' ).endsWith( '#organization' );
};

async function page( { path, indexable, described = false, status = 200 } ) {
	const res = await fetch( BASE + path, { redirect: 'follow' } );
	const html = await res.text();
	const where = `${ args.scenario } ${ path }`;
	if ( res.status !== status ) {
		fail( where, `HTTP ${ res.status }, expected ${ status }` );
		return null;
	}
	const head = ( html.match( /<head\b[^>]*>([\s\S]*?)<\/head>/i ) ?? [ '', '' ] )[ 1 ];
	const row = {
		title: ( head.match( /<title\b/gi ) ?? [] ).length,
		description: count( head, 'meta', 'name', 'description' ),
		canonical: tags( head, 'link' ).filter( ( a ) => ( a.rel ?? '' ).toLowerCase() === 'canonical' ).length,
	};
	if ( 1 !== row.title ) {
		fail( where, `${ row.title } <title> elements` );
	}
	if ( indexable ? 1 !== row.canonical : row.canonical > 1 ) {
		fail( where, `${ row.canonical } canonical links` );
	}
	for ( const [ el, attr, value ] of SINGLE ) {
		const n = count( head, el, attr, value );
		if ( n > 1 ) {
			fail( where, `${ n } × ${ value }` );
		}
	}

	const scripts = [ ...html.matchAll( /<script\b([^>]*)>([\s\S]*?)<\/script>/gi ) ]
		.filter( ( m ) => ( attrs( m[ 1 ] ).type ?? '' ).toLowerCase() === 'application/ld+json' );
	const all = [];
	for ( const m of scripts ) {
		try {
			nodes( JSON.parse( m[ 2 ] ), all );
		} catch ( e ) {
			fail( where, `invalid JSON-LD: ${ e.message }` );
		}
	}
	const pergel = scripts.filter( ( m ) => /\bpergel-schema\b/.test( attrs( m[ 1 ] ).class ?? '' ) ).length;
	const orgs = all.filter( isOrganization );
	const sites = all.filter( ( n ) => types( n ).includes( 'WebSite' ) );
	Object.assign( row, { ld: scripts.length, pergel, organization: orgs.length, website: sites.length } );
	if ( orgs.length > 1 ) {
		fail( where, `${ orgs.length } Organization nodes` );
	}
	if ( sites.length > 1 ) {
		fail( where, `${ sites.length } WebSite nodes` );
	}
	const expected = FALLBACK && 200 === status ? 1 : 0;
	if ( pergel !== expected ) {
		fail( where, `${ pergel } Pergel JSON-LD scripts, expected ${ expected }` );
	}
	if ( FALLBACK && described && 1 !== row.description ) {
		fail( where, `${ row.description } descriptions, the fallback prints one` );
	}
	for ( const org of orgs ) {
		if ( args.phone && org.telephone !== args.phone ) {
			fail( where, `Organization telephone is ${ JSON.stringify( org.telephone ) }` );
		}
		if ( args.social && ! [ org.sameAs ?? [] ].flat().includes( args.social ) ) {
			fail( where, `Organization sameAs lacks ${ args.social }` );
		}
	}
	return row;
}

/* ---- Sitemaps ------------------------------------------------------------ */
async function sitemaps() {
	const where = `${ args.scenario } sitemap`;
	// Keyed by content without comments: one sitemap served at two addresses
	// (The SEO Framework answers sitemap_index.xml with its sitemap.xml and
	// stamps each response with the time) is still one sitemap.
	const found = new Map();
	for ( const path of SITEMAPS ) {
		const res = await fetch( BASE + path, { redirect: 'follow' } );
		const body = await res.text();
		if ( 200 === res.status && /<(sitemapindex|urlset)\b/.test( body ) ) {
			const key = body.replace( /<!--[\s\S]*?-->/g, '' ).replace( /\s+/g, '' );
			found.set( key, [ ...( found.get( key ) ?? [] ), res.url ] );
		}
	}
	const lists = [ ...found.values() ].map( ( list ) => [ ...new Set( list ) ] );
	const urls = lists.map( ( list ) => list.map( ( u ) => u.replace( BASE, '' ) ).join( ' = ' ) );
	if ( 1 !== found.size ) {
		fail( where, `${ found.size } different sitemap indexes: ${ urls.join( ', ' ) || 'none' }` );
	}
	// robots.txt exists only when the site sits at the domain root. Only XML
	// sitemaps count: AIOSEO also lists its RSS sitemap of recent posts.
	const robots = await fetch( BASE + '/robots.txt' );
	let listed = [];
	if ( 200 === robots.status ) {
		listed = [ ...new Set( [ ...( await robots.text() ).matchAll( /^sitemap:\s*(\S+)/gim ) ].map( ( m ) => m[ 1 ] ) ) ]
			.filter( ( u ) => ! /\.rss$/i.test( u ) );
		if ( listed.length > 1 ) {
			fail( where, `robots.txt lists ${ listed.length } XML sitemaps: ${ listed.join( ', ' ) }` );
		}
		const served = lists.flat();
		for ( const url of listed ) {
			const res = await fetch( url, { redirect: 'follow' } );
			if ( ! served.includes( res.url ) ) {
				fail( where, `robots.txt lists ${ url }, which is not the sitemap the site serves` );
			}
		}
	}
	return { sitemap: urls.join( ', ' ), robots: listed.length };
}

const rows = {};
for ( const p of PAGES ) {
	rows[ p.path ] = await page( p );
}
const map = await sitemaps();
console.log( `\n${ args.scenario }: sitemap ${ map.sitemap || '-' }, robots.txt sitemaps ${ map.robots }` );
console.table( rows );
const orgSeen = Object.values( rows ).some( ( r ) => r && r.organization > 0 );
if ( ! orgSeen ) {
	fail( `${ args.scenario }`, 'no page has an Organization node, so the brand details cannot be checked' );
}
if ( failures.length ) {
	console.log( failures.map( ( f ) => `FAIL ${ f }` ).join( '\n' ) );
	process.exit( 1 );
}
console.log( `ok ${ args.scenario }` );
