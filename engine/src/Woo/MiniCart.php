<?php
/**
 * The mini cart in the theme's headers.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

/**
 * Puts WooCommerce's mini cart block after the menu of Pergel's header
 * patterns with the Block Hooks API, so the headers stay free of shop
 * blocks on a site without WooCommerce. Every header pattern keeps its menu
 * in a flex row, where the cart lines up next to it.
 *
 * WooCommerce hooks the same block into every header pattern itself; in
 * Pergel's headers the decision is left to this class, so the setting, the
 * cart pages and the sales modes can keep it out.
 *
 * The block shows the count from the session, not from cart fragments, and
 * updates it when an item is added or removed.
 */
final class MiniCart {

	public const BLOCK = 'woocommerce/mini-cart';

	/**
	 * Adds the filter. It runs after WooCommerce's, which hooks at 9.
	 */
	public static function register(): void {
		add_filter( 'hooked_block_types', array( self::class, 'hook' ), 20, 4 );
	}

	/**
	 * Filter for hooked_block_types: the mini cart after the navigation of
	 * a Pergel header pattern, once, when the setting is on.
	 *
	 * @param mixed $hooked   Block types hooked at this position.
	 * @param mixed $position Relative position: before, after, first_child, last_child.
	 * @param mixed $anchor   Anchor block type; null for free HTML between blocks.
	 * @param mixed $context  Pattern data, template or post the blocks are in.
	 * @return mixed
	 */
	public static function hook( mixed $hooked, mixed $position, mixed $anchor, mixed $context ): mixed {
		if ( ! is_array( $hooked ) || 'after' !== $position || 'core/navigation' !== $anchor || ! self::is_header_pattern( $context ) ) {
			return $hooked;
		}
		$hooked = array_values( array_diff( $hooked, array( self::BLOCK ) ) );
		if ( true !== Settings::get( 'woo.mini_cart' ) || str_contains( (string) ( $context['content'] ?? '' ), 'wp:' . self::BLOCK ) ) {
			return $hooked;
		}
		// The cart and checkout pages show the cart already. There the block
		// prints its legacy markup, and its script module still loads and asks
		// the Store API for the cart without knowing the API's address.
		if ( is_cart() || is_checkout() ) {
			return $hooked;
		}
		// Nothing to put in it under a mode that sells nothing to the visitor.
		if ( Modes::nothing_sells() ) {
			return $hooked;
		}
		$hooked[] = self::BLOCK;
		return $hooked;
	}

	/**
	 * Whether the context is one of Pergel's header patterns.
	 *
	 * @param mixed $context Hook context.
	 * @phpstan-assert-if-true array<string, mixed> $context
	 */
	private static function is_header_pattern( mixed $context ): bool {
		return is_array( $context )
			&& str_starts_with( (string) ( $context['name'] ?? '' ), 'pergel/' )
			&& in_array( 'header', (array) ( $context['categories'] ?? array() ), true );
	}
}
