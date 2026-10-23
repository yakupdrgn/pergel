/**
 * Product page extras (Pergel\Woo\ProductPage): "Buy now", and the buttons
 * that copy a coupon code or the page's address.
 *
 * "Buy now" sends the add-to-cart form, so the quantity, the options and
 * the request stay WooCommerce's; once WooCommerce reports the product
 * added (wc-blocks_added_to_cart), it opens the checkout. While options
 * are missing, it takes the visitor to the first one instead, like the
 * sticky bar's button.
 */

const node = document.getElementById(
	'wp-script-module-data-pergel/product-page'
);
let config = {};
try {
	config = JSON.parse( node ? node.textContent : '{}' ) || {};
} catch {
	config = {};
}
const texts = config.texts || {};
const reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' );

/* Buy now. */

/** The product's own add-to-cart form, not one in a product list. */
const findForm = () =>
	[
		...document.querySelectorAll(
			'form.wp-block-woocommerce-add-to-cart-with-options'
		),
	].find(
		( form ) =>
			! form.closest(
				'.wp-block-woocommerce-product-collection, .wp-block-woocommerce-product-template'
			)
	);

/** The first option not chosen yet, or the form's first control. */
const firstOpen = ( form ) => {
	const group = [ ...form.querySelectorAll( '[role="radiogroup"]' ) ].find(
		( g ) => ! g.querySelector( '[aria-checked="true"]' )
	);
	const radio = group && group.querySelector( '[role="radio"]:not(:disabled)' );
	const select = [ ...form.querySelectorAll( 'select' ) ].find(
		( s ) => ! s.value
	);
	return (
		radio ||
		select ||
		form.querySelector( 'button:not(:disabled), input:not([type="hidden"]), select' )
	);
};

let waiting = 0;

const buyNow = ( button ) => {
	const form = findForm();
	if ( ! form || ! config.checkout ) {
		return;
	}
	const submit = form.querySelector(
		'.single_add_to_cart_button, button[type="submit"]'
	);
	if (
		form.classList.contains( 'is-invalid' ) ||
		! submit ||
		'true' === submit.getAttribute( 'aria-disabled' )
	) {
		form.scrollIntoView( {
			block: 'center',
			behavior: reduce.matches ? 'auto' : 'smooth',
		} );
		const target = firstOpen( form );
		if ( target ) {
			target.focus( { preventScroll: true } );
		}
		return;
	}
	button.setAttribute( 'aria-busy', 'true' );
	window.clearTimeout( waiting );
	const done = () => {
		window.clearTimeout( waiting );
		document.body.removeEventListener( 'wc-blocks_added_to_cart', added );
		button.removeAttribute( 'aria-busy' );
	};
	const added = () => {
		done();
		window.location.assign( config.checkout );
	};
	document.body.addEventListener( 'wc-blocks_added_to_cart', added );
	// No answer (an error WooCommerce shows by the form): give up waiting.
	waiting = window.setTimeout( done, 20000 );
	submit.click();
};

/* Copy a coupon code or the address. */

const copy = async ( button ) => {
	const text = button.dataset.pergelCopy || '';
	const status = button
		.closest( '.pergel-coupons, .pergel-share' )
		?.querySelector( '[data-pergel-copy-status]' );
	let message = texts.copied || '';
	try {
		await navigator.clipboard.writeText( text );
		button.classList.add( 'is-copied' );
		window.setTimeout( () => button.classList.remove( 'is-copied' ), 2000 );
	} catch {
		message = texts.failed || '';
		const code = button.parentElement?.querySelector( 'code' );
		if ( code ) {
			window.getSelection()?.selectAllChildren( code );
		}
	}
	if ( status ) {
		status.textContent = '';
		window.setTimeout( () => {
			status.textContent = message;
		}, 50 );
	}
};

document.addEventListener( 'click', ( event ) => {
	const target = event.target instanceof Element ? event.target : null;
	const buy = target && target.closest( '[data-pergel-buy-now]' );
	if ( buy ) {
		event.preventDefault();
		buyNow( buy );
		return;
	}
	const copier = target && target.closest( '[data-pergel-copy]' );
	if ( copier ) {
		event.preventDefault();
		copy( copier );
	}
} );
