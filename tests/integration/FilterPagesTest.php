<?php
/**
 * Filter pages: chosen filters of a product category with an address, a
 * title and a place in search results of their own.
 *
 * Needs WooCommerce; skipped without it. Run against a shop site
 * (PERGEL_WP_DIR=…/pergel-shop) with pretty permalinks and no SEO plugin.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Seo\Detect;
use Pergel\Seo\Head;
use Pergel\Seo\Module as Seo;
use Pergel\Woo\FilterPages;
use Pergel\Woo\FilterPagesFields;
use Pergel\Woo\Module as Woo;
use PHPUnit\Framework\TestCase;

/**
 * Categories, products, the attribute, the request and the main query are
 * put back after each test.
 */
final class FilterPagesTest extends TestCase {

	/**
	 * The main query before go_to() replaced it.
	 */
	private ?\WP_Query $query = null;

	/**
	 * Categories made by the test, children first.
	 *
	 * @var list<int>
	 */
	private array $terms = array();

	/**
	 * Products made by the test.
	 *
	 * @var list<int>
	 */
	private array $products = array();

	/**
	 * The attribute made by the test, 0 for none.
	 */
	private int $attribute = 0;

	/**
	 * Its taxonomy name.
	 */
	private string $taxonomy = '';

	/**
	 * The request address before the test.
	 */
	private string $uri = '/';

	/**
	 * Filter parameters the test put in $_GET.
	 *
	 * @var list<string>
	 */
	private array $get = array();

	protected function setUp(): void {
		if ( ! Woo::active() ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			$this->markTestSkipped( 'Filter pages need pretty permalinks.' );
		}
		$this->uri = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Put back as it was.
		FilterPages::flush();
		Head::flush();
	}

	protected function tearDown(): void {
		foreach ( $this->products as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		foreach ( array_reverse( $this->terms ) as $term_id ) {
			wp_delete_term( $term_id, 'product_cat' );
		}
		if ( $this->attribute > 0 ) {
			foreach ( get_terms(
				array(
					'taxonomy'   => $this->taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			) as $term_id ) {
				wp_delete_term( (int) $term_id, $this->taxonomy );
			}
			wc_delete_attribute( $this->attribute );
			unregister_taxonomy( $this->taxonomy );
		}
		foreach ( $this->get as $key ) {
			unset( $_GET[ $key ] );
		}
		unset( $_POST['pergel_filter_pages'] );
		$_SERVER['REQUEST_URI'] = $this->uri;
		wp_dequeue_script_module( FilterPages::SCRIPT );
		wp_set_current_user( 0 );
		FilterPages::flush();
		Head::flush();
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
		Head::flush();
	}

	/**
	 * Answers an address as WordPress would: `request`, then the main query.
	 *
	 * @param string               $path  Category path plus the page slug.
	 * @param array<string, mixed> $extra Other query variables.
	 * @return array<string, mixed> Query variables after `request`.
	 */
	private function visit( string $path, array $extra = array() ): array {
		$vars = FilterPages::request( array( 'product_cat' => $path ) + $extra );
		foreach ( array_keys( $vars ) as $key ) {
			if ( is_string( $key ) && str_starts_with( $key, 'filter_' ) ) {
				$this->get[] = $key;
			}
		}
		$this->go_to( $vars );
		return $vars;
	}

	/**
	 * Filter parameters in the request's query, as a visitor's ticks make.
	 *
	 * @param array<string, string> $params Parameters.
	 */
	private function tick( array $params ): void {
		foreach ( $params as $key => $value ) {
			$_GET[ $key ] = $value;
			$this->get[]  = $key;
		}
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

	/**
	 * A published product in a category, with attribute terms.
	 *
	 * @param \WP_Term     $term  Category.
	 * @param list<string> $slugs Terms of the test attribute.
	 */
	private function product( \WP_Term $term, array $slugs = array() ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Filter page test product' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->set_category_ids( array( $term->term_id ) );
		$id               = $product->save();
		$this->products[] = $id;
		if ( $slugs ) {
			wp_set_object_terms( $id, $slugs, $this->attribute() );
		}
		return $id;
	}

	/**
	 * The test attribute's taxonomy, made on first use, with terms "sbs"
	 * and "conical".
	 */
	private function attribute(): string {
		if ( $this->attribute > 0 ) {
			return $this->taxonomy;
		}
		$slug = 'pgfp' . strtolower( wp_generate_password( 5, false ) );
		$made = wc_create_attribute(
			array(
				'name' => 'Features',
				'slug' => $slug,
			)
		);
		$this->assertIsInt( $made );
		$this->attribute = $made;
		$this->taxonomy  = wc_attribute_taxonomy_name( $slug );
		register_taxonomy( $this->taxonomy, 'product' );
		foreach ( array( 'sbs', 'conical' ) as $name ) {
			$this->assertIsArray( wp_insert_term( $name, $this->taxonomy ) );
		}
		return $this->taxonomy;
	}

	/**
	 * The filter parameter of the test attribute.
	 */
	private function param(): string {
		return 'filter_' . substr( $this->attribute(), 3 );
	}

	/**
	 * Stores filter pages on a category.
	 *
	 * @param \WP_Term                   $term  Category.
	 * @param list<array<string, mixed>> $pages Rows.
	 */
	private static function store( \WP_Term $term, array $pages ): void {
		update_term_meta( $term->term_id, FilterPages::META, FilterPages::clean( $pages ) );
	}

	/**
	 * A category with one filter page, "sbs-format", for "sbs" of the test
	 * attribute.
	 */
	private function with_page(): \WP_Term {
		$term = $this->category( 'Cooling blocks' );
		self::store(
			$term,
			array(
				array(
					'slug'        => 'sbs-format',
					'filters'     => $this->param() . '=sbs',
					'title'       => 'SBS cooling blocks | Shop',
					'h1'          => 'SBS-format cooling blocks',
					'description' => 'Blocks in the SBS footprint.',
					'intro'       => 'For robot decks.',
				),
			)
		);
		return $term;
	}

	public function test_clean_keeps_rows_with_a_slug_and_a_filter(): void {
		$pages = FilterPages::clean(
			array(
				array(
					'slug'    => 'SBS Format',
					'filters' => 'https://example.com/shop/?filter_features=Automation-SBS,cap&orderby=price#top',
					'title'   => '<b>Title</b>',
					'intro'   => "Line one\nLine two",
				),
				array(
					'slug'    => 'sbs-format',
					'filters' => 'filter_wells=12',
				),
				array(
					'slug'    => 'page',
					'filters' => 'filter_wells=12',
				),
				array(
					'slug'    => 'only-type',
					'filters' => 'query_type_wells=or',
				),
				array(
					'slug'    => '',
					'filters' => 'filter_wells=8',
				),
				'not a row',
				array(
					'slug'    => 'wells',
					'filters' => array(
						'filter_wells' => '8',
						'orderby'      => 'price',
					),
				),
			)
		);

		$this->assertSame( array( 'sbs-format', 'wells' ), array_column( $pages, 'slug' ), 'Same slug once; "page", no slug and no filter dropped.' );
		$this->assertSame( array( 'filter_features' => 'automation-sbs,cap' ), $pages[0]['filters'], 'From an address: only filter parameters, lower case.' );
		$this->assertSame( 'Title', $pages[0]['title'] );
		$this->assertSame( "Line one\nLine two", $pages[0]['intro'] );
		$this->assertSame( '', $pages[0]['h1'] );
		$this->assertSame( array( 'filter_wells' => '8' ), $pages[1]['filters'] );
		$this->assertSame( array(), FilterPages::clean( 'nothing' ) );
	}

	public function test_page_address_reaches_its_category_with_the_filters(): void {
		$term = $this->with_page();
		$link = get_term_link( $term );
		$this->assertIsString( $link );
		$this->assertSame( trailingslashit( $link ) . 'sbs-format/', FilterPages::url( $term, 'sbs-format' ) );

		$vars = $this->visit( $term->slug . '/sbs-format' );

		$this->assertSame( $term->slug, $vars['product_cat'] );
		$this->assertSame( 'sbs', $vars[ $this->param() ] );
		$this->assertSame( 'sbs', $_GET[ $this->param() ], 'WooCommerce\'s layered navigation reads $_GET.' );
		$this->assertTrue( is_product_category() );
		$this->assertFalse( FilterPages::redirect( 'https://example.com/elsewhere/' ), 'WordPress does not send the address to the category.' );

		$current = FilterPages::current();
		$this->assertIsArray( $current );
		$this->assertSame( 'sbs-format', $current['page']['slug'] );
		$this->assertSame( $term->term_id, $current['term']->term_id );
		$this->assertSame( FilterPages::url( $term, 'sbs-format' ), $current['url'] );
		$this->assertFalse( FilterPages::noindex( false ), 'A filter page is indexed.' );
		$this->assertTrue( FilterPages::noindex( true ), 'An earlier noindex stays.' );
	}

	public function test_other_addresses_are_left_alone(): void {
		$term  = $this->with_page();
		$child = $this->category( 'Child', $term->term_id );
		self::store(
			$term,
			array_merge(
				FilterPages::pages( $term->term_id ),
				array(
					array(
						'slug'    => $child->slug,
						'filters' => $this->param() . '=conical',
					),
				)
			)
		);

		$path = $term->slug . '/' . $child->slug;
		$this->assertSame( array( 'product_cat' => $path ), FilterPages::request( array( 'product_cat' => $path ) ), 'A child category of the same slug keeps its address.' );
		$this->assertSame( array( 'product_cat' => $term->slug . '/nothing' ), FilterPages::request( array( 'product_cat' => $term->slug . '/nothing' ) ), 'An unknown slug.' );
		$this->assertSame( array( 'product_cat' => 'elsewhere/' . $term->slug . '/sbs-format' ), FilterPages::request( array( 'product_cat' => 'elsewhere/' . $term->slug . '/sbs-format' ) ), 'Not the category\'s own path.' );
		$this->assertSame( array( 'product_cat' => $term->slug ), FilterPages::request( array( 'product_cat' => $term->slug ) ), 'The category itself.' );
		$this->assertSame( 'https://example.com/x/', FilterPages::redirect( 'https://example.com/x/' ) );
		$this->go_to( array( 'product_cat' => $term->slug ) );
		$this->assertNull( FilterPages::current() );
		$this->assertSame( 'Fallback', FilterPages::document_title( 'Fallback' ), 'Other pages keep their title.' );
		$this->assertSame( 'Category', FilterPages::heading( 'Category' ) );
	}

	public function test_child_category_named_like_the_slug_does_not_take_the_address(): void {
		$term = $this->with_page();
		// A subcategory named as the page's slug but stored under another slug (as WordPress makes
		// "96-well-levitation-plates" when "96-well" is taken elsewhere).
		$made = wp_insert_term( 'SBS-Format', 'product_cat', array( 'parent' => $term->term_id, 'slug' => 'sbs-format-' . strtolower( wp_generate_password( 6, false ) ) ) );
		$this->assertIsArray( $made );
		$this->terms[] = (int) $made['term_id'];

		$vars = $this->visit( $term->slug . '/sbs-format' );

		$this->assertSame( $term->slug, $vars['product_cat'], 'Only a subcategory slug takes the address, not its name.' );
		$this->assertSame( 'sbs', $vars[ $this->param() ] );
	}

	public function test_page_address_with_more_filters_is_not_the_page(): void {
		$term = $this->with_page();
		$this->tick( array( 'filter_size' => 'large' ) );

		$this->visit( $term->slug . '/sbs-format' );

		$this->assertNull( FilterPages::current() );
		$this->assertTrue( FilterPages::noindex( false ) );
	}

	public function test_same_choice_on_the_category_is_the_page(): void {
		$term = $this->with_page();
		$this->tick(
			array(
				$this->param() => 'sbs',
				'query_type_' . substr( $this->param(), 7 ) => 'or',
			)
		);
		$this->go_to( array( 'product_cat' => $term->slug ) );

		$current = FilterPages::current();
		$this->assertIsArray( $current, 'AND or OR does not count.' );
		$this->assertSame( 'sbs-format', $current['page']['slug'] );
		$this->assertFalse( FilterPages::noindex( false ) );
		$this->assertSame( FilterPages::url( $term, 'sbs-format' ), FilterPages::canonical( 'https://example.com/category/' ) );
	}

	public function test_other_filter_choices_are_noindex(): void {
		$term = $this->with_page();
		$this->go_to( array( 'product_cat' => $term->slug ) );
		$this->assertFalse( FilterPages::noindex( false ), 'The category without filters.' );

		$this->tick( array( $this->param() => 'conical,sbs' ) );
		$this->assertNull( FilterPages::current() );
		$this->assertTrue( FilterPages::noindex( false ), 'A choice without a page.' );

		// Outside product listings, filter parameters mean nothing.
		$this->go_to( array( 'p' => -1 ) );
		$this->assertFalse( FilterPages::noindex( false ) );
		$this->assertNotFalse( has_filter( Seo::NOINDEX, array( FilterPages::class, 'noindex' ) ) );
	}

	public function test_titles_heading_and_intro_of_the_page(): void {
		if ( '' !== Detect::active() ) {
			$this->markTestSkipped( 'An SEO plugin prints the head.' );
		}
		$term = $this->with_page();
		$this->visit( $term->slug . '/sbs-format' );

		$this->assertSame( 'SBS cooling blocks | Shop', FilterPages::document_title( '' ) );
		$this->assertSame( 'SBS-format cooling blocks', FilterPages::heading( 'Category: Cooling blocks' ) );
		$this->assertSame( "<p>For robot decks.</p>\n", FilterPages::intro( '<p>Category text</p>', $term->term_id ) );
		$this->assertSame( '<p>Other</p>', FilterPages::intro( '<p>Other</p>', $term->term_id + 1000 ), 'Another term keeps its description.' );
		$this->assertSame( 'Blocks in the SBS footprint.', FilterPages::description( 'Category description' ) );

		$page = Head::page();
		$url  = FilterPages::url( $term, 'sbs-format' );
		$this->assertSame( $url, $page['url'] );
		$this->assertSame( 'SBS-format cooling blocks', $page['name'] );
		$this->assertSame( 'Blocks in the SBS footprint.', $page['description'] );
		$last = end( $page['crumbs'] );
		$this->assertIsArray( $last );
		$this->assertSame( 'SBS-format cooling blocks', $last['name'] );
		$this->assertSame( $url, $last['url'] );
		$this->assertSame( $term->name, $page['crumbs'][ count( $page['crumbs'] ) - 2 ]['name'], 'The category before the page.' );

		// Page 2: the page number in the title and the address. A new
		// request: no filter parameters of its own.
		unset( $_GET[ $this->param() ] );
		FilterPages::flush();
		$this->visit( $term->slug . '/sbs-format', array( 'paged' => 2 ) );
		$this->assertSame( 'SBS cooling blocks | Shop – Page 2', FilterPages::document_title( '' ) );
		$this->assertSame( $url . 'page/2/', FilterPages::canonical( '' ) );
	}

	public function test_title_falls_back_to_the_heading(): void {
		$term = $this->category( 'Racks' );
		self::store(
			$term,
			array(
				array(
					'slug'    => 'cap-holder',
					'filters' => $this->param() . '=sbs',
					'h1'      => 'Racks with cap holder',
				),
			)
		);
		$this->visit( $term->slug . '/cap-holder' );

		$this->assertSame( esc_html( 'Racks with cap holder – ' . wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) ), FilterPages::document_title( '' ) );
		$this->assertSame( 'Category description', FilterPages::description( 'Category description' ), 'No description of its own.' );
	}

	public function test_seo_plugin_crumbs(): void {
		$term = $this->with_page();
		$this->visit( $term->slug . '/sbs-format' );
		$url  = FilterPages::url( $term, 'sbs-format' );
		$link = get_term_link( $term );

		$rank = FilterPages::rank_math_crumbs(
			array(
				array( 'Home', home_url( '/' ) ),
				array( $term->name, '' ),
			)
		);
		$this->assertSame( array( $term->name, $link ), $rank[1], 'The category gets its link.' );
		$this->assertSame( array( 'SBS-format cooling blocks', $url ), $rank[2] );

		$yoast = FilterPages::yoast_crumbs( array( array( 'text' => 'Home' ) ) );
		$this->assertSame(
			array(
				'text' => 'SBS-format cooling blocks',
				'url'  => $url,
			),
			$yoast[1]
		);
		$this->assertSame( '<link rel="canonical" href="' . esc_url( $url ) . '">', FilterPages::canonical_tag( '' ) );
	}

	public function test_sitemaps_list_the_pages_of_categories_with_products(): void {
		$term  = $this->with_page();
		$empty = $this->category( 'Empty' );
		self::store(
			$empty,
			array(
				array(
					'slug'    => 'none',
					'filters' => 'filter_size=large',
				),
			)
		);
		$this->product( $term, array( 'sbs' ) );
		$url = FilterPages::url( $term, 'sbs-format' );

		$urls = FilterPages::addresses();
		$this->assertContains( $url, $urls );
		$this->assertNotContains( FilterPages::url( $empty, 'none' ), $urls, 'An empty category is left out.' );
		$this->assertStringContainsString( '<loc>' . esc_url( $url ) . '</loc>', (string) FilterPages::sitemap_xml( '' ) );
		$this->assertContains( $url, array_column( (array) FilterPages::sitemap_entries( array() ), 'loc' ) );
	}

	public function test_core_sitemap_provider_joins_only_on_a_sitemap_address(): void {
		$server = wp_sitemaps_get_server();
		// Start from a registry without ours (it keeps providers by name).
		$property = new \ReflectionProperty( \WP_Sitemaps_Registry::class, 'providers' );
		$list     = $property->getValue( $server->registry );
		unset( $list[ FilterPages::SITEMAP ] );
		$property->setValue( $server->registry, $list );

		$page             = new \WP();
		$page->query_vars = array( 'pagename' => 'about' );
		FilterPages::sitemap_request( $page );
		$this->assertArrayNotHasKey( FilterPages::SITEMAP, $server->registry->get_providers(), 'Not on an ordinary page.' );

		$map             = new \WP();
		$map->query_vars = array( 'sitemap' => 'index' );
		FilterPages::sitemap_request( $map );
		$this->assertArrayHasKey( FilterPages::SITEMAP, $server->registry->get_providers() );
	}

	public function test_products_of_a_page(): void {
		$term  = $this->with_page();
		$child = $this->category( 'Child', $term->term_id );
		$sbs   = $this->product( $term, array( 'sbs' ) );
		$below = $this->product( $child, array( 'sbs', 'conical' ) );
		$this->product( $term, array( 'conical' ) );
		$this->product( $term );
		$hidden = $this->product( $term, array( 'sbs' ) );
		$hide   = wc_get_product( $hidden );
		$this->assertInstanceOf( \WC_Product::class, $hide );
		$hide->set_catalog_visibility( 'search' );
		$hide->save();

		$this->visit( $term->slug . '/sbs-format' );
		$current = FilterPages::current();
		$this->assertIsArray( $current );

		$ids = FilterPages::products( $current );
		sort( $ids );
		$this->assertSame( array( $sbs, $below ), $ids, 'In the category and below it, with the filter, visible in the catalogue.' );
	}

	public function test_filter_block_reads_the_page_choice_from_the_category_address(): void {
		$term = $this->with_page();
		$link = get_term_link( $term );
		$this->assertIsString( $link );
		$_SERVER['REQUEST_URI'] = '/product-category/' . $term->slug . '/sbs-format/';
		$this->visit( $term->slug . '/sbs-format' );
		$block = array( 'blockName' => 'woocommerce/product-filters' );

		$this->assertNull( FilterPages::before_filters( null, array( 'blockName' => 'core/paragraph' ) ) );
		$this->assertSame( '/product-category/' . $term->slug . '/sbs-format/', $_SERVER['REQUEST_URI'], 'Other blocks leave it.' );

		$this->assertNull( FilterPages::before_filters( null, $block ) );
		$this->assertSame( wp_parse_url( $link, PHP_URL_PATH ) . '?' . $this->param() . '=sbs', $_SERVER['REQUEST_URI'] );

		$this->assertSame( '<div>filters</div>', FilterPages::after_filters( '<div>filters</div>' ) );
		$this->assertSame( '/product-category/' . $term->slug . '/sbs-format/', $_SERVER['REQUEST_URI'], 'Put back after the block.' );
	}

	public function test_listing_head_attributes_and_marker(): void {
		$plain = $this->category( 'Plain' );
		$this->go_to( array( 'product_cat' => $plain->slug ) );
		$this->assertSame( '', FilterPages::attributes(), 'A category without filter pages.' );
		$this->assertSame( '', FilterPages::marker() );

		$term = $this->with_page();
		$this->go_to( array( 'product_cat' => $term->slug ) );
		$this->assertStringContainsString( 'data-wp-router-region="' . FilterPages::REGION . '"', FilterPages::attributes() );
		$this->assertStringContainsString( 'data-wp-key="pergel-category"', FilterPages::marker(), 'The category itself.' );

		FilterPages::flush();
		$this->visit( $term->slug . '/sbs-format' );
		$url    = FilterPages::url( $term, 'sbs-format' );
		$marker = FilterPages::marker();
		$this->assertStringContainsString( 'data-wp-key="' . md5( $url ) . '"', $marker );
		$this->assertStringContainsString( 'data-pergel-url="' . esc_url( $url ) . '"', $marker );
		$this->assertStringContainsString( 'data-wp-init="callbacks.address"', $marker );
	}

	public function test_category_form_saves_the_rows(): void {
		$term  = $this->category( 'Form' );
		$admin = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		$this->assertNotEmpty( $admin );
		$_POST['pergel_filter_pages'] = array(
			array(
				'slug'    => 'Deep Well',
				'filters' => 'filter_volume=2ml',
				'h1'      => 'Deep-well plates',
			),
			array(
				'slug'    => '',
				'filters' => '',
			),
		);

		FilterPagesFields::save( $term->term_id );
		$this->assertSame( array(), FilterPages::pages( $term->term_id ), 'Not without the right to edit categories.' );

		wp_set_current_user( (int) $admin[0] );
		FilterPagesFields::save( $term->term_id );
		$pages = FilterPages::pages( $term->term_id );
		$this->assertCount( 1, $pages, 'The blank row is dropped.' );
		$this->assertSame( 'deep-well', $pages[0]['slug'] );
		$this->assertSame( array( 'filter_volume' => '2ml' ), $pages[0]['filters'] );

		$_POST['pergel_filter_pages'] = array(
			array(
				'slug'    => '',
				'filters' => '',
			),
		);
		FilterPagesFields::save( $term->term_id );
		$this->assertFalse( metadata_exists( 'term', $term->term_id, FilterPages::META ), 'No rows: the meta is gone.' );
	}
}
