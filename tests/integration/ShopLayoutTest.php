<?php
/**
 * Shop layout settings on the product listing pages.
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
use Pergel\Woo\Filters;
use Pergel\Woo\Layout;
use Pergel\Woo\LayoutFields;
use Pergel\Woo\Listing;
use Pergel\Woo\Module as Woo;
use Pergel\Woo\ShopHeader;
use Pergel\Woo\ShopLook;
use PHPUnit\Framework\TestCase;

/**
 * Settings, categories and the main query are put back after each test.
 */
final class ShopLayoutTest extends TestCase {

	/**
	 * A main product grid like the theme's shop template.
	 */
	private const GRID = '<!-- wp:woocommerce/product-collection {"queryId":0,"query":{"inherit":true,"isProductCollectionBlock":true,"perPage":12,"postType":"product"},"tagName":"div","displayLayout":{"type":"flex","columns":3,"shrinkColumns":true}} -->'
		. '<div class="wp-block-woocommerce-product-collection"><!-- wp:woocommerce/product-template --><!-- wp:post-title /--><!-- /wp:woocommerce/product-template --></div>'
		. '<!-- /wp:woocommerce/product-collection -->';

	/**
	 * The main grid with a price and page numbers.
	 */
	private const FULL_GRID = '<!-- wp:woocommerce/product-collection {"queryId":0,"query":{"inherit":true,"isProductCollectionBlock":true,"perPage":12,"postType":"product"},"tagName":"div","displayLayout":{"type":"flex","columns":3,"shrinkColumns":true}} -->'
		. '<div class="wp-block-woocommerce-product-collection"><!-- wp:woocommerce/product-template --><!-- wp:post-title /--><!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true} /--><!-- /wp:woocommerce/product-template -->'
		. '<!-- wp:query-pagination --><!-- wp:query-pagination-next /--><!-- /wp:query-pagination --></div>'
		. '<!-- /wp:woocommerce/product-collection -->';

	/**
	 * The theme's shop layout: a filter column and a product column.
	 */
	private const COLUMNS = '<!-- wp:columns {"className":"pergel-shop-layout"} --><div class="wp-block-columns pergel-shop-layout">'
		. '<!-- wp:column {"width":"16rem","className":"pergel-shop-layout__filters"} --><div class="wp-block-column pergel-shop-layout__filters" style="flex-basis:16rem"><p>Filters</p></div><!-- /wp:column -->'
		. '<!-- wp:column {"className":"pergel-shop-layout__products"} --><div class="wp-block-column pergel-shop-layout__products"><p>Products</p></div><!-- /wp:column -->'
		. '</div><!-- /wp:columns -->';

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
	 * Categories made by the test.
	 *
	 * @var list<int>
	 */
	private array $terms = array();

	/**
	 * The product made for the test: a grid with no products renders nothing.
	 */
	private int $product = 0;

	/**
	 * Other posts made by the test.
	 *
	 * @var list<int>
	 */
	private array $posts = array();

	protected function setUp(): void {
		// Saved first: tearDown() runs after a skip too.
		$this->saved = get_option( Settings::OPTION, false );
		if ( ! Woo::active() ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
		Settings::update( array( 'shop' => Schema::defaults()['shop'] ) );
		$product = new \WC_Product_Simple();
		$product->set_name( 'Layout test product' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$this->product = $product->save();
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
		foreach ( array_merge( array( $this->product ), $this->posts ) as $post_id ) {
			if ( $post_id > 0 ) {
				wp_delete_post( $post_id, true );
			}
		}
		foreach ( $this->terms as $term_id ) {
			wp_delete_term( $term_id, 'product_cat' );
		}
		Layout::flush();
		wp_dequeue_style( Layout::STYLE );
		wp_dequeue_script_module( Filters::SCRIPT );
		wp_dequeue_script_module( Listing::SCRIPT );
		unset( $_POST['pergel_shop'], $_GET[ Listing::ARG ] );
		wp_set_current_user( 0 );
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

	/**
	 * A product category made for the test.
	 *
	 * @param string $name   Name.
	 * @param int    $parent Parent category id.
	 */
	private function category( string $name, int $parent = 0 ): \WP_Term {
		$made = wp_insert_term( $name . ' ' . wp_generate_password( 6, false ), 'product_cat', array( 'parent' => $parent ) );
		$this->assertIsArray( $made );
		$this->terms[] = (int) $made['term_id'];
		$term          = get_term( (int) $made['term_id'], 'product_cat' );
		$this->assertInstanceOf( \WP_Term::class, $term );
		return $term;
	}

	public function test_defaults_leave_the_template_grid_alone(): void {
		$this->go_to( array( 'post_type' => 'product' ) );
		$this->assertTrue( is_shop() );

		$html = do_blocks( self::GRID );

		$this->assertStringContainsString( 'columns-3', $html );
		$this->assertStringNotContainsString( 'pergel-columns', $html );
		$this->assertNotContains( 'pergel-shop-full', Layout::body_class( array() ) );
		$this->assertSame( 16, Layout::per_page( 16 ) );
	}

	public function test_site_settings_reach_the_main_grid(): void {
		Settings::update(
			array(
				'shop' => array(
					'columns'        => '5',
					'columns_tablet' => '2',
					'columns_phone'  => '1',
					'per_page'       => 24,
					'width'          => 'full',
				),
			)
		);
		$this->go_to( array( 'post_type' => 'product' ) );

		$html = do_blocks( self::GRID );

		$this->assertStringContainsString( 'columns-5', $html );
		$this->assertStringContainsString( 'pergel-columns-tablet', $html );
		$this->assertStringContainsString( '--pergel-columns-tablet:2', $html );
		$this->assertStringContainsString( '--pergel-columns-phone:1', $html );
		$this->assertContains( 'pergel-shop-full', Layout::body_class( array() ) );
		$this->assertSame( 24, Layout::per_page( 16 ) );
	}

	public function test_settings_stay_off_other_pages_and_other_grids(): void {
		Settings::update( array( 'shop' => array( 'columns' => '5' ) ) );
		$this->go_to( array( 'post_type' => 'product' ) );
		// A collection with its own query, e.g. "New arrivals" on the shop page.
		$own = str_replace( '"inherit":true', '"inherit":false', self::GRID );
		$this->assertStringContainsString( 'columns-3', do_blocks( $own ) );

		$this->go_to( array( 'post_type' => 'post' ) );
		$this->assertFalse( Layout::listing() );
		$this->assertStringContainsString( 'columns-3', do_blocks( self::GRID ) );
	}

	public function test_a_category_choice_reaches_its_subcategories(): void {
		Settings::update( array( 'shop' => array( 'columns' => '4' ) ) );
		$parent = $this->category( 'Layout parent' );
		$child  = $this->category( 'Layout child', $parent->term_id );
		$other  = $this->category( 'Layout other' );
		update_term_meta(
			$parent->term_id,
			Layout::TERM_META,
			array(
				'shop.columns'  => '2',
				'shop.per_page' => 9,
			)
		);

		// An empty category renders no grid.
		wp_set_object_terms( $this->product, array( $child->term_id, $other->term_id ), 'product_cat' );

		$this->go_to( array( 'product_cat' => $child->slug ) );
		$this->assertSame( '2', Layout::get( 'shop.columns' ) );
		$this->assertSame( 9, Layout::per_page( 16 ) );
		$this->assertStringContainsString( 'columns-2', do_blocks( self::GRID ) );

		$this->go_to( array( 'product_cat' => $other->slug ) );
		$this->assertSame( '4', Layout::get( 'shop.columns' ) );
		$this->assertStringContainsString( 'columns-4', do_blocks( self::GRID ) );
	}

	public function test_category_form_stores_only_real_choices(): void {
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		wp_set_current_user( (int) $admins[0] );
		$term = $this->category( 'Layout form' );

		$_POST['pergel_shop'] = array(
			'shop.columns'       => '6',
			'shop.columns_phone' => '',
			'shop.width'         => 'enormous',
			'woo.sales_mode'     => 'quote',
		);
		LayoutFields::save_category( $term->term_id );
		$this->assertSame( array( 'shop.columns' => '6' ), get_term_meta( $term->term_id, Layout::TERM_META, true ) );

		$_POST['pergel_shop'] = array( 'shop.columns' => '' );
		LayoutFields::save_category( $term->term_id );
		$this->assertFalse( metadata_exists( 'term', $term->term_id, Layout::TERM_META ) );
	}

	public function test_default_filters_change_nothing(): void {
		$this->go_to( array( 'post_type' => 'product' ) );

		$html = do_blocks( self::COLUMNS );

		$this->assertStringContainsString( 'pergel-shop-layout__filters', $html );
		$this->assertStringNotContainsString( 'pergel-filters-', $html );
		$this->assertFalse( wp_style_is( Layout::STYLE ) );
		$this->assertSame( array(), apply_filters( 'pergel/shop/filters', array() ) );
		$filters = array(
			'blockName' => 'woocommerce/product-filters',
			'attrs'     => array(),
		);
		$this->assertSame( $filters, Filters::block_data( $filters ) );
		$this->assertSame( '<h3 class="wp-block-heading">Price</h3>', Filters::heading( '<h3 class="wp-block-heading">Price</h3>' ) );
		$box = '<div class="wp-block-woocommerce-product-filters"><h2 class="wp-block-heading pergel-filters__title">Filters</h2></div>';
		$this->assertSame( $box, Filters::filters( $box ) );
	}

	public function test_filter_box_title_icon_and_more_button(): void {
		Settings::update(
			array(
				'shop' => array(
					'filter_title' => 'Filter & Refine',
					'filter_icon'  => true,
					'filter_more'  => 'pill',
					'filter_rows'  => 4,
				),
			)
		);
		$this->go_to( array( 'post_type' => 'product' ) );

		$html = Filters::filters( '<div class="wp-block-woocommerce-product-filters"><h2 class="wp-block-heading pergel-filters__title">Filters</h2><h3 class="wp-block-heading">Price</h3></div>' );

		$this->assertMatchesRegularExpression( '#<h2 class="wp-block-heading pergel-filters__title"><svg class="pergel-filters__icon"[^>]*aria-hidden="true".*</svg>Filter &amp; Refine</h2><h3 class="wp-block-heading">Price</h3>#s', $html );
		$this->assertStringContainsString( 'data-pergel-filters="more"', $html );
		$this->assertStringContainsString( 'data-pergel-rows="4"', $html );
		$this->assertStringContainsString( 'data-pergel-more-label="', $html );
		$this->assertContains( Filters::SCRIPT, wp_script_modules()->get_queue() );
		$this->assertTrue( wp_style_is( Layout::STYLE ), 'The icon has its rule.' );

		// The title alone, in the template's words when only the icon is on.
		Settings::update(
			array(
				'shop' => array(
					'filter_title' => '',
					'filter_more'  => 'template',
				),
			)
		);
		$html = Filters::filters( '<div class="wp-block-woocommerce-product-filters"><h2 class="wp-block-heading pergel-filters__title">Filters</h2></div>' );
		$this->assertStringContainsString( '</svg>Filters</h2>', $html );
		$this->assertStringNotContainsString( 'data-pergel-filters', $html );
	}

	public function test_brand_attribute_is_not_a_second_brand_filter(): void {
		if ( ! taxonomy_exists( 'product_brand' ) ) {
			$this->markTestSkipped( 'WooCommerce brands are off.' );
		}
		$id = wc_create_attribute(
			array(
				'name' => 'Brand',
				'slug' => 'brand-' . strtolower( wp_generate_password( 4, false ) ),
			)
		);
		$this->assertIsInt( $id );
		$pattern = dirname( __DIR__, 2 ) . '/theme/patterns/hidden-product-filters.php';
		$draw    = static function () use ( $pattern ): string {
			delete_transient( 'wc_attribute_taxonomies' );
			\WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );
			ob_start();
			include $pattern;
			return (string) ob_get_clean();
		};
		try {
			$this->assertStringContainsString( '"attributeId":' . $id . ',', $draw(), 'Without the brand filter the attribute stays.' );

			Settings::update( array( 'shop' => array( 'filter_brand' => true ) ) );
			$html = $draw();
			$this->assertStringContainsString( '"taxonomy":"product_brand"', $html );
			$this->assertStringNotContainsString( '"attributeId":' . $id . ',', $html );
		} finally {
			wc_delete_attribute( $id );
		}
	}

	public function test_filter_area_settings_reach_the_layout(): void {
		Settings::update(
			array(
				'shop' => array(
					'filters'        => 'right',
					'filters_sticky' => 'scroll',
					'filters_fold'   => true,
					'filter_brand'   => true,
					'filter_rating'  => true,
				),
			)
		);
		$this->go_to( array( 'post_type' => 'product' ) );

		$html = do_blocks( self::COLUMNS );

		$this->assertStringContainsString( 'pergel-filters-right', $html );
		$this->assertStringContainsString( 'pergel-filters-scroll', $html );
		$this->assertTrue( wp_style_is( Layout::STYLE ) );
		$this->assertSame( array( 'brand', 'rating' ), apply_filters( 'pergel/shop/filters', array() ) );
		$this->assertSame(
			'<div><h3 class="wp-block-heading"><button type="button" class="pergel-filter-toggle" aria-expanded="true">Price</button></h3><h3>Other</h3></div>',
			Filters::heading( '<div><h3 class="wp-block-heading">Price</h3><h3>Other</h3></div>' )
		);
	}

	public function test_panel_and_no_filters(): void {
		Settings::update(
			array(
				'shop' => array(
					'filters'        => 'drawer',
					'drawer_side'    => 'right',
					'filters_sticky' => 'sticky',
				),
			)
		);
		$this->go_to( array( 'post_type' => 'product' ) );

		$html = do_blocks( self::COLUMNS );
		$this->assertStringContainsString( 'pergel-filters-drawer', $html );
		$this->assertStringNotContainsString( 'pergel-filters-sticky', $html, 'A panel does not stick.' );
		$filters = Filters::block_data(
			array(
				'blockName' => 'woocommerce/product-filters',
				'attrs'     => array(),
			)
		);
		$this->assertSame( 'always', $filters['attrs']['overlayMode'] );
		$this->assertSame( 'right', $filters['attrs']['overlayPosition'] );

		Settings::update( array( 'shop' => array( 'filters' => 'none' ) ) );
		$html = do_blocks( self::COLUMNS );
		$this->assertStringNotContainsString( 'pergel-shop-layout__filters', $html );
		$this->assertStringContainsString( 'pergel-shop-layout__products', $html );
	}

	public function test_view_and_paging_defaults_change_nothing(): void {
		$this->go_to(
			array(
				'post_type'      => 'product',
				'posts_per_page' => 1,
			)
		);
		$_GET[ Listing::ARG ] = '48';

		$html = do_blocks( self::FULL_GRID );

		$this->assertStringNotContainsString( 'pergel-shop-grid', $html );
		$this->assertStringNotContainsString( 'pergel-list-summary', $html );
		$this->assertStringNotContainsString( 'data-pergel-more', $html );
		$this->assertSame( '<form>sort</form>', Listing::toolbar( '<form>sort</form>' ) );
		$this->assertSame( 16, Layout::per_page( 16 ), 'A number the menu does not offer is ignored.' );
		$this->assertSame( '', ShopHeader::render( '' ) );
	}

	public function test_look_makes_rules_only_for_set_values(): void {
		$this->go_to(
			array(
				'post_type'      => 'product',
				'posts_per_page' => 1,
			)
		);
		$this->assertTrue( Layout::listing() );
		$this->assertFalse( Layout::chosen( Layout::LOOK ) );
		Layout::look();
		$this->assertFalse( wp_style_is( Layout::STYLE ), 'Defaults load nothing.' );
		$this->assertSame( '', ShopLook::css() );

		Settings::update(
			array(
				'shop' => array(
					'filter_edge'        => 2,
					'filter_edge_color'  => 'line',
					'filter_radius'      => 12,
					'filter_head_weight' => '700',
					'filter_divider'     => 'line',
					'filter_accent'      => '#ff0000',
					'toolbar_bg'         => 'surface',
					'control_radius'     => 6,
					'paging_shape'       => 'circle',
					'paging_size'        => 48,
				),
			)
		);
		$this->assertTrue( Layout::chosen( Layout::LOOK ) );
		Layout::look();
		$this->assertTrue( wp_style_is( Layout::STYLE ) );

		$css = ShopLook::css();
		$this->assertStringContainsString( $css, implode( '', (array) wp_styles()->get_data( Layout::STYLE, 'after' ) ) );
		// The box only beside the products, not in the panel.
		$this->assertStringContainsString( '@media (min-width:1025px){.pergel-shop-layout:not(.pergel-filters-drawer) .pergel-shop-layout__filters .wc-block-product-filters{box-sizing:border-box;border:2px solid var(--wp--preset--color--line);border-radius:12px}', $css );
		$this->assertStringContainsString( '.wp-block-heading:not(.pergel-filters__title){font-weight:700}', $css );
		$this->assertStringContainsString( 'border-top:1px solid var(--wp--preset--color--line)', $css );
		$this->assertStringContainsString( 'input[type=checkbox].wc-block-product-filter-checkbox-list__input:checked{--wc-product-filter-checkbox-list-option-element:#ff0000', $css );
		$this->assertStringContainsString( '.wp-block-group:has(.wp-block-woocommerce-catalog-sorting,.wp-block-woocommerce-product-results-count){box-sizing:border-box;background:var(--wp--preset--color--surface)}', $css );
		$this->assertStringContainsString( '.pergel-view-button,.wp-block-search__inside-wrapper){border-radius:6px}', $css );
		$this->assertStringContainsString( '.page-numbers:not(.dots){display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;min-width:48px;height:48px;padding:0 .25em;text-decoration:none;border-radius:999px}', $css );
		$this->assertStringContainsString( '.page-numbers.current{background:var(--wp--preset--color--accent);', $css, 'The current page stands out.' );
		$this->assertStringNotContainsString( 'font-size', $css, 'No sizes set.' );
		$this->assertStringNotContainsString( 'padding:', str_replace( 'padding:0 .25em', '', $css ), 'No inner space set.' );

		Settings::update( array( 'shop' => array( 'filter_edge' => 0 ) ) );
		$this->assertStringContainsString( '.wc-block-product-filters{box-sizing:border-box;border:0;border-radius:12px}', ShopLook::css() );
		$this->assertStringContainsString( '.pergel-filter-search{border-radius:6px}', ShopLook::css(), 'The search box takes the menus\' corners.' );
	}

	public function test_toolbar_controls_title_tab_and_strip(): void {
		$this->go_to( array( 'post_type' => 'product' ) );
		Settings::update(
			array(
				'shop' => array(
					'control_height'     => 36,
					'control_edge'       => 2,
					'control_edge_color' => 'line',
					'filter_title_look'  => 'tab',
					'filter_edge'        => 2,
					'strip_count'        => 8,
					'strip_picture'      => 'square',
				),
			)
		);
		$this->assertTrue( Layout::chosen( Layout::LOOK ) );

		$css      = ShopLook::css();
		$products = '.pergel-shop-layout .pergel-shop-layout__products';
		$this->assertStringContainsString( "$products .pergel-view-button{box-sizing:border-box;width:36px;height:36px;min-width:0;min-height:0}", $css );
		$this->assertStringContainsString( ".wp-block-search__inside-wrapper){border:2px solid var(--wp--preset--color--line)}", $css );
		$this->assertStringContainsString( '.pergel-filters__title{position:absolute;left:calc(-1 * 2px);bottom:100%;', $css );
		$this->assertStringContainsString( 'border:2px solid var(--wp--preset--color--line);border-bottom:0;border-radius:12px 12px 0 0}', $css );
		$this->assertStringContainsString( '.pergel-category-strip>ul>.pergel-category-card{flex:0 0 calc((100% - 7*16px)/8);', $css );
		$this->assertStringContainsString( '@media (max-width:1100px){.pergel-category-strip>ul{gap:16px;scroll-snap-type:x mandatory}.pergel-category-strip>ul>.pergel-category-card{flex:0 0 calc((100% - 5*16px)/6);', $css );
		$this->assertStringContainsString( 'calc((100% - 3*16px)/3.4)', $css );
		$this->assertStringContainsString( '.pergel-category-strip.is-images .pergel-category-card__image{width:100%;height:auto;aspect-ratio:1;object-fit:contain;border-radius:8px;background:var(--wp--preset--color--surface)}', $css );

		Settings::update( array( 'shop' => array( 'control_edge' => 0 ) ) );
		$this->assertStringContainsString( '.wp-block-search__inside-wrapper){border:0}', ShopLook::css() );
	}

	public function test_filter_list_look(): void {
		$this->go_to( array( 'post_type' => 'product' ) );
		Settings::update(
			array(
				'shop' => array(
					'filter_input_size'  => 38,
					'filter_input_edge'  => 2,
					'filter_input_color' => 'line',
					'filter_counts'      => 'right',
					'filter_swatch'      => 18,
					'filter_star_size'   => 16,
					'filter_star_color'  => '#ffb800',
				),
			)
		);
		$this->assertTrue( Layout::chosen( Layout::LOOK ) );

		$css  = ShopLook::css();
		$list = '.pergel-shop-layout .wc-block-product-filters .wc-block-product-filter-checkbox-list';
		$this->assertStringContainsString( '.wc-block-product-filters .pergel-filter-search{height:38px;min-height:38px;padding-block:0;border:2px solid var(--wp--preset--color--line)}', $css );
		$this->assertStringContainsString( "{$list}__text-wrapper{flex:1 1 auto}{$list}__count{margin-inline-start:auto}", $css );
		$this->assertStringContainsString( "{$list}__color-swatch{width:18px;height:18px}", $css );
		$this->assertStringContainsString( "{$list}__stars,{$list}__stars-svg{height:16px}", $css );
		$this->assertStringContainsString( "{$list}__stars{color:#ffb800}", $css );

		Settings::update( array( 'shop' => array( 'filter_input_edge' => 0 ) ) );
		$this->assertStringContainsString( '.pergel-filter-search{height:38px;min-height:38px;padding-block:0;border:0}', ShopLook::css() );
	}

	public function test_list_view_menu_and_load_more(): void {
		Settings::update(
			array(
				'shop' => array(
					'view'          => 'list',
					'view_switch'   => true,
					'per_page_menu' => '48, 12, x, 200, 12',
					'paging'        => 'more',
				),
			)
		);
		$this->assertSame( array( 12, 48 ), Listing::menu() );

		$second = new \WC_Product_Simple();
		$second->set_name( 'Layout test product 2' );
		$second->set_status( 'publish' );
		$second->set_regular_price( '12' );
		$second->set_short_description( 'Short words about the product.' );
		$this->posts[] = $second->save();
		$this->go_to(
			array(
				'post_type'      => 'product',
				'posts_per_page' => 1,
			)
		);

		$_GET[ Listing::ARG ] = '48';
		$this->assertSame( 48, Layout::per_page( 16 ) );
		$_GET[ Listing::ARG ] = '30';
		$this->assertSame( 30, Layout::per_page( 16 ), '"Load more" asks for any number up to 96.' );
		$this->assertTrue( Listing::noindex() );
		$_GET[ Listing::ARG ] = '500';
		$this->assertSame( 16, Layout::per_page( 16 ) );
		unset( $_GET[ Listing::ARG ] );

		$html = do_blocks( self::FULL_GRID );
		$this->assertStringContainsString( 'pergel-shop-grid', $html );
		$this->assertStringContainsString( 'pergel-view-list', $html );
		$this->assertStringContainsString( 'pergel-list-summary', $html );
		$this->assertMatchesRegularExpression( '/<a class="pergel-load-more__button[^"]*" href="[^"]+" data-pergel-more="more" data-pergel-step="16"/', $html );
		$this->assertTrue( wp_style_is( Layout::STYLE ) );

		$toolbar = Listing::toolbar( '<form>sort</form>' );
		$this->assertStringStartsWith( '<div class="pergel-shop-toolbar"><form>sort</form>', $toolbar );
		$this->assertStringContainsString( '<option value="12">12</option><option value="48">48</option>', $toolbar );
		$this->assertStringContainsString( 'data-pergel-view="list" aria-pressed="true"', $toolbar );
	}

	public function test_banner_and_category_strip(): void {
		$banner        = wp_insert_post(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_title'   => 'Layout banner',
				'post_content' => '<!-- wp:paragraph --><p>Spring sale</p><!-- /wp:paragraph -->',
			)
		);
		$this->posts[] = $banner;
		$draft         = wp_insert_post(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'draft',
				'post_title'   => 'Layout draft banner',
				'post_content' => '<!-- wp:paragraph --><p>Not yet</p><!-- /wp:paragraph -->',
			)
		);
		$this->posts[] = $draft;
		$parent        = $this->category( 'Strip parent' );
		$child         = $this->category( 'Strip child', $parent->term_id );
		wp_set_object_terms( $this->product, array( $parent->term_id, $child->term_id ), 'product_cat' );
		Settings::update(
			array(
				'shop' => array(
					'banner'         => $banner,
					'category_strip' => 'names',
				),
			)
		);

		$this->go_to( array( 'post_type' => 'product' ) );
		$html = ShopHeader::render( '' );
		$this->assertMatchesRegularExpression( '#^<div class="pergel-shop-banner alignwide"><p[^>]*>Spring sale</p></div>#', $html );
		$this->assertStringContainsString( esc_html( $parent->name ), $html );
		$this->assertStringNotContainsString( esc_html( $child->name ), $html, 'The shop lists the main categories.' );

		update_term_meta( $parent->term_id, Layout::TERM_META, array( 'shop.banner' => $draft ) );
		$this->go_to( array( 'product_cat' => $parent->slug ) );
		$html = ShopHeader::render( '<p>About</p>' );
		$this->assertStringStartsWith( '<p>About</p><nav class="pergel-category-strip pergel-category-cards is-names', $html, 'An unpublished banner shows nothing.' );
		$this->assertStringContainsString( esc_html( $child->name ), $html );

		// A template with the description under the products: they go
		// before the shop layout, once.
		$top = $this->category( 'Strip top' );
		$sub = $this->category( 'Strip sub', $top->term_id );
		wp_set_object_terms( $this->product, array( $top->term_id, $sub->term_id ), 'product_cat' );
		Settings::update(
			array(
				'shop' => array(
					'banner'         => 0,
					'category_strip' => 'images',
				),
			)
		);
		$this->go_to( array( 'product_cat' => $top->slug ) );
		$html = do_blocks( self::COLUMNS . '<!-- wp:term-description /-->' );
		$this->assertStringStartsWith( '<nav class="pergel-category-strip pergel-category-cards is-images', $html );
		$this->assertSame( 1, substr_count( $html, '<nav class="pergel-category-strip' ) );
	}
}
