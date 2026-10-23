<?php
/**
 * Markup of the wishlist and compare blocks.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * Draws the three list blocks: the buttons on a product, the list page and
 * the header links. Prices come from the product's price HTML, which the
 * sales modes filter, so a list never shows a price the shop hides.
 */
final class ListView {

	/**
	 * Icons drawn for the engine, 24×24, stroked with the text colour.
	 */
	private const ICONS = array(
		'wishlist' => '<path d="M12 20s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.1a4.3 4.3 0 0 1 7.5 2.7C19.5 15.4 12 20 12 20Z"/>',
		'compare'  => '<path d="M7 4v14m-3.5-3.5L7 18l3.5-3.5M17 20V6m-3.5 3.5L17 6l3.5 3.5"/>',
	);

	/**
	 * The pergel/product-actions block: a wishlist and a compare button
	 * for the product in context. Hidden until the script marks which lists
	 * hold the product.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param \WP_Block|null       $block      Block instance.
	 */
	public static function actions( array $attributes, ?\WP_Block $block = null ): string {
		$kinds   = Lists::enabled();
		$product = Lists::product( $block );
		if ( array() === $kinds || ! $product instanceof \WC_Product ) {
			return self::placeholder( __( 'Shown on products: buttons that add the product to the wishlist or to compare.', 'pergel-engine' ) );
		}
		Lists::enqueue();
		$labels = true === ( $attributes['labels'] ?? false );
		$name   = $product->get_name();
		$html   = '';
		foreach ( $kinds as $kind ) {
			$text  = 'compare' === $kind ? __( 'Compare', 'pergel-engine' ) : __( 'Wishlist', 'pergel-engine' );
			$label = $labels
				? '<span>' . esc_html( $text ) . '</span>'
				/* translators: 1: Wishlist or Compare, 2: product name. */
				: '<span class="pergel-sr">' . esc_html( sprintf( _x( '%1$s: %2$s', 'list button', 'pergel-engine' ), $text, $name ) ) . '</span>';
			$html .= '<button type="button" class="wp-block-pergel-product-actions__button is-' . esc_attr( $kind ) . '" data-pergel-list="' . esc_attr( $kind ) . '" data-pergel-product="' . esc_attr( (string) $product->get_id() ) . '" aria-pressed="false" hidden>'
				. self::icon( $kind ) . $label . '</button>';
		}
		return '<div ' . get_block_wrapper_attributes( array( 'class' => $labels ? 'is-labelled' : '' ) ) . '>' . $html . '</div>';
	}

	/**
	 * The pergel/shop-list block: the wishlist or the products to compare.
	 * A customer's wishlist is drawn here; a visitor's lists live in their
	 * browser, so the script draws them.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public static function page( array $attributes ): string {
		$kind = 'compare' === ( $attributes['kind'] ?? '' ) ? 'compare' : 'wishlist';
		if ( ! in_array( $kind, Lists::enabled(), true ) ) {
			return self::placeholder( __( 'Turn on the wishlist and compare under Pergel → Settings → Shop to show the list here.', 'pergel-engine' ) );
		}
		Lists::enqueue();
		$user  = get_current_user_id();
		$drawn = 'wishlist' === $kind && $user > 0;
		$inner = $drawn
			? self::render( $kind, Lists::products( Lists::saved( $user ) ) )
			: '<noscript><p class="wp-block-pergel-shop-list__empty">' . esc_html__( 'Your lists are kept in this browser and need JavaScript to show.', 'pergel-engine' ) . '</p></noscript>';
		$label = 'compare' === $kind ? __( 'Products to compare', 'pergel-engine' ) : __( 'Wishlist', 'pergel-engine' );
		return '<div ' . get_block_wrapper_attributes(
			array(
				'class'                 => 'is-' . $kind,
				'data-pergel-shop-list' => $kind,
				'data-pergel-drawn'     => $drawn ? 'true' : 'false',
				'aria-label'            => $label,
				'aria-busy'             => $drawn ? 'false' : 'true',
				'role'                  => 'region',
				'tabindex'              => '-1',
			)
		) . '><div class="wp-block-pergel-shop-list__body">' . $inner . '</div></div>';
	}

	/**
	 * The pergel/shop-links block: links to the list pages with the number
	 * of products in each, which the script fills in.
	 */
	public static function links(): string {
		$kinds = Lists::linked();
		if ( array() === $kinds ) {
			return self::placeholder( __( 'Links to the wishlist and compare pages. Set the pages under Pergel → Settings → Shop.', 'pergel-engine' ) );
		}
		Lists::enqueue();
		$html = '';
		foreach ( $kinds as $kind ) {
			$text  = 'compare' === $kind ? __( 'Compare', 'pergel-engine' ) : __( 'Wishlist', 'pergel-engine' );
			$html .= '<a class="wp-block-pergel-shop-links__link is-' . esc_attr( $kind ) . '" href="' . esc_url( Lists::page( $kind ) ) . '">'
				. self::icon( $kind ) . '<span class="pergel-sr">' . esc_html( $text ) . '</span>'
				. '<span class="wp-block-pergel-shop-links__count" data-pergel-count="' . esc_attr( $kind ) . '" hidden></span></a>';
		}
		return '<div ' . get_block_wrapper_attributes() . '>' . $html . '</div>';
	}

	/**
	 * A list's products: a grid for the wishlist, a table for compare.
	 *
	 * @param string                  $kind     wishlist or compare.
	 * @param array<int, \WC_Product> $products Products to show.
	 * @phpstan-param list<\WC_Product> $products
	 */
	public static function render( string $kind, array $products ): string {
		if ( array() === $products ) {
			return '<p class="wp-block-pergel-shop-list__empty">' . esc_html( Lists::empty_text( $kind ) ) . '</p>';
		}
		return 'compare' === $kind ? self::table( $products ) : self::grid( $products );
	}

	/**
	 * The wishlist: image, name, price, a button to buy and one to remove.
	 *
	 * @param array<int, \WC_Product> $products Products.
	 * @phpstan-param list<\WC_Product> $products
	 */
	private static function grid( array $products ): string {
		$items = '';
		foreach ( $products as $product ) {
			$link   = (string) get_permalink( $product->get_id() );
			$price  = (string) $product->get_price_html();
			$items .= '<li class="wp-block-pergel-shop-list__item">'
				. '<a class="wp-block-pergel-shop-list__image" href="' . esc_url( $link ) . '" tabindex="-1" aria-hidden="true">' . $product->get_image( 'woocommerce_thumbnail' ) . '</a>'
				. '<h2 class="wp-block-pergel-shop-list__name"><a href="' . esc_url( $link ) . '">' . esc_html( $product->get_name() ) . '</a></h2>'
				. ( '' !== $price ? '<p class="wp-block-pergel-shop-list__price">' . Price::html( $price ) . '</p>' : '' )
				. '<p class="wp-block-pergel-shop-list__actions">' . self::buy( $product ) . self::remove( 'wishlist', $product ) . '</p>'
				. '</li>';
		}
		return '<ul class="wp-block-pergel-shop-list__grid" role="list">' . $items . '</ul>';
	}

	/**
	 * The comparison: a column per product, a row per property. A row no
	 * product has a value for is left out.
	 *
	 * @param array<int, \WC_Product> $products Products.
	 * @phpstan-param list<\WC_Product> $products
	 */
	private static function table( array $products ): string {
		$rows = array(
			__( 'Price', 'pergel-engine' )        => static fn ( \WC_Product $product ): string => Price::html( (string) $product->get_price_html() ),
			__( 'Rating', 'pergel-engine' )       => static fn ( \WC_Product $product ): string => self::rating( $product ),
			__( 'Availability', 'pergel-engine' ) => static fn ( \WC_Product $product ): string => esc_html( self::availability( $product ) ),
			__( 'SKU', 'pergel-engine' )          => static fn ( \WC_Product $product ): string => esc_html( (string) $product->get_sku() ),
			__( 'Weight', 'pergel-engine' )       => static fn ( \WC_Product $product ): string => $product->has_weight() ? esc_html( wc_format_weight( (float) $product->get_weight() ) ) : '',
			__( 'Dimensions', 'pergel-engine' )   => static fn ( \WC_Product $product ): string => $product->has_dimensions() && is_array( $product->get_dimensions( false ) ) ? esc_html( wc_format_dimensions( $product->get_dimensions( false ) ) ) : '',
		);
		foreach ( self::attributes( $products ) as $name => $label ) {
			$rows[ $label ] = static fn ( \WC_Product $product ): string => esc_html( (string) $product->get_attribute( $name ) );
		}

		$head = '<tr><td></td>';
		$buy  = '<tr><th scope="row"><span class="pergel-sr">' . esc_html__( 'Buy', 'pergel-engine' ) . '</span></th>';
		foreach ( $products as $product ) {
			$link  = (string) get_permalink( $product->get_id() );
			$head .= '<th scope="col"><a class="wp-block-pergel-shop-list__image" href="' . esc_url( $link ) . '" tabindex="-1" aria-hidden="true">' . $product->get_image( 'woocommerce_thumbnail' ) . '</a>'
				. '<a class="wp-block-pergel-shop-list__name" href="' . esc_url( $link ) . '">' . esc_html( $product->get_name() ) . '</a>'
				. self::remove( 'compare', $product ) . '</th>';
			$buy  .= '<td>' . self::buy( $product ) . '</td>';
		}

		$body = '';
		foreach ( $rows as $label => $value ) {
			$cells = array_map( $value, $products );
			if ( array() === array_filter( $cells, static fn ( string $cell ): bool => '' !== trim( wp_strip_all_tags( $cell ) ) ) ) {
				continue;
			}
			$body .= '<tr><th scope="row">' . esc_html( (string) $label ) . '</th><td>' . implode( '</td><td>', $cells ) . '</td></tr>';
		}

		return '<div class="wp-block-pergel-shop-list__scroll" role="region" tabindex="0" aria-label="' . esc_attr__( 'Product comparison, scrolls sideways', 'pergel-engine' ) . '">'
			. '<table class="wp-block-pergel-shop-list__table"><caption class="pergel-sr">' . esc_html__( 'Product comparison', 'pergel-engine' ) . '</caption>'
			. '<thead>' . $head . '</tr></thead><tbody>' . $body . $buy . '</tr></tbody></table></div>';
	}

	/**
	 * Visible attributes of the products, by name, in the order met.
	 *
	 * @param array<int, \WC_Product> $products Products.
	 * @phpstan-param list<\WC_Product> $products
	 * @return array<string, string> Attribute name => label.
	 */
	private static function attributes( array $products ): array {
		$attributes = array();
		foreach ( $products as $product ) {
			foreach ( $product->get_attributes() as $name => $attribute ) {
				if ( $attribute instanceof \WC_Product_Attribute && $attribute->get_visible() && ! isset( $attributes[ (string) $name ] ) ) {
					$attributes[ (string) $name ] = wc_attribute_label( $attribute->get_name(), $product );
				}
			}
		}
		return $attributes;
	}

	/**
	 * The product's average rating in words, or '' without reviews.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function rating( \WC_Product $product ): string {
		$rating = (float) $product->get_average_rating();
		if ( ! wc_review_ratings_enabled() || $rating <= 0 ) {
			return '';
		}
		/* translators: 1: average rating, 2: number of reviews. */
		return esc_html( sprintf( _n( '%1$s out of 5 (%2$d review)', '%1$s out of 5 (%2$d reviews)', $product->get_review_count(), 'pergel-engine' ), number_format_i18n( $rating, 1 ), $product->get_review_count() ) );
	}

	/**
	 * Whether the product is in stock, in words.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function availability( \WC_Product $product ): string {
		$availability = $product->get_availability();
		$text         = is_array( $availability ) ? (string) ( $availability['availability'] ?? '' ) : '';
		if ( '' !== $text ) {
			return wp_strip_all_tags( $text );
		}
		return $product->is_in_stock() ? __( 'In stock', 'pergel-engine' ) : __( 'Out of stock', 'pergel-engine' );
	}

	/**
	 * A button that adds the product to the cart, or a link to the product
	 * when it needs options or is not sold to the visitor.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function buy( \WC_Product $product ): string {
		$link = (string) get_permalink( $product->get_id() );
		if ( $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ) {
			return '<a class="wp-element-button" rel="nofollow" href="' . esc_url( add_query_arg( 'add-to-cart', $product->get_id(), $link ) ) . '">'
				. esc_html__( 'Add to cart', 'pergel-engine' ) . '<span class="pergel-sr">: ' . esc_html( $product->get_name() ) . '</span></a>';
		}
		return '<a class="wp-element-button is-secondary" href="' . esc_url( $link ) . '">'
			. esc_html__( 'View product', 'pergel-engine' ) . '<span class="pergel-sr">: ' . esc_html( $product->get_name() ) . '</span></a>';
	}

	/**
	 * A button that takes the product off a list.
	 *
	 * @param string      $kind    wishlist or compare.
	 * @param \WC_Product $product Product.
	 */
	private static function remove( string $kind, \WC_Product $product ): string {
		return '<button type="button" class="wp-block-pergel-shop-list__remove" data-pergel-remove="' . esc_attr( $kind ) . '" data-pergel-product="' . esc_attr( (string) $product->get_id() ) . '">'
			. esc_html__( 'Remove', 'pergel-engine' ) . '<span class="pergel-sr">: ' . esc_html( $product->get_name() ) . '</span></button>';
	}

	/**
	 * An inline icon.
	 *
	 * @param string $kind wishlist or compare.
	 */
	private static function icon( string $kind ): string {
		return '<svg class="pergel-list-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . self::ICONS[ $kind ] . '</svg>';
	}

	/**
	 * What the editor shows where the block has nothing to draw; nothing
	 * on the site.
	 *
	 * @param string $text Note.
	 */
	private static function placeholder( string $text ): string {
		return wp_is_serving_rest_request() ? '<div ' . get_block_wrapper_attributes() . '><p>' . esc_html( $text ) . '</p></div>' : '';
	}
}
