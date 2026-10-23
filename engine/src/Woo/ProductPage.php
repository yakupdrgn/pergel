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
 * - the previous and next product beside the breadcrumb;
 * - "Buy now" in the add-to-cart form, the coupons that apply to the
 *   product under it (CouponField), and share links after the contact
 *   block (pergel/product-cta), which both product templates hold.
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
	 * Most coupons listed.
	 */
	private const COUPONS = 6;

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
		add_filter( 'render_block_woocommerce/add-to-cart-with-options', array( self::class, 'buying' ), 10, 3 );
		add_filter( 'render_block_pergel/product-cta', array( self::class, 'share' ), 10, 3 );
		add_filter( 'script_module_data_' . Layout::PRODUCT_SCRIPT, array( self::class, 'data' ) );
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
	 * Data for the product page script: where "Buy now" goes, and texts.
	 *
	 * @param mixed $data Data so far.
	 * @return array<string, mixed>
	 */
	public static function data( mixed $data ): array {
		return array_merge(
			is_array( $data ) ? $data : array(),
			array(
				'checkout' => esc_url_raw( wc_get_checkout_url() ),
				'texts'    => array(
					'copied' => __( 'Copied', 'pergel-engine' ),
					'failed' => __( 'Copy it by hand: select the text.', 'pergel-engine' ),
				),
			)
		);
	}

	/**
	 * "Buy now" in the add-to-cart form, and the coupons under it.
	 *
	 * @param string    $html     Rendered form.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @phpstan-param array<string, mixed> $block
	 */
	public static function buying( string $html, array $block, \WP_Block $instance ): string {
		if ( '' === trim( $html ) || ! self::main( $instance ) ) {
			return $html;
		}
		$product = wc_get_product( self::$product );
		if ( ! $product instanceof \WC_Product ) {
			return $html;
		}
		if ( true === Layout::get( 'shop.product_buy_now' ) && ! $product->is_type( 'external' ) ) {
			$end = strrpos( $html, '</form>' );
			if ( false !== $end ) {
				$html = substr( $html, 0, $end ) . self::buy_now_button() . substr( $html, $end );
				wp_enqueue_script_module( Layout::PRODUCT_SCRIPT );
			}
		}
		return true === Layout::get( 'shop.product_coupons' ) ? $html . self::coupons( $product ) : $html;
	}

	/**
	 * The "Buy now" button; the script adds the product to the cart with
	 * the form, then opens the checkout.
	 *
	 * @param string $extra Extra class.
	 */
	public static function buy_now_button( string $extra = '' ): string {
		return sprintf(
			'<button type="button" class="wp-element-button pergel-buy-now%1$s" data-pergel-buy-now>%2$s</button>',
			'' === $extra ? '' : ' ' . esc_attr( $extra ),
			esc_html__( 'Buy now', 'pergel-engine' )
		);
	}

	/**
	 * The chosen coupons that apply to the product: published, not used
	 * up, not ended, for everyone (no email limits) and for this product
	 * by its product and category rules.
	 *
	 * @param \WC_Product $product Product.
	 */
	private static function coupons( \WC_Product $product ): string {
		$posts = get_posts(
			array(
				'post_type'        => 'shop_coupon',
				'post_status'      => 'publish',
				'numberposts'      => 20,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'meta_key'         => CouponField::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Few coupons; only with the setting on.
				'meta_value'       => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- As above.
				'suppress_filters' => false,
			)
		);
		$items = '';
		$count = 0;
		foreach ( $posts as $post ) {
			$coupon = new \WC_Coupon( $post->ID );
			if ( $count >= self::COUPONS || ! self::applies( $coupon, $product ) ) {
				continue;
			}
			$items .= self::coupon( $coupon );
			++$count;
		}
		if ( '' === $items ) {
			return '';
		}
		wp_enqueue_script_module( Layout::PRODUCT_SCRIPT );
		return sprintf(
			'<section class="pergel-coupons" aria-labelledby="pergel-coupons-title"><h2 class="pergel-coupons__title" id="pergel-coupons-title">%1$s</h2><ul class="pergel-coupons__list">%2$s</ul><p class="screen-reader-text" role="status" data-pergel-copy-status></p></section>',
			esc_html__( 'Offers', 'pergel-engine' ),
			$items
		);
	}

	/**
	 * Whether a coupon can be used on a product by anyone now.
	 *
	 * @param \WC_Coupon  $coupon  Coupon.
	 * @param \WC_Product $product Product.
	 */
	public static function applies( \WC_Coupon $coupon, \WC_Product $product ): bool {
		$ends  = $coupon->get_date_expires();
		$limit = $coupon->get_usage_limit();
		if ( '' === $coupon->get_code() || ( null !== $ends && $ends->getTimestamp() < time() ) || ( $limit > 0 && $coupon->get_usage_count() >= $limit ) || $coupon->get_email_restrictions() ) {
			return false;
		}
		$ids        = array_filter( array( $product->get_id(), $product->get_parent_id() ) );
		$categories = $product->get_category_ids();
		if ( ( $coupon->get_product_ids() && ! array_intersect( $ids, $coupon->get_product_ids() ) ) || array_intersect( $ids, $coupon->get_excluded_product_ids() ) ) {
			return false;
		}
		if ( ( $coupon->get_product_categories() && ! array_intersect( $categories, $coupon->get_product_categories() ) ) || array_intersect( $categories, $coupon->get_excluded_product_categories() ) ) {
			return false;
		}
		return ! ( $coupon->get_exclude_sale_items() && $product->is_on_sale() );
	}

	/**
	 * One coupon: what it gives, its terms, its code and the copy button.
	 *
	 * @param \WC_Coupon $coupon Coupon.
	 */
	private static function coupon( \WC_Coupon $coupon ): string {
		$amount = (float) $coupon->get_amount();
		$gives  = match ( $coupon->get_discount_type() ) {
			/* translators: %s: discount in per cent, a number such as 10. */
			'percent'       => sprintf( __( '%s%% off', 'pergel-engine' ), wc_format_localized_decimal( wc_format_decimal( $amount, '', true ) ) ),
			/* translators: %s: amount, e.g. $5. */
			'fixed_product' => sprintf( __( '%s off this product', 'pergel-engine' ), wp_strip_all_tags( wc_price( $amount ) ) ),
			/* translators: %s: amount, e.g. $5. */
			default         => sprintf( __( '%s off your order', 'pergel-engine' ), wp_strip_all_tags( wc_price( $amount ) ) ),
		};
		if ( $amount <= 0 && $coupon->get_free_shipping() ) {
			$gives = __( 'Free shipping', 'pergel-engine' );
		}
		$terms = array();
		if ( (float) $coupon->get_minimum_amount() > 0 ) {
			/* translators: %s: amount. */
			$terms[] = sprintf( __( 'Orders over %s', 'pergel-engine' ), wp_strip_all_tags( wc_price( (float) $coupon->get_minimum_amount() ) ) );
		}
		$ends = $coupon->get_date_expires();
		if ( null !== $ends ) {
			/* translators: %s: date. */
			$terms[] = sprintf( __( 'Until %s', 'pergel-engine' ), wp_date( (string) get_option( 'date_format' ), $ends->getTimestamp() ) );
		}
		$about = trim( (string) $coupon->get_description() );
		$code  = strtoupper( $coupon->get_code() );
		return sprintf(
			'<li class="pergel-coupon"><div class="pergel-coupon__text"><p class="pergel-coupon__gives">%1$s</p>%2$s%3$s</div><div class="pergel-coupon__code"><code>%4$s</code><button type="button" class="pergel-coupon__copy" data-pergel-copy="%4$s">%5$s<span class="screen-reader-text"> %4$s</span></button></div></li>',
			esc_html( $gives ),
			'' === $about ? '' : '<p class="pergel-coupon__about">' . esc_html( $about ) . '</p>',
			$terms ? '<p class="pergel-coupon__terms">' . esc_html( implode( ' · ', $terms ) ) . '</p>' : '',
			esc_attr( $code ),
			esc_html__( 'Copy code', 'pergel-engine' )
		);
	}

	/**
	 * Share links after the contact block.
	 *
	 * @param string    $html     Rendered contact block.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @phpstan-param array<string, mixed> $block
	 */
	public static function share( string $html, array $block, \WP_Block $instance ): string {
		if ( true !== Layout::get( 'shop.product_share' ) || ! self::main( $instance ) ) {
			return $html;
		}
		$url   = (string) get_permalink( self::$product );
		$title = wp_strip_all_tags( get_the_title( self::$product ) );
		$image = (string) wp_get_attachment_image_url( (int) get_post_thumbnail_id( self::$product ), 'large' );
		$sites = array(
			'facebook'  => array( 'Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url ) ),
			'x'         => array( 'X', 'https://x.com/intent/post?url=' . rawurlencode( $url ) . '&text=' . rawurlencode( $title ) ),
			'linkedin'  => array( 'LinkedIn', 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $url ) ),
			'whatsapp'  => array( 'WhatsApp', 'https://wa.me/?text=' . rawurlencode( $title . ' ' . $url ) ),
			'pinterest' => array( 'Pinterest', 'https://www.pinterest.com/pin/create/button/?url=' . rawurlencode( $url ) . '&description=' . rawurlencode( $title ) . ( '' === $image ? '' : '&media=' . rawurlencode( $image ) ) ),
			'email'     => array( __( 'Email', 'pergel-engine' ), 'mailto:?subject=' . rawurlencode( $title ) . '&body=' . rawurlencode( $url ) ),
		);
		$links = '';
		foreach ( $sites as $key => [ $name, $href ] ) {
			$links .= sprintf(
				'<li><a class="pergel-share__link is-%1$s" href="%2$s"%3$s>%4$s<span class="screen-reader-text">%5$s</span></a></li>',
				esc_attr( $key ),
				esc_url( $href, array( 'https', 'mailto' ) ),
				'email' === $key ? '' : ' target="_blank" rel="noopener noreferrer"',
				self::icon( $key ),
				/* translators: %s: site name, e.g. Facebook. */
				esc_html( sprintf( 'email' === $key ? __( 'Share by %s', 'pergel-engine' ) : __( 'Share on %s (opens in a new tab)', 'pergel-engine' ), $name ) )
			);
		}
		$links .= sprintf(
			'<li><button type="button" class="pergel-share__link is-copy" data-pergel-copy="%1$s">%2$s<span class="screen-reader-text">%3$s</span></button></li>',
			esc_attr( $url ),
			self::icon( 'copy' ),
			esc_html__( 'Copy the link', 'pergel-engine' )
		);
		wp_enqueue_script_module( Layout::PRODUCT_SCRIPT );
		return $html . sprintf(
			'<div class="pergel-share"><p class="pergel-share__title" id="pergel-share-title">%1$s</p><ul class="pergel-share__list" aria-labelledby="pergel-share-title">%2$s</ul><p class="screen-reader-text" role="status" data-pergel-copy-status></p></div>',
			esc_html__( 'Share', 'pergel-engine' ),
			$links
		);
	}

	/**
	 * A small drawing for a share link, in currentColor.
	 *
	 * @param string $key Site key.
	 */
	private static function icon( string $key ): string {
		$path = match ( $key ) {
			'facebook'  => '<path fill="currentColor" d="M13.5 21v-7.5h2.5l.4-3h-2.9V8.6c0-.9.3-1.5 1.5-1.5h1.5V4.4c-.3 0-1.2-.1-2.2-.1-2.2 0-3.8 1.4-3.8 3.9v2.3H8v3h2.5V21z"/>',
			'x'         => '<path fill="currentColor" d="M17.8 3.5h3l-6.6 7.5 7.8 9.5h-6.1l-4.8-6.2-5.4 6.2H2.6l7-8L2.2 3.5h6.2l4.3 5.7zm-1 15.2h1.7L7.3 5.2H5.5z"/>',
			'linkedin'  => '<path fill="currentColor" d="M5 3.5a2 2 0 1 1 0 4 2 2 0 0 1 0-4zM3.3 9h3.4v11.5H3.3zm5.6 0h3.3v1.6c.5-.9 1.6-1.9 3.4-1.9 3.6 0 4.2 2.3 4.2 5.4v6.4h-3.4v-5.7c0-1.4 0-3.1-1.9-3.1s-2.2 1.5-2.2 3v5.8H8.9z"/>',
			'whatsapp'  => '<path fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M4 20l1.2-3.6A8 8 0 1 1 8 19.1z"/><path fill="currentColor" d="M9.3 8.2c.2-.4.4-.4.6-.4h.4c.2 0 .3.1.4.3l.6 1.5c.1.2 0 .4-.1.5l-.4.5c-.1.1-.1.3 0 .4.5.9 1.3 1.7 2.3 2.2.2.1.3.1.4 0l.5-.6c.1-.2.3-.2.5-.1l1.4.7c.2.1.3.2.3.4 0 .7-.6 1.5-1.4 1.6-.7.1-1.6 0-3-.7-1.7-.9-2.9-2.4-3.3-3.4-.4-.9-.4-1.9.3-2.9z"/>',
			'pinterest' => '<path fill="currentColor" d="M12 3a9 9 0 0 0-3.3 17.4c-.1-.7-.1-1.8 0-2.6l1.1-4.6s-.3-.6-.3-1.4c0-1.3.8-2.3 1.7-2.3.8 0 1.2.6 1.2 1.3 0 .8-.5 2-.8 3.1-.2.9.5 1.7 1.4 1.7 1.7 0 3-1.8 3-4.4 0-2.3-1.7-3.9-4-3.9-2.8 0-4.4 2.1-4.4 4.2 0 .8.3 1.7.7 2.2.1.1.1.2.1.3l-.3 1.1c0 .2-.2.2-.4.1-1.2-.6-2-2.4-2-3.8 0-3.1 2.3-6 6.5-6 3.4 0 6.1 2.4 6.1 5.7 0 3.4-2.1 6.1-5.1 6.1-1 0-1.9-.5-2.2-1.1l-.6 2.3c-.2.9-.8 1.9-1.2 2.6A9 9 0 1 0 12 3z"/>',
			'email'     => '<path fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M3.5 6h17v12h-17zM3.5 6.5l8.5 6.5 8.5-6.5"/>',
			default     => '<path fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
		};
		return '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">' . $path . '</svg>';
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
