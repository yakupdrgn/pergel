<?php
/**
 * Shop starters against a real WordPress: categories, attributes, products
 * and drawn images; install, idempotency, reset and remove.
 *
 * Needs WooCommerce for most tests; on a site without it, it checks that a
 * shop starter is refused before anything is made.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use InvalidArgumentException;
use Pergel\Starters\Shop;
use Pergel\Starters\Starter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WC_Product;

/**
 * Installs a small shop starter of its own from a temporary folder and
 * removes it afterwards.
 */
final class ShopStarterTest extends TestCase {

	private const NAME    = 'pergel-test-shop';
	private const OPTIONS = array( 'show_on_front', 'page_on_front', 'page_for_posts' );

	/**
	 * Temporary starter folder.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Reading settings as they were before the test.
	 *
	 * @var array<string, mixed>
	 */
	private array $saved = array();

	protected function setUp(): void {
		foreach ( self::OPTIONS as $option ) {
			$this->saved[ $option ] = get_option( $option );
		}
		$this->dir = sys_get_temp_dir() . '/pergel-' . uniqid() . '/' . self::NAME;
		mkdir( $this->dir, 0777, true );
		$this->write( self::package() );
	}

	protected function tearDown(): void {
		Starter::remove( self::NAME );
		foreach ( $this->saved as $option => $value ) {
			update_option( $option, $value );
		}
		unlink( $this->dir . '/starter.json' );
		rmdir( $this->dir );
		rmdir( dirname( $this->dir ) );
	}

	public function test_without_woocommerce_a_shop_starter_is_refused(): void {
		if ( Shop::active() ) {
			$this->assertArrayHasKey( 'shop', Starter::available() );
			return;
		}
		$this->assertArrayNotHasKey( 'shop', Starter::available() );
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'needs WooCommerce' );
		Starter::load( $this->dir );
	}

	public function test_the_bundled_shop_starter_is_valid(): void {
		$this->needs_woocommerce();
		$this->assertSame( 'Shop', Starter::load( 'shop' )->title() );
	}

	public function test_dry_run_saves_nothing(): void {
		$this->needs_woocommerce();
		$rows = Starter::load( $this->dir )->install( true );

		$this->assertSame( array( 'create' ), array_values( array_unique( array_column( $rows, 'action' ) ) ) );
		$this->assertContains( 'product', array_column( $rows, 'type' ) );
		$this->assertSame( array(), $this->products() );
		$this->assertSame( 0, wc_attribute_taxonomy_id_by_name( 'pergel-test-tone' ) );
	}

	public function test_install_makes_categories_attributes_products_and_images(): void {
		$this->needs_woocommerce();
		Starter::load( $this->dir )->install();
		$products = $this->products();

		$this->assertSame( array( 'card', 'set', 'tee', 'tote' ), array_keys( $products ) );

		$category = get_term_by( 'slug', 'pergel-test-bags', 'product_cat' );
		$this->assertNotFalse( $category );
		$this->assertGreaterThan( 0, (int) get_term_meta( $category->term_id, 'thumbnail_id', true ) );

		$tote = $products['tote'];
		$this->assertSame( '120.00', $tote->get_regular_price() );
		$this->assertSame( '99.50', $tote->get_sale_price() );
		$this->assertSame( 4, $tote->get_stock_quantity() );
		$this->assertSame( 'PRG-TEST-TOTE', $tote->get_sku() );
		$this->assertSame( array( $category->term_id ), $tote->get_category_ids() );
		$this->assertTrue( $tote->is_featured() );
		$this->assertSame( array( $products['tee']->get_id() ), $tote->get_cross_sell_ids() );
		$this->assertSame( 'image/' . ( function_exists( 'imagewebp' ) ? 'webp' : 'png' ), get_post_mime_type( $tote->get_image_id() ) );
		$this->assertSame( self::NAME, get_post_meta( (int) $tote->get_image_id(), Starter::META, true ) );

		// One variation per combination; each takes the image of its colour.
		$tee        = $products['tee'];
		$variations = array_map( 'wc_get_product', $tee->get_children() );
		$this->assertTrue( $tee->is_type( 'variable' ) );
		$this->assertCount( 4, $variations );
		$by_tone = array();
		foreach ( $variations as $variation ) {
			$by_tone[ $variation->get_attribute( 'pa_pergel-test-tone' ) ][] = $variation->get_image_id();
			$this->assertSame( '80.00', $variation->get_regular_price() );
		}
		$this->assertCount( 2, $by_tone );
		foreach ( $by_tone as $images ) {
			$this->assertCount( 1, array_unique( $images ) );
		}
		$this->assertNotSame( reset( $by_tone )[0], end( $by_tone )[0] );

		$tone = wc_get_attribute( wc_attribute_taxonomy_id_by_name( 'pergel-test-tone' ) );
		$this->assertNotNull( $tone );
		$red = get_term_by( 'slug', 'test-red', 'pa_pergel-test-tone' );
		$this->assertNotFalse( $red );
		$this->assertSame( '#b3261e', get_term_meta( $red->term_id, 'color', true ) );
		$this->assertSame( '0', get_term_meta( $red->term_id, 'order', true ) );

		$this->assertTrue( $products['set']->is_type( 'grouped' ) );
		$this->assertSame( array( $tote->get_id(), $products['card']->get_id() ), $products['set']->get_children() );
		$this->assertSame( 'https://example.com/card', $products['card']->get_product_url() );

		$menu  = get_posts(
			array(
				'post_type'  => 'wp_navigation',
				'meta_key'   => Starter::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- test.
				'meta_value' => self::NAME, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- test.
			)
		);
		$links = array_values( array_filter( parse_blocks( $menu[0]->post_content ), static fn( array $block ): bool => null !== $block['blockName'] ) );
		$this->assertSame( 'taxonomy', $links[1]['attrs']['kind'] );
		$this->assertSame( $category->term_id, $links[1]['attrs']['id'] );
		$this->assertSame( get_term_link( $category ), $links[1]['attrs']['url'] );
		$this->assertSame( wc_get_page_id( 'shop' ), $links[0]['attrs']['id'] );
	}

	public function test_second_install_keeps_everything_and_reset_rewrites(): void {
		$this->needs_woocommerce();
		$starter = Starter::load( $this->dir );
		$starter->install();
		$images = $this->images();
		$tote   = $this->products()['tote'];
		$tote->set_name( 'Edited' );
		$tote->save();

		$again = $starter->install();
		$this->assertSame( array( 'keep' ), array_values( array_unique( array_column( $again, 'action' ) ) ) );
		$this->assertSame( 'Edited', $this->products()['tote']->get_name() );

		$reset = $starter->install( false, true );
		$this->assertContains( 'reset', array_column( $reset, 'action' ) );
		$this->assertNotContains( 'create', array_column( $reset, 'action' ) );
		$this->assertSame( 'Test tote', $this->products()['tote']->get_name() );
		$this->assertSame( $images, $this->images() );
		$this->assertCount( 4, $this->products()['tee']->get_children() );
	}

	public function test_remove_deletes_products_images_terms_and_attributes(): void {
		$this->needs_woocommerce();
		Starter::load( $this->dir )->install();
		$tee        = $this->products()['tee'];
		$variations = $tee->get_children();
		$images     = $this->images();

		$this->assertNotSame( array(), Starter::remove( self::NAME, true ) );
		$this->assertCount( 4, $this->products() );

		Starter::remove( self::NAME );
		$this->assertSame( array(), $this->products() );
		$this->assertSame( array(), $this->images() );
		foreach ( array_merge( $variations, $images ) as $id ) {
			$this->assertNull( get_post( $id ) );
		}
		$this->assertFalse( get_term_by( 'slug', 'pergel-test-bags', 'product_cat' ) );
		$this->assertSame( 0, wc_attribute_taxonomy_id_by_name( 'pergel-test-tone' ) );
		$made = get_option( Shop::ATTRIBUTES_OPTION, array() );
		$this->assertArrayNotHasKey( self::NAME, is_array( $made ) ? $made : array() );
	}

	/**
	 * Broken shop parts and part of the message each should give.
	 *
	 * @return array<string, array{0: callable, 1: string}>
	 */
	public static function broken(): array {
		return array(
			'number price'     => array(
				static function ( array $shop ): array {
					$shop['products'][0]['price'] = 120;
					return $shop;
				},
				'must be a price such as 49.90',
			),
			'sale above price' => array(
				static function ( array $shop ): array {
					$shop['products'][0]['sale'] = '130.00';
					return $shop;
				},
				'sale must be a price below the regular price',
			),
			'unknown category' => array(
				static function ( array $shop ): array {
					$shop['products'][0]['categories'] = array( 'missing' );
					return $shop;
				},
				'unknown category key',
			),
			'unknown term'     => array(
				static function ( array $shop ): array {
					$shop['products'][1]['attributes']['pergel-test-tone'][] = 'green';
					return $shop;
				},
				'must list term keys of a listed attribute',
			),
			'variable child'   => array(
				static function ( array $shop ): array {
					$shop['products'][2]['children'][] = 'tee';
					return $shop;
				},
				'may list only simple and external products',
			),
			'bad colour'       => array(
				static function ( array $shop ): array {
					$shop['attributes'][0]['terms'][0]['color'] = 'red';
					return $shop;
				},
				'must be a #rrggbb colour',
			),
		);
	}

	/**
	 * Each broken shop part is refused before anything is saved.
	 *
	 * @param callable $breaker Changes the shop part.
	 * @param string   $message Part of the error.
	 */
	#[DataProvider( 'broken' )]
	public function test_broken_shop_is_refused( callable $breaker, string $message ): void {
		$this->needs_woocommerce();
		$package         = self::package();
		$package['shop'] = $breaker( $package['shop'] );
		$this->write( $package );
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( $message );
		Starter::load( $this->dir );
	}

	/**
	 * The test package.
	 *
	 * @return array<string, mixed>
	 */
	private static function package(): array {
		return array(
			'format'      => 1,
			'title'       => 'Test shop',
			'description' => 'Products for ShopStarterTest.',
			'pages'       => array(
				array(
					'key'     => 'contact',
					'title'   => 'Test shop contact',
					'slug'    => 'pergel-test-shop-contact',
					'pattern' => 'pergel/page-contact',
				),
			),
			'menu'        => array(
				'title' => 'Test shop menu',
				'pages' => array( 'wc:shop', 'category:bags', 'contact' ),
			),
			'shop'        => array(
				'categories' => array(
					array(
						'key'  => 'bags',
						'name' => 'Test bags',
						'slug' => 'pergel-test-bags',
					),
				),
				'attributes' => array(
					array(
						'slug'  => 'pergel-test-tone',
						'name'  => 'Test tone',
						'terms' => array(
							array(
								'key'   => 'red',
								'name'  => 'Test red',
								'color' => '#b3261e',
							),
							array(
								'key'   => 'blue',
								'name'  => 'Test blue',
								'color' => '#1e3a8a',
							),
						),
					),
					array(
						'slug'  => 'pergel-test-fit',
						'name'  => 'Test fit',
						'terms' => array(
							array(
								'key'  => 'slim',
								'name' => 'Test slim',
							),
							array(
								'key'  => 'loose',
								'name' => 'Test loose',
							),
						),
					),
				),
				'products'   => array(
					array(
						'key'         => 'tote',
						'name'        => 'Test tote',
						'slug'        => 'pergel-test-tote',
						'sku'         => 'PRG-TEST-TOTE',
						'price'       => '120.00',
						'sale'        => '99.50',
						'stock'       => 4,
						'featured'    => true,
						'categories'  => array( 'bags' ),
						'cross_sells' => array( 'tee' ),
					),
					array(
						'key'        => 'tee',
						'type'       => 'variable',
						'name'       => 'Test tee',
						'slug'       => 'pergel-test-tee',
						'price'      => '80.00',
						'attributes' => array(
							'pergel-test-tone' => array( 'red', 'blue' ),
							'pergel-test-fit'  => array( 'slim', 'loose' ),
						),
					),
					array(
						'key'      => 'set',
						'type'     => 'grouped',
						'name'     => 'Test set',
						'slug'     => 'pergel-test-set',
						'children' => array( 'tote', 'card' ),
					),
					array(
						'key'   => 'card',
						'type'  => 'external',
						'name'  => 'Test card',
						'slug'  => 'pergel-test-card',
						'price' => '10.00',
						'url'   => 'https://example.com/card',
					),
				),
			),
		);
	}

	/**
	 * Skips a test that needs WooCommerce on a site without it.
	 */
	private function needs_woocommerce(): void {
		if ( ! Shop::active() ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
	}

	/**
	 * Saves a package as the temporary starter.json.
	 *
	 * @param array<string, mixed> $data Package.
	 */
	private function write( array $data ): void {
		file_put_contents( $this->dir . '/starter.json', (string) wp_json_encode( $data ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- temporary test file.
	}

	/**
	 * Products the test starter made, by key.
	 *
	 * @return array<string, WC_Product>
	 */
	private function products(): array {
		$products = array();
		foreach ( $this->made( 'product' ) as $id ) {
			$product = wc_get_product( $id );
			if ( $product instanceof WC_Product ) {
				$products[ (string) $product->get_meta( Starter::META_KEY ) ] = $product;
			}
		}
		ksort( $products );
		return $products;
	}

	/**
	 * IDs of the images the test starter drew.
	 *
	 * @return list<int>
	 */
	private function images(): array {
		return $this->made( 'attachment' );
	}

	/**
	 * IDs of the posts of a type the test starter made.
	 *
	 * @param string $type Post type.
	 * @return list<int>
	 */
	private function made( string $type ): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => $type,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'meta_key'       => Starter::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- test.
					'meta_value'     => self::NAME, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- test.
				)
			)
		);
	}
}
