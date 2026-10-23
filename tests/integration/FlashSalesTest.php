<?php
/**
 * Flash sale campaigns: their prices, pages and editor fields.
 *
 * Needs WooCommerce; skipped without it. Run against a shop site
 * (PERGEL_WP_DIR=…/pergel-shop).
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Settings\Settings;
use Pergel\Woo\FlashSaleFields;
use Pergel\Woo\FlashSalePrices;
use Pergel\Woo\FlashSales;
use Pergel\Woo\Modes;
use Pergel\Woo\Module as Woo;
use PHPUnit\Framework\TestCase;

/**
 * Settings, products, campaigns, their scheduled steps and the main query
 * are put back after each test.
 */
final class FlashSalesTest extends TestCase {

	/**
	 * The option as it was before the test (false: not saved).
	 *
	 * @var mixed
	 */
	private mixed $saved = false;

	/**
	 * Posts made for the test: products first, then campaigns.
	 *
	 * @var list<int>
	 */
	private array $posts = array();

	/**
	 * Campaigns made for the test.
	 *
	 * @var list<int>
	 */
	private array $sales = array();

	/**
	 * Category made for the test, or 0.
	 */
	private int $category = 0;

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
		Settings::update( array( 'woo' => array( 'flash_sales' => true ) ) );
		// The site may boot with the campaigns off.
		FlashSales::register();
		FlashSales::init();
		// No background steps: an open admin page would run them alongside the test.
		remove_action( 'transition_post_status', array( FlashSales::class, 'status' ) );
	}

	protected function tearDown(): void {
		foreach ( $this->sales as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( array_reverse( $this->posts ) as $id ) {
			wp_delete_post( $id, true );
		}
		if ( $this->category > 0 ) {
			wp_delete_term( $this->category, 'product_cat' );
		}
		delete_option( Settings::OPTION );
		if ( false !== $this->saved ) {
			// Restored as stored, without running it through the schema again.
			remove_all_filters( 'sanitize_option_' . Settings::OPTION );
			add_option( Settings::OPTION, $this->saved, '', true );
			add_filter( 'sanitize_option_' . Settings::OPTION, array( \Pergel\Settings\Module::class, 'sanitize' ) );
		}
		Settings::flush();
		remove_all_filters( 'pergel/woo/enforce_modes' );
		Modes::flush();
		if ( null !== $this->query ) {
			$GLOBALS['wp_the_query'] = $this->query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Puts back the query go_to() replaced.
		}
		wp_reset_query();
	}

	/**
	 * Makes a simple product.
	 *
	 * @param string $price Regular price.
	 * @param string $sale  Its own sale price, or empty.
	 */
	private function product( string $price, string $sale = '' ): \WC_Product_Simple {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Flash sale test ' . wp_generate_password( 4, false ) );
		$product->set_status( 'publish' );
		$product->set_regular_price( $price );
		$product->set_sale_price( $sale );
		if ( $this->category > 0 ) {
			$product->set_category_ids( array( $this->category ) );
		}
		$this->posts[] = $product->save();
		return $product;
	}

	/**
	 * Makes a variable product with two variations.
	 *
	 * @return array{0: \WC_Product_Variable, 1: list<int>}
	 */
	private function variable(): array {
		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'size' );
		$attribute->set_options( array( 'S', 'M' ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$product = new \WC_Product_Variable();
		$product->set_name( 'Flash sale variable ' . wp_generate_password( 4, false ) );
		$product->set_status( 'publish' );
		$product->set_attributes( array( $attribute ) );
		$this->posts[] = $product->save();
		$children      = array();
		foreach ( array(
			'S' => '100',
			'M' => '200',
		) as $size => $price ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $product->get_id() );
			$variation->set_attributes( array( 'size' => $size ) );
			$variation->set_regular_price( $price );
			$variation->set_status( 'publish' );
			$children[]    = $variation->save();
			$this->posts[] = end( $children );
		}
		\WC_Product_Variable::sync( $product->get_id() );
		return array( $product, $children );
	}

	/**
	 * Makes a published campaign.
	 *
	 * @param int       $start    Start, from now in seconds.
	 * @param int       $end      End, from now in seconds.
	 * @param int       $discount Percent.
	 * @param list<int> $products Products.
	 * @param list<int> $cats     Categories.
	 */
	private function sale( int $start, int $end, int $discount, array $products, array $cats = array() ): int {
		$id = wp_insert_post(
			array(
				'post_type'   => FlashSales::TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Flash sale test ' . wp_generate_password( 4, false ),
			)
		);
		$this->assertIsInt( $id );
		$this->sales[] = $id;
		update_post_meta( $id, FlashSales::START, time() + $start );
		update_post_meta( $id, FlashSales::END, time() + $end );
		update_post_meta( $id, FlashSales::DISCOUNT, $discount );
		update_post_meta( $id, FlashSales::PRODUCTS, $products );
		update_post_meta( $id, FlashSales::CATEGORIES, $cats );
		return $id;
	}

	/**
	 * A product as stored now.
	 *
	 * @param int $id Product.
	 */
	private function fresh( int $id ): \WC_Product {
		$product = wc_get_product( $id );
		$this->assertInstanceOf( \WC_Product::class, $product );
		return $product;
	}

	public function test_prices_are_written_and_put_back(): void {
		$plain             = $this->product( '100' );
		$own               = $this->product( '200', '180' );
		[ $parent, $kids ] = $this->variable();
		$id                = $this->sale( -60, HOUR_IN_SECONDS, 20, array( $plain->get_id(), $own->get_id(), $parent->get_id() ) );

		$this->assertTrue( FlashSalePrices::run( $id ) );
		$this->assertSame( 'running', FlashSales::state( $id ) );
		$this->assertSame( '80', wc_format_decimal( $this->fresh( $plain->get_id() )->get_price() ) );
		$this->assertTrue( $this->fresh( $plain->get_id() )->is_on_sale() );
		$this->assertSame( '160', wc_format_decimal( $this->fresh( $own->get_id() )->get_price() ) );
		$this->assertSame( '80', wc_format_decimal( $this->fresh( $kids[0] )->get_price() ) );
		$this->assertSame( '160', wc_format_decimal( $this->fresh( $kids[1] )->get_price() ) );
		$this->assertSame( 80.0, (float) $this->fresh( $parent->get_id() )->get_variation_price( 'min' ), 'The variable product follows its variations.' );
		$this->assertSame( (int) get_post_meta( $id, FlashSales::END, true ), $this->fresh( $plain->get_id() )->get_date_on_sale_to()?->getTimestamp(), 'Ends with the campaign.' );
		$this->assertCount( 4, FlashSalePrices::applied( $id ) );
		$this->assertSame( array( $plain->get_id(), $own->get_id(), $parent->get_id() ), FlashSales::items( $id ) );

		// Run again: nothing changes.
		$this->assertTrue( FlashSalePrices::run( $id ) );
		$this->assertCount( 4, FlashSalePrices::applied( $id ) );

		// The end.
		update_post_meta( $id, FlashSales::END, time() - 1 );
		$this->assertTrue( FlashSalePrices::run( $id ) );
		$this->assertSame( array(), FlashSalePrices::applied( $id ) );
		$this->assertSame( '100', wc_format_decimal( $this->fresh( $plain->get_id() )->get_price() ) );
		$this->assertFalse( $this->fresh( $plain->get_id() )->is_on_sale() );
		$this->assertSame( '180', wc_format_decimal( $this->fresh( $own->get_id() )->get_price() ), 'Its own sale comes back.' );
		$this->assertSame( '', $this->fresh( $kids[1] )->get_meta( FlashSalePrices::META ) );
		$this->assertSame( '200', wc_format_decimal( $this->fresh( $kids[1] )->get_price() ) );
	}

	public function test_never_dearer_than_a_running_own_sale(): void {
		$product = $this->product( '100', '70' );
		$id      = $this->sale( -60, HOUR_IN_SECONDS, 10, array( $product->get_id() ) );

		FlashSalePrices::run( $id );
		$this->assertSame( '70', wc_format_decimal( $this->fresh( $product->get_id() )->get_price() ) );
	}

	public function test_a_price_changed_by_hand_stays(): void {
		$product = $this->product( '100' );
		$id      = $this->sale( -60, HOUR_IN_SECONDS, 20, array( $product->get_id() ) );
		FlashSalePrices::run( $id );

		$changed = $this->fresh( $product->get_id() );
		$changed->set_sale_price( '50' );
		$changed->save();
		wp_trash_post( $id );
		FlashSalePrices::run( $id );

		$this->assertSame( '50', wc_format_decimal( $this->fresh( $product->get_id() )->get_sale_price() ) );
		$this->assertSame( '', $this->fresh( $product->get_id() )->get_meta( FlashSalePrices::META ) );
	}

	public function test_one_running_campaign_per_product(): void {
		$product = $this->product( '100' );
		$first   = $this->sale( -60, HOUR_IN_SECONDS, 20, array( $product->get_id() ) );
		$second  = $this->sale( -60, HOUR_IN_SECONDS, 50, array( $product->get_id() ) );

		FlashSalePrices::run( $first );
		FlashSalePrices::run( $second );
		$this->assertSame( '80', wc_format_decimal( $this->fresh( $product->get_id() )->get_price() ) );
		$this->assertSame( array( $product->get_id() => 'taken' ), FlashSalePrices::skipped( $second ) );

		// Once the first is gone, saving the second takes the product.
		wp_delete_post( $first, true );
		$this->assertSame( '100', wc_format_decimal( $this->fresh( $product->get_id() )->get_price() ), 'Deleting a campaign puts the price back.' );
		FlashSalePrices::schedule( $second );
		$this->assertSame( '50', wc_format_decimal( $this->fresh( $product->get_id() )->get_price() ) );
	}

	public function test_upcoming_and_draft_campaigns_change_nothing(): void {
		$product  = $this->product( '100' );
		$upcoming = $this->sale( HOUR_IN_SECONDS, 2 * HOUR_IN_SECONDS, 20, array( $product->get_id() ) );
		$draft    = $this->sale( -60, HOUR_IN_SECONDS, 20, array( $product->get_id() ) );
		wp_update_post(
			array(
				'ID'          => $draft,
				'post_status' => 'draft',
			)
		);

		FlashSalePrices::run( $upcoming );
		FlashSalePrices::run( $draft );
		$this->assertSame( 'upcoming', FlashSales::state( $upcoming ) );
		$this->assertSame( '100', wc_format_decimal( $this->fresh( $product->get_id() )->get_price() ) );
		$this->assertSame( array(), FlashSalePrices::applied( $draft ) );
	}

	public function test_categories_bring_their_products(): void {
		$term = wp_insert_term( 'Flash sale test ' . wp_generate_password( 4, false ), 'product_cat' );
		$this->assertIsArray( $term );
		$this->category = (int) $term['term_id'];
		$inside         = $this->product( '100' );
		$this->category = 0;
		$outside        = $this->product( '100' );
		$this->category = (int) $term['term_id'];

		$id = $this->sale( -60, HOUR_IN_SECONDS, 20, array(), array( $this->category ) );
		$this->assertSame( array( $inside->get_id() ), FlashSales::products( $id ) );
		$this->assertNotContains( $outside->get_id(), FlashSales::products( $id ) );
	}

	public function test_a_busy_campaign_tries_again_later(): void {
		global $wpdb;
		$product = $this->product( '100' );
		$id      = $this->sale( -60, HOUR_IN_SECONDS, 20, array( $product->get_id() ) );
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name'  => 'pergel_sale_lock_' . $id,
				'option_value' => (string) time(),
				'autoload'     => 'off',
			)
		);

		$this->assertFalse( FlashSalePrices::run( $id ), 'Another step has it.' );
		$this->assertSame( '100', wc_format_decimal( $this->fresh( $product->get_id() )->get_price() ) );
		$this->assertTrue( as_has_scheduled_action( FlashSalePrices::NEXT, array( 'sale' => $id ), FlashSalePrices::GROUP ) );

		// A lock left by a step that died is taken over.
		$wpdb->update( $wpdb->options, array( 'option_value' => (string) ( time() - HOUR_IN_SECONDS ) ), array( 'option_name' => 'pergel_sale_lock_' . $id ) );
		$this->assertTrue( FlashSalePrices::run( $id ) );
		$this->assertSame( '80', wc_format_decimal( $this->fresh( $product->get_id() )->get_price() ) );
		$this->assertNull( $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'pergel_sale_lock_' . $id ) ), 'The lock is let go.' );
	}

	public function test_scheduled_at_the_start_and_the_end(): void {
		$id = $this->sale( HOUR_IN_SECONDS, 2 * HOUR_IN_SECONDS, 20, array() );
		FlashSalePrices::schedule( $id );

		$times = array();
		foreach ( as_get_scheduled_actions(
			array(
				'hook'   => FlashSalePrices::HOOK,
				'args'   => array( 'sale' => $id ),
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			)
		) as $action ) {
			$times[] = $action->get_schedule()->get_date()?->getTimestamp();
		}
		sort( $times );
		$sale = FlashSales::campaign( $id );
		$this->assertSame( array( $sale['start'], $sale['end'] ), $times );

		wp_delete_post( $id, true );
		$this->assertFalse( as_has_scheduled_action( FlashSalePrices::HOOK, array( 'sale' => $id ), FlashSalePrices::GROUP ) );
	}

	public function test_fields_read_the_site_time_zone(): void {
		$values = FlashSaleFields::values(
			array(
				'start'      => '2030-01-02T10:30',
				'end'        => 'wrong',
				'discount'   => '150',
				'products'   => array( '5', 'x', '5', '7' ),
				'categories' => 'none',
			)
		);
		$this->assertSame( ( new \DateTimeImmutable( '2030-01-02 10:30', wp_timezone() ) )->getTimestamp(), $values[ FlashSales::START ] );
		$this->assertSame( 0, $values[ FlashSales::END ] );
		$this->assertSame( 90, $values[ FlashSales::DISCOUNT ] );
		$this->assertSame( array( 5, 7 ), $values[ FlashSales::PRODUCTS ] );
		$this->assertSame( array(), $values[ FlashSales::CATEGORIES ] );
	}

	public function test_list_shows_running_then_upcoming(): void {
		$later   = $this->sale( 2 * HOUR_IN_SECONDS, 3 * HOUR_IN_SECONDS, 10, array() );
		$ended   = $this->sale( -2 * HOUR_IN_SECONDS, -HOUR_IN_SECONDS, 10, array() );
		$running = $this->sale( -HOUR_IN_SECONDS, HOUR_IN_SECONDS, 10, array() );

		$found = ( new \WP_Query(
			FlashSales::loop(
				array(
					'post_type'      => FlashSales::TYPE,
					'posts_per_page' => 10,
					'fields'         => 'ids',
				)
			)
		) )->posts;
		$ours  = array_values( array_intersect( $found, array( $later, $ended, $running ) ) );
		$this->assertSame( array( $running, $later ), $ours );
	}

	public function test_dates_block_follows_the_campaign(): void {
		$id    = $this->sale( -60, HOUR_IN_SECONDS, 25, array() );
		$block = new \WP_Block(
			array(
				'blockName' => 'pergel/sale-timer',
				'attrs'     => array(),
			),
			array( 'postId' => $id )
		);
		$html  = FlashSales::timer( array(), $block );
		$this->assertStringContainsString( 'is-running', $html );
		$this->assertStringContainsString( esc_html( sprintf( __( '%d%% off', 'pergel-engine' ), 25 ) ), $html );
		$this->assertStringContainsString( 'data-pergel-until="' . get_post_meta( $id, FlashSales::END, true ) . '"', $html );
		$this->assertStringNotContainsString( 'data-pergel-until', FlashSales::timer( array( 'countdown' => false ), $block ) );

		update_post_meta( $id, FlashSales::END, time() - 1 );
		$ended = FlashSales::timer( array(), $block );
		$this->assertStringContainsString( 'is-ended', $ended );
		$this->assertStringContainsString( esc_html__( 'This campaign has ended.', 'pergel-engine' ), $ended );
		$this->assertStringNotContainsString( 'data-pergel-until', $ended );

		$other = new \WP_Block( array( 'blockName' => 'pergel/sale-timer' ), array( 'postId' => $this->product( '10' )->get_id() ) );
		$this->assertSame( '', FlashSales::timer( array(), $other ), 'Only for campaigns.' );
	}

	public function test_campaign_page_sections_and_robots(): void {
		$product = $this->product( '100' );
		$id      = $this->sale( -60, HOUR_IN_SECONDS, 20, array( $product->get_id() ) );
		FlashSalePrices::run( $id );
		$this->go_to( $id );
		$grid   = array(
			'blockName' => 'woocommerce/product-collection',
			'attrs'     => array(
				'queryId'   => 7,
				'className' => FlashSales::PRODUCTS_CLASS,
			),
		);
		$others = array(
			'blockName' => 'core/group',
			'attrs'     => array( 'className' => 'alignwide ' . FlashSales::OTHERS_CLASS ),
		);

		$this->assertNull( FlashSales::sections( null, $grid ), 'Running: the grid is drawn.' );
		$this->assertSame( '', FlashSales::sections( null, $others ) );
		FlashSales::collection( $grid );
		$context = FlashSales::context(
			array(
				'queryId' => 7,
				'query'   => array( 'perPage' => 12 ),
			)
		);
		$this->assertSame( array( $product->get_id() ), $context['query']['woocommerceHandPickedProducts'] );
		$this->assertArrayNotHasKey( 'woocommerceHandPickedProducts', FlashSales::context( array( 'queryId' => 8 ) + array( 'query' => array() ) )['query'] );
		$this->assertArrayNotHasKey( 'noindex', FlashSales::robots( array() ) );

		update_post_meta( $id, FlashSales::END, time() - 1 );
		$this->assertSame( '', FlashSales::sections( null, $grid ) );
		$this->assertNull( FlashSales::sections( null, $others ) );
		$this->assertTrue( FlashSales::robots( array() )['noindex'] ?? false );
	}

	public function test_uninstall_puts_the_prices_back_first(): void {
		$product = $this->product( '100', '90' );
		$id      = $this->sale( -60, HOUR_IN_SECONDS, 20, array( $product->get_id() ) );
		FlashSalePrices::run( $id );
		$this->assertSame( '80', wc_format_decimal( $this->fresh( $product->get_id() )->get_price() ) );

		// Every campaign on the site goes, as on a real uninstall.
		FlashSalePrices::uninstall();

		$this->assertNull( get_post( $id ) );
		$this->assertSame( '90', wc_format_decimal( $this->fresh( $product->get_id() )->get_price() ) );
		$this->assertSame( '', $this->fresh( $product->get_id() )->get_meta( FlashSalePrices::META ) );
		$this->assertFalse( as_has_scheduled_action( FlashSalePrices::HOOK, array( 'sale' => $id ), FlashSalePrices::GROUP ) );
	}

	public function test_index_follows_the_steps(): void {
		$plain             = $this->product( '100' );
		[ $parent, $kids ] = $this->variable();
		$id                = $this->sale( -60, HOUR_IN_SECONDS, 20, array( $plain->get_id(), $parent->get_id() ) );

		FlashSalePrices::run( $id );
		$index = FlashSales::index();
		$this->assertSame( (int) get_post_meta( $id, FlashSales::END, true ), $index['sales'][ $id ] ?? null );
		$this->assertSame( $id, $index['products'][ $plain->get_id() ] ?? null );
		$this->assertSame( $id, FlashSales::of( $parent->get_id() ), 'A variable product, not its variations.' );
		$this->assertSame( 0, FlashSales::of( $kids[0] ) );
		$this->assertContains( $id, FlashSales::live() );

		update_post_meta( $id, FlashSales::END, time() - 1 );
		FlashSalePrices::run( $id );
		$this->assertSame( 0, FlashSales::of( $plain->get_id() ) );
		$this->assertNotContains( $id, FlashSales::live() );
	}

	public function test_product_box_under_its_own_price(): void {
		$product = $this->product( '100' );
		$other   = $this->product( '50' );
		$id      = $this->sale( -60, HOUR_IN_SECONDS, 20, array( $product->get_id() ) );
		FlashSalePrices::run( $id );
		$this->go_to( $product->get_id(), 'product' );

		// An unregistered block keeps no context; it is set by hand.
		$own          = new \WP_Block( array( 'blockName' => 'core/null' ) );
		$own->context = array( 'postId' => $product->get_id() );
		$html         = FlashSales::price( '<div class="price"></div>', array(), $own );
		$this->assertStringStartsWith( '<div class="price"></div><div class="pergel-sale-box">', $html );
		$this->assertStringContainsString( esc_html( get_the_title( $id ) ), $html );
		$this->assertStringContainsString( 'data-pergel-until="' . get_post_meta( $id, FlashSales::END, true ) . '"', $html );

		$related          = new \WP_Block( array( 'blockName' => 'core/null' ) );
		$related->context = array( 'postId' => $other->get_id() );
		$this->assertSame( '<div></div>', FlashSales::price( '<div></div>', array(), $related ), 'Not under the prices of other products.' );

		$this->go_to( $other->get_id(), 'product' );
		$related->context = array( 'postId' => $other->get_id() );
		$this->assertSame( '<div></div>', FlashSales::price( '<div></div>', array(), $related ), 'Not for a product outside the campaign.' );
	}

	public function test_card_badge_follows_its_setting(): void {
		$product = $this->product( '100' );
		$id      = $this->sale( -60, HOUR_IN_SECONDS, 20, array( $product->get_id() ) );
		FlashSalePrices::run( $id );

		$this->assertStringContainsString( '<span class="pergel-badge is-flash">' . esc_html__( 'Flash sale', 'pergel-engine' ) . '</span>', \Pergel\Woo\Cards::badges( $product->get_id(), null ) );
		Settings::update( array( 'woo' => array( 'sales_card' => false ) ) );
		$this->assertStringNotContainsString( 'is-flash', \Pergel\Woo\Cards::badges( $product->get_id(), null ) );
	}

	public function test_products_without_a_price_are_left_out(): void {
		$sold    = $this->product( '100' );
		$quote   = $this->product( '100' );
		$catalog = $this->product( '100' );
		$login   = $this->product( '100' );
		foreach ( array(
			Modes::QUOTE   => $quote,
			Modes::CATALOG => $catalog,
			Modes::LOGIN   => $login,
		) as $mode => $product ) {
			update_post_meta( $product->get_id(), Modes::POST_META, $mode );
		}
		Modes::flush();
		$id = $this->sale( -60, HOUR_IN_SECONDS, 20, array( $sold->get_id(), $quote->get_id(), $catalog->get_id(), $login->get_id() ) );
		FlashSalePrices::run( $id );

		$this->assertSame( array( $sold->get_id(), $login->get_id() ), FlashSales::items( $id ), 'Sold in the shop or to customers.' );
		$this->assertCount( 4, FlashSales::chosen( $id ) );
		$this->assertSame( '', $this->fresh( $quote->get_id() )->get_sale_price( 'edit' ), 'No campaign price on a product sold on request.' );
		$this->assertSame( '', $this->fresh( $catalog->get_id() )->get_sale_price( 'edit' ) );
		$this->assertSame( '80', wc_format_decimal( $this->fresh( $login->get_id() )->get_sale_price( 'edit' ) ) );
		$this->assertSame( 0, FlashSales::of( $quote->get_id() ) );

		$none = $this->sale( -60, HOUR_IN_SECONDS, 20, array( $quote->get_id() ) );
		FlashSalePrices::run( $none );
		$this->assertArrayNotHasKey( $none, FlashSales::index()['sales'], 'A campaign that shows nothing is not offered to the band.' );
		$this->assertArrayHasKey( $id, FlashSales::index()['sales'] );

		$later = $this->sale( HOUR_IN_SECONDS, 2 * HOUR_IN_SECONDS, 20, array( $catalog->get_id() ) );
		FlashSalePrices::run( $later );
		$this->assertTrue( FlashSales::bare( $none ) );
		$this->assertTrue( FlashSales::bare( $later ), 'Known before it starts.' );
		$this->assertFalse( FlashSales::bare( $id ) );
		$listed = get_posts(
			FlashSales::loop(
				array(
					'post_type'      => FlashSales::TYPE,
					'fields'         => 'ids',
					'posts_per_page' => -1,
				)
			)
		);
		$this->assertContains( $id, $listed );
		$this->assertNotContains( $none, $listed, 'Not on the list pages.' );
		$this->assertNotContains( $later, $listed );
		$this->go_to( $none );
		$this->assertTrue( FlashSales::robots( array() )['noindex'] ?? false );
		$this->assertSame( '', FlashSales::sections( null, array( 'attrs' => array( 'className' => FlashSales::PRODUCTS_CLASS ) ) ), 'No empty grid.' );
		$this->assertNull( FlashSales::sections( null, array( 'attrs' => array( 'className' => FlashSales::OTHERS_CLASS ) ) ), 'The running campaigns instead.' );

		// A visitor who is not logged in sees no price on the login product,
		// so no badge and no box either.
		add_filter( 'pergel/woo/enforce_modes', '__return_true' );
		Modes::flush();
		wp_set_current_user( 0 );
		$this->assertStringContainsString( 'is-flash', \Pergel\Woo\Cards::badges( $sold->get_id(), null ) );
		$this->assertStringNotContainsString( 'is-flash', \Pergel\Woo\Cards::badges( $login->get_id(), null ) );
		$this->go_to( $login->get_id(), 'product' );
		$block          = new \WP_Block( array( 'blockName' => 'core/null' ) );
		$block->context = array( 'postId' => $login->get_id() );
		$this->assertSame( '<div></div>', FlashSales::price( '<div></div>', array(), $block ) );
	}

	public function test_band_prefers_a_campaign_of_the_category(): void {
		$term = wp_insert_term( 'Flash sale test ' . wp_generate_password( 4, false ), 'product_cat' );
		$this->assertIsArray( $term );
		$child = wp_insert_term( 'Flash sale child ' . wp_generate_password( 4, false ), 'product_cat', array( 'parent' => (int) $term['term_id'] ) );
		$this->assertIsArray( $child );
		$this->category = (int) $term['term_id'];
		$inside         = $this->product( '100' );
		$this->category = 0;
		$outside        = $this->product( '100' );

		$soon           = $this->sale( -60, HOUR_IN_SECONDS, 10, array( $outside->get_id() ) );
		$later          = $this->sale( -60, 2 * HOUR_IN_SECONDS, 20, array(), array( (int) $term['term_id'] ) );
		$this->category = (int) $term['term_id'];
		FlashSalePrices::run( $soon );
		FlashSalePrices::run( $later );

		$this->assertSame( $soon, FlashSales::pick( 0 ), 'The one that ends soonest.' );
		$this->assertSame( $later, FlashSales::pick( (int) $term['term_id'] ) );
		$this->assertSame( $later, FlashSales::pick( (int) $child['term_id'] ), 'A campaign of a parent category counts.' );
		wp_delete_term( (int) $child['term_id'], 'product_cat' );
		$this->assertSame( $later, FlashSales::of( $inside->get_id() ) );
	}

	public function test_running_sale_block(): void {
		$product  = $this->product( '100' );
		$running  = $this->sale( -60, HOUR_IN_SECONDS, 20, array( $product->get_id() ) );
		$upcoming = $this->sale( HOUR_IN_SECONDS, 2 * HOUR_IN_SECONDS, 20, array( $product->get_id() ) );
		FlashSalePrices::run( $running );
		FlashSalePrices::run( $upcoming );

		$html = FlashSales::running_sale(
			array(
				'sale'  => $running,
				'count' => 1,
				'title' => 'Our deals',
			)
		);
		$this->assertStringContainsString( '>Our deals</a></h2>', $html );
		$this->assertStringContainsString( 'wp-block-woocommerce-product-collection', $html );
		$this->assertStringContainsString( esc_html( $product->get_name() ), $html );
		$this->assertStringContainsString( 'href="' . esc_url( (string) get_permalink( $running ) ) . '"', $html );
		$this->assertSame( '', FlashSales::running_sale( array( 'sale' => $upcoming ) ), 'Not before it starts.' );
	}

	public function test_templates_come_from_the_engine(): void {
		$registry = \WP_Block_Templates_Registry::get_instance();
		foreach ( array( 'archive-', 'single-' ) as $kind ) {
			$template = $registry->get_registered( 'pergel-engine//' . $kind . FlashSales::TYPE );
			$this->assertNotNull( $template );
			$this->assertStringContainsString( 'wp:pergel/sale-timer', (string) $template->content );
		}
		$single = FlashSales::template( 'single-' . FlashSales::TYPE );
		$this->assertStringContainsString( '"className":"' . FlashSales::PRODUCTS_CLASS . '"', $single );
		$this->assertStringContainsString( '"className":"' . FlashSales::OTHERS_CLASS . '"', $single );
		foreach ( parse_blocks( $single ) as $block ) {
			$this->assertIsArray( $block['attrs'], 'Every block comment parses.' );
		}
	}

	/**
	 * Shows a campaign or product page, as a page view would.
	 *
	 * @param int    $id   Campaign or product.
	 * @param string $type Its post type.
	 */
	private function go_to( int $id, string $type = FlashSales::TYPE ): void {
		$this->query           ??= $GLOBALS['wp_the_query'];
		$GLOBALS['wp_the_query'] = new \WP_Query( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test page view; put back in tearDown.
			array(
				'p'         => $id,
				'post_type' => $type,
			)
		);
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- As above.
	}
}
