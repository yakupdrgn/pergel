/**
 * Keeps the cart's numbers on the page current: the free shipping bars
 * (Pergel\Woo\FreeShipping) and the cart count of the bar at the bottom of
 * phones (Pergel\Woo\BottomBar).
 *
 * Both come drawn from the server. When WooCommerce says the cart changed,
 * the cart is read once from the Store API, whose response holds the free
 * shipping bar's new markup (extensions.pergel) and the number of items.
 * Several signals close together make one request.
 */

const data = ( () => {
	try {
		return JSON.parse(
			document.getElementById( 'wp-script-module-data-pergel/cart-sync' )
				?.textContent || '{}'
		);
	} catch {
		return {};
	}
} )();

let timer = 0;
let asked = 0;

const show = ( cart ) => {
	const html = cart?.extensions?.pergel?.free_shipping?.html;
	if ( typeof html === 'string' ) {
		for ( const bar of document.querySelectorAll( '[data-pergel-shipping]' ) ) {
			bar.innerHTML = html;
			bar.hidden = html === '';
		}
	}
	if ( Number.isInteger( cart?.items_count ) ) {
		for ( const count of document.querySelectorAll( '[data-pergel-cart-count]' ) ) {
			count.textContent = String( cart.items_count );
			count.hidden = cart.items_count === 0;
		}
	}
};

const refresh = async () => {
	const call = ++asked;
	try {
		const response = await fetch( data.cart, {
			credentials: 'same-origin',
			headers: { Accept: 'application/json' },
		} );
		if ( response.ok && call === asked ) {
			show( await response.json() );
		}
	} catch {
		// Offline or blocked: the page keeps what it showed.
	}
};

const later = () => {
	window.clearTimeout( timer );
	timer = window.setTimeout( refresh, 300 );
};

if ( data.cart ) {
	document.body.addEventListener( 'wc-blocks_added_to_cart', later );
	document.body.addEventListener( 'wc-blocks_removed_from_cart', later );
	window.addEventListener( 'wc-blocks_store_sync_required', later );
	// A page from the back-forward cache may show an older cart.
	window.addEventListener( 'pageshow', ( event ) => {
		if ( event.persisted ) {
			later();
		}
	} );
}
