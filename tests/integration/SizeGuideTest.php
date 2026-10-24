<?php
/**
 * Size guide: where the button goes, which guide a product shows and what
 * the block draws.
 *
 * Needs WooCommerce for most tests; on a site without it, it checks that
 * the block is not registered.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Settings\Settings;
use Pergel\Woo\Modes;
use Pergel\Woo\Module as Woo;
use Pergel\Woo\SizeGuide;
use PHPUnit\Framework\TestCase;

/**
 * Products, categories, patterns and settings are removed or restored
 * after each test.
 */
final class SizeGuideTest extends TestCase {

	/**
	 * The settings option as it was before the test (false: not saved).
	 *
	 * @var mixed
	 */
	private mixed $saved;

	/**
	 * Products made by the test.
	 *
	 * @var list<int>
	 */
	private array $products = array();

	/**
	 * Product categories made by the test.
	 *
	 * @var list<int>
	 */
	private array $categories = array();

	/**
	 * Patterns made by the test.
	 *
	 * @var list<int>
	 */
	private array $patterns = array();

	protected function setUp(): void {
		$this->saved = get_option( Settings::OPTION, false );
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		foreach ( $this->products as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$product->delete( true );
			}
		}
		foreach ( array_reverse( $this->categories ) as $id ) {
			wp_delete_term( $id, 'product_cat' );
		}
		foreach ( $this->patterns as $id ) {
			wp_delete_post( $id, true );
		}
		delete_option( Settings::OPTION );
		if ( false !== $this->saved ) {
			remove_all_filters( 'sanitize_option_' . Settings::OPTION );
			add_option( Settings::OPTION, $this->saved, '', true );
			add_filter( 'sanitize_option_' . Settings::OPTION, array( \Pergel\Settings\Module::class, 'sanitize' ) );
		}
		Settings::flush();
		if ( Woo::active() ) {
			Modes::flush();
		}
	}

	public function test_block_needs_woocommerce(): void {
		$this->assertSame( Woo::active(), \WP_Block_Type_Registry::get_instance()->is_registered( SizeGuide::BLOCK ) );
	}

	public function test_button_goes_before_the_add_to_cart_form_once(): void {
		$this->needs_woocommerce();
		$template          = new \WP_Block_Template();
		$template->content = '<!-- wp:woocommerce/add-to-cart-with-options /-->';

		$this->assertSame( array( SizeGuide::BLOCK ), SizeGuide::hook( array(), 'before', 'woocommerce/add-to-cart-with-options', $template ) );
		$this->assertSame( array(), SizeGuide::hook( array(), 'after', 'woocommerce/add-to-cart-with-options', $template ) );
		$this->assertSame( array(), SizeGuide::hook( array(), 'before', 'core/post-title', $template ) );

		$template->content .= '<!-- wp:pergel/size-guide /-->';
		$this->assertSame( array(), SizeGuide::hook( array(), 'before', 'woocommerce/add-to-cart-with-options', $template ) );
	}

	public function test_choice_is_a_pattern_id_or_none_on_products(): void {
		$this->assertSame( '12', SizeGuide::sanitize( ' 12 ', true ) );
		$this->assertSame( '12', SizeGuide::sanitize( 12 ) );
		$this->assertSame( 'none', SizeGuide::sanitize( 'none', true ) );
		$this->assertSame( '', SizeGuide::sanitize( 'none' ) );
		$this->assertSame( '', SizeGuide::sanitize( '0', true ) );
		$this->assertSame( '', SizeGuide::sanitize( '-3', true ) );
		$this->assertSame( '', SizeGuide::sanitize( '12abc', true ) );
		$this->assertSame( '', SizeGuide::sanitize( array( 12 ), true ) );

		SizeGuide::meta();
		$this->assertSame( 'none', sanitize_meta( SizeGuide::META, 'none', 'post', 'product' ) );
		$this->assertSame( '', sanitize_meta( SizeGuide::META, 'none', 'term', 'product_cat' ) );
		$this->assertSame( '7', sanitize_meta( SizeGuide::META, '7', 'term', 'product_cat' ) );
	}

	public function test_product_follows_its_closest_category(): void {
		$this->needs_woocommerce();
		$parent_guide = $this->pattern( 'Parent guide' );
		$near_guide   = $this->pattern( 'Near guide' );
		$parent       = $this->category( 'Parent' );
		$child        = $this->category( 'Child', $parent );
		$grandchild   = $this->category( 'Grandchild', $child );
		$other        = $this->category( 'Other' );
		update_term_meta( $parent, SizeGuide::META, (string) $parent_guide );

		// From the grandparent, two steps up.
		$this->assertSame( $parent_guide, SizeGuide::guide( $this->product( array( $grandchild ) ) )?->ID );

		// A category one step from the product beats one two steps away.
		update_term_meta( $other, SizeGuide::META, (string) $near_guide );
		$this->assertSame( $near_guide, SizeGuide::guide( $this->product( array( $grandchild, $other ) ) )?->ID );

		// As close: the lower category id.
		update_term_meta( $grandchild, SizeGuide::META, (string) $parent_guide );
		$this->assertSame( $parent_guide, SizeGuide::guide( $this->product( array( $other, $grandchild ) ) )?->ID );

		// No category with a guide.
		$this->assertNull( SizeGuide::guide( $this->product( array( $this->category( 'Bare' ) ) ) ) );
	}

	public function test_product_choice_wins_and_none_hides_the_guide(): void {
		$this->needs_woocommerce();
		$category_guide = $this->pattern( 'Category guide' );
		$own_guide      = $this->pattern( 'Own guide' );
		$category       = $this->category( 'Gloves' );
		update_term_meta( $category, SizeGuide::META, (string) $category_guide );

		$own = $this->product( array( $category ), (string) $own_guide );
		$this->assertSame( $own_guide, SizeGuide::guide( $own )?->ID );
		$this->assertNull( SizeGuide::guide( $this->product( array( $category ), 'none' ) ) );

		// A guide that is not a published, open pattern falls back to the category.
		wp_update_post(
			array(
				'ID'          => $own_guide,
				'post_status' => 'draft',
			)
		);
		$this->assertSame( $category_guide, SizeGuide::guide( $own )?->ID );
		wp_update_post(
			array(
				'ID'            => $own_guide,
				'post_status'   => 'publish',
				'post_password' => 'secret',
			)
		);
		$this->assertSame( $category_guide, SizeGuide::guide( $own )?->ID );

		$page             = (int) wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Pergel size guide test page',
			)
		);
		$this->patterns[] = $page;
		$this->assertSame( $category_guide, SizeGuide::guide( $this->product( array( $category ), (string) $page ) )?->ID );
	}

	public function test_variation_shows_its_parents_guide(): void {
		$this->needs_woocommerce();
		$guide  = $this->pattern( 'Variable guide' );
		$parent = $this->product( array(), (string) $guide );

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_regular_price( '10' );
		$variation->save();
		$this->products[] = $variation->get_id();

		$this->assertSame( $guide, SizeGuide::guide( $variation )?->ID );
	}

	public function test_button_and_dialog_with_the_guide_inside(): void {
		$this->needs_woocommerce();
		$this->settings( true );
		$guide   = $this->pattern( 'Glove sizes', '<!-- wp:paragraph --><p>Measure around your palm.</p><!-- /wp:paragraph -->' );
		$product = $this->product( array(), (string) $guide );
		$id      = 'pergel-size-guide-' . $product->get_id();
		$html    = $this->block( $product->get_id() );

		$this->assertStringContainsString( 'commandfor="' . $id . '" command="show-modal" aria-haspopup="dialog"', $html );
		$this->assertStringContainsString( '<dialog id="' . $id . '"', $html );
		$this->assertStringContainsString( 'aria-labelledby="' . $id . '-title" closedby="any"', $html );
		$this->assertStringContainsString( '<h2 id="' . $id . '-title" class="wp-block-pergel-size-guide__title">Glove sizes</h2>', $html );
		$this->assertStringContainsString( 'command="close" aria-label="' . esc_attr__( 'Close', 'pergel-engine' ) . '"', $html );
		$this->assertStringContainsString( 'Measure around your palm.', $html );
		$this->assertTrue( wp_style_is( 'pergel-size-guide', 'enqueued' ) );
	}

	public function test_nothing_without_a_guide_or_with_the_setting_off(): void {
		$this->needs_woocommerce();
		wp_dequeue_style( 'pergel-size-guide' );
		$this->settings( true );
		$this->assertSame( '', $this->block( $this->product( array(), 'none' )->get_id() ) );
		$this->assertSame( '', $this->block( $this->product( array() )->get_id() ) );
		$this->assertFalse( wp_style_is( 'pergel-size-guide', 'enqueued' ) );

		// An empty pattern draws no empty window.
		$this->assertSame( '', $this->block( $this->product( array(), (string) $this->pattern( 'Empty', '' ) )->get_id() ) );

		$this->settings( false );
		$this->assertSame( '', $this->block( $this->product( array(), (string) $this->pattern( 'Off' ) )->get_id() ) );
	}

	/**
	 * Skips a test that needs WooCommerce on a site without it.
	 */
	private function needs_woocommerce(): void {
		if ( ! Woo::active() ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
	}

	/**
	 * Turns the size guide on or off.
	 *
	 * @param bool $on Whether it is on.
	 */
	private function settings( bool $on ): void {
		Settings::update( array( 'woo' => array( 'size_guide' => $on ) ) );
		Settings::flush();
		Modes::flush();
	}

	/**
	 * A published synced pattern.
	 *
	 * @param string $title   Title.
	 * @param string $content Block markup.
	 */
	private function pattern( string $title, string $content = '<!-- wp:paragraph --><p>Sizes</p><!-- /wp:paragraph -->' ): int {
		$id               = (int) wp_insert_post(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $content,
			)
		);
		$this->patterns[] = $id;
		return $id;
	}

	/**
	 * A product category.
	 *
	 * @param string $name   Name.
	 * @param int    $parent Parent category id.
	 */
	private function category( string $name, int $parent = 0 ): int {
		$term               = wp_insert_term( 'Pergel size guide test ' . $name, 'product_cat', array( 'parent' => $parent ) );
		$this->categories[] = (int) $term['term_id'];
		return (int) $term['term_id'];
	}

	/**
	 * A published simple product.
	 *
	 * @param list<int> $categories Category ids.
	 * @param string    $choice     Its own guide: a pattern id, "none" or ''.
	 */
	private function product( array $categories, string $choice = '' ): \WC_Product {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Pergel size guide test ' . ( count( $this->products ) + 1 ) );
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->set_category_ids( $categories );
		if ( '' !== $choice ) {
			$product->update_meta_data( SizeGuide::META, $choice );
		}
		$product->save();
		$this->products[] = $product->get_id();
		return $product;
	}

	/**
	 * The block for a product.
	 *
	 * @param int $id Product ID.
	 */
	private function block( int $id ): string {
		$block = new \WP_Block(
			array(
				'blockName' => SizeGuide::BLOCK,
				'attrs'     => array(),
			),
			array(
				'postId'   => $id,
				'postType' => 'product',
			)
		);
		return SizeGuide::render( $block );
	}
}
