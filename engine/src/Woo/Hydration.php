<?php
/**
 * Lighter product data for the shop's Interactivity state.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * WooCommerce's product blocks put every product they show into the
 * Interactivity state, through a Store API response made on the server
 * (Hydration). That response formats the product's full description: the
 * whole description runs through the block renderer once per product card,
 * and the result is dropped, since the state keeps no description. With
 * long descriptions (tables, details, lists) this was about half of a
 * listing's server time (90 products: 4.1 s, 2.2 s without it), and it put
 * the styles of the blocks it drew into the page.
 *
 * While WooCommerce hydrates a product, its description reads as empty.
 * Product blocks, the description block and the Store API over HTTP are not
 * touched: only the server-side hydration between its two filters is.
 */
final class Hydration {

	/**
	 * Whether a product hydration is running now.
	 *
	 * @var bool
	 */
	private static bool $product = false;

	/**
	 * Hooks the filters.
	 */
	public static function register(): void {
		add_filter( 'woocommerce_hydration_dispatch_request', array( self::class, 'start' ), 10, 3 );
		add_filter( 'woocommerce_hydration_request_after_callbacks', array( self::class, 'stop' ) );
		add_filter( 'woocommerce_product_get_description', array( self::class, 'description' ), 999 );
		add_filter( 'woocommerce_product_variation_get_description', array( self::class, 'description' ), 999 );
	}

	/**
	 * Filter for woocommerce_hydration_dispatch_request: notes a product
	 * hydration, and leaves the result to WooCommerce.
	 *
	 * @param mixed  $result  Result to use instead of the request; null runs it.
	 * @param mixed  $request The request.
	 * @param string $path    Requested path.
	 * @return mixed
	 */
	public static function start( mixed $result, mixed $request = null, string $path = '' ): mixed {
		self::$product = null === $result && 1 === preg_match( '#^/wc/store(/v\d+)?/products/\d+/?$#', $path );
		return $result;
	}

	/**
	 * Filter for woocommerce_hydration_request_after_callbacks.
	 *
	 * @param mixed $response The response.
	 * @return mixed
	 */
	public static function stop( mixed $response ): mixed {
		self::$product = false;
		return $response;
	}

	/**
	 * Filter for the description getters: empty during a product hydration.
	 *
	 * @param mixed $description The description.
	 * @return mixed
	 */
	public static function description( mixed $description ): mixed {
		return self::$product ? '' : $description;
	}
}
