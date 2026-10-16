/**
 * Frequently bought together (Pergel\Woo\BoughtTogether): keeps the total
 * with the checkboxes and adds the chosen products to the cart through the
 * Store API in one batch. The nonce comes from a Store API response, not
 * from the page, so a cached page still works. Without the script the form
 * posts to the product page.
 */
const block = document.querySelector( '[data-pergel-together]' );

let config = null;
try {
	config = block && JSON.parse( block.dataset.pergelTogether );
} catch {
	// The form still works without the script.
}

const form = config && block.querySelector( 'form' );
const button = form && form.querySelector( 'button[type="submit"]' );

/** A price as the shop writes it: its decimals, separators and format. */
const money = ( amount ) => {
	const { decimals, point, thousand, pattern, symbol } = config.format;
	const [ whole, fraction ] = Math.abs( amount ).toFixed( decimals ).split( '.' );
	const number = whole.replace( /\B(?=(\d{3})+(?!\d))/g, thousand ) + ( fraction ? point + fraction : '' );
	return ( amount < 0 ? '-' : '' ) + pattern.replace( '%1$s', symbol ).replace( '%2$s', number );
};

/** Text of a Store API message, which may carry entities or tags. */
const plain = ( html ) => new window.DOMParser().parseFromString( String( html || '' ), 'text/html' ).body.textContent.trim();

if ( form && button ) {
	const boxes = [ ...form.querySelectorAll( 'input[type="checkbox"]' ) ];
	const main = boxes.find( ( box ) => box.value === String( config.product ) );
	const count = form.querySelector( '.wp-block-pergel-bought-together__count' );
	const amount = form.querySelector( '.wp-block-pergel-bought-together__amount' );
	const status = form.querySelector( '.wp-block-pergel-bought-together__status' );
	let options = null;

	const say = ( text, failed, link ) => {
		status.textContent = text;
		status.classList.toggle( 'is-error', failed );
		if ( link ) {
			const a = document.createElement( 'a' );
			a.href = config.cartUrl;
			a.textContent = config.viewCart;
			status.append( ' ', a );
		}
	};

	const update = () => {
		const chosen = boxes.filter( ( box ) => box.checked && ! box.disabled );
		const total = chosen.reduce( ( sum, box ) => sum + ( parseFloat( box.dataset.price ) || 0 ), 0 );
		count.textContent = config.totals[ chosen.length ] || '';
		amount.textContent = money( total );
	};

	// The product itself, when it has options: it joins once they are chosen above.
	if ( main && config.variations ) {
		const cart = [ ...document.querySelectorAll( 'form.wp-block-woocommerce-add-to-cart-with-options' ) ]
			.filter( ( f ) => f.compareDocumentPosition( block ) & Node.DOCUMENT_POSITION_FOLLOWING )
			.pop();
		const hint = form.querySelector( `#${ main.id }-hint` );
		const price = main.closest( 'li' ).querySelector( '.wp-block-pergel-bought-together__price' );
		const range = price.innerHTML;
		let joined = false;

		const sync = () => {
			const fields = cart ? [ ...cart.querySelectorAll( '[name^="attribute_"]' ) ] : [];
			const values = Object.fromEntries( fields.map( ( field ) => [ field.name, field.value ] ) );
			const complete = fields.length > 0 && fields.every( ( field ) => '' !== field.value );
			const match = complete
				? config.variations.find( ( v ) => Object.entries( v.attributes ).every( ( [ name, value ] ) => '' === value || values[ name ] === value ) )
				: null;
			const ready = !! ( match && match.buyable );
			options = ready ? fields.map( ( field ) => ( { attribute: field.name, value: field.value } ) ) : null;
			main.dataset.price = ready ? String( match.price ) : '0';
			// The chosen option's price in place of the range.
			if ( ready ) {
				price.textContent = money( match.price );
			} else if ( price.innerHTML !== range ) {
				price.innerHTML = range;
			}
			if ( main.disabled === ready ) {
				main.disabled = ! ready;
				hint.hidden = ready;
				if ( ready ) {
					main.removeAttribute( 'aria-describedby' );
				} else {
					main.setAttribute( 'aria-describedby', hint.id );
				}
				// Chosen by default the first time, like the other products.
				if ( ready && ! joined ) {
					main.checked = true;
					joined = true;
				}
			}
			update();
		};

		if ( cart ) {
			// The options are buttons that set hidden fields; read them once they have.
			const later = () => window.requestAnimationFrame( sync );
			new window.MutationObserver( later ).observe( cart, { attributes: true, attributeFilter: [ 'aria-checked', 'value', 'class' ], subtree: true } );
			cart.addEventListener( 'change', later );
			cart.addEventListener( 'click', later );
		}
		sync();
	}

	form.addEventListener( 'change', ( event ) => {
		if ( event.target.matches( 'input[type="checkbox"]' ) ) {
			update();
			say( '', false );
		}
	} );

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		if ( 'true' === button.getAttribute( 'aria-disabled' ) ) {
			return;
		}
		const chosen = boxes.filter( ( box ) => box.checked && ! box.disabled );
		if ( ! chosen.length ) {
			say( config.none, true );
			return;
		}
		button.setAttribute( 'aria-disabled', 'true' );
		say( '', false );
		try {
			// A fresh nonce for this visitor's session.
			const session = await window.fetch( config.cart, { credentials: 'same-origin', headers: { Accept: 'application/json' } } );
			const nonce = session.headers.get( 'Nonce' ) || '';
			const requests = chosen.map( ( box ) => {
				const body = { id: Number( box.value ), quantity: 1 };
				if ( box === main && options ) {
					body.variation = options;
				}
				return { method: 'POST', path: '/wc/store/v1/cart/add-item', body, headers: { Nonce: nonce } };
			} );
			const response = await window.fetch( config.batch, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json', Nonce: nonce },
				body: JSON.stringify( { requests } ),
			} );
			const data = await response.json();
			const results = Array.isArray( data.responses ) ? data.responses : [];
			const added = results.filter( ( r ) => r.status >= 200 && r.status < 300 ).length;
			const errors = results.filter( ( r ) => ! ( r.status >= 200 && r.status < 300 ) ).map( ( r ) => plain( r.body && r.body.message ) ).filter( Boolean );
			if ( added ) {
				// The mini cart and WooCommerce's other blocks fetch the cart again.
				window.dispatchEvent( new window.CustomEvent( 'wc-blocks_store_sync_required', { detail: { type: 'from_@wordpress/data' } } ) );
				document.body.dispatchEvent( new window.CustomEvent( 'wc-blocks_added_to_cart', { bubbles: true, cancelable: true, detail: { preserveCartData: false } } ) );
			}
			const message = [ added ? config.added[ added ] : '', ...errors ].filter( Boolean ).join( ' ' );
			say( message || plain( data.message ) || config.failed, ! added, added > 0 );
		} catch {
			say( config.failed, true );
		} finally {
			button.removeAttribute( 'aria-disabled' );
		}
	} );

	update();
}
