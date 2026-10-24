<?php
/**
 * Wishlist and compare: settings, block hooks, the list markup and the
 * customer's saved wishlist.
 *
 * Needs WooCommerce for most tests; on a site without it, it checks that
 * the blocks are not registered.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Settings\Schema;
use Pergel\Settings\Settings;
use Pergel\Woo\Lists;
use Pergel\Woo\ListView;
use Pergel\Woo\MiniCart;
use Pergel\Woo\Modes;
use Pergel\Woo\Module as Woo;
use PHPUnit\Framework\TestCase;

/**
 * Products, users and settings are removed or restored after each test.
 */
final class ListsTest extends TestCase {

	/**
	 * The settings option as it was before the test (false: not saved).
	 *
	 * @var mixed
	 */
	private mixed $saved;

	/**
	 * Products made by the test.
	 *
	 * @var list<int>
	 */
	private array $products = array();

	/**
	 * User made by the test, or 0.
	 */
	private int $user = 0;

	protected function setUp(): void {
		$this->saved = get_option( Settings::OPTION, false );
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		foreach ( $this->products as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$product->delete( true );
			}
		}
		if ( $this->user > 0 ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $this->user );
		}
		delete_option( Settings::OPTION );
		if ( false !== $this->saved ) {
			remove_all_filters( 'sanitize_option_' . Settings::OPTION );
			add_option( Settings::OPTION, $this->saved, '', true );
			add_filter( 'sanitize_option_' . Settings::OPTION, array( \Pergel\Settings\Module::class, 'sanitize' ) );
		}
		wp_set_current_user( 0 );
		Settings::flush();
		if ( Woo::active() ) {
			Modes::flush();
		}
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
	 * Saves the list settings; the ones not given are turned off, whatever
	 * the site had.
	 *
	 * @param array<string, mixed> $woo Shop settings.
	 */
	private function lists( array $woo ): void {
		$off = array(
			'wishlist'     => false,
			'wishlist_url' => '',
			'compare'      => false,
			'compare_url'  => '',
		);
		Settings::update( array( 'woo' => $woo + $off ) );
		Settings::flush();
		Modes::flush();
	}

	/**
	 * Makes a simple product.
	 *
	 * @param string $mode Own mode; '' follows the shop.
	 */
	private function simple( string $mode = '' ): \WC_Product_Simple {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Pergel list test ' . ( count( $this->products ) + 1 ) );
		$product->set_status( 'publish' );
		$product->set_sku( 'PERGEL-LT-' . wp_rand( 10000, 99999 ) );
		$product->set_regular_price( '100' );
		$product->set_weight( '2' );
		if ( '' !== $mode ) {
			$product->update_meta_data( Modes::POST_META, $mode );
		}
		$product->save();
		$this->products[] = $product->get_id();
		Modes::flush();
		return $product;
	}

	/**
	 * Logs in a new customer.
	 */
	private function customer(): int {
		$this->user = (int) wp_insert_user(
			array(
				'user_login' => 'pergel_list_' . wp_rand( 10000, 99999 ),
				'user_pass'  => wp_generate_password(),
				'role'       => 'customer',
			)
		);
		wp_set_current_user( $this->user );
		return $this->user;
	}

	/**
	 * A REST request with parameters.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $route  Route.
	 * @param array<string, mixed> $params Parameters.
	 * @return \WP_REST_Request<array<string, mixed>>
	 */
	private function request( string $method, string $route, array $params ): \WP_REST_Request {
		$request = new \WP_REST_Request( $method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $request;
	}

	public function test_lists_are_off_by_default(): void {
		$defaults = Schema::defaults()['woo'];
		$this->assertFalse( $defaults['wishlist'] );
		$this->assertFalse( $defaults['compare'] );
		$this->assertSame( '', $defaults['wishlist_url'] );
		$this->assertSame( '', $defaults['compare_url'] );
	}

	public function test_blocks_need_woocommerce(): void {
		$registry = \WP_Block_Type_Registry::get_instance();
		foreach ( array( Lists::ACTIONS, Lists::LINKS, 'pergel/shop-list' ) as $name ) {
			$this->assertSame( Woo::active(), $registry->is_registered( $name ), $name );
		}
	}

	public function test_ids_are_distinct_positive_and_newest(): void {
		$this->assertSame( array( 3, 1, 2 ), Lists::ids( array( 3, '1', 0, -4, 'x', 3, 2 ), 10 ) );
		$this->assertSame( array( 5, 6 ), Lists::ids( '4,5,6', 2 ) );
		$this->assertSame( array(), Lists::ids( null, 4 ) );
	}

	public function test_buttons_follow_product_buttons_but_not_inside_the_cart_form(): void {
		$this->needs_woocommerce();
		$this->lists( array( 'wishlist' => true ) );
		$pattern = array(
			'name'       => 'pergel/test',
			'categories' => array(),
			'content'    => '',
		);

		$this->assertContains( Lists::ACTIONS, Lists::hook( array(), 'after', 'woocommerce/product-button', $pattern ) );
		$this->assertContains( Lists::ACTIONS, Lists::hook( array(), 'after', 'woocommerce/add-to-cart-with-options', $pattern ) );
		$this->assertSame( array(), Lists::hook( array(), 'before', 'woocommerce/product-button', $pattern ) );

		$form       = new \WP_Block_Template();
		$form->slug = 'simple-product-add-to-cart-with-options';
		$this->assertSame( array(), Lists::hook( array(), 'after', 'woocommerce/product-button', $form ) );

		$placed            = $pattern;
		$placed['content'] = '<!-- wp:pergel/product-actions /-->';
		$this->assertSame( array(), Lists::hook( array(), 'after', 'woocommerce/product-button', $placed ) );

		$anchor = array( 'blockName' => 'woocommerce/add-to-cart-with-options' );
		$block  = Lists::hooked_actions( array( 'blockName' => Lists::ACTIONS ), Lists::ACTIONS, 'after', $anchor );
		$this->assertIsArray( $block );
		$this->assertTrue( $block['attrs']['labels'] );
		$card = Lists::hooked_actions( array( 'blockName' => Lists::ACTIONS ), Lists::ACTIONS, 'after', array( 'blockName' => 'woocommerce/product-button' ) );
		$this->assertIsArray( $card );
		$this->assertArrayNotHasKey( 'attrs', $card );
	}

	public function test_header_links_need_a_page_and_come_before_the_cart(): void {
		$this->needs_woocommerce();
		$header = array(
			'name'       => 'pergel/header-classic',
			'categories' => array( 'header' ),
			'content'    => '',
		);

		$this->lists( array( 'wishlist' => true ) );
		$this->assertNotContains( Lists::LINKS, Lists::hook( array(), 'after', 'core/navigation', $header ) );

		$this->lists(
			array(
				'wishlist'     => true,
				'wishlist_url' => 'https://example.test/wishlist/',
			)
		);
		$this->assertSame( array( Lists::LINKS, MiniCart::BLOCK ), Lists::hook( array( MiniCart::BLOCK ), 'after', 'core/navigation', $header ) );
		$this->assertSame( array( 'wishlist' ), Lists::linked() );

		$other = array_merge( $header, array( 'categories' => array( 'footer' ) ) );
		$this->assertSame( array(), Lists::hook( array(), 'after', 'core/navigation', $other ) );
	}

	public function test_visitor_data_is_the_same_for_everyone(): void {
		$this->needs_woocommerce();
		$this->lists(
			array(
				'wishlist' => true,
				'compare'  => true,
			)
		);
		$data = Lists::data( array() );

		$this->assertSame( array( 'wishlist', 'compare' ), $data['kinds'] );
		$this->assertFalse( $data['user'] );
		$this->assertSame( '', $data['nonce'] );
		$this->assertSame( array(), $data['saved'] );
		$this->assertSame( Lists::LIMITS, $data['limits'] );
	}

	public function test_buttons_wait_for_the_script(): void {
		$this->needs_woocommerce();
		$this->lists(
			array(
				'wishlist' => true,
				'compare'  => true,
			)
		);
		$product = $this->simple();
		$block   = new \WP_Block(
			array(
				'blockName' => Lists::ACTIONS,
				'attrs'     => array(),
			),
			array( 'postId' => $product->get_id() )
		);
		$html    = ListView::actions( array(), $block );

		$this->assertSame( 2, substr_count( $html, 'aria-pressed="false" hidden' ) );
		$this->assertStringContainsString( 'data-pergel-list="wishlist" data-pergel-product="' . $product->get_id() . '"', $html );
		$this->assertStringContainsString( $product->get_name(), $html );
		$this->assertContains( Lists::SCRIPT, wp_script_modules()->get_queue() );

		$this->lists(
			array(
				'wishlist' => false,
				'compare'  => false,
			)
		);
		$this->assertSame( '', ListView::actions( array(), $block ) );
	}

	public function test_card_buttons_as_text_links(): void {
		$this->needs_woocommerce();
		$this->lists( array( 'compare' => true ) );
		Settings::update( array( 'shop' => array( 'card_actions' => 'links' ) ) );
		$product = $this->simple();
		$parsed  = array(
			'blockName' => Lists::ACTIONS,
			'attrs'     => array(),
		);
		$card    = new \WP_Block(
			$parsed,
			array(
				'postId'  => $product->get_id(),
				'queryId' => 0,
			)
		);

		$html = ListView::actions( array(), $card );
		$this->assertStringContainsString( 'is-labelled is-links', $html );
		$this->assertStringContainsString( '<span>' . __( 'Add to compare', 'pergel-engine' ) . '</span>', $html );

		// Outside a product list, e.g. the product page, they stay as they are.
		$html = ListView::actions( array(), new \WP_Block( $parsed, array( 'postId' => $product->get_id() ) ) );
		$this->assertStringNotContainsString( 'is-links', $html );
		$this->assertStringContainsString( 'pergel-sr', $html );
	}

	public function test_list_shows_visible_products_in_order_without_hidden_prices(): void {
		$this->needs_woocommerce();
		$this->lists( array( 'compare' => true ) );
		$first  = $this->simple();
		$quoted = $this->simple( Modes::QUOTE );
		$draft  = $this->simple();
		$draft->set_status( 'draft' );
		$draft->save();

		$response = Lists::show(
			$this->request(
				'GET',
				'/pergel/v1/shop-list',
				array(
					'kind' => 'compare',
					'ids'  => implode( ',', array( $quoted->get_id(), $draft->get_id(), $first->get_id(), 999999999 ) ),
				)
			)
		);
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertSame( array( $quoted->get_id(), $first->get_id() ), $data['ids'] );
		$html = (string) $data['html'];
		$this->assertStringContainsString( '<table', $html );
		$this->assertStringContainsString( 'scope="col"', $html );
		$this->assertStringNotContainsString( $draft->get_name(), $html );
		// The weight row is there, the rating row is not: no product has reviews.
		$this->assertStringContainsString( wc_format_weight( 2.0 ), $html );
		$this->assertStringNotContainsString( '>Rating<', $html );
		// One price for the product on sale; none for the one in quote mode.
		$this->assertSame( 1, substr_count( $html, 'woocommerce-Price-amount' ) );
		// Only the product for sale can go straight to the cart.
		$this->assertSame( 1, substr_count( $html, 'add-to-cart=' ) );
	}

	public function test_list_route_hides_prices_over_http(): void {
		$this->needs_woocommerce();
		$this->lists( array( 'compare' => true ) );
		$catalog = $this->simple( Modes::CATALOG );
		$uri     = $_SERVER['REQUEST_URI'] ?? null;
		// A REST request as the browser sends it, not a direct call.
		$_SERVER['REQUEST_URI'] = '/' . rest_get_url_prefix() . '/pergel/v1/shop-list';
		Modes::flush();
		// The routes follow the lists that are on when the server starts; the
		// test site boots with them off, so start a server with the route.
		$server                    = $GLOBALS['wp_rest_server'] ?? null;
		$GLOBALS['wp_rest_server'] = null;
		$hooked                    = has_action( 'rest_api_init', array( Lists::class, 'routes' ) );
		if ( false === $hooked ) {
			add_action( 'rest_api_init', array( Lists::class, 'routes' ) );
		}
		try {
			$request = new \WP_REST_Request( 'GET', '/pergel/v1/shop-list' );
			$request->set_query_params(
				array(
					'kind' => 'compare',
					'ids'  => (string) $catalog->get_id(),
				)
			);
			$data = rest_do_request( $request )->get_data();
		} finally {
			$GLOBALS['wp_rest_server'] = $server;
			if ( false === $hooked ) {
				remove_action( 'rest_api_init', array( Lists::class, 'routes' ) );
			}
			if ( null === $uri ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $uri;
			}
		}

		$this->assertIsArray( $data );
		$this->assertSame( array( $catalog->get_id() ), $data['ids'] );
		$this->assertStringNotContainsString( 'woocommerce-Price-amount', (string) $data['html'] );
		$this->assertStringNotContainsString( 'add-to-cart=', (string) $data['html'] );
		$this->assertTrue( Modes::enforced() );
	}

	public function test_empty_list_says_so(): void {
		$this->needs_woocommerce();
		$this->assertStringContainsString( esc_html( Lists::empty_text( 'wishlist' ) ), ListView::render( 'wishlist', array() ) );
	}

	public function test_customer_wishlist_is_saved_on_change_only(): void {
		$this->needs_woocommerce();
		$this->lists( array( 'wishlist' => true ) );
		$one  = $this->simple();
		$two  = $this->simple();
		$user = $this->customer();

		$this->assertSame( array(), Lists::saved( $user ) );
		$page = ListView::page( array( 'kind' => 'wishlist' ) );
		$this->assertStringContainsString( 'data-pergel-drawn="true"', $page );
		$this->assertSame( '', get_user_meta( $user, Lists::META, true ) );

		Lists::change(
			$this->request(
				'POST',
				'/pergel/v1/wishlist',
				array(
					'action' => 'add',
					'ids'    => array( $one->get_id() ),
				)
			)
		);
		$merged = Lists::change(
			$this->request(
				'POST',
				'/pergel/v1/wishlist',
				array(
					'action' => 'merge',
					'ids'    => array( $two->get_id(), $one->get_id(), 999999999 ),
				)
			)
		);
		$this->assertSame( array( 'ids' => array( $one->get_id(), $two->get_id() ) ), $merged->get_data() );
		$this->assertSame( array( $one->get_id(), $two->get_id() ), Lists::saved( $user ) );

		Lists::change(
			$this->request(
				'POST',
				'/pergel/v1/wishlist',
				array(
					'action' => 'remove',
					'ids'    => array( $one->get_id() ),
				)
			)
		);
		$this->assertSame( array( $two->get_id() ), Lists::saved( $user ) );

		$data = Lists::data( array() );
		$this->assertTrue( $data['user'] );
		$this->assertNotSame( '', $data['nonce'] );
		$this->assertSame( array( $two->get_id() ), $data['saved'] );
		$this->assertStringContainsString( $two->get_name(), ListView::page( array( 'kind' => 'wishlist' ) ) );
	}

	public function test_privacy_tools_export_and_erase_the_saved_wishlist(): void {
		$this->needs_woocommerce();
		$one  = $this->simple();
		$user = $this->customer();
		wp_update_user(
			array(
				'ID'         => $user,
				'user_email' => 'wish-' . $user . '@example.invalid',
			)
		);
		update_user_meta( $user, Lists::META, (string) $one->get_id() );

		$this->assertArrayHasKey( 'pergel-wishlist', apply_filters( 'wp_privacy_personal_data_exporters', array() ) );
		$this->assertArrayHasKey( 'pergel-wishlist', apply_filters( 'wp_privacy_personal_data_erasers', array() ) );
		$export = Lists::export( 'wish-' . $user . '@example.invalid' );
		$this->assertSame( $one->get_name(), $export['data'][0]['data'][0]['value'] );
		$this->assertTrue( Lists::erase( 'wish-' . $user . '@example.invalid' )['items_removed'] );
		$this->assertSame( array(), Lists::saved( $user ) );
		$this->assertFalse( Lists::erase( 'wish-' . $user . '@example.invalid' )['items_removed'] );
		$this->assertSame( array(), Lists::export( 'nobody@example.invalid' )['data'] );
	}

	public function test_visitor_list_page_is_drawn_by_the_script(): void {
		$this->needs_woocommerce();
		$this->lists( array( 'compare' => true ) );
		$page = ListView::page( array( 'kind' => 'compare' ) );

		$this->assertStringContainsString( 'data-pergel-shop-list="compare"', $page );
		$this->assertStringContainsString( 'aria-busy="true"', $page );
		$this->assertStringContainsString( '<noscript>', $page );
		$this->assertSame( '', ListView::page( array( 'kind' => 'wishlist' ) ) );
	}
}
