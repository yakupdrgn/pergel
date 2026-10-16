/**
 * Wishlist and compare (Pergel\Woo\Lists): the buttons on products, the
 * counts in the header and the list pages.
 *
 * A visitor's lists are kept in this browser (localStorage), so cached pages
 * stay the same for everyone. A logged-in customer's wishlist is kept in
 * their account: it comes with the page, every change is sent to
 * /pergel/v1/wishlist, and a list saved in the browser before logging in is
 * moved into the account. The list pages ask /pergel/v1/shop-list for the
 * products' markup. Changes show at once and are taken back when saving
 * fails; other tabs follow through the storage event.
 */

const node = document.getElementById( 'wp-script-module-data-pergel/shop-lists' );
let config = null;
try {
	config = JSON.parse( node ? node.textContent : 'null' );
} catch {
	config = null;
}

if ( config && Array.isArray( config.kinds ) ) {
	const kinds = config.kinds;
	const account = config.user && kinds.includes( 'wishlist' );
	const key = ( kind ) => `pergel-${ kind }`;
	const lists = {};

	const clean = ( ids, kind ) => {
		const seen = [];
		for ( const id of Array.isArray( ids ) ? ids : [] ) {
			const number = Number.parseInt( id, 10 );
			if ( number > 0 && ! seen.includes( number ) ) {
				seen.push( number );
			}
		}
		return seen.slice( -config.limits[ kind ] );
	};

	const local = ( kind ) => {
		try {
			return clean( JSON.parse( window.localStorage.getItem( key( kind ) ) || '[]' ), kind );
		} catch {
			return [];
		}
	};

	const keep = ( kind, ids ) => {
		try {
			if ( ids.length ) {
				window.localStorage.setItem( key( kind ), JSON.stringify( ids ) );
			} else {
				window.localStorage.removeItem( key( kind ) );
			}
		} catch {
			// Storage blocked: the list lasts as long as the page.
		}
	};

	const status = document.createElement( 'div' );
	status.setAttribute( 'aria-live', 'polite' );
	status.style.cssText = 'position:absolute;width:1px;height:1px;margin:-1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap';
	document.body.append( status );
	const announce = ( text ) => {
		status.textContent = '';
		window.setTimeout( () => {
			status.textContent = text;
		}, 50 );
	};

	const send = async ( action, ids ) => {
		const response = await window.fetch( config.save, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			body: JSON.stringify( { action, ids } ),
		} );
		if ( ! response.ok ) {
			throw new Error( String( response.status ) );
		}
		const answer = await response.json();
		return clean( answer.ids, 'wishlist' );
	};

	const show = () => {
		for ( const button of document.querySelectorAll( '[data-pergel-list]' ) ) {
			const kind = button.dataset.pergelList;
			if ( ! lists[ kind ] ) {
				continue;
			}
			const id = Number.parseInt( button.dataset.pergelProduct, 10 );
			button.setAttribute( 'aria-pressed', lists[ kind ].includes( id ) ? 'true' : 'false' );
			button.hidden = false;
		}
		for ( const count of document.querySelectorAll( '[data-pergel-count]' ) ) {
			const ids = lists[ count.dataset.pergelCount ] || [];
			count.textContent = String( ids.length );
			count.hidden = 0 === ids.length;
		}
	};

	const draw = async ( region, focus ) => {
		const kind = region.dataset.pergelShopList;
		const body = region.querySelector( '.wp-block-pergel-shop-list__body' );
		if ( ! lists[ kind ] || ! body ) {
			return;
		}
		const ids = lists[ kind ];
		const settle = () => {
			region.setAttribute( 'aria-busy', 'false' );
			if ( focus ) {
				region.focus();
			}
		};
		if ( ! ids.length ) {
			const empty = document.createElement( 'p' );
			empty.className = 'wp-block-pergel-shop-list__empty';
			empty.textContent = config.empties[ kind ];
			body.replaceChildren( empty );
			settle();
			return;
		}
		region.setAttribute( 'aria-busy', 'true' );
		const url = new URL( config.list );
		url.searchParams.set( 'kind', kind );
		url.searchParams.set( 'ids', ids.join( ',' ) );
		try {
			// With the nonce a customer's prices follow their account; without it
			// the answer is the same for every visitor.
			const response = await window.fetch( url, config.user ? { headers: { 'X-WP-Nonce': config.nonce } } : { credentials: 'omit' } );
			if ( ! response.ok ) {
				throw new Error( String( response.status ) );
			}
			const answer = await response.json();
			body.innerHTML = answer.html;
			// Products that were deleted or hidden since they were saved.
			const shown = clean( answer.ids, kind );
			if ( shown.length !== ids.length && ! ( account && 'wishlist' === kind ) ) {
				lists[ kind ] = shown;
				keep( kind, shown );
				show();
			}
		} catch {
			announce( config.texts.failed );
		}
		settle();
	};

	const redraw = ( kind, focus = false ) => {
		for ( const region of document.querySelectorAll( `[data-pergel-shop-list="${ kind }"]` ) ) {
			draw( region, focus );
		}
	};

	const words = ( kind, added ) => {
		if ( 'compare' === kind ) {
			return added ? config.texts.compare : config.texts.dropped;
		}
		return added ? config.texts.added : config.texts.removed;
	};

	const change = async ( kind, id, add, fromList ) => {
		const before = lists[ kind ];
		if ( add === before.includes( id ) ) {
			return;
		}
		if ( add && 'compare' === kind && before.length >= config.limits.compare ) {
			announce( config.texts.full );
			return;
		}
		lists[ kind ] = add ? clean( [ ...before, id ], kind ) : before.filter( ( item ) => item !== id );
		show();
		if ( ! ( account && 'wishlist' === kind ) ) {
			keep( kind, lists[ kind ] );
			announce( words( kind, add ) );
			redraw( kind, fromList );
			return;
		}
		try {
			lists.wishlist = await send( add ? 'add' : 'remove', [ id ] );
			announce( words( kind, add ) );
			redraw( kind, fromList );
		} catch {
			lists.wishlist = before;
			announce( config.texts.failed );
		}
		show();
	};

	for ( const kind of kinds ) {
		lists[ kind ] = local( kind );
	}

	let moving = Promise.resolve( false );
	if ( account ) {
		const guest = lists.wishlist;
		lists.wishlist = clean( config.saved, 'wishlist' );
		if ( guest.some( ( id ) => ! lists.wishlist.includes( id ) ) ) {
			moving = send( 'merge', guest ).then(
				( ids ) => {
					lists.wishlist = ids;
					keep( 'wishlist', [] );
					return true;
				},
				() => false
			);
		} else {
			keep( 'wishlist', [] );
		}
	}

	show();
	moving.then( ( moved ) => {
		show();
		for ( const region of document.querySelectorAll( '[data-pergel-shop-list]' ) ) {
			if ( moved || 'true' !== region.dataset.pergelDrawn ) {
				draw( region, false );
			}
		}
	} );

	document.addEventListener( 'click', ( event ) => {
		const target = event.target instanceof Element ? event.target : null;
		const toggle = target && target.closest( '[data-pergel-list]' );
		if ( toggle && lists[ toggle.dataset.pergelList ] ) {
			const kind = toggle.dataset.pergelList;
			const id = Number.parseInt( toggle.dataset.pergelProduct, 10 );
			change( kind, id, ! lists[ kind ].includes( id ), false );
			return;
		}
		const remove = target && target.closest( '[data-pergel-remove]' );
		if ( remove && lists[ remove.dataset.pergelRemove ] ) {
			change( remove.dataset.pergelRemove, Number.parseInt( remove.dataset.pergelProduct, 10 ), false, true );
		}
	} );

	window.addEventListener( 'storage', ( event ) => {
		const kind = kinds.find( ( item ) => key( item ) === event.key );
		if ( ! kind || ( account && 'wishlist' === kind ) ) {
			return;
		}
		lists[ kind ] = local( kind );
		show();
		redraw( kind );
	} );
}
