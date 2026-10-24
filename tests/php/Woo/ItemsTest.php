<?php
/**
 * Card and summary items: hide, order and add blocks.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Woo;

use Pergel\Woo\Items;
use PHPUnit\Framework\TestCase;

/**
 * Works on parsed blocks built by hand, as parse_blocks() makes them.
 */
final class ItemsTest extends TestCase {

	/**
	 * A parsed block; each child gets a null in innerContent.
	 *
	 * @param string                     $name     Block name.
	 * @param list<array<string, mixed>> $children Inner blocks.
	 * @param array<string, mixed>       $attrs    Attributes.
	 * @return array<string, mixed>
	 */
	private static function block( string $name, array $children = array(), array $attrs = array() ): array {
		$content = $children ? array( '<div>' ) : array();
		foreach ( $children as $child ) {
			$content[] = null;
			$content[] = "\n";
		}
		if ( $children ) {
			$content[] = '</div>';
		}
		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => $children,
			'innerHTML'    => '',
			'innerContent' => $content,
		);
	}

	/**
	 * Block names of a block's children.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return list<string>
	 */
	private static function names( array $block ): array {
		return array_map( static fn ( array $child ): string => (string) $child['blockName'], $block['innerBlocks'] );
	}

	/**
	 * Each child still has exactly one null in innerContent.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 */
	private function assertNullsMatch( array $block ): void {
		$this->assertCount( count( $block['innerBlocks'] ), array_keys( $block['innerContent'], null, true ) );
		foreach ( $block['innerBlocks'] as $child ) {
			$this->assertNullsMatch( $child );
		}
	}

	/**
	 * A card template like the theme's: picture, name, price, button.
	 *
	 * @return array<string, mixed>
	 */
	private static function card(): array {
		return self::block(
			'woocommerce/product-template',
			array(
				self::block( 'woocommerce/product-image' ),
				self::block( 'core/post-title' ),
				self::block( 'woocommerce/product-price' ),
				self::block( 'woocommerce/product-button' ),
			)
		);
	}

	public function test_orders_and_hides_card_items(): void {
		$card = Items::arrange( self::card(), 'card', array( 'image', 'price', 'title' ) );

		$this->assertSame( array( 'woocommerce/product-image', 'woocommerce/product-price', 'core/post-title' ), self::names( $card ) );
		$this->assertNullsMatch( $card );
		$this->assertSame( '<div>', $card['innerContent'][0] );
		$this->assertSame( '</div>', end( $card['innerContent'] ) );
	}

	public function test_adds_a_missing_item_after_the_one_before_it(): void {
		$card = Items::arrange( self::card(), 'card', array( 'image', 'title', 'sku', 'price', 'button' ) );

		$this->assertSame( array( 'woocommerce/product-image', 'core/post-title', 'woocommerce/product-sku', 'woocommerce/product-price', 'woocommerce/product-button' ), self::names( $card ) );
		$this->assertTrue( $card['innerBlocks'][2]['attrs']['isDescendentOfQueryLoop'] );
		$this->assertNullsMatch( $card );

		$first = Items::arrange( self::card(), 'card', array( 'rating', 'image', 'title', 'price', 'button' ) );
		$this->assertSame( 'woocommerce/product-rating', $first['innerBlocks'][0]['blockName'], 'Nothing before it: before the first item.' );
	}

	public function test_orders_inside_the_group_that_holds_most_items(): void {
		// The picture outside, the rest in a group: as in some child themes.
		$card = self::block(
			'woocommerce/product-template',
			array(
				self::block( 'woocommerce/product-image' ),
				self::block(
					'core/group',
					array(
						self::block( 'core/post-title' ),
						self::block( 'woocommerce/product-sku' ),
						self::block( 'woocommerce/product-button' ),
					)
				),
			)
		);

		$card = Items::arrange( $card, 'card', array( 'sku', 'image', 'title', 'price', 'button' ) );

		$this->assertSame( array( 'woocommerce/product-image', 'core/group' ), self::names( $card ), 'The picture keeps its place.' );
		$this->assertSame( array( 'woocommerce/product-sku', 'core/post-title', 'woocommerce/product-price', 'woocommerce/product-button' ), self::names( $card['innerBlocks'][1] ) );
		$this->assertNullsMatch( $card );

		$hidden = Items::arrange( $card, 'card', array( 'title', 'button' ) );
		$this->assertSame( array( 'core/group' ), self::names( $hidden ), 'Left out: hidden wherever it is.' );
		$this->assertSame( array( 'core/post-title', 'woocommerce/product-button' ), self::names( $hidden['innerBlocks'][0] ) );
		$this->assertNullsMatch( $hidden );
	}

	public function test_summary_leaves_other_grids_and_other_terms_alone(): void {
		$page = self::block(
			'core/group',
			array(
				self::block(
					'core/column',
					array(
						self::block( 'core/post-terms', array(), array( 'term' => 'product_brand' ) ),
						self::block( 'core/post-title' ),
						self::block( 'woocommerce/product-price' ),
						self::block( 'woocommerce/add-to-cart-with-options', array( self::block( 'woocommerce/product-quantity' ) ) ),
					)
				),
				self::block( 'woocommerce/product-collection', array( self::block( 'woocommerce/product-template', array( self::block( 'woocommerce/product-price' ), self::block( 'core/post-title' ) ) ) ) ),
			)
		);

		$page = Items::arrange( $page, 'summary', array( 'price', 'title', 'category' ) );

		$this->assertSame( array( 'core/post-terms', 'woocommerce/product-price', 'core/post-title', 'core/post-terms' ), self::names( $page['innerBlocks'][0] ), 'The brand list is not the category: it stays, the buying form goes, the category is added after the name.' );
		$this->assertSame( 'product_cat', $page['innerBlocks'][0]['innerBlocks'][3]['attrs']['term'] );
		$this->assertSame( array( 'woocommerce/product-price', 'core/post-title' ), self::names( $page['innerBlocks'][1]['innerBlocks'][0] ), 'Related products are not the summary.' );
		$this->assertNullsMatch( $page );
	}

	public function test_blocks_with_their_own_inside_are_not_added(): void {
		$card = Items::arrange( self::card(), 'summary', array( 'title', 'price', 'buying', 'meta' ) );
		$this->assertNotContains( 'woocommerce/add-to-cart-with-options', self::names( $card ) );
		$this->assertNotContains( 'woocommerce/product-meta', self::names( $card ) );
	}

	public function test_keys_keep_the_known_items_once(): void {
		$this->assertSame( array( 'price', 'title' ), Items::keys( 'price,nope,title,price', Items::CARD ) );
		$this->assertSame( array(), Items::keys( '', Items::CARD ) );
		$this->assertSame( array(), Items::keys( null, Items::CARD ) );
	}
}
