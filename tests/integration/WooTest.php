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
		remove_all_filters( 'pergel/woo/shop_request' );
		Settings::flush();
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
				'shop_assets_only'  => true,
				'no_cart_fragments' => true,
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

		// No main query has run: not a shop page.
		$this->assertFalse( Assets::shop_request() );
		$this->assertSame( array(), Assets::styles( $styles ) );

		add_filter( 'pergel/woo/shop_request', '__return_true' );
		$this->assertSame( $styles, Assets::styles( $styles ) );
		remove_all_filters( 'pergel/woo/shop_request' );

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

	public function test_compatibility_is_declared(): void {
		$this->needs_woocommerce();
		$features = wc_get_container()->get( FeaturesController::class );
		$plugin   = plugin_basename( \Pergel\FILE );
		foreach ( array( 'custom_order_tables', 'cart_checkout_blocks' ) as $feature ) {
			$this->assertContains( $plugin, $features->get_compatible_plugins_for_feature( $feature, true )['compatible'], $feature );
		}
	}
}
