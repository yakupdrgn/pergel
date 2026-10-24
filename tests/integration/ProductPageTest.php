<?php
/**
 * Product page extras: the option styles and the sticky add-to-cart bar.
 *
 * Needs WooCommerce for most tests; on a site without it, it checks that
 * the bar's block is not registered.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Settings\Schema;
use Pergel\Settings\Settings;
use Pergel\Woo\Modes;
use Pergel\Woo\Module as Woo;
use Pergel\Woo\StickyCart;
use Pergel\Woo\Swatches;
use PHPUnit\Framework\TestCase;

/**
 * Products and settings are removed or restored after each test.
 */
final class ProductPageTest extends TestCase {

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
		delete_option( Settings::OPTION );
		if ( false !== $this->saved ) {
			remove_all_filters( 'sanitize_option_' . Settings::OPTION );
			add_option( Settings::OPTION, $this->saved, '', true );
			add_filter( 'sanitize_option_' . Settings::OPTION, array( \Pergel\Settings\Module::class, 'sanitize' ) );
		}
		Settings::flush();
		if ( Woo::active() ) {
			Modes::flush();
		}
		wp_dequeue_style( Swatches::STYLE );
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
	 * Saves the product page settings; the ones not given are turned off.
	 *
	 * @param array<string, mixed> $woo Shop settings.
	 */
	private function settings( array $woo ): void {
		$off = array(
			'swatches'    => false,
			'sticky_cart' => false,
		);
		Settings::update( array( 'woo' => $woo + $off ) );
		Settings::flush();
		Modes::flush();
	}

	/**
	 * Saves a product made by the test.
	 *
	 * @param \WC_Product $product Product.
	 * @param string      $mode    Own sales mode; '' follows the shop.
	 */
	private function keep( \WC_Product $product, string $mode = '' ): \WC_Product {
		$product->set_name( 'Pergel product page test ' . ( count( $this->products ) + 1 ) );
		$product->set_status( 'publish' );
		if ( '' !== $mode ) {
			$product->update_meta_data( Modes::POST_META, $mode );
		}
		$product->save();
		$this->products[] = $product->get_id();
		Modes::flush();
		return $product;
	}

	/**
	 * A simple product with a price.
	 *
	 * @param string $mode Own sales mode; '' follows the shop.
	 */
	private function simple( string $mode = '' ): \WC_Product {
		$product = new \WC_Product_Simple();
		$product->set_regular_price( '100' );
		return $this->keep( $product, $mode );
	}

	/**
	 * The bar's block for a product.
	 *
	 * @param int $id Product ID.
	 */
	private function bar( int $id ): string {
		$block = new \WP_Block(
			array(
				'blockName' => StickyCart::BLOCK,
				'attrs'     => array(),
			),
			array(
				'postId'   => $id,
				'postType' => 'product',
			)
		);
		return StickyCart::render( $block );
	}

	public function test_both_are_off_by_default(): void {
		$defaults = Schema::defaults()['woo'];
		$this->assertFalse( $defaults['swatches'] );
		$this->assertFalse( $defaults['sticky_cart'] );
	}

	public function test_bar_block_needs_woocommerce(): void {
		$this->assertSame( Woo::active(), \WP_Block_Type_Registry::get_instance()->is_registered( StickyCart::BLOCK ) );
	}

	public function test_bar_follows_the_add_to_cart_form_once(): void {
		$this->needs_woocommerce();
		$template          = new \WP_Block_Template();
		$template->slug    = 'single-product';
		$template->content = '<!-- wp:woocommerce/add-to-cart-with-options /-->';

		$this->assertSame( array( 'x', StickyCart::BLOCK ), StickyCart::hook( array( 'x' ), 'after', 'woocommerce/add-to-cart-with-options', $template ) );
		$this->assertSame( array(), StickyCart::hook( array(), 'before', 'woocommerce/add-to-cart-with-options', $template ) );
		$this->assertSame( array(), StickyCart::hook( array(), 'after', 'woocommerce/product-button', $template ) );

		$template->content .= '<!-- wp:pergel/sticky-add-to-cart /-->';
		$this->assertSame( array(), StickyCart::hook( array(), 'after', 'woocommerce/add-to-cart-with-options', $template ) );
	}

	public function test_bar_waits_for_the_script_and_presses_the_form_button(): void {
		$this->needs_woocommerce();
		$this->settings( array( 'sticky_cart' => true ) );
		$product = $this->simple();
		$html    = $this->bar( $product->get_id() );

		$this->assertStringContainsString( 'data-pergel-sticky="' . $product->get_id() . '"', $html );
		$this->assertMatchesRegularExpression( '/<div [^>]*hidden/', $html );
		$this->assertStringContainsString( esc_html( $product->get_name() ), $html );
		$this->assertStringContainsString( '<button type="button"', $html );
		$this->assertStringContainsString( esc_html( $product->single_add_to_cart_text() ), $html );
		$this->assertStringContainsString( 'data-choose=""', $html );
		$this->assertStringContainsString( 'alt=""', $html );
	}

	public function test_bar_look_from_the_settings(): void {
		$this->needs_woocommerce();
		$this->settings( array( 'sticky_cart' => true ) );
		Settings::update( array( 'shop' => Schema::defaults()['shop'] ) );
		$product = $this->simple();
		$product->set_short_description( '<p>Two <strong>lines</strong> about it.</p>' );
		$product->save();

		$html = $this->bar( $product->get_id() );
		$this->assertStringNotContainsString( 'is-button-', $html, 'The template\'s look by default.' );
		$this->assertStringNotContainsString( '--pergel-sticky-', $html );
		$this->assertStringNotContainsString( '__text', $html );

		Settings::update(
			array(
				'shop' => array(
					'sticky_bg'     => 'surface',
					'sticky_image'  => 84,
					'sticky_text'   => true,
					'sticky_button' => 'outline',
					'sticky_shape'  => 'pill',
				),
			)
		);
		$html = $this->bar( $product->get_id() );
		$this->assertStringContainsString( 'style="--pergel-sticky-bg:var(--wp--preset--color--surface);--pergel-sticky-image:84px"', $html );
		$this->assertStringContainsString( 'class="is-button-outline is-shape-pill', $html );
		$this->assertStringContainsString( '<p class="wp-block-pergel-sticky-add-to-cart__text">Two lines about it.</p>', $html );
	}

	public function test_variable_product_asks_for_its_options_first(): void {
		$this->needs_woocommerce();
		$this->settings( array( 'sticky_cart' => true ) );
		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'Size' );
		$attribute->set_options( array( 'S', 'M' ) );
		$attribute->set_variation( true );
		$attribute->set_visible( true );
		$parent = new \WC_Product_Variable();
		$parent->set_attributes( array( $attribute ) );
		$parent = $this->keep( $parent );
		foreach ( array( 'S', 'M' ) as $size ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
			$variation->set_attributes( array( 'size' => $size ) );
			$variation->set_regular_price( '50' );
			$variation->set_status( 'publish' );
			$variation->save();
		}
		\WC_Product_Variable::sync( $parent->get_id() );

		$html = $this->bar( $parent->get_id() );
		$this->assertStringContainsString( 'data-choose="' . esc_attr__( 'Choose options', 'pergel-engine' ) . '"', $html );
	}

	public function test_no_bar_where_the_product_cannot_be_bought(): void {
		$this->needs_woocommerce();
		$this->settings( array( 'sticky_cart' => true ) );

		$this->assertSame( '', $this->bar( $this->simple( Modes::QUOTE )->get_id() ) );
		$this->assertSame( '', $this->bar( $this->simple( Modes::CATALOG )->get_id() ) );

		$sold_out = $this->simple();
		$sold_out->set_stock_status( 'outofstock' );
		$sold_out->save();
		$this->assertSame( '', $this->bar( $sold_out->get_id() ) );

		$external = new \WC_Product_External();
		$external->set_regular_price( '10' );
		$external->set_product_url( 'https://example.test/' );
		$this->assertSame( '', $this->bar( $this->keep( $external )->get_id() ) );

		$post = (int) wp_insert_post( array( 'post_title' => 'Not a product' ) );
		$this->assertSame( '', $this->bar( $post ) );
		wp_delete_post( $post, true );
	}

	public function test_no_bar_while_the_setting_is_off(): void {
		$this->needs_woocommerce();
		$this->settings( array() );
		$this->assertSame( '', $this->bar( $this->simple()->get_id() ) );
	}

	public function test_option_styles_load_only_with_the_selector(): void {
		$this->needs_woocommerce();
		Swatches::register_style();

		Swatches::render( '' );
		$this->assertFalse( wp_style_is( Swatches::STYLE, 'enqueued' ) );

		$this->assertSame( '<div>options</div>', Swatches::render( '<div>options</div>' ) );
		$this->assertTrue( wp_style_is( Swatches::STYLE, 'enqueued' ) );
		$this->assertFileExists( (string) wp_styles()->get_data( Swatches::STYLE, 'path' ) );
	}
}
