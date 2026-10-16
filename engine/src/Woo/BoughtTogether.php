<?php
/**
 * Frequently bought together.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

use const Pergel\FILE;
use const Pergel\VERSION;

/**
 * The product with up to three of its cross-sells (Product data → Linked
 * products), each with a checkbox, and one button that adds the chosen
 * ones to the cart. The total follows the checkboxes.
 *
 * The script sends the items to the Store API in one batch, with the nonce
 * from a Store API response rather than from the page, so a cached page
 * still works; then the mini cart refreshes. Without the script the form
 * posts to the product page, which adds the items and goes to the cart.
 * A cross-sell with options, or one sold in another way, gets a link to
 * its page instead of a checkbox. The product itself, when it has options,
 * joins once they are chosen in the add-to-cart form above. No bundle
 * price: each item costs what it costs.
 *
 * The block (pergel/bought-together) is hooked after the add-to-cart form;
 * it draws nothing for a product the visitor cannot buy here (see Modes)
 * and nothing without a cross-sell to show.
 */
final class BoughtTogether {

	public const BLOCK = 'pergel/bought-together';

	/**
	 * Most cross-sells shown.
	 */
	public const LIMIT = 3;

	/**
	 * Fields of the form.
	 */
	public const FIELD_PRODUCT = 'pergel_together_product';
	public const FIELD_ITEMS   = 'pergel_together';

	/**
	 * Most variations a product with options carries in the page; with more,
	 * it does not join the bundle.
	 */
	private const MAX_VARIATIONS = 100;

	/**
	 * Handles of the style and the script module.
	 */
	private const ASSET = 'pergel-bought-together';

	/**
	 * Adds the hooks. Woo\Module calls it when the setting is on.
	 */
	public static function register(): void {
		add_filter( 'hooked_block_types', array( self::class, 'hook' ), 22, 4 );
		if ( ! is_admin() ) {
			// After WooCommerce has loaded the cart, before the page is drawn.
			add_action( 'wp_loaded', array( self::class, 'submit' ), 30 );
		}
	}

	/**
	 * Filter for hooked_block_types: the block after the add-to-cart form,
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
	 * The block's markup. The style and the script load only here.
	 *
	 * @param \WP_Block|null $block Block instance.
	 */
	public static function render( ?\WP_Block $block = null ): string {
		if ( wp_is_serving_rest_request() ) {
			return '<div ' . get_block_wrapper_attributes() . '><p>' . esc_html__( 'Shown on product pages with cross-sells: the product and up to three of them, added to the cart with one button.', 'pergel-engine' ) . '</p></div>';
		}
		$product = true === Settings::get( 'woo.bought_together' ) ? self::product( $block ) : null;
		$others  = $product instanceof \WC_Product ? self::others( $product ) : array();
		if ( ! $product instanceof \WC_Product || array() === $others ) {
			return '';
		}
		wp_enqueue_style( self::ASSET, plugins_url( 'blocks/bought-together/style.css', FILE ), array(), VERSION );
		wp_enqueue_script_module( self::ASSET, plugins_url( 'blocks/bought-together/view.js', FILE ), array(), VERSION, array( 'fetchpriority' => 'low' ) );

		$base       = 'pergel-together-' . $product->get_id();
		$variations = $product->is_type( 'variable' ) ? self::variations( $product ) : null;
		$items      = '';
		$total      = 0.0;
		$count      = 0;
		$addable    = 0;

		// The product itself: it joins unless it has too many options to carry.
		if ( ! $product->is_type( 'variable' ) || null !== $variations ) {
			$simple = ! $product->is_type( 'variable' );
			$price  = $simple ? (float) wc_get_price_to_display( $product ) : 0.0;
			if ( $simple ) {
				$total += $price;
				++$count;
			}
			++$addable;
			/* translators: %s: product name. */
			$label  = sprintf( __( 'This product: %s', 'pergel-engine' ), $product->get_name() );
			$items .= self::item( $product, $base, $label, $price, $simple, ! $simple, true );
		}
		foreach ( $others as $other ) {
			if ( self::addable( $other ) ) {
				$price  = (float) wc_get_price_to_display( $other );
				$total += $price;
				++$count;
				++$addable;
				$items .= self::item( $other, $base, $other->get_name(), $price, true, false, false );
			} else {
				$items .= self::link( $other );
			}
		}

		$config = array(
			'cart'       => rest_url( 'wc/store/v1/cart' ),
			'batch'      => rest_url( 'wc/store/v1/batch' ),
			'cartUrl'    => wc_get_cart_url(),
			'product'    => $product->get_id(),
			'variations' => $variations,
			'format'     => array(
				'decimals' => wc_get_price_decimals(),
				'point'    => wc_get_price_decimal_separator(),
				'thousand' => wc_get_price_thousand_separator(),
				'pattern'  => html_entity_decode( get_woocommerce_price_format(), ENT_QUOTES, 'UTF-8' ),
				'symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
			),
			'totals'     => self::counted( 'total' ),
			'added'      => self::counted( 'added' ),
			'none'       => __( 'Choose at least one product.', 'pergel-engine' ),
			'failed'     => __( 'The products could not be added to the cart. Please try again.', 'pergel-engine' ),
			'viewCart'   => __( 'View cart', 'pergel-engine' ),
		);

		return '<section ' . get_block_wrapper_attributes(
			array(
				'aria-labelledby'      => $base . '-title',
				'data-pergel-together' => (string) wp_json_encode( $config ),
			)
		) . '>'
			. '<h2 id="' . esc_attr( $base ) . '-title" class="wp-block-pergel-bought-together__title">' . esc_html__( 'Frequently bought together', 'pergel-engine' ) . '</h2>'
			. '<form class="wp-block-pergel-bought-together__form" method="post" action="' . esc_url( (string) get_permalink( $product->get_id() ) ) . '">'
			. '<input type="hidden" name="' . esc_attr( self::FIELD_PRODUCT ) . '" value="' . esc_attr( (string) $product->get_id() ) . '">'
			. '<ul class="wp-block-pergel-bought-together__items">' . $items . '</ul>'
			. ( $addable > 0
				? '<p class="wp-block-pergel-bought-together__total"><span class="wp-block-pergel-bought-together__count">' . esc_html( self::counted( 'total' )[ $count ] ?? '' ) . '</span> '
					. '<strong class="wp-block-pergel-bought-together__amount">' . Price::html( wc_price( $total ) ) . '</strong></p>'
					. '<button type="submit" class="wp-block-pergel-bought-together__button wp-element-button">' . esc_html__( 'Add selected to cart', 'pergel-engine' ) . '</button>'
					. '<p class="wp-block-pergel-bought-together__status" role="status"></p>'
				: '' )
			. '</form></section>';
	}

	/**
	 * A row with a checkbox.
	 *
	 * @param \WC_Product $product  Product.
	 * @param string      $base     Id prefix.
	 * @param string      $label    Label text.
	 * @param float       $price    Price shown, as the shop displays it.
	 * @param bool        $checked  Whether it starts chosen.
	 * @param bool        $waits    Whether it waits for options chosen above.
	 * @param bool        $current  Whether it is the product of the page.
	 */
	private static function item( \WC_Product $product, string $base, string $label, float $price, bool $checked, bool $waits, bool $current ): string {
		$id    = $base . '-' . $product->get_id();
		$image = $product->get_image(
			'woocommerce_gallery_thumbnail',
			array(
				'alt'     => '',
				'class'   => 'wp-block-pergel-bought-together__image',
				'loading' => 'lazy',
			)
		);
		$hint  = $waits ? '<span id="' . esc_attr( $id ) . '-hint" class="wp-block-pergel-bought-together__hint">' . esc_html__( 'Choose its options above first.', 'pergel-engine' ) . '</span>' : '';
		return '<li class="wp-block-pergel-bought-together__item' . ( $current ? ' is-current' : '' ) . '">'
			. '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::FIELD_ITEMS ) . '[]" value="' . esc_attr( (string) $product->get_id() ) . '" data-price="' . esc_attr( (string) $price ) . '"'
			. ( $checked ? ' checked' : '' ) . ( $waits ? ' disabled aria-describedby="' . esc_attr( $id ) . '-hint"' : '' ) . '>'
			. $image
			. '<span class="wp-block-pergel-bought-together__info">'
			. '<label for="' . esc_attr( $id ) . '" class="wp-block-pergel-bought-together__name">' . esc_html( $label ) . '</label>'
			. '<span class="wp-block-pergel-bought-together__price">' . Price::html( $product->get_price_html() ) . '</span>'
			. $hint
			. '</span></li>';
	}

	/**
	 * A row with a link to the product's page.
	 *
	 * @param \WC_Product $product Product.
	 */
	private static function link( \WC_Product $product ): string {
		$image = $product->get_image(
			'woocommerce_gallery_thumbnail',
			array(
				'alt'     => '',
				'class'   => 'wp-block-pergel-bought-together__image',
				'loading' => 'lazy',
			)
		);
		$text  = $product->is_type( 'variable' ) ? __( 'Choose its options on its page.', 'pergel-engine' ) : __( 'Details on its page.', 'pergel-engine' );
		return '<li class="wp-block-pergel-bought-together__item is-link">'
			. '<span class="wp-block-pergel-bought-together__spacer"></span>'
			. $image
			. '<span class="wp-block-pergel-bought-together__info">'
			. '<a class="wp-block-pergel-bought-together__name" href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a>'
			. '<span class="wp-block-pergel-bought-together__price">' . Price::html( $product->get_price_html() ) . '</span>'
			. '<span class="wp-block-pergel-bought-together__hint">' . esc_html( $text ) . '</span>'
			. '</span></li>';
	}

	/**
	 * Texts for 0 to LIMIT + 1 products.
	 *
	 * @param string $kind "total" or "added".
	 * @return array<int, string>
	 */
	private static function counted( string $kind ): array {
		$list = array();
		for ( $n = 0; $n <= self::LIMIT + 1; $n++ ) {
			$list[ $n ] = 'total' === $kind
				/* translators: %d: number of products. */
				? sprintf( _n( 'Total for %d product:', 'Total for %d products:', $n, 'pergel-engine' ), $n )
				/* translators: %d: number of products. */
				: sprintf( _n( '%d product added to your cart.', '%d products added to your cart.', $n, 'pergel-engine' ), $n );
		}
		return $list;
	}

	/**
	 * The product of the page: one in the block's context that the visitor
	 * can buy here.
	 *
	 * @param \WP_Block|null $block Block instance.
	 */
	public static function product( ?\WP_Block $block ): ?\WC_Product {
		$id      = $block instanceof \WP_Block && is_int( $block->context['postId'] ?? null ) ? $block->context['postId'] : (int) get_the_ID();
		$product = $id > 0 && 'product' === get_post_type( $id ) ? wc_get_product( $id ) : null;
		if ( ! $product instanceof \WC_Product || ! $product->is_type( array( 'simple', 'variable' ) ) ) {
			return null;
		}
		// Purchasable covers the sales modes: Selling turns it off where the product is hidden.
		return $product->is_purchasable() && $product->is_in_stock() ? $product : null;
	}

	/**
	 * Up to LIMIT cross-sells to show: visible and in stock.
	 *
	 * @param \WC_Product $product Product of the page.
	 * @return list<\WC_Product>
	 */
	public static function others( \WC_Product $product ): array {
		$list = array();
		foreach ( $product->get_cross_sell_ids() as $id ) {
			$other = wc_get_product( $id );
			if ( $other instanceof \WC_Product && $other->get_id() !== $product->get_id() && 'publish' === $other->get_status() && $other->is_visible() && $other->is_in_stock() ) {
				$list[] = $other;
			}
			if ( count( $list ) >= self::LIMIT ) {
				break;
			}
		}
		return $list;
	}

	/**
	 * Whether a cross-sell can be added with a checkbox: a simple product
	 * the visitor can buy here, sold one at a time or more.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function addable( \WC_Product $product ): bool {
		return $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() && $product->get_min_purchase_quantity() <= 1;
	}

	/**
	 * The variations the script matches the chosen options with: their
	 * attributes ('' for any value), price and whether they can be bought.
	 * Null when there are too many to carry.
	 *
	 * @param \WC_Product $product Variable product.
	 * @return list<array{attributes: array<string, string>, price: float, buyable: bool}>|null
	 */
	private static function variations( \WC_Product $product ): ?array {
		$children = $product->get_children();
		if ( count( $children ) > self::MAX_VARIATIONS ) {
			return null;
		}
		$list = array();
		foreach ( $children as $child ) {
			$variation = wc_get_product( $child );
			if ( ! $variation instanceof \WC_Product_Variation || 'publish' !== $variation->get_status() ) {
				continue;
			}
			$list[] = array(
				'attributes' => array_map( 'strval', $variation->get_variation_attributes() ),
				'price'      => (float) wc_get_price_to_display( $variation ),
				'buyable'    => $variation->is_purchasable() && $variation->is_in_stock() && '' !== $variation->get_price(),
			);
		}
		return $list;
	}

	/**
	 * The form without the script: adds the chosen items and goes to the
	 * cart. Like WooCommerce's own add-to-cart links it takes no nonce, so
	 * a cached page works; it only adds to the visitor's own cart.
	 */
	public static function submit(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- No nonce, as above.
		if ( ! isset( $_POST[ self::FIELD_PRODUCT ] ) || ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) {
			return;
		}
		$product = wc_get_product( absint( wp_unslash( $_POST[ self::FIELD_PRODUCT ] ) ) );
		$chosen  = isset( $_POST[ self::FIELD_ITEMS ] ) && is_array( $_POST[ self::FIELD_ITEMS ] ) ? array_map( 'absint', wp_unslash( $_POST[ self::FIELD_ITEMS ] ) ) : array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! $product instanceof \WC_Product || true !== Settings::get( 'woo.bought_together' ) ) {
			return;
		}
		$allowed = array();
		if ( $product->is_type( 'simple' ) && self::addable( $product ) ) {
			$allowed[] = $product->get_id();
		}
		foreach ( self::others( $product ) as $other ) {
			if ( self::addable( $other ) ) {
				$allowed[] = $other->get_id();
			}
		}
		$added = array();
		foreach ( array_values( array_intersect( $allowed, $chosen ) ) as $id ) {
			if ( false !== WC()->cart->add_to_cart( $id, 1 ) ) {
				$added[ $id ] = 1;
			}
		}
		if ( array() === $added ) {
			if ( array() === array_intersect( $allowed, $chosen ) ) {
				wc_add_notice( __( 'Choose at least one product.', 'pergel-engine' ), 'error' );
			}
			wp_safe_redirect( (string) get_permalink( $product->get_id() ) );
			exit;
		}
		wc_add_to_cart_message( $added, true );
		wp_safe_redirect( wc_get_cart_url() );
		exit;
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
