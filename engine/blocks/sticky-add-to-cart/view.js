/**
 * Sticky add-to-cart bar (Pergel\Woo\StickyCart): shown while the
 * add-to-cart form is scrolled past. Its button presses the form's own
 * button, so the quantity, the options, the AJAX request and the mini cart
 * stay WooCommerce's. While the form cannot be sent, it takes the visitor
 * to the first option not chosen yet. The price and the button text follow
 * the form's.
 */
const bar = document.querySelector( '[data-pergel-sticky]' );

const before = ( node, other ) => !! ( node.compareDocumentPosition( other ) & Node.DOCUMENT_POSITION_FOLLOWING );

/** The add-to-cart form just before the bar. */
const findForm = () => [ ...document.querySelectorAll( 'form.wp-block-woocommerce-add-to-cart-with-options' ) ].filter( ( form ) => before( form, bar ) ).pop();

/** The product's own price: the last one before the form outside product lists. */
const findPrice = ( form ) => [ ...document.querySelectorAll( '.wp-block-woocommerce-product-price' ) ]
	.filter( ( price ) => before( price, form ) && ! price.closest( '.wp-block-woocommerce-product-collection, .wp-block-woocommerce-product-template' ) )
	.pop();

/** The first option not chosen yet, or the form's first control. */
const firstOpen = ( form ) => {
	const group = [ ...form.querySelectorAll( '[role="radiogroup"]' ) ].find( ( g ) => ! g.querySelector( '[aria-checked="true"]' ) );
	const radio = group && group.querySelector( '[role="radio"]:not(:disabled)' );
	const select = [ ...form.querySelectorAll( 'select' ) ].find( ( s ) => ! s.value );
	return radio || select || form.querySelector( 'button:not(:disabled), input:not([type="hidden"]), select' );
};

const form = bar && findForm();

if ( bar && form && 'IntersectionObserver' in window ) {
	const root = document.documentElement;
	const button = bar.querySelector( '.wp-block-pergel-sticky-add-to-cart__button' );
	const price = bar.querySelector( '.wp-block-pergel-sticky-add-to-cart__price' );
	const source = findPrice( form );
	const reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	const real = () => form.querySelector( '.single_add_to_cart_button, button[type="submit"]' );
	const blocked = () => {
		const submit = real();
		return form.classList.contains( 'is-invalid' ) || ! submit || submit.hidden || 'true' === submit.getAttribute( 'aria-disabled' );
	};

	const sync = () => {
		const submit = real();
		const text = submit && ( submit.querySelector( '[data-wp-text]' ) || submit ).textContent.trim();
		const label = bar.dataset.choose && blocked() ? bar.dataset.choose : text;
		if ( label && button.textContent !== label ) {
			button.textContent = label;
		}
		const shown = source && ( source.querySelector( '.wc-block-components-product-price' ) || source );
		if ( shown && price.innerHTML !== shown.innerHTML ) {
			price.innerHTML = shown.innerHTML;
		}
	};

	button.addEventListener( 'click', () => {
		if ( ! blocked() ) {
			real().click();
			return;
		}
		form.scrollIntoView( { block: 'center', behavior: reduce.matches ? 'auto' : 'smooth' } );
		const target = firstOpen( form );
		if ( target ) {
			target.focus( { preventScroll: true } );
		}
	} );

	// Only once the button is above the screen, not before it is reached:
	// the area reaches far below the screen, so leaving it means scrolling
	// past, even with a jump (End key, a link) that never crosses the screen.
	const watched = form.querySelector( '.wp-block-woocommerce-product-button' ) || form;
	new window.IntersectionObserver(
		( [ entry ] ) => bar.classList.toggle( 'is-shown', ! entry.isIntersecting ),
		{ rootMargin: '0px 0px 100000px 0px' }
	).observe( watched );

	const watch = new window.MutationObserver( sync );
	watch.observe( form, { attributes: true, attributeFilter: [ 'class' ], subtree: true, childList: true, characterData: true } );
	if ( source ) {
		watch.observe( source, { subtree: true, childList: true, characterData: true } );
	}

	// The page keeps room for the bar, and focus is never hidden under it.
	const measure = () => root.style.setProperty( '--pergel-sticky-cart', `${ bar.offsetHeight }px` );
	if ( 'ResizeObserver' in window ) {
		new window.ResizeObserver( measure ).observe( bar );
	}
	bar.hidden = false;
	root.classList.add( 'has-pergel-sticky-cart' );
	measure();
	sync();
}
