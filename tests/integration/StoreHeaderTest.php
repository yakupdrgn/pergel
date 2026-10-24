<?php
/**
 * The store header in place of the theme's header part.
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
use Pergel\Woo\Module as Woo;
use Pergel\Woo\StoreHeader;
use PHPUnit\Framework\TestCase;

/**
 * Settings and the main query are put back after each test.
 */
final class StoreHeaderTest extends TestCase {

	/**
	 * The header part as a template places it, made sticky.
	 */
	private const PART = '<!-- wp:template-part {"slug":"header","tagName":"div","pergelSticky":"always"} /-->';

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

	protected function setUp(): void {
		// Saved first: tearDown() runs after a skip too.
		$this->saved = get_option( Settings::OPTION, false );
		if ( ! Woo::active() ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
		Settings::update(
			array(
				'woo'  => Schema::defaults()['woo'],
				'shop' => Schema::defaults()['shop'],
			)
		);
		$this->go_to( array( 'post_type' => 'product' ) );
	}

	protected function tearDown(): void {
		remove_filter( 'pre_render_block', array( StoreHeader::class, 'swap' ) );
		delete_option( Settings::OPTION );
		if ( false !== $this->saved ) {
			// Restored as stored, without running it through the schema again.
			remove_all_filters( 'sanitize_option_' . Settings::OPTION );
			add_option( Settings::OPTION, $this->saved, '', true );
			add_filter( 'sanitize_option_' . Settings::OPTION, array( \Pergel\Settings\Module::class, 'sanitize' ) );
		}
		Settings::flush();
		wp_dequeue_style( StoreHeader::STYLE );
		if ( null !== $this->query ) {
			$GLOBALS['wp_the_query'] = $this->query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Puts back the query go_to() replaced.
		}
		wp_reset_query();
	}

	/**
	 * Makes a page view the main query.
	 *
	 * @param array<string, mixed> $vars Query vars.
	 */
	private function go_to( array $vars ): void {
		$this->query           ??= $GLOBALS['wp_the_query'];
		$GLOBALS['wp_the_query'] = new \WP_Query( $vars ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test page view; put back in tearDown.
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- As above.
	}

	/**
	 * Sets shop settings and renders the header part as the page would.
	 *
	 * @param array<string, mixed> $shop Shop settings.
	 * @param array<string, mixed> $more Other groups.
	 */
	private function header( array $shop, array $more = array() ): string {
		Settings::update( array( 'shop' => $shop ) + $more );
		remove_filter( 'pre_render_block', array( StoreHeader::class, 'swap' ) );
		StoreHeader::load();
		return do_blocks( self::PART );
	}

	public function test_the_theme_header_stays_by_default(): void {
		$this->assertFalse( StoreHeader::applies() );
		$html = $this->header( array() );
		$this->assertStringNotContainsString( 'pergel-store-header', $html );
		$this->assertFalse( wp_style_is( StoreHeader::STYLE ) );
	}

	public function test_the_store_header_takes_the_part_on_shop_pages(): void {
		$html = $this->header( array( 'header_type' => 'store' ) );

		$this->assertMatchesRegularExpression( '/^<div class="wp-block-template-part pergel-store-header pergel-header is-sticky">/', $html, 'The part keeps its tag and its sticky option.' );
		$this->assertStringContainsString( '<header class="pergel-store-header__rows">', $html );
		$this->assertStringContainsString( 'pergel-store-header__search', $html );
		$this->assertStringContainsString( 'name="post_type" value="product"', $html );
		$this->assertStringContainsString( 'pergel-store-header__bar', $html );
		$this->assertStringContainsString( 'wp-block-navigation', $html );
		$this->assertTrue( wp_style_is( StoreHeader::STYLE ) );
		$this->assertStringNotContainsString( 'pergel-store-header', do_blocks( '<!-- wp:template-part {"slug":"footer","tagName":"div"} /-->' ), 'Only the header part.' );

		// Other pages keep the theme's header, unless it shows everywhere.
		$this->go_to( array( 'post_type' => 'page' ) );
		$this->assertFalse( StoreHeader::applies() );
		$this->assertStringNotContainsString( 'pergel-store-header', $this->header( array( 'header_type' => 'store' ) ) );
		$this->assertStringContainsString( 'pergel-store-header', $this->header( array( 'header_on' => 'site' ) ) );
	}

	public function test_the_search_lists_the_top_categories(): void {
		$term = wp_insert_term( 'Header test category', 'product_cat' );
		$this->assertIsArray( $term );
		$product = new \WC_Product_Simple();
		$product->set_name( 'Header test product' );
		$product->set_status( 'publish' );
		$product->set_category_ids( array( (int) $term['term_id'] ) );
		$id = $product->save();

		try {
			$html = $this->header( array( 'header_type' => 'store' ) );
			$this->assertMatchesRegularExpression( '/wp-block-search__inside-wrapper[^>]*><select class="pergel-store-header__categories" name="product_cat"/', $html );
			$this->assertStringContainsString( '>Header test category</option>', $html );

			$html = $this->header( array( 'header_categories' => false ) );
			$this->assertStringNotContainsString( 'pergel-store-header__categories', $html );
		} finally {
			wp_delete_post( $id, true );
			wp_delete_term( (int) $term['term_id'], 'product_cat' );
		}
	}

	public function test_rows_follow_the_item_lists(): void {
		$html = $this->header(
			array(
				'header_type'      => 'store',
				'header_top_items' => 'phone,text',
				'header_text'      => 'Free delivery',
				'header_items'     => 'button,logo,contact',
				'header_call'      => 'Ring us',
				'header_button'    => 'Ask for a quote',
				'header_bar'       => false,
			),
			array(
				'brand' => array( 'phone' => '+90 212 555 01 01' ),
				'woo'   => array( 'contact_url' => 'https://example.com/quote/' ),
			)
		);

		// The top bar in its order; the phone links to its digits.
		$top = strpos( $html, 'href="tel:+902125550101"' );
		$this->assertNotFalse( $top );
		$this->assertGreaterThan( $top, strpos( $html, 'Free delivery' ) );

		// The row in its order, without the search; the menu follows the logo
		// as the bar is off.
		$this->assertStringContainsString( 'pergel-store-header__main is-spread', $html );
		$button = strpos( $html, 'Ask for a quote' );
		$logo   = strpos( $html, 'pergel-store-header__logo' );
		$menu   = strpos( $html, 'pergel-store-header__menu' );
		$phone  = strpos( $html, 'Ring us' );
		$this->assertTrue( false !== $button && $button < $logo && $logo < $menu && $menu < $phone );
		$this->assertStringContainsString( 'href="https://example.com/quote/"', $html );
		$this->assertStringNotContainsString( 'pergel-store-header__search', $html );
		$this->assertStringNotContainsString( 'pergel-store-header__bar', $html );

		// Off, the top bar goes; without a contact page, so does the button.
		$html = $this->header( array( 'header_top' => false ), array( 'woo' => array( 'contact_url' => '' ) ) );
		$this->assertStringNotContainsString( 'pergel-store-header__top', $html );
		$this->assertStringNotContainsString( 'Ask for a quote', $html );
	}

	public function test_look_values_become_properties(): void {
		$html = $this->header(
			array(
				'header_type'       => 'store',
				'search_radius'     => 8,
				'header_bar_bg'     => '#215ada',
				'header_top_bg'     => 'base',
				'header_bar_height' => 60,
			)
		);
		$this->assertStringContainsString( 'style="--pergel-sh-top-bg:var(--wp--preset--color--base);--pergel-sh-radius:8px;--pergel-sh-bar-bg:#215ada;--pergel-sh-bar-height:60px;"', $html );
	}
}
