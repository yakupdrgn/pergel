<?php
/**
 * The bar at the bottom of phones and the free shipping progress.
 *
 * Needs WooCommerce; skipped without it. Run against a shop site
 * (PERGEL_WP_DIR=…/pergel-shop).
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Settings\Schema;
use Pergel\Settings\Settings;
use Pergel\Woo\BottomBar;
use Pergel\Woo\FreeShipping;
use Pergel\Woo\Module as Woo;
use PHPUnit\Framework\TestCase;

/**
 * Settings, products, the shipping zone, the cart and the main query are
 * put back after each test. The customer ships to Iceland, so the test
 * zone is the only one that matches.
 */
final class ShopBarsTest extends TestCase {

	/**
	 * The option as it was before the test (false: not saved).
	 *
	 * @var mixed
	 */
	private mixed $saved = false;

	/**
	 * Products made for the test.
	 *
	 * @var list<int>
	 */
	private array $posts = array();

	/**
	 * The shipping zone made for the test, or null.
	 */
	private ?\WC_Shipping_Zone $zone = null;

	/**
	 * The customer's shipping country before the test.
	 */
	private string $country = '';

	/**
	 * The main query before go_to() replaced it.
	 */
	private ?\WP_Query $query = null;

	protected function setUp(): void {
		// Saved first: tearDown() runs after a skip too.
		$this->saved = get_option( Settings::OPTION, false );
		if ( ! Woo::active() ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
		Settings::update(
			array(
				'woo'  => Schema::defaults()['woo'],
				'shop' => Schema::defaults()['shop'],
			)
		);
		wc_load_cart();
		$this->country = (string) WC()->customer->get_shipping_country();
		WC()->customer->set_shipping_country( 'IS' );
		WC()->customer->set_billing_country( 'IS' );
		WC()->cart->empty_cart();
	}

	protected function tearDown(): void {
		if ( function_exists( 'WC' ) && WC()->cart instanceof \WC_Cart ) {
			WC()->cart->empty_cart();
			WC()->customer->set_shipping_country( $this->country );
			WC()->customer->set_billing_country( $this->country );
		}
		if ( null !== $this->zone ) {
			$this->zone->delete();
		}
		delete_option( Settings::OPTION );
		if ( false !== $this->saved ) {
			// Restored as stored, without running it through the schema again.
			remove_all_filters( 'sanitize_option_' . Settings::OPTION );
			add_option( Settings::OPTION, $this->saved, '', true );
			add_filter( 'sanitize_option_' . Settings::OPTION, array( \Pergel\Settings\Module::class, 'sanitize' ) );
		}
		Settings::flush();
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
		}
		wp_dequeue_style( FreeShipping::STYLE );
		if ( null !== $this->query ) {
			$GLOBALS['wp_the_query'] = $this->query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Puts back the query go_to() replaced.
		}
		wp_reset_query();
	}

	/**
	 * Makes a product.
	 *
	 * @param string $price Price.
	 */
	private function product( string $price ): \WC_Product_Simple {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Shipping test ' . wp_generate_password( 4, false ) );
		$product->set_status( 'publish' );
		$product->set_regular_price( $price );
		$this->posts[] = $product->save();
		return $product;
	}

	/**
	 * Makes the test zone with a free shipping method.
	 *
	 * @param string $requires What the method requires.
	 * @param string $minimum  Its minimum order amount.
	 */
	private function zone( string $requires, string $minimum ): void {
		$this->zone = new \WC_Shipping_Zone();
		$this->zone->set_zone_name( 'Pergel test zone' );
		$this->zone->add_location( 'IS', 'country' );
		$this->zone->save();
		$instance = $this->zone->add_shipping_method( 'free_shipping' );
		update_option(
			'woocommerce_free_shipping_' . $instance . '_settings',
			array(
				'title'            => 'Free',
				'requires'         => $requires,
				'min_amount'       => $minimum,
				'ignore_discounts' => 'no',
			)
		);
		\WC_Cache_Helper::invalidate_cache_group( 'shipping_zones' );
	}

	public function test_amount_from_the_zone_else_the_setting(): void {
		$this->assertSame( array( 0.0, false ), FreeShipping::minimum( WC()->cart ), 'Nothing set: no amount.' );

		Settings::update( array( 'woo' => array( 'shipping_min' => 300 ) ) );
		$this->assertSame( array( 300.0, false ), FreeShipping::minimum( WC()->cart ) );

		$this->zone( 'coupon', '0' );
		$this->assertSame( array( 300.0, false ), FreeShipping::minimum( WC()->cart ), 'A coupon-only method has no amount.' );
		$this->zone->delete();

		$this->zone( 'min_amount', '500' );
		$this->assertSame( array( 500.0, false ), FreeShipping::minimum( WC()->cart ), 'The zone\'s method comes first.' );
	}

	public function test_text_and_bar_follow_the_cart(): void {
		Settings::update( array( 'woo' => array( 'shipping_min' => 300 ) ) );
		$product = $this->product( '100' );

		$empty = FreeShipping::inner();
		$this->assertStringContainsString( 'pergel-free-shipping__text', $empty );
		$this->assertStringContainsString( 'width:0%', $empty );

		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();
		$part = FreeShipping::inner();
		$this->assertStringContainsString( 'width:33%', $part );
		$this->assertStringContainsString( '<strong>', $part, 'The amount still to spend.' );
		$this->assertStringNotContainsString( 'is-full', $part );

		WC()->cart->add_to_cart( $product->get_id(), 2 );
		WC()->cart->calculate_totals();
		$full = FreeShipping::inner();
		$this->assertStringContainsString( 'is-full', $full );
		$this->assertStringContainsString( esc_html__( 'Your order ships free.', 'pergel-engine' ), $full );

		Settings::update( array( 'woo' => array( 'shipping_min' => 0 ) ) );
		$this->assertSame( '', FreeShipping::inner(), 'No amount: no bar.' );
		$this->assertSame( '', FreeShipping::cart( '' ) );
	}

	public function test_bars_in_their_places(): void {
		Settings::update( array( 'woo' => array( 'shipping_min' => 300 ) ) );
		// The site may boot with the bars off, so the style is not registered yet.
		FreeShipping::assets();

		$this->assertStringStartsWith( '<div class="pergel-free-shipping is-cart" data-pergel-shipping', FreeShipping::cart( '<div class="cart"></div>' ) );
		$this->assertStringEndsWith( '<div class="cart"></div>', FreeShipping::cart( '<div class="cart"></div>' ) );
		$this->assertStringStartsWith( '<div class="pergel-free-shipping is-checkout"', FreeShipping::checkout( '<div></div>' ) );
		$this->assertStringStartsWith( '<div class="pergel-free-shipping is-mini"', FreeShipping::mini( '<div class="footer"></div>' ) );
		$this->assertTrue( wp_style_is( FreeShipping::STYLE ) );
		$this->assertContains( \Pergel\Woo\Layout::CART_SCRIPT, wp_script_modules()->get_queue() );

		// Under the form of the product on screen only.
		$product = $this->product( '50' );
		$this->go_to( $product->get_id() );
		// An unregistered block keeps no context; it is set by hand.
		$block          = new \WP_Block( array( 'blockName' => 'core/null' ) );
		$block->context = array( 'postId' => $product->get_id() );
		$this->assertStringContainsString( 'is-product', FreeShipping::product( '<form></form>', array(), $block ) );
		$other          = new \WP_Block( array( 'blockName' => 'core/null' ) );
		$other->context = array( 'postId' => $product->get_id() + 1000 );
		$this->assertSame( '<form></form>', FreeShipping::product( '<form></form>', array(), $other ) );
		$product->set_virtual( true );
		$product->save();
		$this->assertSame( '<form></form>', FreeShipping::product( '<form></form>', array(), $block ), 'Nothing ships.' );
	}

	public function test_store_api_cart_carries_the_bar(): void {
		Settings::update( array( 'woo' => array( 'shipping_min' => 300 ) ) );
		FreeShipping::extend();

		$data = FreeShipping::data();
		$this->assertStringContainsString( 'pergel-free-shipping__track', $data['free_shipping']['html'] );
		$this->assertArrayHasKey( 'html', FreeShipping::schema()['free_shipping']['properties'] );

		$response = rest_do_request( new \WP_REST_Request( 'GET', '/wc/store/v1/cart' ) );
		$this->assertSame( 200, $response->get_status() );
		// As the browser reads it: the extensions are an object in PHP.
		$cart = json_decode( (string) wp_json_encode( $response->get_data() ), true );
		$this->assertIsArray( $cart );
		$this->assertStringContainsString( 'pergel-free-shipping__track', (string) ( $cart['extensions']['pergel']['free_shipping']['html'] ?? '' ) );
	}

	public function test_bottom_bar_items_follow_the_settings(): void {
		$this->assertSame( array( 'shop', 'search', 'account', 'cart' ), array_keys( BottomBar::items() ), 'No wishlist page, no filters off the shop pages.' );

		Settings::update(
			array(
				'woo' => array(
					'wishlist'     => true,
					'wishlist_url' => home_url( '/wishlist/' ),
					'bar_search'   => false,
				),
			)
		);
		$this->assertSame( array( 'shop', 'wishlist', 'account', 'cart' ), array_keys( BottomBar::items() ) );

		$html = BottomBar::markup();
		$this->assertStringContainsString( '<nav class="pergel-bottom-bar" aria-label="' . esc_attr__( 'Shop shortcuts', 'pergel-engine' ) . '">', $html );
		$this->assertStringContainsString( 'data-pergel-count="wishlist"', $html );
		$this->assertStringContainsString( 'data-pergel-cart-count hidden>0</span>', $html, 'An empty cart shows no count.' );
		$this->assertStringNotContainsString( 'pergel-bottom-search', $html, 'No search, no dialog.' );
		$this->assertContains( 'has-pergel-bottom-bar', BottomBar::body_class( array() ) );
	}

	public function test_bottom_bar_search_looks_among_products(): void {
		$html = BottomBar::markup();

		$this->assertStringContainsString( '<dialog id="pergel-bottom-search"', $html );
		$this->assertStringContainsString( 'aria-controls="pergel-bottom-search"', $html );
		$this->assertMatchesRegularExpression( '#<input type="hidden" name="post_type" value="product"\s*/?>#', $html );
	}

	/**
	 * Shows a product page, as a page view would.
	 *
	 * @param int $id Product id.
	 */
	private function go_to( int $id ): void {
		$this->query           ??= $GLOBALS['wp_the_query'];
		$GLOBALS['wp_the_query'] = new \WP_Query( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test page view; put back in tearDown.
			array(
				'p'         => $id,
				'post_type' => 'product',
			)
		);
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- As above.
	}
}
