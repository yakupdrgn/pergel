<?php
/**
 * What product cards and the product summary show, and in which order.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

/**
 * The "What a card shows" and "What the summary shows" settings: a list of
 * items in order, with the items left out hidden. The blocks themselves
 * are moved (render_block_data), so screen readers and the keyboard meet
 * them in the order they are seen.
 *
 * Templates group their blocks in different ways (some put the name and
 * price in a group beside the picture), so the items are ordered inside
 * the group that holds most of them; an item in another group keeps its
 * group, and is hidden there when left out. A ticked item the template
 * lacks is added to that group, if its block draws itself.
 *
 * Cards and ProductPage add the filters only while a list is set.
 */
final class Items {

	/**
	 * Card items and the blocks that are them; the first block is the one
	 * added when the template lacks the item.
	 */
	public const CARD = array(
		'image'   => array( 'woocommerce/product-image' ),
		'title'   => array( 'core/post-title' ),
		'rating'  => array( 'woocommerce/product-rating' ),
		'price'   => array( 'woocommerce/product-price' ),
		'excerpt' => array( 'woocommerce/product-summary', 'core/post-excerpt' ),
		'sku'     => array( 'woocommerce/product-sku' ),
		'stock'   => array( 'woocommerce/product-stock-indicator' ),
		'button'  => array( 'woocommerce/product-button' ),
		'actions' => array( 'pergel/product-actions' ),
	);

	/**
	 * Summary items beside the product pictures.
	 */
	public const SUMMARY = array(
		'category' => array( 'core/post-terms' ),
		'title'    => array( 'core/post-title' ),
		'rating'   => array( 'woocommerce/product-rating' ),
		'price'    => array( 'woocommerce/product-price' ),
		'excerpt'  => array( 'core/post-excerpt', 'woocommerce/product-summary' ),
		'sku'      => array( 'woocommerce/product-sku' ),
		'stock'    => array( 'woocommerce/product-stock-indicator' ),
		'buying'   => array( 'woocommerce/add-to-cart-with-options', 'woocommerce/add-to-cart-form' ),
		'cta'      => array( 'pergel/product-cta' ),
		'meta'     => array( 'woocommerce/product-meta' ),
		'also'     => array( 'pergel/product-box' ),
		'facts'    => array( 'pergel/product-box' ),
		'trust'    => array( 'pergel/product-box' ),
	);

	/**
	 * Items whose block can be added on its own: the others hold blocks
	 * of their own (the buying form, the meta group).
	 */
	public const ADDABLE = array( 'image', 'title', 'rating', 'price', 'excerpt', 'sku', 'stock', 'button', 'actions', 'category', 'cta', 'also', 'facts', 'trust' );

	/**
	 * Blocks whose insides belong to something else: other product grids
	 * and template parts.
	 */
	private const OTHERS = array( 'woocommerce/product-collection', 'woocommerce/product-template', 'core/query', 'core/template-part' );

	/**
	 * Set once the summary is arranged on this page.
	 *
	 * @var bool
	 */
	private static bool $summary = false;

	/**
	 * Arranges each card of a product grid. Hooked on render_block_data.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return array<string, mixed>
	 */
	public static function cards( array $block ): array {
		if ( 'woocommerce/product-template' !== ( $block['blockName'] ?? null ) ) {
			return $block;
		}
		$order = self::keys( Settings::get( 'shop.card_items' ), self::CARD );
		return $order ? self::arrange( $block, 'card', $order ) : $block;
	}

	/**
	 * Arranges the summary beside the product pictures, once a page: in the
	 * first block that holds two summary items or more. Hooked on
	 * render_block_data.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return array<string, mixed>
	 */
	public static function summary( array $block ): array {
		if ( self::$summary || in_array( $block['blockName'] ?? null, self::OTHERS, true ) ) {
			return $block;
		}
		$order = self::keys( Settings::get( 'shop.summary_items' ), self::SUMMARY );
		if ( ! $order || self::most( $block, 'summary' )[1] < 2 ) {
			return $block;
		}
		self::$summary = true;
		return self::arrange( $block, 'summary', $order );
	}

	/**
	 * Lets the summary be arranged again, for tests.
	 */
	public static function flush(): void {
		self::$summary = false;
	}

	/**
	 * The items of a setting value that are known, in order.
	 *
	 * @param mixed                       $value Comma-separated item keys.
	 * @param array<string, list<string>> $map   Item map.
	 * @return list<string>
	 */
	public static function keys( mixed $value, array $map ): array {
		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}
		return array_values( array_unique( array_intersect( explode( ',', $value ), array_keys( $map ) ) ) );
	}

	/**
	 * The items a template shows, in the order it shows them; read for the
	 * settings screen.
	 *
	 * @param string $kind card or summary.
	 * @return list<string>
	 */
	public static function in_template( string $kind ): array {
		$template = get_block_template( get_stylesheet() . '//' . ( 'card' === $kind ? 'archive-product' : 'single-product' ) );
		$blocks   = $template ? parse_blocks( (string) $template->content ) : array();
		$root     = array(
			'blockName'   => null,
			'innerBlocks' => $blocks,
		);
		if ( 'card' === $kind ) {
			$grid = self::find( $root, 'woocommerce/product-template' );
			return null === $grid ? array() : self::found( $grid, $kind, true );
		}
		return self::found( $root, $kind, false );
	}

	/**
	 * Hides the items left out, orders the rest in the group that holds
	 * most of them and adds the ticked items the template lacks.
	 *
	 * @param array<string, mixed> $block Parsed block: a card template or a
	 *                                    block holding the summary.
	 * @param string               $kind  card or summary.
	 * @param array                $order Items to show, in order.
	 * @phpstan-param list<string> $order
	 * @return array<string, mixed>
	 */
	public static function arrange( array $block, string $kind, array $order ): array {
		$block   = self::hide( $block, $kind, $order, true );
		$missing = array_values( array_diff( array_intersect( $order, self::ADDABLE ), self::found( $block, $kind, true ) ) );
		$path    = self::most( $block, $kind )[0];
		return self::at( $block, $path, static fn ( array $group ): array => self::order( $group, $kind, $order, $missing ) );
	}

	/**
	 * The item a block is, or null.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @param string               $kind  card or summary.
	 */
	private static function key( array $block, string $kind ): ?string {
		$name = $block['blockName'] ?? null;
		foreach ( 'card' === $kind ? self::CARD : self::SUMMARY as $key => $names ) {
			if ( 'pergel/product-box' === $name && ( $block['attrs']['box'] ?? 'facts' ) !== $key ) {
				// A summary box is the item it shows.
				continue;
			}
			if ( in_array( $name, $names, true ) ) {
				// Only the category list is the category item.
				return 'core/post-terms' === $name && 'product_cat' !== ( $block['attrs']['term'] ?? null ) ? null : $key;
			}
		}
		return null;
	}

	/**
	 * Whether the inside of a block is searched: not other grids, and in a
	 * card not other cards.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @param bool                 $root  Whether it is the block arranged.
	 */
	private static function inside( array $block, bool $root ): bool {
		return $root || ! in_array( $block['blockName'] ?? null, self::OTHERS, true );
	}

	/**
	 * The items found in a block, in order.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @param string               $kind  card or summary.
	 * @param bool                 $root  Whether it is the block arranged.
	 * @return list<string>
	 */
	private static function found( array $block, string $kind, bool $root ): array {
		$keys = array();
		if ( ! self::inside( $block, $root ) ) {
			return $keys;
		}
		foreach ( $block['innerBlocks'] ?? array() as $child ) {
			$key = self::key( $child, $kind );
			if ( null !== $key ) {
				$keys[] = $key;
			} else {
				$keys = array_merge( $keys, self::found( $child, $kind, false ) );
			}
		}
		return array_values( array_unique( $keys ) );
	}

	/**
	 * The first block of a name, depth first.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @param string               $name  Block name.
	 * @return array<string, mixed>|null
	 */
	private static function find( array $block, string $name ): ?array {
		foreach ( $block['innerBlocks'] ?? array() as $child ) {
			if ( ( $child['blockName'] ?? null ) === $name ) {
				return $child;
			}
			$found = self::find( $child, $name );
			if ( null !== $found ) {
				return $found;
			}
		}
		return null;
	}

	/**
	 * The group with most items as its own children: its path of child
	 * indexes from the block, and the number of items.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @param string               $kind  card or summary.
	 * @param bool                 $root  Whether it is the block arranged.
	 * @return array{0: list<int>, 1: int}
	 */
	private static function most( array $block, string $kind, bool $root = true ): array {
		$best = array( array(), 0 );
		if ( ! self::inside( $block, $root ) ) {
			return $best;
		}
		foreach ( $block['innerBlocks'] ?? array() as $child ) {
			if ( null !== self::key( $child, $kind ) ) {
				++$best[1];
			}
		}
		foreach ( $block['innerBlocks'] ?? array() as $index => $child ) {
			if ( null !== self::key( $child, $kind ) ) {
				continue;
			}
			$inner = self::most( $child, $kind, false );
			if ( $inner[1] > $best[1] ) {
				$best = array( array_merge( array( (int) $index ), $inner[0] ), $inner[1] );
			}
		}
		return $best;
	}

	/**
	 * Runs a change on the block at a path.
	 *
	 * @param array<string, mixed> $block  Parsed block.
	 * @param array                $path   Child indexes.
	 * @phpstan-param list<int> $path
	 * @param callable             $change The change.
	 * @phpstan-param callable(array<string, mixed>): array<string, mixed> $change
	 * @return array<string, mixed>
	 */
	private static function at( array $block, array $path, callable $change ): array {
		if ( ! $path ) {
			return $change( $block );
		}
		$index                          = array_shift( $path );
		$block['innerBlocks'][ $index ] = self::at( $block['innerBlocks'][ $index ], $path, $change );
		return $block;
	}

	/**
	 * Takes out the items left out of the list, wherever they are.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @param string               $kind  card or summary.
	 * @param array                $order Items to show.
	 * @phpstan-param list<string> $order
	 * @param bool                 $root  Whether it is the block arranged.
	 * @return array<string, mixed>
	 */
	private static function hide( array $block, string $kind, array $order, bool $root ): array {
		if ( ! self::inside( $block, $root ) ) {
			return $block;
		}
		$children = array();
		$content  = array();
		$nth      = 0;
		foreach ( $block['innerContent'] ?? array() as $part ) {
			if ( null !== $part ) {
				$content[] = $part;
				continue;
			}
			$child = $block['innerBlocks'][ $nth++ ] ?? null;
			if ( ! is_array( $child ) ) {
				continue;
			}
			$key = self::key( $child, $kind );
			if ( null !== $key && ! in_array( $key, $order, true ) ) {
				continue;
			}
			$children[] = null === $key ? self::hide( $child, $kind, $order, false ) : $child;
			$content[]  = null;
		}
		$block['innerBlocks']  = $children;
		$block['innerContent'] = $content;
		return $block;
	}

	/**
	 * Puts a group's items in order and adds the missing ones after the item
	 * before them in the list.
	 *
	 * @param array<string, mixed> $group   Parsed block.
	 * @param string               $kind    card or summary.
	 * @param array                $order   Items to show, in order.
	 * @phpstan-param list<string> $order
	 * @param array                $missing Items to add.
	 * @phpstan-param list<string> $missing
	 * @return array<string, mixed>
	 */
	private static function order( array $group, string $kind, array $order, array $missing ): array {
		$children = $group['innerBlocks'] ?? array();
		$slots    = array();
		$items    = array();
		foreach ( $children as $index => $child ) {
			$key = self::key( $child, $kind );
			if ( null !== $key ) {
				$slots[]         = $index;
				$items[ $key ][] = $child;
			}
		}
		$sorted = array();
		foreach ( $order as $key ) {
			array_push( $sorted, ...( $items[ $key ] ?? array() ) );
		}
		foreach ( $slots as $n => $index ) {
			$children[ $index ] = $sorted[ $n ];
		}
		$group['innerBlocks'] = $children;

		foreach ( $missing as $key ) {
			$at = self::place( $group, $kind, $order, $key, $slots );
			array_splice( $group['innerBlocks'], $at, 0, array( self::block( $key, $kind ) ) );
			$group['innerContent'] = self::room( $group['innerContent'] ?? array(), $at );
		}
		return $group;
	}

	/**
	 * Where a missing item goes: after the last child that comes before it
	 * in the list, else before the first item, else at the end.
	 *
	 * @param array<string, mixed> $group Parsed block.
	 * @param string               $kind  card or summary.
	 * @param array                $order Items to show, in order.
	 * @phpstan-param list<string> $order
	 * @param string               $key   The missing item.
	 * @param array                $slots Places of the group's items.
	 * @phpstan-param list<int> $slots
	 */
	private static function place( array $group, string $kind, array $order, string $key, array $slots ): int {
		$before   = array_slice( $order, 0, (int) array_search( $key, $order, true ) );
		$at       = null;
		$children = array_values( $group['innerBlocks'] );
		foreach ( $children as $index => $child ) {
			if ( in_array( self::key( $child, $kind ), $before, true ) ) {
				$at = $index + 1;
			}
		}
		return $at ?? ( $slots ? $slots[0] : count( $children ) );
	}

	/**
	 * Makes room in innerContent for a new child at a place: each child is
	 * a null there, in order.
	 *
	 * @param array $content innerContent.
	 * @phpstan-param list<string|null> $content
	 * @param int   $at      Place of the new child.
	 * @return list<string|null>
	 */
	private static function room( array $content, int $at ): array {
		$nulls = array_keys( $content, null, true );
		if ( isset( $nulls[ $at ] ) ) {
			$where = $nulls[ $at ];
		} elseif ( $nulls ) {
			$where = end( $nulls ) + 1;
		} else {
			// No child yet: before the closing markup, if there is any.
			$where = count( $content ) > 1 ? count( $content ) - 1 : count( $content );
		}
		array_splice( $content, $where, 0, array( null ) );
		return $content;
	}

	/**
	 * A new block for an item.
	 *
	 * @param string $key  Item.
	 * @param string $kind card or summary.
	 * @return array<string, mixed>
	 */
	private static function block( string $key, string $kind ): array {
		$name  = ( 'card' === $kind ? self::CARD : self::SUMMARY )[ $key ][0];
		$attrs = array();
		if ( str_starts_with( $name, 'woocommerce/' ) ) {
			$attrs = 'card' === $kind ? array( 'isDescendentOfQueryLoop' => true ) : array( 'isDescendentOfSingleProductTemplate' => true );
		} elseif ( 'core/post-title' === $name ) {
			$attrs = 'card' === $kind ? array(
				'level'  => 3,
				'isLink' => true,
			) : array( 'level' => 1 );
		} elseif ( 'core/post-terms' === $name ) {
			$attrs = array( 'term' => 'product_cat' );
		} elseif ( 'pergel/product-box' === $name ) {
			$attrs = array( 'box' => $key );
		}
		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}
}
