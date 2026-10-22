<?php
/**
 * Product card settings in the product grids.
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
use Pergel\Woo\BadgeField;
use Pergel\Woo\Cards;
use Pergel\Woo\Layout;
use Pergel\Woo\Module as Woo;
use PHPUnit\Framework\TestCase;

/**
 * Settings, the product and the main query are put back after each test.
 */
final class CardsTest extends TestCase {

	/**
	 * A product grid with a picture, sale badge, title, price and the
	 * wishlist buttons, like the theme's cards.
	 */
	private const GRID = '<!-- wp:woocommerce/product-collection {"queryId":0,"query":{"inherit":true,"isProductCollectionBlock":true,"perPage":12,"postType":"product"},"tagName":"div","displayLayout":{"type":"flex","columns":3,"shrinkColumns":true}} -->'
		. '<div class="wp-block-woocommerce-product-collection"><!-- wp:woocommerce/product-template -->'
		. '<!-- wp:woocommerce/product-image {"isDescendentOfQueryLoop":true} --><!-- wp:woocommerce/product-sale-badge {"isDescendentOfQueryLoop":true} /--><!-- /wp:woocommerce/product-image -->'
		. '<!-- wp:post-title {"isLink":true} /--><!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true} /--><!-- wp:pergel/product-actions /-->'
		. '<!-- /wp:woocommerce/product-template --></div><!-- /wp:woocommerce/product-collection -->';

	/**
	 * The option as it was before the test (false: not saved).
	 *
	 * @var mixed
	 */
	private mixed $saved = false;

	/**
	 * The main query before go_to() replaced it.
	 */
	private ?\WP_Query $query = null;

	/**
	 * The product made for the test.
	 */
	private int $product = 0;

	protected function setUp(): void {
		// Saved first: tearDown() runs after a skip too.
		$this->saved = get_option( Settings::OPTION, false );
		if ( ! Woo::active() ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
		Settings::update( array( 'shop' => Schema::defaults()['shop'] ) );
		$product = new \WC_Product_Simple();
		$product->set_name( 'Card test product' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->set_sale_price( '8' );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 3 );
		$this->product = $product->save();
		Cards::register();
		$this->go_to(
			array(
				'post_type' => 'product',
				'post__in'  => array( $this->product ),
			)
		);
	}

	protected function tearDown(): void {
		delete_option( Settings::OPTION );
		if ( false !== $this->saved ) {
			// Restored as stored, without running it through the schema again.
			remove_all_filters( 'sanitize_option_' . Settings::OPTION );
			add_option( Settings::OPTION, $this->saved, '', true );
			add_filter( 'sanitize_option_' . Settings::OPTION, array( \Pergel\Settings\Module::class, 'sanitize' ) );
		}
		Settings::flush();
		if ( $this->product > 0 ) {
			wp_delete_post( $this->product, true );
		}
		wp_dequeue_style( Layout::CARD_STYLE );
		unset( $_POST[ Cards::BADGE_META ] );
		if ( null !== $this->query ) {
			$GLOBALS['wp_the_query'] = $this->query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Puts back the query go_to() replaced.
		}
		wp_reset_query();
	}

	/**
	 * Runs the main query for the given query vars, as a page view would.
	 *
	 * @param array<string, mixed> $vars Query vars.
	 */
	private function go_to( array $vars ): void {
		$this->query           ??= $GLOBALS['wp_the_query'];
		$GLOBALS['wp_the_query'] = new \WP_Query( $vars ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test page view; put back in tearDown.
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- As above.
	}

	public function test_defaults_leave_the_cards_alone(): void {
		$html = do_blocks( self::GRID );

		$this->assertStringContainsString( 'Card test product', $html );
		$this->assertStringNotContainsString( 'pergel-card', $html );
		$this->assertStringNotContainsString( 'pergel-badge', $html );
		$this->assertStringContainsString( 'wc-block-components-product-sale-badge__text', $html );
		$this->assertStringContainsString( 'class="wp-block-pergel-product-actions"', $html );
		$this->assertFalse( wp_style_is( Layout::CARD_STYLE ) );
	}

	public function test_card_settings_reach_the_cards(): void {
		Settings::update(
			array(
				'shop' => array(
					'card_style'     => 'overlay',
					'card_discount'  => true,
					'card_stock'     => true,
					'badge_sale'     => 'percent',
					'badge_new'      => 30,
					'badge_featured' => true,
				),
			)
		);
		$product = wc_get_product( $this->product );
		$this->assertInstanceOf( \WC_Product::class, $product );
		$product->update_meta_data( Cards::BADGE_META, 'Limited' );
		$product->save();
		$this->assertSame( 20, Cards::discount( $product ) );

		$html = do_blocks( self::GRID );

		$this->assertStringContainsString( 'pergel-cards pergel-card-overlay', $html );
		$this->assertMatchesRegularExpression( '#<span class="pergel-badges"><span class="pergel-badge is-own">Limited</span><span class="pergel-badge is-new">[^<]+</span></span>#', $html );
		$this->assertStringNotContainsString( 'is-popular', $html, 'The product is not featured.' );
		$this->assertMatchesRegularExpression( '#sale-badge__text"[^>]*>[^<]*20[^<]*</span>#', $html );
		$this->assertMatchesRegularExpression( '#pergel-card-pill is-discount">[^<]*20#', $html );
		$this->assertMatchesRegularExpression( '#pergel-card-pill is-stock">[^<]*3#', $html );
		// The buttons move into the picture, once.
		$this->assertStringContainsString( 'pergel-card-actions wp-block-pergel-product-actions', $html );
		$this->assertStringNotContainsString( 'class="wp-block-pergel-product-actions"', $html );
		$this->assertTrue( wp_style_is( Layout::CARD_STYLE ) );

		Settings::update( array( 'shop' => array( 'badge_sale' => 'none' ) ) );
		$this->assertStringNotContainsString( 'sale-badge__text', do_blocks( self::GRID ) );
	}

	public function test_category_and_brand_above_the_name(): void {
		Settings::update(
			array(
				'shop' => array(
					'card_category' => true,
					'card_brand'    => true,
				),
			)
		);
		$made = wp_insert_term( 'Card category ' . wp_generate_password( 6, false ), 'product_cat' );
		$this->assertIsArray( $made );
		wp_set_object_terms( $this->product, array( (int) $made['term_id'] ), 'product_cat' );

		$html = do_blocks( self::GRID );
		wp_delete_term( (int) $made['term_id'], 'product_cat' );

		$this->assertMatchesRegularExpression( '#<div class="pergel-card-meta">.*Card category.*</div><h2#s', $html );
	}

	public function test_cards_load_only_where_used(): void {
		remove_all_filters( 'render_block_core/post-title' );
		$this->go_to( array( 'post_type' => 'post' ) );

		Layout::cards();
		$this->assertFalse( has_filter( 'render_block_core/post-title', array( Cards::class, 'title' ) ), 'Defaults: product pages keep their file count.' );

		Settings::update( array( 'shop' => array( 'card_rating' => true ) ) );
		Layout::cards();
		$this->assertNotFalse( has_filter( 'render_block_core/post-title', array( Cards::class, 'title' ) ) );
	}

	public function test_badge_text_is_cleaned(): void {
		$this->assertSame( 'Limited', BadgeField::clean( "  <b>Limited</b>\n" ) );
		$this->assertSame( 24, mb_strlen( BadgeField::clean( str_repeat( 'ç', 40 ) ) ) );
		$this->assertSame( '', BadgeField::clean( array( 'x' ) ) );
	}
}
