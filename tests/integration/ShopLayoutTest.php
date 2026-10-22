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
use Pergel\Woo\Layout;
use Pergel\Woo\LayoutFields;
use Pergel\Woo\Module as Woo;
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
		if ( $this->product > 0 ) {
			wp_delete_post( $this->product, true );
		}
		foreach ( $this->terms as $term_id ) {
			wp_delete_term( $term_id, 'product_cat' );
		}
		Layout::flush();
		unset( $_POST['pergel_shop'] );
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
}
