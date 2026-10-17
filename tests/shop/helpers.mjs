/**
 * Helpers for the shop tests: WP-CLI on the test site, the cart, axe and
 * the orders the checkout makes.
 */
import { execFileSync } from 'node:child_process';
import { expect } from 'playwright/test';
import { AxeBuilder } from '@axe-core/playwright';

const AXE_TAGS = [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ];

/**
 * Runs WP-CLI on the site in WP_DIR and returns its output.
 *
 * @param {...string} args WP-CLI arguments.
 * @return {string} Output without the trailing newline.
 */
export function wp( ...args ) {
	const path = process.env.WP_DIR ? [ `--path=${ process.env.WP_DIR }` ] : [];
	const phar = process.env.PERGEL_WP_PHAR;
	const [ cmd, pre ] = phar ? [ 'php', [ phar ] ] : [ 'wp', [] ];
	return execFileSync( cmd, [ ...pre, ...path, ...args ], { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'inherit' ] } ).trim();
}

/** Id of a post by its slug. */
export function postId( type, slug ) {
	const id = wp( 'post', 'list', `--post_type=${ type }`, `--name=${ slug }`, '--field=ID' );
	expect( id, `${ type } ${ slug }` ).toMatch( /^\d+$/ );
	return id;
}

/** Fails on any WCAG 2.2 A/AA violation on the page as it is now. */
export async function expectAccessible( page ) {
	const { violations } = await new AxeBuilder( { page } ).withTags( AXE_TAGS ).analyze();
	const found = violations.map( ( v ) => {
		const node = v.nodes[ 0 ];
		return `${ v.id } (${ v.nodes.length }): ${ node?.target.join( ' ' ) } ${ [ ...( node?.any ?? [] ), ...( node?.all ?? [] ) ].map( ( c ) => c.message ).join( ' ' ) }`;
	} );
	expect( found, page.url() ).toEqual( [] );
}

/** The product form on a product page; related products have buttons too. */
export const productForm = ( page ) => page.locator( '.wp-block-woocommerce-add-to-cart-with-options' );

/**
 * Adds a product to the cart from its page.
 *
 * @param {import('playwright/test').Page} page
 * @param {string}   slug    Product slug.
 * @param {string[]} options Variation choices, by their labels.
 */
export async function addToCart( page, slug, options = [] ) {
	await page.goto( `/product/${ slug }/` );
	const form = productForm( page );
	for ( const option of options ) {
		await form.getByRole( 'radio', { name: option, exact: true } ).click();
	}
	const badge = page.locator( '.wc-block-mini-cart__badge' ).first();
	const before = Number( ( await badge.textContent() ) || 0 );
	// The badge counts up before the Store API has stored the item.
	const stored = page.waitForResponse( ( r ) => r.url().includes( 'wc/store' ) && 'POST' === r.request().method() );
	await form.getByRole( 'button', { name: /add to cart/i } ).click();
	expect( ( await stored ).ok() ).toBe( true );
	await expect( badge ).toHaveText( String( before + 1 ) );
}

/**
 * The order the page was sent to after the checkout.
 *
 * @return {Promise<{id: string, method: string, customer: number, via: string, variation: number}>}
 */
export async function receivedOrder( page ) {
	// Placing the order and loading the next page take a while on php -S.
	await expect( page ).toHaveURL( /\/order-received\/\d+\//, { timeout: 30_000 } );
	const id = page.url().match( /\/order-received\/(\d+)\// )[ 1 ];
	const order = JSON.parse(
		wp( 'eval', `$o = wc_get_order( ${ id } ); $i = current( $o->get_items() ); echo wp_json_encode( array( 'method' => $o->get_payment_method(), 'customer' => $o->get_customer_id(), 'via' => $o->get_created_via(), 'variation' => $i ? $i->get_variation_id() : 0 ) );` )
	);
	return { id, ...order };
}
