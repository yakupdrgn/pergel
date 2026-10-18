<?php
/**
 * Sales modes: which products sell to the visitor, and what a hidden price
 * leaves out.
 *
 * Needs WooCommerce; skipped on a site without it, where it checks that no
 * shop class has loaded.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Settings\Settings;
use Pergel\Woo\MiniCart;
use Pergel\Woo\Modes;
use Pergel\Woo\Module as Woo;
use Pergel\Woo\Pages;
use Pergel\Woo\Selling;
use PHPUnit\Framework\TestCase;

/**
 * Products, categories and settings are removed or restored after each test.
 */
final class SellingTest extends TestCase {

	/**
	 * The settings option as it was before the test (false: not saved).
	 *
	 * @var mixed
	 */
	private mixed $saved;

	/**
	 * The mode index as it was before the test.
	 *
	 * @var mixed
	 */
	private mixed $index;

	/**
	 * Products made by the test.
	 *
	 * @var list<int>
	 */
	private array $products = array();

	/**
	 * Categories made by the test.
	 *
	 * @var list<int>
	 */
	private array $terms = array();

	protected function setUp(): void {
		if ( ! Woo::active() ) {
			return;
		}
		$this->saved = get_option( Settings::OPTION, false );
		$this->index = get_option( Modes::INDEX, false );
		wp_set_current_user( 0 );
		add_filter( 'pergel/woo/enforce_modes', '__return_true' );
		Modes::flush();
	}

	protected function tearDown(): void {
		if ( ! Woo::active() ) {
			return;
		}
		foreach ( $this->products as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$product->delete( true );
			}
		}
		foreach ( $this->terms as $id ) {
			wp_delete_term( $id, 'product_cat' );
		}
		delete_option( Settings::OPTION );
		if ( false !== $this->saved ) {
			remove_all_filters( 'sanitize_option_' . Settings::OPTION );
			add_option( Settings::OPTION, $this->saved, '', true );
			add_filter( 'sanitize_option_' . Settings::OPTION, array( \Pergel\Settings\Module::class, 'sanitize' ) );
		}
		delete_option( Modes::INDEX );
		if ( false !== $this->index ) {
			add_option( Modes::INDEX, $this->index, '', true );
		}
		remove_filter( 'pergel/woo/enforce_modes', '__return_true' );
		wp_set_current_user( 0 );
		unset( $GLOBALS['post'] );
		Settings::flush();
		Modes::flush();
	}

	/**
	 * Skips a test that needs WooCommerce on a site without it.
	 */
	private function needs_woocommerce(): void {
		if ( ! Woo::active() ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
	}

	/**
	 * Sets the shop's mode.
	 *
	 * @param string $mode Mode.
	 */
	private function shop( string $mode ): void {
		Settings::update( array( 'woo' => array( 'sales_mode' => $mode ) ) );
		Settings::flush();
		Modes::flush();
	}

	/**
	 * Makes a simple product with a price.
	 *
	 * @param list<int> $categories Category ids.
	 * @param string    $mode       Own mode; '' follows the categories.
	 */
	private function product( array $categories = array(), string $mode = '' ): \WC_Product_Simple {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Pergel test product' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '100' );
		$product->set_category_ids( $categories );
		if ( '' !== $mode ) {
			$product->update_meta_data( Modes::POST_META, $mode );
		}
		$product->save();
		$this->products[] = $product->get_id();
		Modes::flush();
		return $product;
	}

	/**
	 * Makes a product category.
	 *
	 * @param string $name   Name.
	 * @param string $mode   Own mode; '' follows the parent.
	 * @param int    $parent Parent id.
	 */
	private function category( string $name, string $mode = '', int $parent = 0 ): int {
		$term = wp_insert_term( $name, 'product_cat', array( 'parent' => $parent ) );
		$this->assertIsArray( $term );
		$id            = (int) $term['term_id'];
		$this->terms[] = $id;
		if ( '' !== $mode ) {
			update_term_meta( $id, Modes::TERM_META, $mode );
		}
		return $id;
	}

	public function test_without_woocommerce_no_mode_code_loads(): void {
		if ( Woo::active() ) {
			$this->markTestSkipped( 'WooCommerce is active on this site.' );
		}
		foreach ( array( Modes::class, Selling::class, Pages::class, \Pergel\Woo\Fields::class ) as $class ) {
			$this->assertFalse( class_exists( $class, false ), $class );
		}
		$this->assertFalse( has_filter( 'woocommerce_is_purchasable' ) );
	}

	public function test_modes_rank_from_open_to_strict(): void {
		$this->needs_woocommerce();
		$this->assertTrue( Modes::valid( 'quote' ) );
		$this->assertFalse( Modes::valid( 'free' ) );
		$this->assertSame( '', Modes::sanitize( 'free' ) );
		$this->assertSame( 'login', Modes::sanitize( 'login' ) );
		$this->assertSame( 'quote', Modes::strictest( array( 'sale', 'quote', 'login' ) ) );
		$this->assertSame( 'catalog', Modes::strictest( array( 'catalog', 'sale' ) ) );
		$this->assertSame( 'sale', Modes::strictest( array() ) );
		$this->assertSame( 'sale', Modes::site() );

		$this->assertTrue( Modes::sells( 'sale' ) );
		$this->assertFalse( Modes::sells( 'login' ) );
		$this->assertFalse( Modes::sells( 'quote' ) );
		wp_set_current_user( 1 );
		$this->assertTrue( Modes::sells( 'login' ) );
		$this->assertFalse( Modes::sells( 'catalog' ) );
	}

	public function test_product_then_strictest_category_then_shop(): void {
		$this->needs_woocommerce();
		$parent  = $this->category( 'Pergel test quote', 'quote' );
		$child   = $this->category( 'Pergel test quote child', '', $parent );
		$catalog = $this->category( 'Pergel test catalog', 'catalog' );
		$plain   = $this->category( 'Pergel test plain' );

		$inherits = $this->product( array( $child ) );
		$own      = $this->product( array( $child ), 'sale' );
		$both     = $this->product( array( $child, $catalog ) );
		$shop     = $this->product( array( $plain ) );

		$this->assertSame( 'quote', Modes::of( $inherits ) );
		$this->assertSame( 'quote', Modes::of( $inherits->get_id() ) );
		$this->assertSame( 'sale', Modes::of( $own ) );
		$this->assertSame( 'catalog', Modes::of( $both ) );
		$this->assertSame( 'sale', Modes::of( $shop ) );
		$this->assertSame( 'quote', Modes::of_category( $child ) );
		$term = get_term( $child, 'product_cat' );
		$this->assertInstanceOf( \WP_Term::class, $term );
		$this->assertSame( 'quote', Modes::of_category( $term->slug ) );
		$this->assertSame( 'sale', Modes::of_category( $plain ) );
		$this->assertEqualsCanonicalizing( array( 'quote', 'catalog', 'sale' ), Modes::index()['used'] );

		// The shop's mode reaches products without a mode of their own.
		$this->shop( 'login' );
		$this->assertSame( 'login', Modes::of( $shop ) );
		$this->assertSame( 'quote', Modes::of( $inherits ) );

		// Removing a category's mode rebuilds the index.
		delete_term_meta( $parent, Modes::TERM_META );
		$this->assertSame( 'login', Modes::of( $inherits ) );
		$this->assertArrayNotHasKey( $child, Modes::index()['terms'] );
	}

	public function test_hidden_product_has_no_price_and_no_cart(): void {
		$this->needs_woocommerce();
		$product = $this->product();
		$this->assertSame( '100', $product->get_price() );
		$this->assertTrue( $product->is_purchasable() );

		$this->shop( 'quote' );
		$product = wc_get_product( $product->get_id() );
		$this->assertInstanceOf( \WC_Product::class, $product );
		$this->assertTrue( Modes::hidden( $product ) );
		$this->assertSame( '', $product->get_price() );
		$this->assertSame( '', $product->get_regular_price() );
		$this->assertSame( '', $product->get_price_html() );
		$this->assertFalse( $product->is_purchasable() );
		$this->assertSame( '', Selling::variation_price( '5', null, $product ) );
		$this->assertSame( array( 'a', 'pergel-no-price' ), Selling::prices_hash( array( 'a' ), $product ) );

		// The admin screens and stock tasks see the stored price.
		remove_filter( 'pergel/woo/enforce_modes', '__return_true' );
		add_filter( 'pergel/woo/enforce_modes', '__return_false' );
		Modes::flush();
		$this->assertSame( '100', $product->get_price() );
		remove_filter( 'pergel/woo/enforce_modes', '__return_false' );
	}

	public function test_prices_after_login(): void {
		$this->needs_woocommerce();
		$product = $this->product();
		$this->shop( 'login' );

		$this->assertTrue( Modes::hidden( $product ) );
		$this->assertTrue( Modes::nothing_sells() );
		wp_set_current_user( 1 );
		Modes::flush();
		$this->assertFalse( Modes::hidden( $product ) );
		$this->assertFalse( Modes::nothing_sells() );
		$this->assertSame( '100', $product->get_price() );
	}

	public function test_structured_data_loses_its_offers(): void {
		$this->needs_woocommerce();
		$graph = array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				array(
					'@type'  => 'Product',
					'name'   => 'A',
					'offers' => array( 'price' => '100' ),
				),
				array(
					'@type' => 'WebPage',
				),
			),
		);
		$this->assertSame(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => array(
					array(
						'@type' => 'Product',
						'name'  => 'A',
					),
					array( '@type' => 'WebPage' ),
				),
			),
			Selling::without_offers( $graph )
		);

		$product = $this->product();
		$node    = array(
			'@type'  => 'Product',
			'offers' => array( array( 'price' => '100' ) ),
		);
		$this->assertSame( $node, Selling::structured_data( $node, $product ) );
		$this->shop( 'catalog' );
		$this->assertSame( array( '@type' => 'Product' ), Selling::structured_data( $node, $product ) );
	}

	public function test_feed_keeps_products_for_sale_only(): void {
		$this->needs_woocommerce();
		$sale  = $this->product();
		$quote = $this->product( array(), 'quote' );
		$login = $this->product( array(), 'login' );

		// Whoever runs the sync, a product on quote stays out of the feed.
		remove_filter( 'pergel/woo/enforce_modes', '__return_true' );
		Modes::flush();
		$this->assertSame( array( $sale ), Selling::feed( array( $sale, $quote, $login ) ) );
	}

	public function test_store_api_lists_drop_price_parameters_while_the_shop_hides_prices(): void {
		$this->needs_woocommerce();
		$request = static function (): \WP_REST_Request {
			$request = new \WP_REST_Request( 'GET', '/wc/store/v1/products' );
			$request->set_param( 'min_price', '1000' );
			$request->set_param( 'max_price', '2000' );
			$request->set_param( 'orderby', 'price' );
			$request->set_param( 'calculate_price_range', true );
			return $request;
		};

		$open = $request();
		Selling::store_api( null, null, $open );
		$this->assertSame( '1000', $open->get_param( 'min_price' ) );
		$this->assertSame( 'price', $open->get_param( 'orderby' ) );

		$this->shop( 'quote' );
		$hidden = $request();
		$this->assertNull( Selling::store_api( null, null, $hidden ) );
		$this->assertNull( $hidden->get_param( 'min_price' ) );
		$this->assertNull( $hidden->get_param( 'max_price' ) );
		$this->assertFalse( $hidden->get_param( 'calculate_price_range' ) );
		$this->assertSame( 'menu_order', $hidden->get_param( 'orderby' ) );

		$cart = new \WP_REST_Request( 'GET', '/wc/store/v1/cart' );
		$cart->set_param( 'orderby', 'price' );
		Selling::store_api( null, null, $cart );
		$this->assertSame( 'price', $cart->get_param( 'orderby' ) );
	}

	public function test_listing_options_lose_price_sorting_where_prices_are_hidden(): void {
		$this->needs_woocommerce();
		$options = array(
			'menu_order' => 'Default',
			'price'      => 'Low to high',
			'price-desc' => 'High to low',
		);
		$this->assertSame( $options, Selling::orderby_options( $options ) );
		$this->assertSame( 'price', Selling::default_orderby( 'price' ) );
		$this->assertSame( '<div>filter</div>', Selling::price_filter( '<div>filter</div>' ) );

		$this->shop( 'quote' );
		$this->assertSame( array( 'menu_order' => 'Default' ), Selling::orderby_options( $options ) );
		$this->assertSame( 'menu_order', Selling::default_orderby( 'price-desc' ) );
		$this->assertSame( 'popularity', Selling::default_orderby( 'popularity' ) );
		$this->assertSame( '', Selling::price_filter( '<div>filter</div>' ) );
	}

	public function test_one_hidden_price_takes_the_price_tools_from_every_listing(): void {
		$this->needs_woocommerce();
		$options = array(
			'menu_order' => 'Default',
			'price'      => 'Low to high',
		);
		$this->product();
		$this->assertSame( $options, Selling::orderby_options( $options ) );

		// The shop sells; one product in catalog mode would give its price away
		// to a range filter or a price sorting, in the shop and in the Store API.
		$this->product( array(), 'catalog' );
		Modes::flush();
		$this->assertSame( array( 'menu_order' => 'Default' ), Selling::orderby_options( $options ) );
		$this->assertSame( '', Selling::price_filter( '<div>filter</div>' ) );
		$range = new \WP_REST_Request( 'GET', '/wc/store/v1/products/collection-data' );
		$range->set_param( 'calculate_price_range', true );
		$range->set_param( 'min_price', '10' );
		Selling::store_api( null, null, $range );
		$this->assertFalse( $range->get_param( 'calculate_price_range' ) );
		$this->assertNull( $range->get_param( 'min_price' ) );
	}

	public function test_cart_form_leaves_a_hidden_product(): void {
		$this->needs_woocommerce();
		$sale  = $this->product();
		$quote = $this->product( array(), 'quote' );
		$form  = '<form class="cart"></form>';

		$GLOBALS['post'] = get_post( $sale->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Product on screen; reset in tearDown.
		$this->assertSame( $form, Selling::cart_form( $form, array() ) );
		$GLOBALS['post'] = get_post( $quote->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- As above.
		$this->assertSame( '', Selling::cart_form( $form, array() ) );
	}

	public function test_shop_pages_stay_out_of_the_sitemap(): void {
		$this->needs_woocommerce();
		$ids = Pages::ids( array( 'cart', 'checkout', 'myaccount' ) );
		if ( array() === $ids ) {
			$this->markTestSkipped( 'The shop pages are not set up on this site.' );
		}
		$args = Pages::sitemap( array( 'post__not_in' => array( 7777 ) ), 'page' );
		$this->assertSame( array_merge( array( 7777 ), $ids ), $args['post__not_in'] );
		$this->assertSame( array(), Pages::sitemap( array(), 'post' ) );
	}

	public function test_cart_links_leave_the_menus_when_nothing_sells(): void {
		$this->needs_woocommerce();
		$cart = wc_get_page_id( 'cart' );
		if ( $cart <= 0 ) {
			$this->markTestSkipped( 'The cart page is not set up on this site.' );
		}
		$link  = array(
			'blockName' => 'core/navigation-link',
			'attrs'     => array(
				'id'   => $cart,
				'kind' => 'post-type',
			),
		);
		$item  = (object) array(
			'object'    => 'page',
			'object_id' => (string) $cart,
		);
		$other = (object) array(
			'object'    => 'page',
			'object_id' => '999999',
		);

		$this->assertFalse( Modes::nothing_sells() );
		$this->assertSame( '<a>Cart</a>', Pages::link( '<a>Cart</a>', $link ) );
		$this->assertSame( array( $item, $other ), Pages::menu_items( array( $item, $other ) ) );

		$this->shop( 'quote' );
		$this->assertTrue( Modes::nothing_sells() );
		$this->assertSame( '', Pages::link( '<a>Cart</a>', $link ) );
		$this->assertSame( array( $other ), Pages::menu_items( array( $item, $other ) ) );
		$this->assertSame( array(), Pages::pages( array( get_post( $cart ) ) ) );

		// A product that sells keeps the cart in the menus.
		$this->product( array(), 'sale' );
		$this->assertFalse( Modes::nothing_sells() );
		$this->assertSame( '<a>Cart</a>', Pages::link( '<a>Cart</a>', $link ) );
	}

	public function test_mini_cart_only_where_something_sells(): void {
		$this->needs_woocommerce();
		$header = array(
			'name'       => 'pergel/header-classic',
			'categories' => array( 'header' ),
			'content'    => '<!-- wp:navigation /-->',
		);
		// WooCommerce hooks the block too; in Pergel's headers it stays once.
		$this->assertSame( array( MiniCart::BLOCK ), MiniCart::hook( array( MiniCart::BLOCK ), 'after', 'core/navigation', $header ) );

		$this->shop( 'quote' );
		$this->assertSame( array(), MiniCart::hook( array( MiniCart::BLOCK ), 'after', 'core/navigation', $header ) );

		$this->shop( 'sale' );
		Settings::update( array( 'woo' => array( 'mini_cart' => false ) ) );
		Settings::flush();
		$this->assertSame( array(), MiniCart::hook( array( MiniCart::BLOCK ), 'after', 'core/navigation', $header ) );
		// Other themes' headers are left to WooCommerce.
		$this->assertSame( array( MiniCart::BLOCK ), MiniCart::hook( array( MiniCart::BLOCK ), 'after', 'core/navigation', array( 'name' => 'acme/header' ) + $header ) );
	}
}
