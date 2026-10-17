/**
 * Sales modes on a live shop: a product in quote mode and a category in
 * catalog mode show no price and no cart form, and their products cannot
 * be put in the cart, not through the Store API and not through the classic
 * ?add-to-cart link either.
 */
import { test, expect } from 'playwright/test';
import { wp, postId, productForm } from './helpers.mjs';

/** Price blocks of the product itself, not those of related products. */
const ownPrice = ( page ) => page.locator( '.wp-block-woocommerce-product-price:not(.wp-block-woocommerce-product-collection *)' );

async function cartCount( page ) {
	const cart = await ( await page.request.get( '/wp-json/wc/store/v1/cart' ) ).json();
	return cart.items_count;
}

async function expectNotSold( page, slug, id ) {
	await page.goto( `/product/${ slug }/` );
	await expect( page.locator( 'h1' ) ).toBeVisible();
	await expect( productForm( page ) ).toHaveCount( 0 );
	for ( const price of await ownPrice( page ).allTextContents() ) {
		expect( price.trim() ).toBe( '' );
	}
	const offers = await page.locator( 'script[type="application/ld+json"]' ).allTextContents();
	expect( offers.join( '' ) ).not.toMatch( /"price"\s*:/ );

	const cart = await page.request.get( '/wp-json/wc/store/v1/cart' );
	const add = await page.request.post( '/wp-json/wc/store/v1/cart/add-item', {
		headers: { Nonce: cart.headers().nonce },
		data: { id: Number( id ), quantity: 1 },
	} );
	expect( add.status(), await add.text() ).toBe( 400 );

	await page.goto( `/?add-to-cart=${ id }` );
	expect( await cartCount( page ) ).toBe( 0 );
}

test( 'a product in quote mode cannot be bought', async ( { page } ) => {
	const id = postId( 'product', 'canvas-tote-bag' );
	wp( 'post', 'meta', 'update', id, '_pergel_sales_mode', 'quote' );
	try {
		await expectNotSold( page, 'canvas-tote-bag', id );
	} finally {
		wp( 'post', 'meta', 'delete', id, '_pergel_sales_mode' );
	}
	// Back on sale, the same product goes in the cart.
	await page.goto( '/product/canvas-tote-bag/' );
	await expect( productForm( page ).getByRole( 'button', { name: /add to cart/i } ) ).toBeVisible();
} );

test( 'a category in catalog mode passes it to its products', async ( { page } ) => {
	const term = wp( 'term', 'list', 'product_cat', '--slug=stationery', '--field=term_id' );
	const id = postId( 'product', 'a5-notebook' );
	wp( 'term', 'meta', 'update', term, 'pergel_sales_mode', 'catalog' );
	try {
		await expectNotSold( page, 'a5-notebook', id );
	} finally {
		wp( 'term', 'meta', 'delete', term, 'pergel_sales_mode' );
	}
} );
