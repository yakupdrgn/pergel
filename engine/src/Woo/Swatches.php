<?php
/**
 * Product options in the theme's look.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use const Pergel\FILE;
use const Pergel\VERSION;

/**
 * Styles the option buttons and colour swatches of the variation selector
 * in the add-to-cart form. The data is WooCommerce's own: an attribute with
 * the "Color / image" type keeps a colour or an image on each term, and
 * WooCommerce draws the swatches. This class only adds CSS, and only on
 * pages that show the selector. Woo\Module calls it when the setting is on.
 */
final class Swatches {

	public const STYLE = 'pergel-swatches';

	public const SELECTOR = 'woocommerce/add-to-cart-with-options-variation-selector';

	/**
	 * Adds the hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_style' ) );
		add_filter( 'render_block_' . self::SELECTOR, array( self::class, 'render' ) );
	}

	/**
	 * Registers the CSS (core inlines it, it is small). Hooked on init.
	 */
	public static function register_style(): void {
		wp_register_style( self::STYLE, plugins_url( 'assets/swatches.css', FILE ), array(), VERSION );
		wp_style_add_data( self::STYLE, 'path', dirname( FILE ) . '/assets/swatches.css' );
	}

	/**
	 * Loads the CSS where the variation selector is drawn.
	 *
	 * @param mixed $content Rendered selector.
	 * @return mixed
	 */
	public static function render( mixed $content ): mixed {
		if ( is_string( $content ) && '' !== $content ) {
			wp_enqueue_style( self::STYLE );
		}
		return $content;
	}
}
