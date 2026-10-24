<?php
/**
 * Frequently bought together: where the block goes, which products it
 * offers and how.
 *
 * Needs WooCommerce for most tests; on a site without it, it checks that
 * the block is not registered.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Settings\Settings;
use Pergel\Woo\BoughtTogether;
use Pergel\Woo\Modes;
use Pergel\Woo\Module as Woo;
use PHPUnit\Framework\TestCase;

/**
 * Products and settings are removed or restored after each test.
 */
final class BoughtTogetherTest extends TestCase {

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
		foreach ( array_reverse( $this->products ) as $id ) {
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
	}

	public function test_block_needs_woocommerce(): void {
		$this->assertSame( Woo::active(), \WP_Block_Type_Registry::get_instance()->is_registered( BoughtTogether::BLOCK ) );
	}

	public function test_block_follows_the_add_to_cart_form_once(): void {
		$this->needs_woocommerce();
		$template          = new \WP_Block_Template();
		$template->content = '<!-- wp:woocommerce/add-to-cart-with-options /-->';

		$this->assertSame( array( BoughtTogether::BLOCK ), BoughtTogether::hook( array(), 'after', 'woocommerce/add-to-cart-with-options', $template ) );
		$this->assertSame( array(), BoughtTogether::hook( array(), 'before', 'woocommerce/add-to-cart-with-options', $template ) );

		$template->content .= '<!-- wp:pergel/bought-together /-->';
		$this->assertSame( array(), BoughtTogether::hook( array(), 'after', 'woocommerce/add-to-cart-with-options', $template ) );
	}

	public function test_product_and_its_cross_sells_with_a_total(): void {
		$this->needs_woocommerce();
		$this->settings( true );
		$first    = $this->simple( '20' );
		$second   = $this->simple( '30' );
		$options  = $this->variable();
		$fourth   = $this->simple( '40' );
		$main     = $this->simple( '100', array( $first->get_id(), $second->get_id(), $options->get_id(), $fourth->get_id() ) );
		$html     = $this->block( $main->get_id() );
		$base     = 'pergel-together-' . $main->get_id();
		$checkbox = static fn( \WC_Product $p ): string => 'id="' . $base . '-' . $p->get_id() . '" name="' . BoughtTogether::FIELD_ITEMS . '[]" value="' . $p->get_id() . '"';

		$this->assertStringContainsString( 'aria-labelledby="' . $base . '-title"', $html );
		$this->assertStringContainsString( 'name="' . BoughtTogether::FIELD_PRODUCT . '" value="' . $main->get_id() . '"', $html );
		$this->assertStringContainsString( 'action="' . esc_url( (string) get_permalink( $main->get_id() ) ) . '"', $html );
		foreach ( array( $main, $first, $second ) as $product ) {
			$this->assertStringContainsString( $checkbox( $product ), $html );
		}
		$this->assertStringContainsString( esc_html( sprintf( /* translators: %s: product name. */ __( 'This product: %s', 'pergel-engine' ), $main->get_name() ) ), $html );

		// The one with options links to its page; only three cross-sells.
		$this->assertStringNotContainsString( $checkbox( $options ), $html );
		$this->assertStringContainsString( 'href="' . esc_url( $options->get_permalink() ) . '"', $html );
		$this->assertStringContainsString( esc_html__( 'Choose its options on its page.', 'pergel-engine' ), $html );
		$this->assertStringNotContainsString( 'value="' . $fourth->get_id() . '"', $html );
		$this->assertSame( 4, substr_count( $html, '<li ' ) );

		$this->assertStringContainsString( $this->total( 3 ), $html );
		$this->assertSame( self::text( wc_price( 150 ) ), $this->amount( $html ) );
		$this->assertStringContainsString( 'role="status"', $html );
		$this->assertTrue( wp_style_is( 'pergel-bought-together', 'enqueued' ) );

		$config = $this->config( $html );
		$this->assertSame( $main->get_id(), $config['product'] );
		$this->assertNull( $config['variations'] );
		$this->assertSame( rest_url( 'wc/store/v1/batch' ), $config['batch'] );
		$this->assertSame( wc_get_price_decimals(), $config['format']['decimals'] );
		$this->assertCount( BoughtTogether::LIMIT + 2, $config['added'] );
	}

	public function test_cross_sells_that_cannot_be_shown_are_skipped(): void {
		$this->needs_woocommerce();
		$this->settings( true );
		$gone = $this->simple( '10' );
		$gone->set_stock_status( 'outofstock' );
		$gone->save();
		$draft = $this->simple( '10' );
		$draft->set_status( 'draft' );
		$draft->save();
		$hidden = $this->simple( '10' );
		$hidden->set_catalog_visibility( 'hidden' );
		$hidden->save();
		$main = $this->simple( '100', array( $gone->get_id(), $draft->get_id(), $hidden->get_id() ) );

		$this->assertSame( array(), BoughtTogether::others( $main ) );
		$this->assertSame( '', $this->block( $main->get_id() ) );
	}

	public function test_cross_sell_sold_another_way_gets_a_link(): void {
		$this->needs_woocommerce();
		$this->settings( true );
		$quote = $this->simple( '10', array(), Modes::QUOTE );
		$main  = $this->simple( '100', array( $quote->get_id() ) );
		$html  = $this->block( $main->get_id() );

		$this->assertFalse( BoughtTogether::addable( $quote ) );
		$this->assertStringContainsString( 'href="' . esc_url( $quote->get_permalink() ) . '"', $html );
		$this->assertStringContainsString( esc_html__( 'Details on its page.', 'pergel-engine' ), $html );
		$this->assertStringContainsString( $this->total( 1 ), $html );
	}

	public function test_only_simple_products_on_sale_get_a_checkbox(): void {
		$this->needs_woocommerce();
		$this->assertTrue( BoughtTogether::addable( $this->simple( '10' ) ) );
		$this->assertFalse( BoughtTogether::addable( $this->simple( '' ) ) );
		$this->assertFalse( BoughtTogether::addable( $this->variable() ) );
		$gone = $this->simple( '10' );
		$gone->set_stock_status( 'outofstock' );
		$gone->save();
		$this->assertFalse( BoughtTogether::addable( $gone ) );
		$this->assertFalse( BoughtTogether::addable( $this->simple( '10', array(), Modes::CATALOG ) ) );
	}

	public function test_product_with_options_waits_for_them(): void {
		$this->needs_woocommerce();
		$this->settings( true );
		$other = $this->simple( '20' );
		$main  = $this->variable( array( $other->get_id() ) );
		$html  = $this->block( $main->get_id() );
		$id    = 'pergel-together-' . $main->get_id() . '-' . $main->get_id();

		$this->assertMatchesRegularExpression( '/id="' . $id . '"[^>]*value="' . $main->get_id() . '"[^>]* disabled aria-describedby="' . $id . '-hint"/', $html );
		$this->assertDoesNotMatchRegularExpression( '/id="' . $id . '"[^>]* checked/', $html );
		$this->assertStringContainsString( '<span id="' . $id . '-hint"', $html );
		// Until then the total holds the cross-sell alone.
		$this->assertSame( self::text( wc_price( 20 ) ), $this->amount( $html ) );
		$this->assertStringContainsString( $this->total( 1 ), $html );

		$variations = $this->config( $html )['variations'];
		$this->assertCount( 2, $variations );
		$this->assertSame( array( 'attribute_size' => 'S' ), $variations[0]['attributes'] );
		$this->assertEqualsWithDelta( 15.0, $variations[0]['price'], 0.001 );
		$this->assertTrue( $variations[0]['buyable'] );
		$this->assertFalse( $variations[1]['buyable'] );
	}

	public function test_nothing_where_it_cannot_be_bought_here(): void {
		$this->needs_woocommerce();
		$this->settings( true );
		$other = $this->simple( '20' );

		$this->assertSame( '', $this->block( $this->simple( '100' )->get_id() ) );
		$this->assertSame( '', $this->block( $this->simple( '100', array( $other->get_id() ), Modes::QUOTE )->get_id() ) );
		$this->assertSame( '', $this->block( $this->simple( '100', array( $other->get_id() ), Modes::CATALOG )->get_id() ) );
		$gone = $this->simple( '100', array( $other->get_id() ) );
		$gone->set_stock_status( 'outofstock' );
		$gone->save();
		$this->assertSame( '', $this->block( $gone->get_id() ) );

		$main = $this->simple( '100', array( $other->get_id() ) );
		$this->assertNotSame( '', $this->block( $main->get_id() ) );
		$this->settings( false );
		$this->assertSame( '', $this->block( $main->get_id() ) );
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
	 * Turns the block on or off.
	 *
	 * @param bool $on Whether it is on.
	 */
	private function settings( bool $on ): void {
		Settings::update( array( 'woo' => array( 'bought_together' => $on ) ) );
		Settings::flush();
		Modes::flush();
	}

	/**
	 * Saves a product made by the test.
	 *
	 * @param \WC_Product $product     Product.
	 * @param list<int>   $cross_sells Cross-sell ids.
	 * @param string      $mode        Own sales mode; '' follows the shop.
	 */
	private function keep( \WC_Product $product, array $cross_sells, string $mode ): \WC_Product {
		$product->set_name( 'Pergel together test ' . ( count( $this->products ) + 1 ) );
		$product->set_status( 'publish' );
		$product->set_cross_sell_ids( $cross_sells );
		if ( '' !== $mode ) {
			$product->update_meta_data( Modes::POST_META, $mode );
		}
		$product->save();
		$this->products[] = $product->get_id();
		Modes::flush();
		return $product;
	}

	/**
	 * A simple product.
	 *
	 * @param string    $price       Regular price; '' for none.
	 * @param list<int> $cross_sells Cross-sell ids.
	 * @param string    $mode        Own sales mode; '' follows the shop.
	 */
	private function simple( string $price, array $cross_sells = array(), string $mode = '' ): \WC_Product {
		$product = new \WC_Product_Simple();
		$product->set_regular_price( $price );
		return $this->keep( $product, $cross_sells, $mode );
	}

	/**
	 * A product with a size option: S for 15, M out of stock for 25.
	 *
	 * @param list<int> $cross_sells Cross-sell ids.
	 */
	private function variable( array $cross_sells = array() ): \WC_Product {
		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'size' );
		$attribute->set_options( array( 'S', 'M' ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$product = new \WC_Product_Variable();
		$product->set_attributes( array( $attribute ) );
		$product = $this->keep( $product, $cross_sells, '' );

		foreach ( array(
			'S' => '15',
			'M' => '25',
		) as $size => $price ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $product->get_id() );
			$variation->set_attributes( array( 'size' => $size ) );
			$variation->set_regular_price( $price );
			$variation->set_stock_status( 'M' === $size ? 'outofstock' : 'instock' );
			$variation->save();
			$this->products[] = $variation->get_id();
		}
		\WC_Product_Variable::sync( $product->get_id() );
		Modes::flush();
		$fresh = wc_get_product( $product->get_id() );
		return $fresh instanceof \WC_Product ? $fresh : $product;
	}

	/**
	 * The block for a product.
	 *
	 * @param int $id Product ID.
	 */
	private function block( int $id ): string {
		$block = new \WP_Block(
			array(
				'blockName' => BoughtTogether::BLOCK,
				'attrs'     => array(),
			),
			array(
				'postId'   => $id,
				'postType' => 'product',
			)
		);
		return BoughtTogether::render( $block );
	}

	/**
	 * The total's text for a number of products.
	 *
	 * @param int $count Number of products.
	 */
	private function total( int $count ): string {
		/* translators: %d: number of products. */
		return esc_html( sprintf( _n( 'Total for %d product:', 'Total for %d products:', $count, 'pergel-engine' ), $count ) );
	}

	/**
	 * What a visitor reads in the total's amount.
	 *
	 * @param string $html Block markup.
	 */
	private function amount( string $html ): string {
		$this->assertSame( 1, preg_match( '#<strong class="wp-block-pergel-bought-together__amount">(.*?)</strong>#s', $html, $match ) );
		return self::text( $match[1] );
	}

	/**
	 * Text of a piece of HTML. WordPress 7.2 writes the same markup with
	 * other attribute order and entities, so prices are compared as read.
	 *
	 * @param string $html Markup.
	 */
	private static function text( string $html ): string {
		return html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * The script's settings in the block.
	 *
	 * @param string $html Block markup.
	 * @return array<string, mixed>
	 */
	private function config( string $html ): array {
		preg_match( '/data-pergel-together="([^"]*)"/', $html, $m );
		return (array) json_decode( html_entity_decode( $m[1] ?? '', ENT_QUOTES ), true );
	}
}
