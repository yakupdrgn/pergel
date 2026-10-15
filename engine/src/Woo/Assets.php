<?php
/**
 * WooCommerce files on the front end.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

/**
 * WooCommerce loads its classic styles and its jQuery scripts on every
 * page. Outside shop pages nothing uses them: the theme's blocks bring
 * their own files. The legacy cart fragments script asks the server for
 * the cart on each page view; the mini cart block does not need it.
 */
final class Assets {

	/**
	 * Classic scripts that are only used on shop pages. Their helpers
	 * (jQuery, blockUI, js-cookie) load only as their dependencies.
	 */
	private const SHOP_SCRIPTS = array( 'woocommerce', 'wc-add-to-cart', 'wc-add-to-cart-variation', 'wc-single-product' );

	/**
	 * Shortcodes that print classic shop markup on an ordinary page.
	 */
	private const SHORTCODES = array( 'products', 'product', 'product_page', 'product_category', 'product_categories', 'add_to_cart', 'sale_products', 'best_selling_products', 'top_rated_products', 'featured_products', 'recent_products', 'shop_messages', 'woocommerce_cart', 'woocommerce_checkout', 'woocommerce_my_account', 'woocommerce_order_tracking' );

	/**
	 * Filter for woocommerce_enqueue_styles: none outside shop pages.
	 *
	 * @param mixed $styles Stylesheets WooCommerce is about to load.
	 * @return mixed
	 */
	public static function styles( mixed $styles ): mixed {
		return self::limited() && ! self::shop_request() ? array() : $styles;
	}

	/**
	 * Drops the classic scripts outside shop pages and the cart fragments
	 * script everywhere, as configured.
	 */
	public static function scripts(): void {
		$settings = Settings::all()['woo'];
		if ( true === $settings['no_cart_fragments'] ) {
			wp_dequeue_script( 'wc-cart-fragments' );
		}
		if ( ! self::limited() || self::shop_request() ) {
			return;
		}
		foreach ( self::SHOP_SCRIPTS as $handle ) {
			wp_dequeue_script( $handle );
		}
		wp_dequeue_style( 'woocommerce-inline' );
		// Block theme fixes for classic shop markup, all under .woocommerce.
		wp_dequeue_style( 'woocommerce-blocktheme' );
	}

	/**
	 * Whether the "shop pages only" setting is on.
	 */
	private static function limited(): bool {
		return true === Settings::all()['woo']['shop_assets_only'];
	}

	/**
	 * Whether this page shows the shop: WooCommerce templates, cart,
	 * checkout, account, or a post with a WooCommerce shortcode.
	 */
	public static function shop_request(): bool {
		$shop = is_woocommerce() || is_cart() || is_checkout() || is_account_page();
		if ( ! $shop && is_singular() ) {
			$content = (string) get_post_field( 'post_content', get_queried_object_id() );
			foreach ( self::SHORTCODES as $tag ) {
				if ( has_shortcode( $content, $tag ) ) {
					$shop = true;
					break;
				}
			}
		}

		/**
		 * Filters whether WooCommerce's classic files load on this page.
		 *
		 * @param bool $shop True on shop pages.
		 */
		return (bool) apply_filters( 'pergel/woo/shop_request', $shop );
	}
}
