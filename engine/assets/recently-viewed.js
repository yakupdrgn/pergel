/**
 * Recently viewed products (Pergel\Woo\Related).
 *
 * The browser keeps the IDs of the products viewed last, newest first; the
 * server only draws their cards, so a cached page works. A product page
 * adds its own product after the boxes are filled, so a box never shows
 * the product on screen. IDs the server no longer shows are forgotten.
 */

const KEY = 'pergel-recently-viewed';

const data = ( () => {
	try {
		return JSON.parse(
			document.getElementById(
				'wp-script-module-data-pergel/recently-viewed'
			)?.textContent || '{}'
		);
	} catch {
		return {};
	}
} )();

const limit = Number( data.limit ) || 12;
const current = Number( data.product ) || 0;

const read = () => {
	try {
		const list = JSON.parse( window.localStorage.getItem( KEY ) || '[]' );
		return Array.isArray( list )
			? list.filter( ( id ) => Number.isInteger( id ) && id > 0 )
			: [];
	} catch {
		return [];
	}
};

const write = ( list ) => {
	try {
		window.localStorage.setItem(
			KEY,
			JSON.stringify( list.slice( 0, limit ) )
		);
	} catch {
		// Storage is full or turned off: nothing is remembered.
	}
};

const fill = async ( box, seen ) => {
	const count = Number( box.dataset.pergelRecent ) || 4;
	// Asked for all of them, so ones the server drops still leave enough.
	const ids = seen.filter( ( id ) => id !== current );
	if ( ! data.url || ids.length === 0 ) {
		return null;
	}
	const url = new URL( data.url, window.location.href );
	url.searchParams.set( 'ids', ids.join( ',' ) );
	url.searchParams.set( 'count', String( count ) );
	try {
		const response = await fetch( url, {
			headers: { Accept: 'application/json' },
		} );
		if ( ! response.ok ) {
			return null;
		}
		const body = await response.json();
		if ( body.html ) {
			box.querySelector(
				'.wp-block-pergel-recently-viewed__list'
			).innerHTML = body.html;
			box.hidden = false;
		}
		return Array.isArray( body.ids ) ? { asked: ids, shown: body.ids } : null;
	} catch {
		return null;
	}
};

const seen = read();
const results = await Promise.all(
	[ ...document.querySelectorAll( '[data-pergel-recent]' ) ].map( ( box ) =>
		fill( box, seen )
	)
);

let list = seen;
const answer = results.find( Boolean );
if ( answer ) {
	const gone = answer.asked.filter( ( id ) => ! answer.shown.includes( id ) );
	list = list.filter( ( id ) => ! gone.includes( id ) );
}
if ( current > 0 ) {
	list = [ current, ...list.filter( ( id ) => id !== current ) ];
}
if ( list.join() !== seen.join() ) {
	write( list );
}
