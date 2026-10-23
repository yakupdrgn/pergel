<?php
/**
 * Banner and category strip above the products.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

/**
 * The "Above the products" shop settings:
 *
 * - a banner: a synced pattern, e.g. a campaign;
 * - a category strip: the main categories in the shop, the subcategories
 *   in a category, as links with pictures or as names;
 * - with the flash sales on, a band for a running campaign (FlashSales).
 *
 * They go after the listing's term description (the shop page has the same
 * block, empty) or before the shop layout columns, whichever the template
 * draws first, so a template with the description under the products still
 * shows them above. Both default to nothing.
 */
final class ShopHeader {

	/**
	 * Whether the banner is being drawn; a pattern that holds the shop
	 * template must not draw itself again.
	 *
	 * @var bool
	 */
	private static bool $drawing = false;

	/**
	 * The page they were drawn on ("shop" or a category id), so they are
	 * drawn once.
	 *
	 * @var string
	 */
	private static string $drawn = '';

	/**
	 * Adds the banner and the strip after the term description.
	 *
	 * @param string $html Rendered term description; empty on the shop page.
	 */
	public static function render( string $html ): string {
		return $html . self::once();
	}

	/**
	 * Adds the banner and the strip before the shop layout columns.
	 *
	 * @param string $html Rendered columns.
	 */
	public static function before( string $html ): string {
		return self::once() . $html;
	}

	/**
	 * The banner and the strip, the first time they are asked for on this
	 * page; empty after that.
	 */
	private static function once(): string {
		if ( self::$drawing || ! self::shop_or_category() ) {
			return '';
		}
		$page = is_shop() ? 'shop' : (string) get_queried_object_id();
		if ( self::$drawn === $page ) {
			return '';
		}
		$band  = true === Settings::get( 'woo.flash_sales' ) && true === Settings::get( 'woo.sales_band' ) ? FlashSales::band() : '';
		$extra = $band . self::banner() . self::strip();
		if ( '' !== $extra ) {
			self::$drawn = $page;
			Layout::style();
		}
		return $extra;
	}

	/**
	 * Whether this is the shop page or a product category.
	 */
	private static function shop_or_category(): bool {
		return function_exists( 'is_shop' ) && ( is_shop() || is_product_category() );
	}

	/**
	 * The chosen pattern, drawn as blocks; nothing when it is gone or not
	 * published.
	 */
	public static function banner(): string {
		$id   = Layout::get( 'shop.banner' );
		$post = is_int( $id ) && $id > 0 ? get_post( $id ) : null;
		if ( ! $post instanceof \WP_Post || 'wp_block' !== $post->post_type || 'publish' !== $post->post_status ) {
			return '';
		}
		self::$drawing = true;
		$blocks        = do_blocks( $post->post_content );
		self::$drawing = false;
		if ( '' === trim( $blocks ) ) {
			return '';
		}
		return '<div class="pergel-shop-banner alignwide">' . $blocks . '</div>';
	}

	/**
	 * The category cards: the main categories in the shop, the
	 * subcategories in a category.
	 */
	public static function strip(): string {
		$style = Layout::get( 'shop.category_strip' );
		// "none" first: the default does not load CategoryCards.
		if ( ! is_string( $style ) || 'none' === $style || ! in_array( $style, CategoryCards::STYLES, true ) ) {
			return '';
		}
		$parent = is_product_category() ? get_queried_object_id() : 0;
		$items  = CategoryCards::items( CategoryCards::terms( $parent, CategoryCards::MOST ), $style );
		if ( '' === $items ) {
			return '';
		}
		wp_enqueue_style( CategoryCards::STYLE_HANDLE );
		return sprintf(
			'<nav class="pergel-category-strip pergel-category-cards is-%1$s alignwide" aria-label="%2$s"><ul>%3$s</ul></nav>',
			esc_attr( $style ),
			esc_attr( 0 === $parent ? __( 'Product categories', 'pergel-engine' ) : __( 'Subcategories', 'pergel-engine' ) ),
			$items
		);
	}
}
