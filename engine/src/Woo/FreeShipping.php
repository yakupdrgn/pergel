<?php
/**
 * Free shipping progress.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

use const Pergel\FILE;
use const Pergel\VERSION;

/**
 * How much more the customer needs to spend for free shipping, with a bar
 * that fills up: in the mini cart above the subtotal, above the cart and
 * the checkout, and under the add-to-cart form, each where its setting is
 * on.
 *
 * The amount is the minimum of the "Free shipping" method in the customer's
 * shipping zone (when it asks for a minimum order amount, alone or with a
 * coupon as either), else the shop setting. The cart total is the one that
 * method compares: the subtotal as displayed, less discounts unless the
 * method ignores them.
 *
 * The bars are drawn on the server. The same markup rides along in every
 * Store API cart response (extensions.pergel), so the cart script
 * (assets/cart-sync.js) only swaps it in when WooCommerce says the cart
 * changed: after an item is added, and whenever its cart stores ask each
 * other to sync (mini cart, cart and checkout blocks).
 */
final class FreeShipping {

	/**
	 * Handle of the style.
	 */
	public const STYLE = 'pergel-free-shipping';

	/**
	 * Adds the hooks. Woo\Module calls it while a bar is on.
	 */
	public static function register(): void {
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			self::extend();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( self::class, 'extend' ) );
		}
		if ( is_admin() ) {
			return;
		}
		add_action( 'init', array( self::class, 'assets' ) );
		if ( true === Settings::get( 'woo.shipping_mini' ) ) {
			add_filter( 'render_block_woocommerce/mini-cart-footer-block', array( self::class, 'mini' ) );
		}
		if ( true === Settings::get( 'woo.shipping_cart' ) ) {
			add_filter( 'render_block_woocommerce/cart', array( self::class, 'cart' ) );
		}
		if ( true === Settings::get( 'woo.shipping_checkout' ) ) {
			add_filter( 'render_block_woocommerce/checkout', array( self::class, 'checkout' ) );
		}
		if ( true === Settings::get( 'woo.shipping_product' ) ) {
			add_filter( 'render_block_woocommerce/add-to-cart-with-options', array( self::class, 'product' ), 10, 3 );
		}
	}

	/**
	 * Adds the bar's markup to the Store API cart.
	 */
	public static function extend(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'cart',
				'namespace'       => 'pergel',
				'data_callback'   => array( self::class, 'data' ),
				'schema_callback' => array( self::class, 'schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * The cart's extension data.
	 *
	 * @return array{free_shipping: array{html: string}}
	 */
	public static function data(): array {
		return array( 'free_shipping' => array( 'html' => self::inner() ) );
	}

	/**
	 * Schema of the extension data.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function schema(): array {
		return array(
			'free_shipping' => array(
				'description' => 'Free shipping progress, as markup.',
				'type'        => 'object',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'properties'  => array(
					'html' => array(
						'description' => 'Contents of each bar; empty when there is no free shipping amount.',
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
				),
			),
		);
	}

	/**
	 * The bar in the mini cart, above the subtotal: it stays in view while
	 * the items scroll.
	 *
	 * @param string $html Rendered footer.
	 */
	public static function mini( string $html ): string {
		return self::bar( 'mini' ) . $html;
	}

	/**
	 * The bar above the cart.
	 *
	 * @param string $html Rendered cart block.
	 */
	public static function cart( string $html ): string {
		return self::bar( 'cart' ) . $html;
	}

	/**
	 * The bar above the checkout.
	 *
	 * @param string $html Rendered checkout block.
	 */
	public static function checkout( string $html ): string {
		return self::bar( 'checkout' ) . $html;
	}

	/**
	 * The bar under the add-to-cart form of the product on screen, when the
	 * visitor can buy it here.
	 *
	 * @param string               $html     Rendered form.
	 * @param array<string, mixed> $block    Parsed block.
	 * @param \WP_Block|null       $instance Block instance.
	 */
	public static function product( string $html, array $block = array(), ?\WP_Block $instance = null ): string {
		$id = $instance instanceof \WP_Block && is_int( $instance->context['postId'] ?? null ) ? $instance->context['postId'] : 0;
		if ( '' === trim( $html ) || ! is_singular( 'product' ) || get_queried_object_id() !== $id ) {
			return $html;
		}
		$product = wc_get_product( $id );
		if ( ! $product instanceof \WC_Product || ! $product->is_purchasable() || $product->is_virtual() || Modes::hidden( $product ) ) {
			return $html;
		}
		return $html . self::bar( 'product' );
	}

	/**
	 * A bar with its place, or nothing without a free shipping amount.
	 *
	 * @param string $place mini, cart, product or checkout.
	 */
	public static function bar( string $place ): string {
		$inner = self::inner();
		if ( '' === $inner ) {
			return '';
		}
		wp_enqueue_style( self::STYLE );
		wp_enqueue_script_module( Layout::CART_SCRIPT );
		return '<div class="pergel-free-shipping is-' . esc_attr( $place ) . '" data-pergel-shipping aria-live="polite">' . $inner . '</div>';
	}

	/**
	 * The text and the bar for the cart as it is now; '' when there is no
	 * free shipping amount or no cart.
	 */
	public static function inner(): string {
		$cart = function_exists( 'WC' ) ? WC()->cart : null;
		if ( ! $cart instanceof \WC_Cart || Modes::nothing_sells() ) {
			return '';
		}
		[ $minimum, $ignore_discounts ] = self::minimum( $cart );
		if ( $minimum <= 0 ) {
			return '';
		}
		$total = (float) $cart->get_displayed_subtotal();
		if ( ! $ignore_discounts ) {
			$total -= (float) $cart->get_discount_total();
			if ( $cart->display_prices_including_tax() ) {
				$total -= (float) $cart->get_discount_tax();
			}
		}
		$total = round( $total, wc_get_price_decimals() );
		$share = (int) min( 100, max( 0, floor( $total / $minimum * 100 ) ) );
		if ( $total >= $minimum ) {
			$text = esc_html__( 'Your order ships free.', 'pergel-engine' );
		} elseif ( $cart->is_empty() ) {
			/* translators: %s: amount, e.g. ₺500.00. */
			$text = sprintf( esc_html__( 'Free shipping on orders of %s or more.', 'pergel-engine' ), Price::html( wc_price( $minimum ) ) );
		} else {
			/* translators: %s: amount still to spend, e.g. ₺120.00. */
			$text = sprintf( esc_html__( 'Add %s more to get free shipping.', 'pergel-engine' ), '<strong>' . Price::html( wc_price( $minimum - $total ) ) . '</strong>' );
		}
		return '<p class="pergel-free-shipping__text">' . $text . '</p>'
			. '<div class="pergel-free-shipping__track' . ( $share >= 100 ? ' is-full' : '' ) . '" aria-hidden="true"><span class="pergel-free-shipping__fill" style="width:' . $share . '%"></span></div>';
	}

	/**
	 * The free shipping amount for the cart and whether discounts count
	 * against it: the lowest minimum among the "Free shipping" methods of
	 * the customer's zone, else the shop setting.
	 *
	 * @param \WC_Cart $cart Cart.
	 * @return array{0: float, 1: bool}
	 */
	public static function minimum( \WC_Cart $cart ): array {
		$packages = $cart->get_shipping_packages();
		$package  = is_array( $packages ) ? reset( $packages ) : false;
		$found    = null;
		$ignore   = false;
		if ( is_array( $package ) && class_exists( 'WC_Shipping_Zones' ) ) {
			$zone = \WC_Shipping_Zones::get_zone_matching_package( $package );
			foreach ( $zone->get_shipping_methods( true ) as $method ) {
				if ( ! $method instanceof \WC_Shipping_Free_Shipping || ! in_array( $method->requires, array( 'min_amount', 'either' ), true ) ) {
					continue;
				}
				$amount = (float) wc_format_decimal( (string) $method->min_amount );
				if ( $amount > 0 && ( null === $found || $amount < $found ) ) {
					$found  = $amount;
					$ignore = 'yes' === $method->ignore_discounts;
				}
			}
		}
		if ( null !== $found ) {
			return array( $found, $ignore );
		}
		$setting = Settings::get( 'woo.shipping_min' );
		return array( is_int( $setting ) ? (float) $setting : 0.0, false );
	}

	/**
	 * Registers the style. Hooked on init.
	 */
	public static function assets(): void {
		wp_register_style( self::STYLE, plugins_url( 'assets/free-shipping.css', FILE ), array(), VERSION );
		wp_style_add_data( self::STYLE, 'path', dirname( FILE ) . '/assets/free-shipping.css' );
	}
}
