<?php
/**
 * Fixes to the markup of WooCommerce blocks.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * Two gaps in the markup of the product form, found with axe:
 *
 * - The variation selector is a list, but its attributes are not marked as
 *   list items, so screen readers announce an empty list.
 * - The add to cart button of a variable product only looks disabled until
 *   a variation is chosen. It is marked as disabled too, bound to the same
 *   state that greys it out.
 */
final class Markup {

	/**
	 * Hooks the block filters.
	 */
	public static function register(): void {
		add_filter( 'render_block_woocommerce/add-to-cart-with-options-variation-selector-attribute', array( self::class, 'list_item' ) );
		add_filter( 'render_block_woocommerce/product-button', array( self::class, 'disabled_button' ) );
	}

	/**
	 * Marks a variation attribute as an item of the selector's list.
	 *
	 * @param mixed $html Block markup.
	 * @return mixed
	 */
	public static function list_item( mixed $html ): mixed {
		if ( ! is_string( $html ) ) {
			return $html;
		}
		$tags = new \WP_HTML_Tag_Processor( $html );
		if ( $tags->next_tag() && null === $tags->get_attribute( 'role' ) ) {
			$tags->set_attribute( 'role', 'listitem' );
		}
		return $tags->get_updated_html();
	}

	/**
	 * Marks a button that the form greys out as disabled for screen readers.
	 *
	 * @param mixed $html Block markup.
	 * @return mixed
	 */
	public static function disabled_button( mixed $html ): mixed {
		if ( ! is_string( $html ) || ! str_contains( $html, 'data-wp-class--disabled' ) ) {
			return $html;
		}
		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag( array( 'tag_name' => 'button' ) ) ) {
			$state = $tags->get_attribute( 'data-wp-class--disabled' );
			if ( ! is_string( $state ) || null !== $tags->get_attribute( 'data-wp-bind--aria-disabled' ) ) {
				continue;
			}
			$tags->set_attribute( 'data-wp-bind--aria-disabled', $state );
			$tags->set_attribute( 'aria-disabled', true === $tags->has_class( 'disabled' ) ? 'true' : 'false' );
		}
		return $tags->get_updated_html();
	}
}
