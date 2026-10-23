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
 * plugin, the Organization node lacks the brand details, the visible
 * breadcrumb (pergel/breadcrumb) differs from the BreadcrumbList, the FAQ
 * page lacks its one FAQPage (or another page has one), or the site serves
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

/**
 * fetch() that asks once more when the connection drops. PHP's built-in
 * server on CI segfaults now and then (tools/ci/serve.sh starts it again),
 * which is a test-server fault, not an SEO one (docs/DECISIONS.md).
 */
async function get( url, init = {} ) {
	try {
		return await fetch( url, init );
	} catch ( error ) {
		console.log( `${ url }: ${ error.cause?.message ?? error.message }; asking again once the server is back` );
		await new Promise( ( resolve ) => setTimeout( resolve, 2000 ) );
		return fetch( url, init );
	}
}
const FALLBACK = 'none' === args.scenario;

// Pages from tools/fixtures/seed.php. "indexable" pages need exactly one canonical,
// "described" ones (content to describe) one description in the fallback,
// "trail" ones a visible breadcrumb. The category is reached through the
// canonical redirect, so any category base works.
const PAGES = [
	{ path: '/', indexable: true, described: true },
	{ path: '/blog/', indexable: true, trail: true },
	{ path: '/pergel-sample-post/', indexable: true, described: true, trail: true },
	{ path: '/pergel-sample-page/', indexable: true, described: true, trail: true },
	{ path: '/pergel-sample-faq/', indexable: true, described: true, trail: true, questions: 2 },
	{ path: '/?category_name=pergel-test', indexable: true, trail: true },
	{ path: '/?s=pergel', indexable: false },
	// tools/seo/noindex.php: a page Pergel keeps out of search results.
	{ path: '/pergel-sample-page/?pergel-noindex=1', indexable: false, noindex: true },
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

/* ---- Breadcrumb ---------------------------------------------------------- */
const ENTITIES = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ', ldquo: '“', rdquo: '”', lsquo: '‘', rsquo: '’', ndash: '–', mdash: '—', hellip: '…', raquo: '»', laquo: '«' };
const decode = ( text ) => String( text ?? '' )
	.replace( /<[^>]*>/g, '' )
	.replace( /&(?:#(\d+)|#x([0-9a-f]+)|(\w+));/gi, ( m, dec, hex, name ) => {
		if ( dec || hex ) {
			return String.fromCodePoint( parseInt( dec ?? hex, dec ? 10 : 16 ) );
		}
		return ENTITIES[ name.toLowerCase() ] ?? m;
	} )
	.trim();

/** Steps of the visible pergel/breadcrumb trail, or null when the page has none. */
const visibleTrail = ( html ) => {
	const nav = html.match( /<nav\b[^>]*\bwp-block-pergel-breadcrumb\b[^>]*>([\s\S]*?)<\/nav>/i );
	if ( ! nav ) {
		return null;
	}
	return [ ...nav[ 1 ].matchAll( /<li\b[^>]*>([\s\S]*?)<\/li>/gi ) ].map( ( m ) => ( {
		name: decode( m[ 1 ] ),
		url: decode( attrs( m[ 1 ].match( /<a\b[^>]*>/i )?.[ 0 ] ?? '' ).href ?? '' ),
	} ) );
};

/** Steps of a BreadcrumbList node; each plugin words its ListItems a little differently. */
const schemaTrail = ( list ) => [ list.itemListElement ?? [] ].flat()
	.slice()
	.sort( ( a, b ) => ( a.position ?? 0 ) - ( b.position ?? 0 ) )
	.map( ( el ) => ( {
		name: decode( el.name ?? el.item?.name ),
		url: 'string' === typeof el.item ? el.item : el.item?.[ '@id' ] ?? '',
	} ) );

/** Compares the visible trail with the BreadcrumbList; returns the number of visible steps. */
function breadcrumb( where, html, all, trail ) {
	const visible = visibleTrail( html );
	const lists = all.filter( ( n ) => types( n ).includes( 'BreadcrumbList' ) );
	if ( lists.length > 1 ) {
		fail( where, `${ lists.length } BreadcrumbList nodes` );
	}
	if ( ! visible ) {
		if ( trail ) {
			fail( where, 'no visible breadcrumb' );
		}
		return 0;
	}
	if ( 1 !== lists.length ) {
		fail( where, 'visible breadcrumb without a BreadcrumbList' );
		return visible.length;
	}
	const schema = schemaTrail( lists[ 0 ] );
	const names = ( steps ) => steps.map( ( s ) => s.name ).join( ' > ' );
	if ( names( visible ) !== names( schema ) ) {
		fail( where, `breadcrumb "${ names( visible ) }" but BreadcrumbList "${ names( schema ) }"` );
	}
	// The current page is not a link in the visible trail, so links are compared up to it.
	visible.slice( 0, -1 ).forEach( ( step, i ) => {
		if ( step.url !== schema[ i ]?.url ) {
			fail( where, `breadcrumb step ${ i + 1 } links to ${ step.url }, BreadcrumbList to ${ schema[ i ]?.url }` );
		}
	} );
	return visible.length;
}

async function page( { path, indexable, described = false, trail = false, questions = 0, status = 200, noindex = false } ) {
	const res = await get( BASE + path, { redirect: 'follow' } );
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
	if ( noindex ) {
		const robots = tags( head, 'meta' ).filter( ( a ) => ( a.name ?? '' ).toLowerCase() === 'robots' ).map( ( a ) => a.content ?? '' );
		if ( 1 !== robots.length || ! /noindex/i.test( robots[ 0 ] ) ) {
			fail( where, `robots ${ JSON.stringify( robots ) }, expected one tag with noindex` );
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
	const faqs = all.filter( ( n ) => types( n ).includes( 'FAQPage' ) );
	Object.assign( row, { ld: scripts.length, pergel, organization: orgs.length, website: sites.length, trail: breadcrumb( where, html, all, trail ), faq: faqs.length } );
	if ( faqs.length !== ( questions ? 1 : 0 ) ) {
		fail( where, `${ faqs.length } FAQPage nodes, expected ${ questions ? 1 : 0 }` );
	} else if ( questions && [ faqs[ 0 ].mainEntity ?? [] ].flat().length !== questions ) {
		fail( where, `FAQPage has ${ [ faqs[ 0 ].mainEntity ?? [] ].flat().length } questions, expected ${ questions }` );
	}
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
		const res = await get( BASE + path, { redirect: 'follow' } );
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
	const robots = await get( BASE + '/robots.txt' );
	let listed = [];
	if ( 200 === robots.status ) {
		listed = [ ...new Set( [ ...( await robots.text() ).matchAll( /^sitemap:\s*(\S+)/gim ) ].map( ( m ) => m[ 1 ] ) ) ]
			.filter( ( u ) => ! /\.rss$/i.test( u ) );
		if ( listed.length > 1 ) {
			fail( where, `robots.txt lists ${ listed.length } XML sitemaps: ${ listed.join( ', ' ) }` );
		}
		const served = lists.flat();
		for ( const url of listed ) {
			const res = await get( url, { redirect: 'follow' } );
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
