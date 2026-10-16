<?php
/**
 * Sticky add-to-cart bar.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

/**
 * A bar at the bottom of the screen with the product's image, name, price
 * and button, shown while the add-to-cart form is scrolled out of view.
 *
 * The bar's button presses the form's own button, so the quantity, the
 * chosen options, the AJAX request and the mini cart stay WooCommerce's.
 * While the form cannot be sent (options not chosen yet), it scrolls to
 * the options and moves focus there instead. The price follows the chosen
 * variation. The block (pergel/sticky-add-to-cart) is hooked after the
 * add-to-cart form; it draws nothing for a product the visitor cannot buy
 * here (see Modes) and nothing without JavaScript.
 */
final class StickyCart {

	public const BLOCK = 'pergel/sticky-add-to-cart';

	/**
	 * Product types whose form has one button that adds one product.
	 */
	private const TYPES = array( 'simple', 'variable' );

	/**
	 * Adds the hooks. Woo\Module calls it when the setting is on.
	 */
	public static function register(): void {
		add_filter( 'hooked_block_types', array( self::class, 'hook' ), 22, 4 );
	}

	/**
	 * Filter for hooked_block_types: the bar after the add-to-cart form,
	 * not where it is placed by hand.
	 *
	 * @param mixed $hooked   Block types hooked at this position.
	 * @param mixed $position Relative position.
	 * @param mixed $anchor   Anchor block type.
	 * @param mixed $context  Pattern data, template or post the blocks are in.
	 * @return mixed
	 */
	public static function hook( mixed $hooked, mixed $position, mixed $anchor, mixed $context ): mixed {
		if ( ! is_array( $hooked ) || 'after' !== $position || 'woocommerce/add-to-cart-with-options' !== $anchor ) {
			return $hooked;
		}
		if ( ! str_contains( self::content( $context ), 'wp:' . self::BLOCK ) ) {
			$hooked[] = self::BLOCK;
		}
		return $hooked;
	}

	/**
	 * The block's markup: hidden until the script shows it. The editor gets
	 * a description, since the bar itself is fixed to the screen.
	 *
	 * @param \WP_Block|null $block Block instance.
	 */
	public static function render( ?\WP_Block $block = null ): string {
		if ( wp_is_serving_rest_request() ) {
			return '<div ' . get_block_wrapper_attributes() . '><p>' . esc_html__( 'Shown on product pages: a bar with the add-to-cart button while the form is out of view.', 'pergel-engine' ) . '</p></div>';
		}
		$product = true === Settings::get( 'woo.sticky_cart' ) ? self::product( $block ) : null;
		if ( ! $product instanceof \WC_Product ) {
			return '';
		}
		$name   = $product->get_name();
		$choose = $product->is_type( 'variable' ) ? __( 'Choose options', 'pergel-engine' ) : '';
		$image  = $product->get_image(
			'woocommerce_gallery_thumbnail',
			array(
				'alt'     => '',
				'class'   => 'wp-block-pergel-sticky-add-to-cart__image',
				'loading' => 'lazy',
			)
		);
		return '<div ' . get_block_wrapper_attributes(
			array(
				'data-pergel-sticky' => (string) $product->get_id(),
				'data-choose'        => $choose,
				'hidden'             => 'hidden',
			)
		) . '><div class="wp-block-pergel-sticky-add-to-cart__inner">'
			. $image
			. '<div class="wp-block-pergel-sticky-add-to-cart__info">'
			. '<p class="wp-block-pergel-sticky-add-to-cart__name">' . esc_html( $name ) . '</p>'
			. '<p class="wp-block-pergel-sticky-add-to-cart__price">' . Price::html( $product->get_price_html() ) . '</p>'
			. '</div>'
			. '<button type="button" class="wp-block-pergel-sticky-add-to-cart__button wp-element-button">' . esc_html( '' !== $choose ? $choose : $product->single_add_to_cart_text() ) . '</button>'
			. '</div></div>';
	}

	/**
	 * The product the bar is for: one in the block's context that the
	 * visitor can buy here, of a type with a single button.
	 *
	 * @param \WP_Block|null $block Block instance.
	 */
	public static function product( ?\WP_Block $block ): ?\WC_Product {
		$id      = $block instanceof \WP_Block && is_int( $block->context['postId'] ?? null ) ? $block->context['postId'] : (int) get_the_ID();
		$product = $id > 0 && 'product' === get_post_type( $id ) ? wc_get_product( $id ) : null;
		if ( ! $product instanceof \WC_Product || ! $product->is_type( self::TYPES ) ) {
			return null;
		}
		// Purchasable covers the sales modes: Selling turns it off where the product is hidden.
		return $product->is_purchasable() && $product->is_in_stock() ? $product : null;
	}

	/**
	 * Markup of the hook context: a pattern's content, a template's or a
	 * post's.
	 *
	 * @param mixed $context Hook context.
	 */
	private static function content( mixed $context ): string {
		if ( is_array( $context ) ) {
			return (string) ( $context['content'] ?? '' );
		}
		if ( $context instanceof \WP_Block_Template ) {
			return (string) $context->content;
		}
		return $context instanceof \WP_Post ? $context->post_content : '';
	}
}
