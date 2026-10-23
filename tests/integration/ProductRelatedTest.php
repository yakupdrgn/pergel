<?php
/**
 * More products: related products, the comparison with similar products
 * and recently viewed ones.
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
use Pergel\Woo\Layout;
use Pergel\Woo\Module as Woo;
use Pergel\Woo\Related;
use PHPUnit\Framework\TestCase;

/**
 * Settings, products, the category and the main query are put back after
 * each test.
 */
final class ProductRelatedTest extends TestCase {

	/**
	 * The theme's related products block, shortened.
	 */
	private const RELATED = '<!-- wp:woocommerce/product-collection {"queryId":1,"query":{"perPage":4,"pages":1,"offset":0,"postType":"product","order":"asc","orderBy":"title","search":"","exclude":[],"inherit":false,"taxQuery":{},"isProductCollectionBlock":true,"featured":false,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[],"filterable":false,"relatedBy":{"categories":true,"tags":true}},"displayLayout":{"type":"flex","columns":4,"shrinkColumns":true},"collection":"woocommerce/product-collection/related","align":"wide"} -->'
		. '<div class="wp-block-woocommerce-product-collection alignwide"><!-- wp:heading -->' . "\n" . '<h2 class="wp-block-heading">Related products</h2>' . "\n" . '<!-- /wp:heading -->'
		. "\n\n" . '<!-- wp:woocommerce/product-template --><!-- wp:post-title {"isLink":true,"__woocommerceNamespace":"woocommerce/product-collection/product-title"} /--><!-- /wp:woocommerce/product-template -->'
		. "\n\n" . '<!-- wp:query-pagination --><!-- wp:query-pagination-numbers /--><!-- /wp:query-pagination --></div><!-- /wp:woocommerce/product-collection -->';

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
	private array $posts = array();

	/**
	 * The category the products are in.
	 */
	private int $term = 0;

	protected function setUp(): void {
		// Saved first: tearDown() runs after a skip too.
		$this->saved = get_option( Settings::OPTION, false );
		if ( ! Woo::active() ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
		Settings::update( array( 'shop' => Schema::defaults()['shop'] ) );
		$term = wp_insert_term( 'Related test ' . wp_generate_password( 6, false ), 'product_cat' );
		$this->assertIsArray( $term );
		$this->term = (int) $term['term_id'];
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
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
		}
		if ( $this->term > 0 ) {
			wp_delete_term( $this->term, 'product_cat' );
		}
		remove_filter( 'render_block_data', array( Related::class, 'collection' ), 10 );
		remove_filter( 'render_block_woocommerce/product-collection', array( Related::class, 'around' ), 10 );
		remove_filter( 'script_module_data_' . Layout::RECENT_SCRIPT, array( Related::class, 'data' ), 10 );
		wp_dequeue_style( Layout::RELATED_STYLE );
		wp_dequeue_script_module( Layout::RECENT_SCRIPT );
		Layout::flush();
		if ( null !== $this->query ) {
			$GLOBALS['wp_the_query'] = $this->query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Puts back the query go_to() replaced.
		}
		wp_reset_query();
	}

	/**
	 * Makes a product in the test category.
	 *
	 * @param string $name  Name.
	 * @param string $price Regular price.
	 * @param string $sale  Sale price, or ''.
	 */
	private function product( string $name, string $price, string $sale = '' ): \WC_Product_Simple {
		$product = new \WC_Product_Simple();
		$product->set_name( $name );
		$product->set_status( 'publish' );
		$product->set_regular_price( $price );
		$product->set_sale_price( $sale );
		$product->set_weight( '2' );
		$product->set_category_ids( array( $this->term ) );
		$this->posts[] = $product->save();
		return $product;
	}

	/**
	 * Shows a product page, as a page view would, and runs Layout's check.
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
		Layout::product_page();
	}

	/**
	 * Names of the inner blocks of a parsed block.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return list<string>
	 */
	private static function names( array $block ): array {
		return array_values( array_map( static fn ( array $inner ): string => (string) $inner['blockName'], (array) $block['innerBlocks'] ) );
	}

	public function test_defaults_leave_the_related_products_alone(): void {
		$product = $this->product( 'Related plain', '10' );
		$this->go_to( $product->get_id() );

		$this->assertFalse( has_filter( 'render_block_data', array( Related::class, 'collection' ) ) );
		$this->assertNotContains( Layout::RECENT_SCRIPT, wp_script_modules()->get_queue() );
	}

	public function test_number_title_and_carousel(): void {
		$product = $this->product( 'Related carousel', '10' );
		$this->product( 'Related beside it', '12' );
		Settings::update(
			array(
				'shop' => array(
					'related_style' => 'carousel',
					'related_count' => 8,
					'related_title' => 'You may also like',
				),
			)
		);
		$this->go_to( $product->get_id() );

		$block = parse_blocks( self::RELATED )[0];
		$block = Related::collection( $block );

		$this->assertSame( 8, $block['attrs']['query']['perPage'] );
		$this->assertSame( 'carousel', $block['attrs']['displayLayout']['type'] );
		$this->assertSame( array( 'core/group', 'woocommerce/product-template' ), self::names( $block ), 'The heading moves into the header row; no page numbers.' );
		$header = $block['innerBlocks'][0];
		$this->assertSame( 'wc-product-collection-carousel-header', $header['attrs']['className'] );
		$this->assertSame( array( 'core/heading', 'woocommerce/product-gallery-large-image-next-previous' ), self::names( $header ) );
		$this->assertStringContainsString( '>You may also like</h2>', $header['innerBlocks'][0]['innerHTML'] );
		$this->assertSame( 'nowrap', $block['innerBlocks'][1]['attrs']['layout']['flexWrap'] );
		$this->assertSame( count( $block['innerBlocks'] ), count( array_filter( $block['innerContent'], 'is_null' ) ), 'One slot per inner block.' );

		// Outside a template the block needs the product named.
		$html = do_blocks( str_replace( '"relatedBy":', '"productReference":' . $product->get_id() . ',"relatedBy":', self::RELATED ) );
		$this->assertStringContainsString( 'Related beside it', $html );
		$this->assertStringContainsString( 'wc-product-collection-carousel-header', $html );
		$this->assertStringContainsString( 'You may also like', $html );
		$this->assertStringContainsString( 'wc-block-next-previous-buttons', $html );
	}

	public function test_grid_and_a_category_title(): void {
		$product = $this->product( 'Related grid', '10' );
		update_term_meta( $this->term, Layout::TERM_META, array( 'shop.related_title' => 'More from this range' ) );
		Settings::update( array( 'shop' => array( 'related_style' => 'grid' ) ) );
		$this->go_to( $product->get_id() );

		$block = Related::collection( parse_blocks( self::RELATED )[0] );

		$this->assertSame( 'flex', $block['attrs']['displayLayout']['type'] );
		$this->assertSame( 4, $block['attrs']['query']['perPage'], 'The template\'s number stays.' );
		$this->assertSame( array( 'core/heading', 'woocommerce/product-template', 'core/query-pagination' ), self::names( $block ) );
		$this->assertStringContainsString( '>More from this range</h2>', $block['innerBlocks'][0]['innerHTML'] );
	}

	public function test_other_product_collections_are_left_alone(): void {
		$product = $this->product( 'Related other', '10' );
		Settings::update( array( 'shop' => array( 'related_style' => 'carousel' ) ) );
		$this->go_to( $product->get_id() );

		$other = parse_blocks( str_replace( '"collection":"woocommerce/product-collection/related",', '', self::RELATED ) )[0];
		$this->assertSame( $other, Related::collection( $other ) );
	}

	public function test_similar_products_upsells_first_then_nearest_price(): void {
		$product = $this->product( 'Related main', '100' );
		$far     = $this->product( 'Related far', '400' );
		$near    = $this->product( 'Related near', '110' );
		$middle  = $this->product( 'Related middle', '150' );
		$upsell  = $this->product( 'Related upsell', '900' );
		$hidden  = $this->product( 'Related hidden', '100' );
		$hidden->set_catalog_visibility( 'hidden' );
		$hidden->save();
		$product->set_upsell_ids( array( $upsell->get_id() ) );
		$product->save();

		$ids = static fn ( array $list ): array => array_map( static fn ( \WC_Product $item ): int => $item->get_id(), $list );
		$this->assertSame( array( $upsell->get_id(), $near->get_id(), $middle->get_id() ), $ids( Related::similar( $product, 3 ) ) );
		$this->assertSame( array( $upsell->get_id() ), $ids( Related::similar( $product, 1 ) ) );
		$this->assertNotContains( $far->get_id(), $ids( Related::similar( $product, 3 ) ) );
	}

	public function test_compare_table_before_the_related_products(): void {
		$product = $this->product( 'Compare main', '100', '80' );
		$other   = $this->product( 'Compare other', '120' );
		Settings::update( array( 'shop' => array( 'related_compare' => '2' ) ) );
		$this->go_to( $product->get_id() );

		$html = do_blocks( self::RELATED );

		$this->assertStringStartsWith( '<section class="pergel-compare alignwide"', $html );
		$this->assertStringContainsString( 'style="--pergel-compare-columns:2"', $html );
		$this->assertSame( 2, substr_count( $html, '<th scope="col"' ), 'The product and one similar one.' );
		$this->assertStringContainsString( 'Compare other', $html );
		$this->assertStringContainsString( 'pergel-compare__this', $html );
		$this->assertStringContainsString( 'pergel-compare__discount">' . sprintf( __( '-%d%%', 'pergel-engine' ), 20 ), $html );
		$this->assertStringContainsString( '<th scope="row">' . __( 'Weight', 'pergel-engine' ) . '</th>', $html );
		$this->assertStringNotContainsString( '<th scope="row">' . __( 'Rating', 'pergel-engine' ) . '</th>', $html, 'No product has a rating.' );
		$this->assertStringContainsString( 'add-to-cart=' . $other->get_id(), $html );
		$this->assertStringNotContainsString( 'add-to-cart=' . $product->get_id(), $html, 'The product on screen has its own button.' );
		$this->assertTrue( wp_style_is( Layout::RELATED_STYLE ) );
	}

	public function test_no_table_without_a_similar_product(): void {
		$product = $this->product( 'Compare alone', '100' );
		Settings::update( array( 'shop' => array( 'related_compare' => '4' ) ) );
		$this->go_to( $product->get_id() );

		$this->assertStringNotContainsString( 'pergel-compare', do_blocks( self::RELATED ) );
	}

	public function test_recently_viewed_after_the_related_products(): void {
		$product = $this->product( 'Recent main', '10' );
		Settings::update( array( 'shop' => array( 'related_recent' => 'show' ) ) );
		$this->go_to( $product->get_id() );

		$this->assertContains( Layout::RECENT_SCRIPT, wp_script_modules()->get_queue() );
		$data = Related::data( array() );
		$this->assertSame( $product->get_id(), $data['product'] );
		$this->assertSame( Related::LIMIT, $data['limit'] );

		$html = do_blocks( self::RELATED );
		$this->assertMatchesRegularExpression( '#<section [^>]*class="[^"]*wp-block-pergel-recently-viewed[^"]*"[^>]*data-pergel-recent="4"[^>]*hidden>#', $html );
		$this->assertStringContainsString( __( 'Recently viewed', 'pergel-engine' ), $html );
		$this->assertStringEndsWith( '</section>', trim( $html ) );
	}

	public function test_remembering_only_shows_nothing(): void {
		$product = $this->product( 'Recent track', '10' );
		Settings::update( array( 'shop' => array( 'related_recent' => 'track' ) ) );
		$this->go_to( $product->get_id() );

		$this->assertContains( Layout::RECENT_SCRIPT, wp_script_modules()->get_queue(), 'The product is remembered.' );
		$this->assertStringNotContainsString( 'wp-block-pergel-recently-viewed', do_blocks( self::RELATED ) );
	}

	public function test_recently_viewed_block(): void {
		$this->assertSame( '', do_blocks( '<!-- wp:pergel/recently-viewed /-->' ), 'Off: nothing on the site.' );

		Settings::update( array( 'shop' => array( 'related_recent' => 'track' ) ) );
		$html = do_blocks( '<!-- wp:pergel/recently-viewed {"count":6,"title":"Seen lately"} /-->' );

		$this->assertStringContainsString( 'data-pergel-recent="6"', $html );
		$this->assertStringContainsString( '>Seen lately</h2>', $html );
		$this->assertContains( Layout::RECENT_SCRIPT, wp_script_modules()->get_queue() );
		$this->assertSame( 0, Related::data( array() )['product'], 'Not a product page.' );
	}

	public function test_recently_viewed_route(): void {
		// A server started now, with the setting off, has no route.
		$server                    = $GLOBALS['wp_rest_server'] ?? null;
		$GLOBALS['wp_rest_server'] = null;
		try {
			$this->assertArrayNotHasKey( '/pergel/v1/recently-viewed', rest_get_server()->get_routes( 'pergel/v1' ), 'Off by default.' );
		} finally {
			$GLOBALS['wp_rest_server'] = $server;
		}

		$first  = $this->product( 'Recent first', '10' );
		$second = $this->product( 'Recent second', '20' );
		$draft  = $this->product( 'Recent draft', '30' );
		$draft->set_status( 'draft' );
		$draft->save();
		Settings::update( array( 'shop' => array( 'related_recent' => 'track' ) ) );
		Layout::recently_viewed();

		$request = new \WP_REST_Request( 'GET', '/pergel/v1/recently-viewed' );
		$request->set_param( 'ids', $second->get_id() . ',' . $draft->get_id() . ',' . $first->get_id() );
		$request->set_param( 'count', 1 );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertSame( array( $second->get_id(), $first->get_id() ), $data['ids'], 'In order, without the draft.' );
		$this->assertSame( 1, substr_count( $data['html'], '<li ' ), 'As many as asked for.' );
		$this->assertStringContainsString( 'Recent second', $data['html'] );

		$request->set_param( 'ids', 'x' );
		$this->assertSame( 400, rest_do_request( $request )->get_status() );
	}
}
