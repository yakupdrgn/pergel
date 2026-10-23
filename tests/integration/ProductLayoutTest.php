<?php
/**
 * Product page layout settings on the product template.
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
use Pergel\Woo\CouponField;
use Pergel\Woo\Layout;
use Pergel\Woo\Module as Woo;
use Pergel\Woo\ProductPage;
use PHPUnit\Framework\TestCase;

/**
 * Settings, the products, the categories and the main query are put back
 * after each test.
 */
final class ProductLayoutTest extends TestCase {

	/**
	 * The columns of the theme's product template, shortened: gallery,
	 * then name and price.
	 */
	private const TEMPLATE = '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column {"width":"55%"} --><div class="wp-block-column" style="flex-basis:55%">'
		. '<!-- wp:woocommerce/product-gallery --><div class="wp-block-woocommerce-product-gallery wc-block-product-gallery"><!-- wp:woocommerce/product-gallery-thumbnails /-->'
		. "\n\n" . '<!-- wp:woocommerce/product-gallery-large-image --><!-- wp:woocommerce/product-image {"showProductLink":false,"showSaleBadge":false} /--><!-- /wp:woocommerce/product-gallery-large-image --></div><!-- /wp:woocommerce/product-gallery -->'
		. '</div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column">'
		. '<!-- wp:post-title {"level":1} /--><!-- wp:woocommerce/product-price {"isDescendentOfSingleProductTemplate":true} /-->'
		. '</div><!-- /wp:column --></div><!-- /wp:columns -->';

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
	 * Products made for the test.
	 *
	 * @var list<int>
	 */
	private array $products = array();

	/**
	 * Categories made for the test.
	 *
	 * @var list<int>
	 */
	private array $terms = array();

	/**
	 * Coupons made for the test.
	 *
	 * @var list<int>
	 */
	private array $coupons = array();

	protected function setUp(): void {
		// Saved first: tearDown() runs after a skip too.
		$this->saved = get_option( Settings::OPTION, false );
		if ( ! Woo::active() ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
		Settings::update( array( 'shop' => Schema::defaults()['shop'] ) );
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
		foreach ( $this->products as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( $this->terms as $id ) {
			wp_delete_term( $id, 'product_cat' );
		}
		foreach ( $this->coupons as $id ) {
			wp_delete_post( $id, true );
		}
		$hooks = array(
			'render_block_data'                                   => 'gallery_data',
			'render_block_context'                                => 'zoom',
			'render_block_woocommerce/product-gallery'            => 'gallery',
			'render_block_woocommerce/product-gallery-large-image' => 'large_image',
			'render_block_core/columns'                           => 'columns',
			'render_block_core/post-title'                        => 'title',
			'render_block_woocommerce/product-price'              => 'price',
			'render_block_pergel/breadcrumb'                      => 'nav',
			'render_block_woocommerce/add-to-cart-with-options'   => 'buying',
			'render_block_pergel/product-cta'                     => 'share',
		);
		foreach ( $hooks as $hook => $method ) {
			remove_filter( $hook, array( ProductPage::class, $method ), 10 );
		}
		wp_dequeue_style( Layout::PRODUCT_STYLE );
		wp_dequeue_style( Layout::CARD_STYLE );
		Layout::flush();
		if ( null !== $this->query ) {
			$GLOBALS['wp_the_query'] = $this->query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Puts back the query go_to() replaced.
		}
		wp_reset_query();
	}

	/**
	 * Makes a product in a new category.
	 *
	 * @param string $category Category name.
	 * @return array{0: int, 1: int} Product and category ids.
	 */
	private function product( string $category ): array {
		$term = wp_insert_term( $category . ' ' . wp_generate_password( 6, false ), 'product_cat' );
		$this->assertIsArray( $term );
		$this->terms[] = (int) $term['term_id'];
		$product       = new \WC_Product_Simple();
		$product->set_name( $category . ' product' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '20' );
		$product->set_sale_price( '15' );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 4 );
		$product->set_category_ids( array( (int) $term['term_id'] ) );
		$id               = $product->save();
		$this->products[] = $id;
		return array( $id, (int) $term['term_id'] );
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
		$GLOBALS['post']         = get_post( $id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- As above.
		Layout::flush();
	}

	public function test_defaults_do_not_load_the_product_page(): void {
		[ $id ] = $this->product( 'Page defaults' );
		$this->go_to( $id );

		Layout::product_page();

		$this->assertFalse( has_filter( 'render_block_core/columns', array( ProductPage::class, 'columns' ) ) );
		$this->assertFalse( wp_style_is( Layout::PRODUCT_STYLE ) );
	}

	public function test_product_then_category_then_site(): void {
		[ $id, $term ] = $this->product( 'Page levels' );
		$this->go_to( $id );
		$this->assertSame( 'template', Layout::get( 'shop.product_layout' ) );

		update_term_meta( $term, Layout::TERM_META, array( 'shop.product_layout' => 'right' ) );
		Layout::flush();
		$this->assertSame( 'right', Layout::get( 'shop.product_layout' ), 'The category chooses.' );

		update_post_meta( $id, Layout::PRODUCT_META, array( 'shop.product_layout' => 'three' ) );
		$this->assertSame( 'three', Layout::get( 'shop.product_layout' ), 'The product chooses.' );

		update_post_meta( $id, Layout::PRODUCT_META, array( 'shop.product_zoom' => false ) );
		$this->assertTrue( Layout::get( 'shop.product_zoom' ), 'Not a product level setting.' );
	}

	public function test_settings_reach_the_template(): void {
		[ $id, $term ] = $this->product( 'Page settings' );
		[ $other ]     = $this->product( 'Page upsell' );
		wp_set_object_terms( $other, array( $term ), 'product_cat', true );
		$product = wc_get_product( $id );
		$this->assertInstanceOf( \WC_Product::class, $product );
		$product->set_upsell_ids( array( $other ) );
		$product->save();
		Settings::update(
			array(
				'shop' => array(
					'product_layout'   => 'three',
					'product_thumbs'   => 'bottom',
					'product_sticky'   => true,
					'product_zoom'     => false,
					'product_category' => true,
					'product_discount' => true,
					'product_stock'    => true,
				),
			)
		);
		$this->go_to( $id );
		Layout::product_page();
		$this->assertNotFalse( has_filter( 'render_block_core/columns', array( ProductPage::class, 'columns' ) ) );

		$html = do_blocks( self::TEMPLATE );

		$this->assertMatchesRegularExpression( '#<div class="wp-block-columns[^"]*pergel-product-layout is-pictures-three is-sticky-details#', $html );
		$this->assertMatchesRegularExpression( '#<aside class="wp-block-column pergel-product-side"[^>]*>.*Page upsell product.*</aside>#s', $html, 'The upsell in the third column.' );
		$this->assertMatchesRegularExpression( '#class="wp-block-woocommerce-product-gallery [^"]*is-vertical#', $html, 'Small pictures under the big one.' );
		$this->assertSame( array( 'woocommerce/product-gallery-large-image', 'woocommerce/product-gallery-thumbnails' ), $this->gallery_blocks() );
		$this->assertMatchesRegularExpression( '#<p class="pergel-product-category"><a href="[^"]+">Page settings [^<]+</a></p><h1#', $html );
		$this->assertMatchesRegularExpression( '#pergel-card-pill is-discount">[^<]*25#', $html );
		$this->assertMatchesRegularExpression( '#pergel-card-pill is-stock">[^<]*4#', $html );
		$this->assertStringNotContainsString( 'actions.startZoom', $html );
		$this->assertTrue( wp_style_is( Layout::PRODUCT_STYLE ) );
		$this->assertTrue( wp_style_is( Layout::CARD_STYLE ) );
	}

	public function test_no_small_pictures_on_one_product(): void {
		[ $id ] = $this->product( 'Page none' );
		update_post_meta( $id, Layout::PRODUCT_META, array( 'shop.product_thumbs' => 'none' ) );
		$this->go_to( $id );
		Layout::product_page();

		$html = do_blocks( self::TEMPLATE );

		$this->assertStringContainsString( 'wc-block-product-gallery-large-image', $html );
		$this->assertSame( array( 'woocommerce/product-gallery-large-image' ), $this->gallery_blocks() );
		$this->assertStringNotContainsString( 'pergel-product-layout', $html, "The layout stays the template's." );

		update_post_meta( $id, Layout::PRODUCT_META, array( 'shop.product_thumbs' => 'left' ) );
		$this->assertSame( array( 'woocommerce/product-gallery-thumbnails', 'woocommerce/product-gallery-large-image' ), $this->gallery_blocks() );
	}

	/**
	 * The gallery's inner blocks after ProductPage::gallery_data(), checked
	 * against its inner content: one null per inner block.
	 *
	 * @return list<string>
	 */
	private function gallery_blocks(): array {
		$columns = parse_blocks( self::TEMPLATE )[0];
		$gallery = ProductPage::gallery_data( $columns['innerBlocks'][0]['innerBlocks'][0] );
		$this->assertCount( count( $gallery['innerBlocks'] ), array_keys( $gallery['innerContent'], null, true ) );
		return array_map( static fn ( array $block ): string => (string) $block['blockName'], $gallery['innerBlocks'] );
	}

	public function test_buy_now_coupons_and_share(): void {
		[ $id, $term ] = $this->product( 'Page buying' );
		$this->coupon( 'pergel-shown', array() );
		$this->coupon( 'pergel-hidden', array(), false );
		$this->coupon( 'pergel-other-category', array( 'product_categories' => array( $term + 100000 ) ) );
		Settings::update(
			array(
				'shop' => array(
					'product_buy_now' => true,
					'product_coupons' => true,
					'product_share'   => true,
				),
			)
		);
		$this->go_to( $id );
		Layout::product_page();

		$html = do_blocks( '<!-- wp:woocommerce/add-to-cart-with-options /--><!-- wp:pergel/product-cta /-->' );

		$this->assertMatchesRegularExpression( '#<button type="button" class="wp-element-button pergel-buy-now" data-pergel-buy-now>[^<]+</button></form>#', $html );
		$this->assertStringContainsString( 'data-pergel-copy="PERGEL-SHOWN"', $html );
		$this->assertStringNotContainsString( 'PERGEL-HIDDEN', $html, 'Not chosen for product pages.' );
		$this->assertStringNotContainsString( 'PERGEL-OTHER-CATEGORY', $html, 'For another category.' );
		$this->assertStringContainsString( 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( (string) get_permalink( $id ) ), $html );
		$this->assertStringContainsString( 'class="pergel-share__link is-copy" data-pergel-copy="' . esc_attr( (string) get_permalink( $id ) ) . '"', $html );
	}

	public function test_coupon_rules(): void {
		[ $id, $term ] = $this->product( 'Page rules' );
		$product       = wc_get_product( $id );
		$this->assertInstanceOf( \WC_Product::class, $product );
		$coupon = static function ( array $props ): \WC_Coupon {
			$coupon = new \WC_Coupon();
			$coupon->set_code( 'rule-' . wp_generate_password( 6, false ) );
			$coupon->set_props( $props );
			return $coupon;
		};

		$this->assertTrue( ProductPage::applies( $coupon( array() ), $product ) );
		$this->assertTrue( ProductPage::applies( $coupon( array( 'product_categories' => array( $term ) ) ), $product ) );
		$this->assertFalse( ProductPage::applies( $coupon( array( 'excluded_product_categories' => array( $term ) ) ), $product ) );
		$this->assertFalse( ProductPage::applies( $coupon( array( 'product_ids' => array( $id + 100000 ) ) ), $product ) );
		$this->assertFalse( ProductPage::applies( $coupon( array( 'excluded_product_ids' => array( $id ) ) ), $product ) );
		$this->assertFalse( ProductPage::applies( $coupon( array( 'email_restrictions' => array( 'someone@example.com' ) ) ), $product ) );
		$this->assertFalse( ProductPage::applies( $coupon( array( 'date_expires' => time() - DAY_IN_SECONDS ) ), $product ) );
		$this->assertFalse( ProductPage::applies( $coupon( array( 'exclude_sale_items' => true ) ), $product ), 'The product is on sale.' );
	}

	/**
	 * Makes a coupon.
	 *
	 * @param string               $code  Code.
	 * @param array<string, mixed> $props Coupon properties.
	 * @param bool                 $shown Whether it is chosen for product pages.
	 */
	private function coupon( string $code, array $props, bool $shown = true ): int {
		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 10 );
		$coupon->set_props( $props );
		if ( $shown ) {
			$coupon->update_meta_data( CouponField::META, 'yes' );
		}
		$id              = $coupon->save();
		$this->coupons[] = $id;
		return $id;
	}

	public function test_product_values_are_cleaned(): void {
		$this->assertSame(
			array(
				'shop.product_layout' => 'left',
				'shop.product_sticky' => false,
			),
			Schema::level_values(
				array(
					'shop.product_layout' => 'left',
					'shop.product_thumbs' => 'sideways',
					'shop.product_sticky' => '0',
					'shop.product_zoom'   => '1',
					'shop.product_banner' => '',
				),
				'product'
			)
		);
	}
}
