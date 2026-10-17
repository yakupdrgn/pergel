/**
 * Buying from the shop starter's catalog: the block checkout as a guest,
 * the classic checkout shortcode, and a customer who logs in and buys a
 * variable product. The cart and the checkout with an item in them are
 * checked with axe on the way (the performance budget sees them empty).
 */
import { randomBytes } from 'node:crypto';
import { test, expect } from 'playwright/test';
import { wp, postId, expectAccessible, addToCart, placeOrder, receivedOrder } from './helpers.mjs';

/**
 * Opens the block checkout. The block draws its form only after about
 * 660 KB of WooCommerce scripts have loaded, which takes a while on php -S.
 */
async function openCheckout( page ) {
	await page.goto( '/checkout/' );
	await expect( page.getByRole( 'button', { name: 'Place Order' } ) ).toBeVisible( { timeout: 20_000 } );
}

/** Fills the block checkout's address form. */
async function fillBlockAddress( page ) {
	await page.getByLabel( 'First name' ).fill( 'Deniz' );
	await page.getByLabel( 'Last name' ).fill( 'Yılmaz' );
	await page.getByLabel( 'Address', { exact: true } ).fill( 'Test Sokak 2' );
	await page.getByLabel( 'Postal code' ).fill( '34000' );
	await page.getByLabel( 'City' ).fill( 'Istanbul' );
}

test( 'a guest pays cash on delivery at the block checkout', async ( { page } ) => {
	await addToCart( page, 'canvas-tote-bag' );

	await page.goto( '/cart/' );
	await expect( page.getByRole( 'link', { name: 'Canvas Tote Bag' } ).first() ).toBeVisible();
	await expectAccessible( page );

	await openCheckout( page );
	await expectAccessible( page );

	await page.getByLabel( 'Email address' ).fill( 'guest@example.com' );
	await fillBlockAddress( page );
	await page.getByLabel( 'Cash on delivery' ).check();
	await placeOrder( page );

	const order = await receivedOrder( page );
	expect( order ).toMatchObject( { method: 'cod', customer: 0, via: 'store-api' } );
	await expect( page.getByText( 'Canvas Tote Bag' ).first() ).toBeVisible();
	await expectAccessible( page );
} );

test.describe( 'the classic checkout', () => {
	let checkout;

	test.beforeAll( () => {
		checkout = wp( 'option', 'get', 'woocommerce_checkout_page_id' );
		wp( 'option', 'update', 'woocommerce_checkout_page_id', postId( 'page', 'pergel-classic-checkout' ) );
	} );

	test.afterAll( () => {
		wp( 'option', 'update', 'woocommerce_checkout_page_id', checkout );
	} );

	test( 'a guest pays by bank transfer', async ( { page } ) => {
		await addToCart( page, 'ceramic-mug' );

		await page.goto( '/pergel-classic-checkout/' );
		await expect( page.locator( '#place_order' ) ).toBeVisible();
		await page.locator( '#billing_first_name' ).fill( 'Deniz' );
		await page.locator( '#billing_last_name' ).fill( 'Yılmaz' );
		await page.locator( '#billing_address_1' ).fill( 'Test Sokak 2' );
		await page.locator( '#billing_postcode' ).fill( '34000' );
		await page.locator( '#billing_city' ).fill( 'Istanbul' );
		await page.locator( '#billing_email' ).fill( 'classic@example.com' );
		await page.locator( 'label[for="payment_method_bacs"]' ).click();
		await expect( page.locator( '#payment_method_bacs' ) ).toBeChecked();
		// The form is refreshed after each change; wait until it settles.
		await expect( page.locator( '.blockUI' ) ).toHaveCount( 0 );
		await page.locator( '#place_order' ).click();

		const order = await receivedOrder( page );
		expect( order ).toMatchObject( { method: 'bacs', customer: 0, via: 'checkout' } );
	} );
} );

test.describe( 'a customer', () => {
	const login = `pergel-customer-${ Date.now().toString( 36 ) }`;
	const password = randomBytes( 18 ).toString( 'base64url' );
	let user;

	test.beforeAll( () => {
		user = Number( wp( 'user', 'create', login, `${ login }@example.com`, '--role=customer', `--user_pass=${ password }`, '--porcelain' ) );
	} );

	test.afterAll( () => {
		wp( 'user', 'delete', String( user ), '--yes' );
	} );

	test( 'logs in, buys a variable product and finds the order in the account', async ( { page } ) => {
		await page.goto( '/my-account/' );
		await page.locator( '#username' ).fill( login );
		await page.locator( '#password' ).fill( password );
		await page.locator( 'button[name="login"]' ).click();
		await expect( page.locator( '.woocommerce-MyAccount-navigation' ) ).toBeVisible();
		await expectAccessible( page );

		await addToCart( page, 'basic-t-shirt', [ 'Navy', 'L' ] );
		await openCheckout( page );
		await expect( page.getByLabel( 'Email address' ) ).toHaveValue( `${ login }@example.com` );
		await fillBlockAddress( page );
		await page.getByLabel( 'Cash on delivery' ).check();
		await placeOrder( page );

		const order = await receivedOrder( page );
		expect( order ).toMatchObject( { method: 'cod', customer: user, via: 'store-api' } );
		expect( order.variation ).toBeGreaterThan( 0 );

		await page.goto( '/my-account/orders/' );
		await expect( page.getByRole( 'link', { name: `View order number ${ order.id }` } ) ).toBeVisible();
		await expectAccessible( page );
	} );
} );
