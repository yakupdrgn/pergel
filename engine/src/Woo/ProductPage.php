<?php
/**
 * Product page layout from the settings.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * Applies the "Product page" settings to the product on screen, while its
 * template renders; the template stays a plain block template (K10):
 *
 * - layout: the columns that hold the product gallery get classes for
 *   pictures on the left or right, and for three columns a column of
 *   recommended products (upsells, else related products);
 * - small pictures beside or under the big one, none, or every picture
 *   in a grid (the gallery block's own layout and inner blocks);
 * - zoom, badges on the big picture, the details kept in view;
 * - a banner, the category and the brand around the product name, the
 *   discount and the stock beside the price;
 * - the previous and next product beside the breadcrumb.
 *
 * Layout loads this class only on product pages where one of these
 * settings differs from its default. Values come from Layout::get(), so a
 * product and its category can choose their own.
 */
final class ProductPage {

	/**
	 * Most recommended products in the third column.
	 */
	private const RECOMMENDED = 4;

	/**
	 * The product on screen.
	 *
	 * @var int
	 */
	private static int $product = 0;

	/**
	 * Set while the banner pattern renders, so a product name inside it
	 * does not draw the banner again.
	 *
	 * @var bool
	 */
	private static bool $drawing = false;

	/**
	 * Adds the hooks and the CSS. Called on wp.
	 *
	 * @param int $product The product on screen.
	 */
	public static function register( int $product ): void {
		self::$product = $product;
		add_filter( 'render_block_data', array( self::class, 'gallery_data' ) );
		add_filter( 'render_block_context', array( self::class, 'zoom' ), 10, 2 );
		add_filter( 'render_block_woocommerce/product-gallery', array( self::class, 'gallery' ), 10, 3 );
		add_filter( 'render_block_woocommerce/product-gallery-large-image', array( self::class, 'large_image' ), 10, 3 );
		add_filter( 'render_block_core/columns', array( self::class, 'columns' ), 10, 2 );
		add_filter( 'render_block_core/post-title', array( self::class, 'title' ), 10, 3 );
		add_filter( 'render_block_woocommerce/product-price', array( self::class, 'price' ), 10, 3 );
		add_filter( 'render_block_pergel/breadcrumb', array( self::class, 'nav' ) );
		wp_enqueue_style( Layout::PRODUCT_STYLE );
		if ( true === Layout::get( 'shop.product_badges' ) || true === Layout::get( 'shop.product_discount' ) || true === Layout::get( 'shop.product_stock' ) ) {
			Layout::card_style();
		}
	}

	/**
	 * Zoom off: the big picture reads it from its context. The gallery's
	 * attribute would come too late; WordPress has already worked out the
	 * context its inner blocks get.
	 *
	 * @param array<string, mixed> $context Block context.
	 * @param array<string, mixed> $block   Parsed block.
	 * @return array<string, mixed>
	 */
	public static function zoom( array $context, array $block ): array {
		if ( 'woocommerce/product-gallery-large-image' === ( $block['blockName'] ?? '' ) && false === Layout::get( 'shop.product_zoom' ) ) {
			$context['hoverZoom'] = false;
		}
		return $context;
	}

	/**
	 * Small pictures: the gallery block's layout and inner blocks, before
	 * it renders.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return array<string, mixed>
	 */
	public static function gallery_data( array $block ): array {
		if ( 'woocommerce/product-gallery' !== ( $block['blockName'] ?? '' ) ) {
			return $block;
		}
		$thumbs = Layout::get( 'shop.product_thumbs' );
		if ( 'left' === $thumbs || 'bottom' === $thumbs ) {
			$block['attrs']['layout'] = array(
				'type'        => 'flex',
				'flexWrap'    => 'nowrap',
				'orientation' => 'left' === $thumbs ? 'horizontal' : 'vertical',
			);
			$block                    = self::move_thumbs( $block, 'left' === $thumbs ? 'first' : 'last' );
		} elseif ( 'none' === $thumbs ) {
			$block = self::move_thumbs( $block, 'none' );
		}
		return $block;
	}

	/**
	 * Moves the thumbnails block first or last among the gallery's inner
	 * blocks, or takes it out. Each inner block has a null in the inner
	 * content, filled in order.
	 *
	 * @param array<string, mixed> $block Parsed gallery block.
	 * @param string               $where "first", "last" or "none".
	 * @return array<string, mixed>
	 */
	private static function move_thumbs( array $block, string $where ): array {
		$inner   = is_array( $block['innerBlocks'] ?? null ) ? array_values( $block['innerBlocks'] ) : array();
		$content = is_array( $block['innerContent'] ?? null ) ? array_values( $block['innerContent'] ) : array();
		$index   = null;
		foreach ( $inner as $i => $child ) {
			if ( 'woocommerce/product-gallery-thumbnails' === ( $child['blockName'] ?? '' ) ) {
				$index = $i;
				break;
			}
		}
		if ( null === $index ) {
			return $block;
		}
		$thumbs = $inner[ $index ];
		array_splice( $inner, $index, 1 );
		$nulls = array_keys( $content, null, true );
		array_splice( $content, $nulls[ $index ], 1 );
		if ( 'none' !== $where ) {
			$nulls = array_keys( $content, null, true );
			if ( 'first' === $where ) {
				array_unshift( $inner, $thumbs );
				array_splice( $content, $nulls ? $nulls[0] : min( 1, count( $content ) ), 0, array( null ) );
			} else {
				$inner[] = $thumbs;
				array_splice( $content, $nulls ? end( $nulls ) + 1 : min( 1, count( $content ) ), 0, array( null ) );
			}
		}
		$block['innerBlocks']  = $inner;
		$block['innerContent'] = $content;
		return $block;
	}

	/**
	 * "All pictures": every picture in a grid instead of the gallery, the
	 * first one wide. Each links to the full-size picture.
	 *
	 * @param string    $html     Rendered gallery.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @phpstan-param array<string, mixed> $block
	 */
	public static function gallery( string $html, array $block, \WP_Block $instance ): string {
		if ( 'grid' !== Layout::get( 'shop.product_thumbs' ) || ! self::main( $instance ) ) {
			return $html;
		}
		$product = wc_get_product( self::$product );
		if ( ! $product instanceof \WC_Product ) {
			return $html;
		}
		$ids = array_values( array_unique( array_filter( array_map( 'intval', array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) ) ) ) );
		if ( count( $ids ) < 2 ) {
			return $html;
		}
		$items = '';
		foreach ( $ids as $index => $id ) {
			$alt    = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
			$items .= sprintf(
				'<li class="pergel-product-pictures__item">%1$s<a href="%2$s">%3$s</a></li>',
				0 === $index ? self::badges( $product ) : '',
				esc_url( (string) wp_get_attachment_image_url( $id, 'full' ) ),
				wp_get_attachment_image(
					$id,
					'woocommerce_single',
					false,
					array(
						'alt'           => '' !== $alt ? $alt : $product->get_name(),
						'loading'       => 0 === $index ? 'eager' : 'lazy',
						'fetchpriority' => 0 === $index ? 'high' : 'auto',
					)
				)
			);
		}
		return '<ul class="pergel-product-pictures">' . $items . '</ul>';
	}

	/**
	 * Badges on the big picture.
	 *
	 * @param string    $html     Rendered large image.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @phpstan-param array<string, mixed> $block
	 */
	public static function large_image( string $html, array $block, \WP_Block $instance ): string {
		if ( true !== Layout::get( 'shop.product_badges' ) || ! self::main( $instance ) ) {
			return $html;
		}
		$product = wc_get_product( self::$product );
		$badges  = $product instanceof \WC_Product ? self::badges( $product ) : '';
		if ( '' === $badges ) {
			return $html;
		}
		$tags = new \WP_HTML_Tag_Processor( $html );
		if ( ! $tags->next_tag() ) {
			return $html;
		}
		$tags->add_class( 'has-pergel-badges' );
		$html = $tags->get_updated_html();
		$end  = strpos( $html, '>' );
		return false === $end ? $html : substr( $html, 0, $end + 1 ) . $badges . substr( $html, $end + 1 );
	}

	/**
	 * The product's badges, when that setting is on.
	 *
	 * @param \WC_Product $product Product.
	 */
	private static function badges( \WC_Product $product ): string {
		return true === Layout::get( 'shop.product_badges' ) ? Cards::badges( $product->get_id(), $product ) : '';
	}

	/**
	 * The layout classes on the columns that hold the gallery, and the
	 * third column.
	 *
	 * @param string               $html  Rendered columns.
	 * @param array<string, mixed> $block Parsed block.
	 */
	public static function columns( string $html, array $block ): string {
		if ( ! self::holds_gallery( $block ) ) {
			return $html;
		}
		$layout = Layout::get( 'shop.product_layout' );
		$sticky = true === Layout::get( 'shop.product_sticky' );
		if ( ! in_array( $layout, array( 'left', 'right', 'three' ), true ) && ! $sticky ) {
			return $html;
		}
		$tags = new \WP_HTML_Tag_Processor( $html );
		if ( ! $tags->next_tag() ) {
			return $html;
		}
		$tags->add_class( 'pergel-product-layout' );
		if ( is_string( $layout ) && 'template' !== $layout ) {
			$tags->add_class( "is-pictures-$layout" );
		}
		if ( $sticky ) {
			$tags->add_class( 'is-sticky-details' );
		}
		$html = $tags->get_updated_html();
		if ( 'three' === $layout ) {
			$side = self::recommended();
			$end  = strrpos( $html, '</div>' );
			if ( '' !== $side && false !== $end ) {
				$html = substr( $html, 0, $end ) . $side . substr( $html, $end );
			}
		}
		return $html;
	}

	/**
	 * Whether one of the columns holds the product gallery.
	 *
	 * @param array<string, mixed> $block Parsed columns block.
	 */
	private static function holds_gallery( array $block ): bool {
		foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $column ) {
			foreach ( (array) ( $column['innerBlocks'] ?? array() ) as $child ) {
				if ( in_array( $child['blockName'] ?? '', array( 'woocommerce/product-gallery', 'woocommerce/product-image-gallery' ), true ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * The third column: upsells, else related products.
	 */
	private static function recommended(): string {
		$product = wc_get_product( self::$product );
		if ( ! $product instanceof \WC_Product ) {
			return '';
		}
		$ids = $product->get_upsell_ids();
		if ( ! $ids ) {
			$ids = wc_get_related_products( $product->get_id(), self::RECOMMENDED );
		}
		$items = '';
		foreach ( array_slice( array_map( 'intval', $ids ), 0, self::RECOMMENDED ) as $id ) {
			$other = wc_get_product( $id );
			if ( ! $other instanceof \WC_Product || 'publish' !== $other->get_status() || ! $other->is_visible() ) {
				continue;
			}
			$price  = (string) $other->get_price_html();
			$items .= sprintf(
				'<li class="pergel-product-side__item"><a class="pergel-product-side__link" href="%1$s">%2$s<span class="pergel-product-side__name">%3$s</span></a>%4$s</li>',
				esc_url( (string) $other->get_permalink() ),
				wp_kses_post( $other->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ) ),
				esc_html( $other->get_name() ),
				'' === $price ? '' : '<span class="pergel-product-side__price">' . wp_kses_post( $price ) . '</span>'
			);
		}
		if ( '' === $items ) {
			return '';
		}
		return sprintf(
			'<aside class="wp-block-column pergel-product-side" aria-labelledby="pergel-product-side-title"><h2 class="pergel-product-side__title" id="pergel-product-side-title">%1$s</h2><ul>%2$s</ul></aside>',
			esc_html__( 'You may also like', 'pergel-engine' ),
			$items
		);
	}

	/**
	 * The banner and the category above the product name, the brand
	 * under it.
	 *
	 * @param string    $html     Rendered title.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @phpstan-param array<string, mixed> $block
	 */
	public static function title( string $html, array $block, \WP_Block $instance ): string {
		if ( self::$drawing || ! self::main( $instance ) ) {
			return $html;
		}
		return self::banner() . self::category() . $html . self::brand();
	}

	/**
	 * The chosen pattern; nothing when it is gone or not published.
	 */
	private static function banner(): string {
		$id   = Layout::get( 'shop.product_banner' );
		$post = is_int( $id ) && $id > 0 ? get_post( $id ) : null;
		if ( ! $post instanceof \WP_Post || 'wp_block' !== $post->post_type || 'publish' !== $post->post_status ) {
			return '';
		}
		self::$drawing = true;
		$blocks        = do_blocks( $post->post_content );
		self::$drawing = false;
		return '' === trim( $blocks ) ? '' : '<div class="pergel-product-banner">' . $blocks . '</div>';
	}

	/**
	 * The product's category as a link.
	 */
	private static function category(): string {
		if ( true !== Layout::get( 'shop.product_category' ) ) {
			return '';
		}
		$term = Layout::product_category( self::$product );
		$url  = null === $term || (int) get_option( 'default_product_cat', 0 ) === $term->term_id ? '' : get_term_link( $term );
		if ( null === $term || ! is_string( $url ) || '' === $url ) {
			return '';
		}
		return sprintf( '<p class="pergel-product-category"><a href="%1$s">%2$s</a></p>', esc_url( $url ), esc_html( $term->name ) );
	}

	/**
	 * The product's brands, with their pictures when they have one.
	 */
	private static function brand(): string {
		if ( true !== Layout::get( 'shop.product_brand' ) || ! taxonomy_exists( 'product_brand' ) ) {
			return '';
		}
		$terms = get_the_terms( self::$product, 'product_brand' );
		$links = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$url     = get_term_link( $term );
			$picture = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
			$inside  = $picture > 0
				? wp_get_attachment_image(
					$picture,
					'thumbnail',
					false,
					array(
						'class'   => 'pergel-product-brand__image',
						'alt'     => $term->name,
						'loading' => 'lazy',
					)
				)
				: '';
			$inside  = '' !== $inside ? $inside : esc_html( $term->name );
			$links[] = is_string( $url ) ? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), $inside ) : $inside;
		}
		if ( ! $links ) {
			return '';
		}
		return sprintf(
			'<p class="pergel-product-brand"><span class="pergel-product-brand__label">%1$s</span> %2$s</p>',
			esc_html( _n( 'Brand:', 'Brands:', count( $links ), 'pergel-engine' ) ),
			implode( ' ', $links )
		);
	}

	/**
	 * The discount and the stock beside the price.
	 *
	 * @param string    $html     Rendered price.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @phpstan-param array<string, mixed> $block
	 */
	public static function price( string $html, array $block, \WP_Block $instance ): string {
		$discount = true === Layout::get( 'shop.product_discount' );
		$stock    = true === Layout::get( 'shop.product_stock' );
		if ( ( ! $discount && ! $stock ) || '' === trim( $html ) || ! self::main( $instance ) ) {
			return $html;
		}
		$product = wc_get_product( self::$product );
		if ( ! $product instanceof \WC_Product ) {
			return $html;
		}
		$pills   = '';
		$percent = $discount ? Cards::discount( $product ) : 0;
		if ( $percent > 0 ) {
			$pills .= sprintf(
				'<span class="pergel-card-pill is-discount">%1$s<span class="screen-reader-text"> %2$s</span></span>',
				/* translators: %d: discount in per cent. */
				esc_html( sprintf( __( '-%d%%', 'pergel-engine' ), $percent ) ),
				esc_html__( 'discount', 'pergel-engine' )
			);
		}
		if ( $stock ) {
			$pills .= Cards::stock( $product );
		}
		return '' === $pills ? $html : '<div class="pergel-product-price">' . $html . '<div class="pergel-card-pills">' . $pills . '</div></div>';
	}

	/**
	 * The previous and next product in the product's category, and a link
	 * back to the category, after the breadcrumb.
	 *
	 * @param string $html Rendered breadcrumb.
	 */
	public static function nav( string $html ): string {
		if ( true !== Layout::get( 'shop.product_nav' ) || ! is_singular( 'product' ) || get_queried_object_id() !== self::$product ) {
			return $html;
		}
		$term  = Layout::product_category( self::$product );
		$back  = null === $term ? get_permalink( wc_get_page_id( 'shop' ) ) : get_term_link( $term );
		$name  = null === $term ? __( 'Shop', 'pergel-engine' ) : $term->name;
		$links = '';
		foreach ( array(
			'previous' => true,
			'next'     => false,
		) as $kind => $previous ) {
			$post = null === $term ? get_adjacent_post( false, '', $previous ) : get_adjacent_post( true, '', $previous, 'product_cat' );
			if ( $post instanceof \WP_Post ) {
				$links .= sprintf(
					'<a class="pergel-product-nav__link is-%1$s" href="%2$s" rel="%3$s"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="%4$s"/></svg><span class="screen-reader-text">%5$s</span></a>',
					esc_attr( $kind ),
					esc_url( (string) get_permalink( $post ) ),
					$previous ? 'prev' : 'next',
					$previous ? 'M15 6l-6 6 6 6' : 'M9 6l6 6-6 6',
					/* translators: %s: product name. */
					esc_html( sprintf( $previous ? __( 'Previous product: %s', 'pergel-engine' ) : __( 'Next product: %s', 'pergel-engine' ), get_the_title( $post ) ) )
				);
			}
			if ( $previous && is_string( $back ) && '' !== $back ) {
				$links .= sprintf(
					'<a class="pergel-product-nav__link is-back" href="%1$s"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false"><rect x="4" y="4" width="6.5" height="6.5" rx="1"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1"/></svg><span class="screen-reader-text">%2$s</span></a>',
					esc_url( $back ),
					/* translators: %s: category name. */
					esc_html( sprintf( __( 'Back to %s', 'pergel-engine' ), $name ) )
				);
			}
		}
		if ( '' === $links ) {
			return $html;
		}
		return sprintf(
			'<div class="pergel-product-top alignwide">%1$s<nav class="pergel-product-nav" aria-label="%2$s">%3$s</nav></div>',
			$html,
			esc_attr__( 'Products', 'pergel-engine' ),
			$links
		);
	}

	/**
	 * Whether a block belongs to the product on screen, not to a product
	 * grid on the page.
	 *
	 * @param \WP_Block $instance Block instance.
	 */
	private static function main( \WP_Block $instance ): bool {
		return ! isset( $instance->context['queryId'] ) && (int) ( $instance->context['postId'] ?? 0 ) === self::$product;
	}
}
