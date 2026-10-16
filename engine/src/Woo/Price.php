<?php
/**
 * Price markup.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * WooCommerce's price markup, made safe without losing its parts:
 * wp_kses_post() drops the <bdi> that keeps the amount and the currency
 * symbol together in right-to-left text, and the translate="no" that stops
 * page translators from changing the symbol.
 */
final class Price {

	/**
	 * Price HTML with only the tags and attributes a post may hold, plus
	 * those two.
	 *
	 * @param string $html Price HTML, as from WC_Product::get_price_html() or wc_price().
	 */
	public static function html( string $html ): string {
		$tags         = wp_kses_allowed_html( 'post' );
		$tags['bdi']  = array();
		$tags['span'] = ( $tags['span'] ?? array() ) + array( 'translate' => true );
		return wp_kses( $html, $tags );
	}
}
