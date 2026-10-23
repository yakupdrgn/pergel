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
 * page, and its product scripts on every product. Only classic shop markup
 * uses them: account pages, WooCommerce shortcodes and the blocks that
 * print classic templates. The theme's shop templates are made of blocks
 * that bring their own files, so elsewhere these files are left out. The
 * legacy cart fragments script asks the server for the cart on each page
 * view; the mini cart block does not need it.
 */
final class Assets {

	/**
	 * Classic scripts, only used by classic shop markup. Their helpers
	 * (jQuery, blockUI, js-cookie) load only as their dependencies.
	 */
	private const SHOP_SCRIPTS = array( 'woocommerce', 'wc-add-to-cart', 'wc-add-to-cart-variation', 'wc-single-product' );

	/**
	 * Shortcodes that print classic shop markup.
	 */
	private const SHORTCODES = array( 'products', 'product', 'product_page', 'product_category', 'product_categories', 'add_to_cart', 'sale_products', 'best_selling_products', 'top_rated_products', 'featured_products', 'recent_products', 'shop_messages', 'woocommerce_cart', 'woocommerce_checkout', 'woocommerce_my_account', 'woocommerce_order_tracking' );

	/**
	 * Blocks that print classic shop markup.
	 */
	private const CLASSIC_BLOCKS = array( 'woocommerce/legacy-template', 'woocommerce/classic-shortcode', 'woocommerce/add-to-cart-form', 'woocommerce/product-image-gallery' );

	/**
	 * Filter for woocommerce_enqueue_styles: none without classic markup.
	 *
	 * @param mixed $styles Stylesheets WooCommerce is about to load.
	 * @return mixed
	 */
	public static function styles( mixed $styles ): mixed {
		return self::limited() && ! self::classic_request() ? array() : $styles;
	}

	/**
	 * Drops the classic files from pages without classic markup and the
	 * cart fragments script everywhere, as configured.
	 */
	public static function scripts(): void {
		$settings = Settings::all()['woo'];
		if ( true === $settings['no_cart_fragments'] ) {
			wp_dequeue_script( 'wc-cart-fragments' );
		}
		if ( ! self::limited() || self::classic_request() ) {
			return;
		}
		foreach ( self::SHOP_SCRIPTS as $handle ) {
			wp_dequeue_script( $handle );
		}
		wp_dequeue_style( 'woocommerce-inline' );
		// Block theme fixes for classic shop markup, all under .woocommerce.
		wp_dequeue_style( 'woocommerce-blocktheme' );
		self::icon_font();
	}

	/**
	 * WooCommerce's icon font for the blocks that draw stars with it (the
	 * rating of each review, for one): only the classic stylesheet declares
	 * it. The declaration alone downloads nothing; the browser fetches the
	 * font when a star is drawn.
	 */
	private static function icon_font(): void {
		if ( ! wp_style_is( 'wc-blocks-style', 'registered' ) || ! function_exists( 'WC' ) ) {
			return;
		}
		$fonts = WC()->plugin_url() . '/assets/fonts/WooCommerce';
		$css   = '';
		foreach ( array( 'WooCommerce', 'star' ) as $family ) {
			$css .= sprintf(
				'@font-face{font-family:%1$s;src:url("%2$s.woff2") format("woff2"),url("%2$s.woff") format("woff");font-weight:400;font-style:normal;font-display:block}',
				$family,
				esc_url( $fonts )
			);
		}
		wp_add_inline_style( 'wc-blocks-style', $css );
	}

	/**
	 * Whether the "classic files only where needed" setting is on.
	 */
	private static function limited(): bool {
		return true === Settings::all()['woo']['shop_assets_only'];
	}

	/**
	 * Whether this page shows classic shop markup: an account page, a
	 * WooCommerce shortcode, or a classic block in the post or the template.
	 */
	public static function classic_request(): bool {
		$classic = is_account_page();
		if ( ! $classic && is_singular() ) {
			$classic = self::has_classic( (string) get_post_field( 'post_content', get_queried_object_id() ) );
		}
		if ( ! $classic && ( is_woocommerce() || is_cart() || is_checkout() ) ) {
			global $_wp_current_template_content;
			$classic = ! wp_is_block_theme() || ! is_string( $_wp_current_template_content ) || self::has_classic( $_wp_current_template_content );
		}

		/**
		 * Filters whether WooCommerce's classic files load on this page.
		 *
		 * @param bool $classic True when the page shows classic shop markup.
		 */
		return (bool) apply_filters( 'pergel/woo/classic_files', $classic );
	}

	/**
	 * Whether block content holds a WooCommerce shortcode or classic block.
	 *
	 * @param string $content Post or template content.
	 */
	private static function has_classic( string $content ): bool {
		foreach ( self::CLASSIC_BLOCKS as $block ) {
			if ( has_block( $block, $content ) ) {
				return true;
			}
		}
		foreach ( self::SHORTCODES as $tag ) {
			if ( has_shortcode( $content, $tag ) ) {
				return true;
			}
		}
		return false;
	}
}
