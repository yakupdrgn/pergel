<?php
/**
 * Boxes in the product summary.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

/**
 * Draws the pergel/product-box block, which the "What the summary shows"
 * list adds as its also, facts and trust items (Items):
 *
 * - also: "You may also like", a few similar products (Related::similar:
 *   upsells, then products of the same category with the nearest price),
 *   each with its picture, category, name, price and a round button that
 *   adds it to the cart, or opens it when it cannot be bought from there;
 * - facts: the product's facts in the rows of shop.facts_rows (categories,
 *   tags, brands, SKU, availability, product type, weight, dimensions);
 *   rows without a value are left out;
 * - trust: a chosen picture (e.g. payment card logos) and a line of text.
 *
 * The look settings (shop.box_*) arrive as --pergel-box-* custom
 * properties on each box. The class loads only while a box renders.
 */
final class ProductBoxes {

	use LookValues;

	/**
	 * What a box can show.
	 */
	public const BOXES = array( 'also', 'facts', 'trust' );

	/**
	 * Every row of the facts box.
	 */
	public const ROWS = array( 'category', 'tag', 'brand', 'sku', 'stock', 'type', 'weight', 'size' );

	/**
	 * The rows while the list keeps its default arrangement.
	 */
	public const FACTS = array( 'category', 'tag', 'brand', 'sku', 'stock' );

	/**
	 * The block's markup; nothing without a product or anything to show.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param \WP_Block            $block      Block instance.
	 */
	public static function render( array $attributes, \WP_Block $block ): string {
		$box     = in_array( $attributes['box'] ?? null, self::BOXES, true ) ? (string) $attributes['box'] : 'facts';
		$product = self::product( $block );
		if ( null === $product ) {
			return '';
		}
		$inside = match ( $box ) {
			'also'  => self::also( $product ),
			'trust' => self::trust(),
			default => self::facts( $product ),
		};
		if ( '' === $inside ) {
			return '';
		}
		return sprintf(
			'<div %1$s>%2$s</div>',
			get_block_wrapper_attributes(
				array(
					'class' => "is-$box",
					'style' => self::look(),
				)
			),
			$inside
		);
	}

	/**
	 * The product of the block: from its context, else the one on screen,
	 * else the one the editor previews.
	 *
	 * @param \WP_Block $block Block instance.
	 */
	private static function product( \WP_Block $block ): ?\WC_Product {
		$id = (int) ( $block->context['postId'] ?? 0 );
		if ( $id <= 0 ) {
			$id = is_singular( 'product' ) ? get_queried_object_id() : ( 'product' === get_post_type() ? (int) get_the_ID() : 0 );
		}
		$product = $id > 0 ? wc_get_product( $id ) : null;
		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * The look values that are set, as custom properties.
	 */
	private static function look(): string {
		$values = array(
			'bg'     => self::color( 'box_bg' ),
			'edge'   => self::color( 'box_edge' ),
			'radius' => self::px( 'box_radius' ),
			'pad'    => self::px( 'box_padding' ),
		);
		$style  = '';
		foreach ( $values as $name => $value ) {
			if ( '' !== $value ) {
				$style .= "--pergel-box-$name:$value;";
			}
		}
		return $style;
	}

	/**
	 * "You may also like": similar products.
	 *
	 * @param \WC_Product $product The product.
	 */
	private static function also( \WC_Product $product ): string {
		$count = Settings::get( 'shop.also_count' );
		$items = '';
		foreach ( Related::similar( $product, is_int( $count ) ? $count : 2 ) as $other ) {
			$items .= self::item( $other );
		}
		if ( '' === $items ) {
			return '';
		}
		$title = Settings::get( 'shop.also_title' );
		$id    = wp_unique_id( 'pergel-box-' );
		return sprintf(
			'<h2 class="pergel-box__title" id="%1$s">%2$s</h2><ul class="pergel-box__products" aria-labelledby="%1$s">%3$s</ul>',
			esc_attr( $id ),
			esc_html( is_string( $title ) && '' !== $title ? $title : __( 'You may also like', 'pergel-engine' ) ),
			$items
		);
	}

	/**
	 * One similar product.
	 *
	 * @param \WC_Product $product Product.
	 */
	private static function item( \WC_Product $product ): string {
		$link  = (string) get_permalink( $product->get_id() );
		$price = (string) $product->get_price_html();
		$term  = Layout::product_category( $product->get_id() );
		$term  = null !== $term && (int) get_option( 'default_product_cat', 0 ) !== $term->term_id ? $term : null;
		// Bought from the button when the product can be; else it opens.
		$cart = $product->is_purchasable() && $product->is_in_stock() && $product->supports( 'ajax_add_to_cart' );
		return '<li class="pergel-box__product">'
			. '<a class="pergel-box__image" href="' . esc_url( $link ) . '" tabindex="-1" aria-hidden="true">'
			. $product->get_image( 'woocommerce_gallery_thumbnail', array( 'loading' => 'lazy' ) )
			. '</a><div class="pergel-box__text">'
			. ( null === $term ? '' : '<span class="pergel-box__category">' . esc_html( $term->name ) . '</span>' )
			. '<a class="pergel-box__name" href="' . esc_url( $link ) . '">' . esc_html( $product->get_name() ) . '</a>'
			. '</div><div class="pergel-box__end">'
			. ( '' === $price ? '' : '<span class="pergel-box__price">' . wp_kses_post( $price ) . '</span>' )
			. sprintf(
				'<a class="pergel-box__button%1$s" href="%2$s" rel="nofollow">%3$s<span class="screen-reader-text">%4$s</span></a>',
				$cart ? ' is-cart' : '',
				esc_url( $cart ? $product->add_to_cart_url() : $link ),
				self::icon( $cart ),
				esc_html( $cart ? $product->add_to_cart_description() : $product->get_name() )
			)
			. '</div></li>';
	}

	/**
	 * The button's drawing, in currentColor: a cart, or an arrow.
	 *
	 * @param bool $cart Whether the button adds to the cart.
	 */
	private static function icon( bool $cart ): string {
		$path = $cart
			? '<path d="M3 4h2l2.4 10.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 8H6.2"/><circle cx="9" cy="19" r="1.4"/><circle cx="17" cy="19" r="1.4"/>'
			: '<path d="M5 12h14M13 6l6 6-6 6"/>';
		return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
	}

	/**
	 * The product's facts.
	 *
	 * @param \WC_Product $product The product.
	 */
	private static function facts( \WC_Product $product ): string {
		$value = Settings::get( 'shop.facts_rows' );
		$rows  = is_string( $value ) && '' !== $value ? array_intersect( explode( ',', $value ), self::ROWS ) : self::FACTS;
		$list  = '';
		foreach ( $rows as $row ) {
			$fact = self::fact( $product, $row );
			if ( null !== $fact ) {
				$list .= '<div class="pergel-box__fact"><dt>' . esc_html( $fact[0] ) . '</dt><dd>' . $fact[1] . '</dd></div>';
			}
		}
		return '' === $list ? '' : '<dl class="pergel-box__facts">' . $list . '</dl>';
	}

	/**
	 * One row of the facts: its label and its escaped value, or null when
	 * the product has none.
	 *
	 * @param \WC_Product $product The product.
	 * @param string      $row     Row.
	 * @return array{0: string, 1: string}|null
	 */
	private static function fact( \WC_Product $product, string $row ): ?array {
		$id = $product->get_id();
		switch ( $row ) {
			case 'category':
			case 'tag':
			case 'brand':
				$taxonomy = array(
					'category' => 'product_cat',
					'tag'      => 'product_tag',
					'brand'    => 'product_brand',
				)[ $row ];
				$terms    = taxonomy_exists( $taxonomy ) ? get_the_terms( $id, $taxonomy ) : false;
				if ( ! is_array( $terms ) || ! $terms ) {
					return null;
				}
				$links = array();
				foreach ( $terms as $term ) {
					$url     = get_term_link( $term );
					$links[] = is_string( $url ) ? '<a href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>' : esc_html( $term->name );
				}
				$count = count( $links );
				$label = match ( $row ) {
					'category' => _n( 'Category:', 'Categories:', $count, 'pergel-engine' ),
					'tag'      => _n( 'Tag:', 'Tags:', $count, 'pergel-engine' ),
					default    => _n( 'Brand:', 'Brands:', $count, 'pergel-engine' ),
				};
				return array( $label, implode( ', ', $links ) );

			case 'sku':
				$sku = (string) $product->get_sku();
				return '' === $sku ? null : array( __( 'SKU:', 'pergel-engine' ), esc_html( $sku ) );

			case 'stock':
				$text = (string) ( $product->get_availability()['availability'] ?? '' );
				if ( '' === $text ) {
					$text = (string) ( wc_get_product_stock_status_options()[ $product->get_stock_status() ] ?? '' );
				}
				return '' === $text ? null : array( __( 'Availability:', 'pergel-engine' ), esc_html( wp_strip_all_tags( $text ) ) );

			case 'type':
				$type = (string) ( wc_get_product_types()[ $product->get_type() ] ?? '' );
				return '' === $type ? null : array( __( 'Product type:', 'pergel-engine' ), esc_html( $type ) );

			case 'weight':
				return $product->has_weight() ? array( __( 'Weight:', 'pergel-engine' ), esc_html( wc_format_weight( $product->get_weight() ) ) ) : null;

			case 'size':
				return $product->has_dimensions() ? array( __( 'Dimensions:', 'pergel-engine' ), esc_html( wc_format_dimensions( $product->get_dimensions( false ) ) ) ) : null;
		}
		return null;
	}

	/**
	 * The chosen picture and the line of text.
	 */
	private static function trust(): string {
		$image   = Settings::get( 'shop.trust_image' );
		$text    = Settings::get( 'shop.trust_text' );
		$picture = is_int( $image ) && $image > 0
			? wp_get_attachment_image(
				$image,
				'medium',
				false,
				array(
					'class'   => 'pergel-box__picture',
					'loading' => 'lazy',
				)
			)
			: '';
		return $picture . '<p class="pergel-box__trust">' . esc_html( is_string( $text ) && '' !== $text ? $text : __( 'Guaranteed safe and secure checkout', 'pergel-engine' ) ) . '</p>';
	}
}
