<?php
/**
 * The shop module: nothing without WooCommerce, shop files on shop pages.
 *
 * Runs on any site. Tests that need WooCommerce are skipped without it;
 * run the suite against a shop site too (PERGEL_WP_DIR=…/pergel-shop).
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Automattic\WooCommerce\Internal\Features\FeaturesController;
use Pergel\Core\Plugin;
use Pergel\Settings\Schema;
use Pergel\Settings\Screen;
use Pergel\Settings\Settings;
use Pergel\Woo\Assets;
use Pergel\Woo\Markup;
use Pergel\Woo\MiniCart;
use Pergel\Woo\Trail;
use Pergel\Woo\Module as Woo;
use PHPUnit\Framework\TestCase;

/**
 * Settings changes are rolled back after each test.
 */
final class WooTest extends TestCase {

	/**
	 * The option as it was before the test (false: not saved).
	 *
	 * @var mixed
	 */
	private mixed $saved;

	protected function setUp(): void {
		$this->saved = get_option( Settings::OPTION, false );
	}

	protected function tearDown(): void {
		delete_option( Settings::OPTION );
		if ( false !== $this->saved ) {
			// Restored as stored, without running it through the schema again.
			remove_all_filters( 'sanitize_option_' . Settings::OPTION );
			add_option( Settings::OPTION, $this->saved, '', true );
			add_filter( 'sanitize_option_' . Settings::OPTION, array( \Pergel\Settings\Module::class, 'sanitize' ) );
		}
		remove_all_filters( 'pergel/woo/classic_files' );
		remove_filter( 'woocommerce_is_cart', '__return_true' );
		Settings::flush();
		wp_reset_query();
	}

	/**
	 * Skips a test that needs WooCommerce on a site without it.
	 */
	private function needs_woocommerce(): void {
		if ( ! Woo::active() ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
	}

	public function test_module_loads_only_with_woocommerce(): void {
		$this->assertSame( class_exists( 'WooCommerce', false ), Woo::active() );
		$this->assertSame( Woo::active(), in_array( 'woo', Plugin::loaded(), true ) );
		$this->assertSame( Woo::active(), false !== has_action( 'before_woocommerce_init', array( Woo::class, 'declare_compatibility' ) ) );
	}

	public function test_without_woocommerce_no_shop_code_runs(): void {
		if ( Woo::active() ) {
			$this->markTestSkipped( 'WooCommerce is active on this site.' );
		}
		$this->assertFalse( class_exists( Assets::class, false ) );
		$this->assertFalse( has_filter( 'woocommerce_enqueue_styles' ) );
	}

	public function test_defaults_keep_shop_files_on_shop_pages(): void {
		$this->assertSame(
			array(
				'sales_mode'        => 'sale',
				'whatsapp'          => '',
				'contact_url'       => '',
				'mini_cart'         => true,
				'shop_assets_only'  => true,
				'no_cart_fragments' => true,
				'wishlist'          => false,
				'wishlist_url'      => '',
				'compare'           => false,
				'compare_url'       => '',
			),
			Schema::defaults()['woo']
		);
		$this->assertTrue( Schema::defaults()['modules']['woo'] );
	}

	public function test_shop_tab_follows_woocommerce(): void {
		$this->assertSame( Woo::active(), Screen::shown( 'woo' ) );
		$this->assertSame( Woo::active(), Screen::shown( 'woo', 'shop_assets_only' ) );
		$this->assertSame( Woo::active(), Screen::shown( 'modules', 'woo' ) );
		$this->assertTrue( Screen::shown( 'modules', 'forms' ) );
		$this->assertTrue( Screen::shown( 'brand' ) );
	}

	public function test_hidden_tab_keeps_its_saved_values(): void {
		Settings::update( array( 'woo' => array( 'shop_assets_only' => false ) ) );
		// A save from another tab posts only that tab's fields.
		Settings::update( array( 'brand' => array( 'name' => 'Ariumlab' ) ) );

		$this->assertFalse( Settings::get( 'woo.shop_assets_only' ) );
		$this->assertTrue( Settings::get( 'woo.no_cart_fragments' ) );
	}

	public function test_classic_styles_only_on_shop_pages(): void {
		$this->needs_woocommerce();
		$styles = array( 'woocommerce-general' => array( 'src' => 'x.css' ) );

		// No main query has run: no classic shop markup.
		$this->assertFalse( Assets::classic_request() );
		$this->assertSame( array(), Assets::styles( $styles ) );

		add_filter( 'pergel/woo/classic_files', '__return_true' );
		$this->assertSame( $styles, Assets::styles( $styles ) );
		remove_all_filters( 'pergel/woo/classic_files' );

		Settings::update( array( 'woo' => array( 'shop_assets_only' => false ) ) );
		$this->assertSame( $styles, Assets::styles( $styles ) );
	}

	public function test_shop_scripts_dequeued_outside_shop_pages(): void {
		$this->needs_woocommerce();
		$handles = array( 'woocommerce', 'wc-add-to-cart', 'wc-cart-fragments' );
		foreach ( $handles as $handle ) {
			wp_register_script( $handle, 'https://example.test/' . $handle . '.js', array(), '1', true );
			wp_enqueue_script( $handle );
		}
		wp_register_style( 'woocommerce-blocktheme', 'https://example.test/blocktheme.css', array(), '1' );
		wp_enqueue_style( 'woocommerce-blocktheme' );

		Assets::scripts();

		foreach ( $handles as $handle ) {
			$this->assertFalse( wp_script_is( $handle, 'enqueued' ), $handle );
		}
		$this->assertFalse( wp_style_is( 'woocommerce-blocktheme', 'enqueued' ) );

		foreach ( $handles as $handle ) {
			wp_deregister_script( $handle );
		}
		wp_deregister_style( 'woocommerce-blocktheme' );
	}

	public function test_mini_cart_follows_the_menu_of_pergel_headers_once(): void {
		$this->needs_woocommerce();
		$header = array(
			'name'       => 'pergel/header-classic',
			'categories' => array( 'header' ),
			'content'    => '<!-- wp:navigation /-->',
		);

		$this->assertSame( array( MiniCart::BLOCK ), MiniCart::hook( array(), 'after', 'core/navigation', $header ) );
		$this->assertSame( array(), MiniCart::hook( array(), 'before', 'core/navigation', $header ) );
		$this->assertSame( array(), MiniCart::hook( array(), 'after', 'core/site-title', $header ) );
		$this->assertSame( array(), MiniCart::hook( array(), 'after', null, $header ) );
		$this->assertSame( array(), MiniCart::hook( array(), 'after', 'core/navigation', array( 'categories' => array( 'header' ) ) + array( 'name' => 'acme/header' ) ) );
		$this->assertSame( array(), MiniCart::hook( array(), 'after', 'core/navigation', array( 'categories' => array( 'footer' ) ) + $header ) );
		$this->assertSame( array(), MiniCart::hook( array(), 'after', 'core/navigation', array( 'content' => '<!-- wp:woocommerce/mini-cart /-->' ) + $header ) );
		$this->assertSame( array( MiniCart::BLOCK ), MiniCart::hook( array( MiniCart::BLOCK ), 'after', 'core/navigation', $header ) );
	}

	public function test_no_mini_cart_on_the_cart_page(): void {
		$this->needs_woocommerce();
		// WooCommerce keeps its first answer for the page, so the test uses
		// its filter instead of a cart page query.
		add_filter( 'woocommerce_is_cart', '__return_true' );

		$this->assertTrue( is_cart() );
		$this->assertSame(
			array(),
			MiniCart::hook(
				array(),
				'after',
				'core/navigation',
				array(
					'name'       => 'pergel/header-classic',
					'categories' => array( 'header' ),
				)
			)
		);
	}

	public function test_product_crumbs_pass_through_the_shop_and_its_category(): void {
		$this->needs_woocommerce();
		$parent  = wp_insert_term( 'Pergel test parent', 'product_cat' );
		$child   = is_array( $parent ) ? wp_insert_term( 'Pergel test child', 'product_cat', array( 'parent' => $parent['term_id'] ) ) : $parent;
		$product = wp_insert_post(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
				'post_title'  => 'Pergel test product',
			)
		);
		$this->assertIsArray( $child );
		$this->assertIsInt( $product );
		wp_set_object_terms( $product, array( $child['term_id'] ), 'product_cat' );

		try {
			$this->go_to(
				array(
					'p'         => $product,
					'post_type' => 'product',
				)
			);
			$home   = array(
				'name' => 'Home',
				'url'  => home_url( '/' ),
			);
			$self   = array(
				'name' => 'Pergel test product',
				'url'  => (string) get_permalink( $product ),
			);
			$names  = array_column( (array) Trail::crumbs( array( $home, $self ) ), 'name' );
			$shop   = wc_get_page_id( 'shop' ) > 0 && (int) get_option( 'page_on_front' ) !== wc_get_page_id( 'shop' ) ? array( get_the_title( wc_get_page_id( 'shop' ) ) ) : array();
			$expect = array_merge( array( 'Home' ), $shop, array( 'Pergel test parent', 'Pergel test child', 'Pergel test product' ) );

			$this->assertSame( $expect, $names );
			$this->assertSame( array( $home ), Trail::crumbs( array( $home ) ) );
		} finally {
			wp_delete_post( $product, true );
			wp_delete_term( $child['term_id'], 'product_cat' );
			wp_delete_term( $parent['term_id'], 'product_cat' );
		}
	}

	public function test_variation_attributes_are_list_items(): void {
		$this->assertSame( '<div role="listitem" class="a"><span>Size</span></div>', Markup::list_item( '<div class="a"><span>Size</span></div>' ) );
		$this->assertSame( '<div role="group"></div>', Markup::list_item( '<div role="group"></div>' ) );
	}

	public function test_greyed_out_buttons_are_marked_disabled(): void {
		$state = 'woocommerce/add-to-cart-with-options::!state.isFormValid';
		$html  = Markup::disabled_button( '<div><button class="add_to_cart_button disabled" data-wp-class--disabled="' . $state . '">Add</button></div>' );
		$tags  = new \WP_HTML_Tag_Processor( $html );
		$tags->next_tag( array( 'tag_name' => 'button' ) );

		$this->assertSame( 'true', $tags->get_attribute( 'aria-disabled' ) );
		$this->assertSame( $state, $tags->get_attribute( 'data-wp-bind--aria-disabled' ) );
		$this->assertSame( '<button class="add_to_cart_button">Add</button>', Markup::disabled_button( '<button class="add_to_cart_button">Add</button>' ) );
	}

	public function test_shop_templates_and_patterns_ship_with_the_theme(): void {
		foreach ( array( 'archive-product', 'product-search-results', 'single-product', 'page-cart', 'page-checkout', 'order-confirmation' ) as $template ) {
			$this->assertFileExists( get_theme_file_path( "templates/{$template}.html" ) );
		}
		foreach ( array( 'details', 'filters', 'related', 'terms' ) as $part ) {
			$this->assertIsArray( \WP_Block_Patterns_Registry::get_instance()->get_registered( "pergel/hidden-product-{$part}" ), $part );
		}
	}

	public function test_the_shop_lists_products_beside_its_filters(): void {
		$template = (string) file_get_contents( get_theme_file_path( 'templates/archive-product.html' ) );
		$this->assertStringContainsString( '<!-- wp:pattern {"slug":"pergel/hidden-product-filters"} /-->', $template );

		$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( 'pergel/hidden-product-filters' );
		$this->assertIsArray( $pattern );
		$blocks = array_values( array_filter( parse_blocks( $pattern['content'] ), static fn ( array $block ): bool => null !== $block['blockName'] ) );
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'woocommerce/product-filters', $blocks[0]['blockName'] );

		$names = array_column( $blocks[0]['innerBlocks'], 'blockName' );
		foreach ( array( 'woocommerce/product-filter-active', 'woocommerce/product-filter-price', 'woocommerce/product-filter-taxonomy', 'woocommerce/product-filter-status' ) as $filter ) {
			$this->assertContains( $filter, $names, $filter );
		}
		// One attribute filter for each of the store's first three attributes.
		$attributes = function_exists( 'wc_get_attribute_taxonomies' ) ? count( wc_get_attribute_taxonomies() ) : 0;
		$this->assertCount( min( 3, $attributes ), array_keys( $names, 'woocommerce/product-filter-attribute', true ) );
	}

	public function test_the_fallback_menu_leaves_out_the_cart_checkout_and_account(): void {
		$this->needs_woocommerce();
		$links = \Pergel\Theme\navigation_fallback( array( array( 'blockName' => 'core/page-list' ) ) );
		$ids   = array_map( static fn ( array $link ): int => (int) $link['attrs']['id'], $links );
		foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
			$this->assertNotContains( wc_get_page_id( $page ), $ids, $page );
		}
	}

	public function test_products_without_an_image_show_the_theme_placeholder(): void {
		$this->needs_woocommerce();
		$chosen = get_option( 'woocommerce_placeholder_image', 0 );
		if ( is_numeric( $chosen ) && (int) $chosen > 0 && ! str_starts_with( wp_basename( (string) get_attached_file( (int) $chosen ) ), 'woocommerce-placeholder' ) ) {
			$this->markTestSkipped( 'The shop has its own placeholder image.' );
		}
		$this->assertSame( get_theme_file_uri( 'assets/images/placeholders/product.svg' ), wc_placeholder_img_src() );
		$this->assertStringContainsString( 'placeholders/product.svg', wc_placeholder_img() );
	}

	/**
	 * Runs the main query for the given query vars, as a page view would.
	 *
	 * @param array<string, mixed> $vars Query vars.
	 */
	private function go_to( array $vars ): void {
		$GLOBALS['wp_the_query'] = new \WP_Query( $vars ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test page view; reset in tearDown.
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- As above.
	}

	public function test_compatibility_is_declared(): void {
		$this->needs_woocommerce();
		$features = wc_get_container()->get( FeaturesController::class );
		$plugin   = plugin_basename( \Pergel\FILE );
		foreach ( array( 'custom_order_tables', 'cart_checkout_blocks' ) as $feature ) {
			$this->assertContains( $plugin, $features->get_compatible_plugins_for_feature( $feature, true )['compatible'], $feature );
		}
	}
}
