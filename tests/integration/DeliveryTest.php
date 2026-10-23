<?php
/**
 * Estimated delivery date: the block's rules and the shipping class's own
 * delivery time.
 *
 * Needs WooCommerce for most tests; on a site without it, it checks that
 * the block is not registered.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Settings\Settings;
use Pergel\Woo\Delivery;
use Pergel\Woo\Modes;
use Pergel\Woo\Module as Woo;
use PHPUnit\Framework\TestCase;

/**
 * Products, shipping classes and settings are removed or restored after
 * each test.
 */
final class DeliveryTest extends TestCase {

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
	 * Shipping classes made by the test.
	 *
	 * @var list<int>
	 */
	private array $classes = array();

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
		foreach ( $this->classes as $id ) {
			wp_delete_term( $id, 'product_shipping_class' );
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
	 * Saves the delivery settings over the defaults.
	 *
	 * @param array<string, mixed> $woo Shop settings.
	 */
	private function settings( array $woo ): void {
		Settings::update( array( 'woo' => $woo + array( 'delivery' => true ) ) );
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
		$product->set_name( 'Pergel delivery test ' . ( count( $this->products ) + 1 ) );
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
	 * The block for a product.
	 *
	 * @param int $id Product ID.
	 */
	private function block( int $id ): string {
		$block = new \WP_Block(
			array(
				'blockName' => Delivery::BLOCK,
				'attrs'     => array(),
			),
			array(
				'postId'   => $id,
				'postType' => 'product',
			)
		);
		return Delivery::render( $block );
	}

	/**
	 * The rules on a block's markup.
	 *
	 * @param string $html Block markup.
	 * @return array<string, mixed>
	 */
	private function rules( string $html ): array {
		$this->assertMatchesRegularExpression( '/data-pergel-delivery="([^"]*)"/', $html );
		preg_match( '/data-pergel-delivery="([^"]*)"/', $html, $m );
		return (array) json_decode( html_entity_decode( $m[1], ENT_QUOTES ), true );
	}

	public function test_block_needs_woocommerce(): void {
		$this->assertSame( Woo::active(), \WP_Block_Type_Registry::get_instance()->is_registered( Delivery::BLOCK ) );
	}

	public function test_delivery_time_is_read_as_written(): void {
		$this->assertSame( array( 3, 3 ), Delivery::days( '3' ) );
		$this->assertSame( array( 2, 4 ), Delivery::days( '2-4' ) );
		$this->assertSame( array( 2, 4 ), Delivery::days( ' 4 – 2 ' ) );
		$this->assertSame( array( 0, 1 ), Delivery::days( '0-1' ) );
		$this->assertNull( Delivery::days( '' ) );
		$this->assertNull( Delivery::days( 'soon' ) );
		$this->assertNull( Delivery::days( '2-99' ) );
		$this->assertNull( Delivery::days( 3 ) );
	}

	public function test_estimate_follows_the_add_to_cart_form_once(): void {
		$this->needs_woocommerce();
		$template          = new \WP_Block_Template();
		$template->slug    = 'single-product';
		$template->content = '<!-- wp:woocommerce/add-to-cart-with-options /-->';

		$this->assertSame( array( Delivery::BLOCK ), Delivery::hook( array(), 'after', 'woocommerce/add-to-cart-with-options', $template ) );
		$this->assertSame( array(), Delivery::hook( array(), 'before', 'woocommerce/add-to-cart-with-options', $template ) );

		$template->content .= '<!-- wp:pergel/delivery-estimate /-->';
		$this->assertSame( array(), Delivery::hook( array(), 'after', 'woocommerce/add-to-cart-with-options', $template ) );
	}

	public function test_block_carries_the_rules_and_waits_for_the_script(): void {
		$this->needs_woocommerce();
		$this->settings(
			array(
				'delivery_cutoff'   => '14:30',
				'delivery_handling' => 0,
				'delivery_min'      => 4,
				'delivery_max'      => 2,
				'delivery_days'     => 'mon_sat',
				'delivery_holidays' => "2026-10-29\n2026-12-31/2027-01-01",
			)
		);
		$html  = $this->block( $this->simple()->get_id() );
		$rules = $this->rules( $html );

		$this->assertMatchesRegularExpression( '/<div [^>]*hidden/', $html );
		$this->assertStringContainsString( esc_html__( 'Estimated delivery:', 'pergel-engine' ), $html );
		$this->assertSame( 14 * 60 + 30, $rules['cutoff'] );
		$this->assertSame( 0, $rules['handling'] );
		$this->assertSame( 2, $rules['min'] );
		$this->assertSame( 4, $rules['max'] );
		$this->assertSame( array( 1, 2, 3, 4, 5, 6 ), $rules['days'] );
		$this->assertSame( array( array( '2026-10-29', '2026-10-29' ), array( '2026-12-31', '2027-01-01' ) ), $rules['off'] );
		$this->assertSame( str_replace( '_', '-', determine_locale() ), $rules['locale'] );
		$this->assertSame( array(), $rules['skip'] );
		// Ships today only with no handling days, the time in the site's format.
		$this->assertStringContainsString( 'wp-block-pergel-delivery-estimate__today', $html );
		$this->assertStringContainsString( esc_html( (string) wp_date( (string) get_option( 'time_format' ), ( new \DateTimeImmutable( 'today 14:30', wp_timezone() ) )->getTimestamp() ) ), $html );
	}

	public function test_no_ships_today_line_with_handling_days_or_no_cut_off(): void {
		$this->needs_woocommerce();
		$this->settings( array( 'delivery_handling' => 1 ) );
		$this->assertStringNotContainsString( '__today', $this->block( $this->simple()->get_id() ) );

		$this->settings(
			array(
				'delivery_handling' => 0,
				'delivery_cutoff'   => '',
			)
		);
		$html = $this->block( $this->simple()->get_id() );
		$this->assertStringNotContainsString( '__today', $html );
		$this->assertNull( $this->rules( $html )['cutoff'] );
	}

	public function test_shipping_class_sets_its_own_delivery_time(): void {
		$this->needs_woocommerce();
		$this->settings(
			array(
				'delivery_min' => 1,
				'delivery_max' => 3,
			)
		);
		$term            = wp_insert_term( 'Pergel delivery test class', 'product_shipping_class' );
		$class           = (int) $term['term_id'];
		$this->classes[] = $class;

		Delivery::save_class( $class, array( 'pergel_delivery' => '5 - 7' ) );
		$this->assertSame( '5-7', get_term_meta( $class, Delivery::META, true ) );

		$product = $this->simple();
		$product->set_shipping_class_id( $class );
		$product->save();
		$rules = $this->rules( $this->block( $product->get_id() ) );
		$this->assertSame( array( 5, 7 ), array( $rules['min'], $rules['max'] ) );

		// The classes screen shows it; the cached term stays as it was.
		$term  = get_term( $class, 'product_shipping_class' );
		$shown = Delivery::classes( array( $term ) );
		$this->assertSame( '5-7', $shown[0]->pergel_delivery );
		$this->assertFalse( isset( get_term( $class, 'product_shipping_class' )->pergel_delivery ) );

		// Not a delivery time: back to the settings.
		Delivery::save_class( $class, array( 'pergel_delivery' => 'soon' ) );
		$this->assertSame( '', get_term_meta( $class, Delivery::META, true ) );
		Delivery::save_class( $class, array( 'name' => 'Only the name' ) );
		$rules = $this->rules( $this->block( $product->get_id() ) );
		$this->assertSame( array( 1, 3 ), array( $rules['min'], $rules['max'] ) );
	}

	public function test_classes_screen_gets_the_column_before_the_count(): void {
		$this->needs_woocommerce();
		$columns = Delivery::columns(
			array(
				'wc-shipping-class-name'  => 'Shipping class',
				'wc-shipping-class-count' => 'Product count',
			)
		);
		$this->assertSame( array( 'wc-shipping-class-name', 'pergel-delivery', 'wc-shipping-class-count' ), array_keys( $columns ) );

		ob_start();
		Delivery::column();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'name="pergel_delivery"', $html );
		$this->assertStringContainsString( '{{ data.pergel_delivery }}', $html );
	}

	public function test_variations_without_an_estimate_are_listed(): void {
		$this->needs_woocommerce();
		$this->settings( array() );
		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'Size' );
		$attribute->set_options( array( 'S', 'M', 'L' ) );
		$attribute->set_variation( true );
		$attribute->set_visible( true );
		$parent = new \WC_Product_Variable();
		$parent->set_attributes( array( $attribute ) );
		$parent = $this->keep( $parent );
		foreach ( array( 'S', 'M', 'L' ) as $size ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
			$variation->set_attributes( array( 'size' => $size ) );
			$variation->set_regular_price( '50' );
			$variation->set_status( 'publish' );
			$variation->set_stock_status( 'M' === $size ? 'outofstock' : 'instock' );
			$variation->set_virtual( 'L' === $size );
			$variation->save();
		}
		\WC_Product_Variable::sync( $parent->get_id() );

		$rules = $this->rules( $this->block( $parent->get_id() ) );
		$skip  = $rules['skip'];
		sort( $skip );
		$this->assertSame( array( array( 'L' ), array( 'M' ) ), $skip );
	}

	public function test_no_estimate_where_nothing_is_shipped_or_sold(): void {
		$this->needs_woocommerce();
		$this->settings( array() );

		$this->assertSame( '', $this->block( $this->simple( Modes::QUOTE )->get_id() ) );
		$this->assertSame( '', $this->block( $this->simple( Modes::CATALOG )->get_id() ) );

		$sold_out = $this->simple();
		$sold_out->set_stock_status( 'outofstock' );
		$sold_out->save();
		$this->assertSame( '', $this->block( $sold_out->get_id() ) );

		$virtual = $this->simple();
		$virtual->set_virtual( true );
		$virtual->save();
		$this->assertSame( '', $this->block( $virtual->get_id() ) );

		$external = new \WC_Product_External();
		$external->set_regular_price( '10' );
		$external->set_product_url( 'https://example.test/' );
		$this->assertSame( '', $this->block( $this->keep( $external )->get_id() ) );
	}

	public function test_no_estimate_while_the_setting_is_off(): void {
		$this->needs_woocommerce();
		$this->settings( array( 'delivery' => false ) );
		$this->assertSame( '', $this->block( $this->simple()->get_id() ) );
	}
}
