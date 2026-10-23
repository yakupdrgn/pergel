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
use Pergel\Woo\CategoryCards;
use Pergel\Woo\Layout;
use Pergel\Woo\Module as Woo;
use Pergel\Woo\QuickView;
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
		Settings::update(
			array(
				'shop' => Schema::defaults()['shop'],
				// The wishlist button draws the product-actions block; the
				// flash sale badge would add the card hooks everywhere.
				'woo'  => array(
					'wishlist'    => true,
					'flash_sales' => false,
				),
			)
		);
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
		wp_dequeue_script_module( Layout::CARDS_SCRIPT );
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

	public function test_countdown_stock_bar_and_quick_view_button(): void {
		Settings::update(
			array(
				'shop' => array(
					'card_countdown' => true,
					'card_stock_bar' => true,
					'quick_view'     => true,
				),
			)
		);
		$product = wc_get_product( $this->product );
		$this->assertInstanceOf( \WC_Product::class, $product );
		$end = time() + 2 * DAY_IN_SECONDS + 3 * HOUR_IN_SECONDS + 60;
		$product->set_date_on_sale_to( (string) $end );
		$product->set_total_sales( 9 );
		$product->save();

		$html = do_blocks( self::GRID );

		$this->assertMatchesRegularExpression( '#<span class="pergel-countdown" data-pergel-until="\d+"><span class="pergel-countdown__parts" aria-hidden="true"><span class="pergel-countdown__part"><b data-pergel-part="d">02</b>#', $html );
		$this->assertStringContainsString( '<b data-pergel-part="h">03</b>', $html );
		// 9 sold, 3 left: three quarters.
		$this->assertStringContainsString( 'style="width:75%"', $html );
		$this->assertMatchesRegularExpression( '#pergel-stock-bar__text"><span>[^<]*9</span><span>[^<]*3</span>#', $html );
		$this->assertMatchesRegularExpression( '#<div class="pergel-card-actions"><button type="button" class="pergel-card-button pergel-quick-button" data-pergel-quick="' . $this->product . '" aria-haspopup="dialog">#', $html );
		$this->assertTrue( wp_style_is( Layout::CARD_STYLE ) );
		$this->assertArrayHasKey( 'quick', Cards::data( null ) );

		// No end date, or nothing sold: neither is drawn.
		$product->set_date_on_sale_to( null );
		$product->set_total_sales( 0 );
		$product->save();
		$html = do_blocks( self::GRID );
		$this->assertStringNotContainsString( 'pergel-countdown', $html );
		$this->assertStringNotContainsString( 'pergel-stock-bar', $html );
	}

	public function test_quick_view_route(): void {
		$server = rest_get_server();
		$this->assertArrayNotHasKey( '/pergel/v1/quick-view', $server->get_routes( 'pergel/v1' ), 'Off by default.' );

		Settings::update( array( 'shop' => array( 'quick_view' => true ) ) );
		QuickView::routes();
		$request = new \WP_REST_Request( 'GET', '/pergel/v1/quick-view' );
		$request->set_param( 'id', $this->product );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertSame( 'Card test product', $data['title'] );
		$this->assertStringContainsString( 'id="pergel-quick-title"', $data['html'] );
		$this->assertStringContainsString( 'name="add-to-cart" value="' . $this->product . '"', $data['html'] );
		$this->assertStringContainsString( 'max="3"', $data['html'], 'At most what is in stock.' );

		wp_update_post(
			array(
				'ID'          => $this->product,
				'post_status' => 'draft',
			)
		);
		$this->assertSame( 404, rest_do_request( $request )->get_status(), 'A draft is not shown.' );
	}

	public function test_category_cards_block(): void {
		$parent = wp_insert_term( 'Cards parent ' . wp_generate_password( 6, false ), 'product_cat' );
		$this->assertIsArray( $parent );
		$child = wp_insert_term( 'Cards child ' . wp_generate_password( 6, false ), 'product_cat', array( 'parent' => (int) $parent['term_id'] ) );
		$this->assertIsArray( $child );
		wp_set_object_terms( $this->product, array( (int) $parent['term_id'], (int) $child['term_id'] ), 'product_cat' );

		$html = do_blocks( '<!-- wp:pergel/category-cards {"cardStyle":"list","parent":' . (int) $parent['term_id'] . '} /-->' );
		$list = do_blocks( '<!-- wp:pergel/category-cards {"cardStyle":"names","parent":0,"number":24} /-->' );
		wp_delete_term( (int) $child['term_id'], 'product_cat' );
		wp_delete_term( (int) $parent['term_id'], 'product_cat' );

		$this->assertMatchesRegularExpression( '#<nav [^>]*class="[^"]*pergel-category-cards is-list#', $html );
		$this->assertStringContainsString( 'Cards child', $html );
		$this->assertStringNotContainsString( 'Cards parent', $html, 'Only the subcategories of the chosen one.' );
		$this->assertStringNotContainsString( 'Cards child', $list, 'Main categories only.' );
		$this->assertTrue( wp_style_is( CategoryCards::STYLE_HANDLE ) );
	}

	public function test_colours_of_a_product_with_options(): void {
		// With WooCommerce's "Color swatches" feature off (as on CI),
		// wc_create_attribute() would quietly store the type as "select".
		$types = static fn ( array $types ): array => $types + array( 'wc-visual' => 'Color / image' );
		add_filter( 'product_attributes_type_selector', $types );
		$slug = 'pgt' . strtolower( wp_generate_password( 5, false ) );
		$made = wc_create_attribute(
			array(
				'name' => 'Card colour',
				'slug' => $slug,
				'type' => 'wc-visual',
			)
		);
		remove_filter( 'product_attributes_type_selector', $types );
		$this->assertIsInt( $made );
		$taxonomy = wc_attribute_taxonomy_name( $slug );
		register_taxonomy( $taxonomy, 'product' );
		$terms = array();
		foreach ( array(
			'red'  => '#ff0000',
			'blue' => '#0000ff',
		) as $name => $color ) {
			$term = wp_insert_term( $name, $taxonomy );
			$this->assertIsArray( $term );
			update_term_meta( (int) $term['term_id'], 'color', $color );
			$terms[ $name ] = (int) $term['term_id'];
		}
		$picture = wp_insert_attachment(
			array(
				'post_title'     => 'Red picture',
				'post_mime_type' => 'image/png',
				'post_status'    => 'inherit',
			)
		);
		$attribute = new \WC_Product_Attribute();
		$attribute->set_id( $made );
		$attribute->set_name( $taxonomy );
		$attribute->set_options( array_values( $terms ) );
		$attribute->set_variation( true );
		$product = new \WC_Product_Variable();
		$product->set_name( 'Card colours' );
		$product->set_status( 'publish' );
		$product->set_attributes( array( $attribute ) );
		$parent = $product->save();
		wp_set_object_terms( $parent, array_values( $terms ), $taxonomy );
		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent );
		$variation->set_attributes( array( $taxonomy => 'red' ) );
		$variation->set_regular_price( '5' );
		$variation->set_image_id( $picture );
		$variation->save();
		$plain = new \WC_Product_Variation();
		$plain->set_parent_id( $parent );
		$plain->set_attributes( array( $taxonomy => 'blue' ) );
		$plain->set_regular_price( '5' );
		$plain->save();
		$loaded = wc_get_product( $parent );
		$this->assertInstanceOf( \WC_Product_Variable::class, $loaded );

		$html = Cards::swatches( $loaded );

		wp_delete_post( $parent, true );
		wp_delete_post( $variation->get_id(), true );
		wp_delete_post( $plain->get_id(), true );
		wp_delete_post( $picture, true );
		foreach ( $terms as $id ) {
			wp_delete_term( $id, $taxonomy );
		}
		wc_delete_attribute( $made );

		// The label comes from WooCommerce's attribute cache, made before this test.
		$this->assertMatchesRegularExpression( '#^<div class="pergel-card-swatches" role="group" aria-label="[^"]+">#', $html );
		$this->assertMatchesRegularExpression( '#<button type="button" class="pergel-card-swatch" style="background-color:\#ff0000" title="red" data-pergel-src="[^"]*" data-pergel-srcset="[^"]*" aria-pressed="false"><span class="screen-reader-text">red</span></button>#', $html, 'Red has a picture of its own.' );
		$this->assertStringContainsString( '<span class="pergel-card-swatch" style="background-color:#0000ff" title="blue">', $html, 'Blue has none.' );
	}

	public function test_badge_text_is_cleaned(): void {
		$this->assertSame( 'Limited', BadgeField::clean( "  <b>Limited</b>\n" ) );
		$this->assertSame( 24, mb_strlen( BadgeField::clean( str_repeat( 'ç', 40 ) ) ) );
		$this->assertSame( '', BadgeField::clean( array( 'x' ) ) );
	}
}
