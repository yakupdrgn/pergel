<?php
/**
 * The description reads as empty only while a product is hydrated.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Woo;

use Pergel\Woo\Hydration;
use PHPUnit\Framework\TestCase;

/**
 * Calls the filters in the order WooCommerce's Hydration runs them.
 */
final class HydrationTest extends TestCase {

	/**
	 * Ends any hydration a test left open.
	 */
	protected function tearDown(): void {
		Hydration::stop( null );
	}

	/**
	 * Outside a hydration the description is untouched.
	 */
	public function test_description_is_kept_outside_a_hydration(): void {
		$this->assertSame( '<p>Long</p>', Hydration::description( '<p>Long</p>' ) );
	}

	/**
	 * Between the two filters of a product route the description is empty.
	 */
	public function test_description_is_empty_while_a_product_is_hydrated(): void {
		$this->assertNull( Hydration::start( null, null, '/wc/store/v1/products/4913' ) );
		$this->assertSame( '', Hydration::description( '<p>Long</p>' ) );
		$this->assertSame( 'response', Hydration::stop( 'response' ) );
		$this->assertSame( '<p>Long</p>', Hydration::description( '<p>Long</p>' ) );
	}

	/**
	 * Cart, product lists and other product routes keep it.
	 */
	public function test_other_routes_keep_the_description(): void {
		foreach ( array( '/wc/store/v1/cart', '/wc/store/v1/products', '/wc/store/v1/products/12/reviews', '/wc/store/v1/products/attributes/3' ) as $path ) {
			Hydration::start( null, null, $path );
			$this->assertSame( '<p>Long</p>', Hydration::description( '<p>Long</p>' ), $path );
			Hydration::stop( null );
		}
	}

	/**
	 * When another filter answers the request, nothing is formatted, so nothing is changed.
	 */
	public function test_a_result_from_another_filter_keeps_the_description(): void {
		$this->assertSame( 'ready', Hydration::start( 'ready', null, '/wc/store/v1/products/4913' ) );
		$this->assertSame( '<p>Long</p>', Hydration::description( '<p>Long</p>' ) );
	}
}
