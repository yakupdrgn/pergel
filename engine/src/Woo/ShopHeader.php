<?php
/**
 * Banner and category strip above the products.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * The "Above the products" shop settings, shown after the listing's term
 * description (the shop page has the same block, empty):
 *
 * - a banner: a synced pattern, e.g. a campaign;
 * - a category strip: the main categories in the shop, the subcategories
 *   in a category, as links with pictures or as names.
 *
 * Both default to nothing.
 */
final class ShopHeader {

	/**
	 * Most categories in the strip.
	 */
	private const MOST = 24;

	/**
	 * Whether the banner is being drawn; a pattern that holds the shop
	 * template must not draw itself again.
	 *
	 * @var bool
	 */
	private static bool $drawing = false;

	/**
	 * Adds the banner and the strip after the term description.
	 *
	 * @param string $html Rendered term description; empty on the shop page.
	 */
	public static function render( string $html ): string {
		if ( self::$drawing || ! self::shop_or_category() ) {
			return $html;
		}
		$extra = self::banner() . self::strip();
		if ( '' === $extra ) {
			return $html;
		}
		Layout::style();
		return $html . $extra;
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
	 * The category links: pictures with names, or names only.
	 */
	public static function strip(): string {
		$style = Layout::get( 'shop.category_strip' );
		if ( 'images' !== $style && 'names' !== $style ) {
			return '';
		}
		$parent = is_product_category() ? get_queried_object_id() : 0;
		$terms  = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => $parent,
				'hide_empty' => true,
				'menu_order' => 'ASC',
				'number'     => self::MOST + 1,
			)
		);
		if ( ! is_array( $terms ) || ! $terms ) {
			return '';
		}
		$items   = '';
		$default = (int) get_option( 'default_product_cat', 0 );
		$shown   = 0;
		foreach ( $terms as $term ) {
			// "Uncategorized" stays out of the strip.
			if ( ! $term instanceof \WP_Term || $term->term_id === $default || $shown >= self::MOST ) {
				continue;
			}
			++$shown;
			$link = get_term_link( $term );
			if ( ! is_string( $link ) ) {
				continue;
			}
			$items .= sprintf(
				'<li><a class="pergel-category-strip__link" href="%1$s">%2$s<span class="pergel-category-strip__name">%3$s</span></a></li>',
				esc_url( $link ),
				'images' === $style ? self::picture( $term ) : '',
				esc_html( $term->name )
			);
		}
		if ( '' === $items ) {
			return '';
		}
		return sprintf(
			'<nav class="pergel-category-strip is-%1$s alignwide" aria-label="%2$s"><ul>%3$s</ul></nav>',
			esc_attr( $style ),
			esc_attr( 0 === $parent ? __( 'Product categories', 'pergel-engine' ) : __( 'Subcategories', 'pergel-engine' ) ),
			$items
		);
	}

	/**
	 * The category image, or its first letter on a plain circle.
	 *
	 * @param \WP_Term $term Category.
	 */
	private static function picture( \WP_Term $term ): string {
		$image = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
		$html  = $image > 0 ? wp_get_attachment_image(
			$image,
			'woocommerce_thumbnail',
			false,
			array(
				'class'   => 'pergel-category-strip__image',
				'alt'     => '',
				'loading' => 'lazy',
				'sizes'   => '6rem',
			)
		) : '';
		if ( '' !== $html ) {
			return $html;
		}
		return sprintf( '<span class="pergel-category-strip__image is-empty" aria-hidden="true">%s</span>', esc_html( mb_substr( $term->name, 0, 1 ) ) );
	}
}
