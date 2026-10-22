<?php
/**
 * Product cards in the product grids.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

/**
 * The "Product cards" and "Badges" shop settings, for the products of every
 * Product Collection grid (the shop, categories, and grids such as "New
 * arrivals" on other pages):
 *
 * - a card style, as a class on the grid (shop-cards.css);
 * - category, brand and rating around the name, discount and stock beside
 *   the price;
 * - badges on the picture (the sale badge in per cent or none, new,
 *   popular, sold out, and a product's own badge);
 * - the first gallery picture while the pointer is over the card.
 *
 * Layout loads this class only on listing pages and where a card setting
 * was chosen, so product pages keep their file count. Every default leaves
 * the cards as the template draws them.
 */
final class Cards {

	/**
	 * Product meta with a product's own badge text.
	 */
	public const BADGE_META = '_pergel_badge';

	/**
	 * Longest badge text.
	 */
	public const BADGE_LENGTH = 24;

	/**
	 * Adds the front-end hooks; Layout calls it.
	 */
	public static function register(): void {
		add_filter( 'render_block_woocommerce/product-collection', array( self::class, 'grid' ), 10, 2 );
		add_filter( 'render_block_core/post-title', array( self::class, 'title' ), 10, 3 );
		add_filter( 'render_block_woocommerce/product-price', array( self::class, 'price' ), 20, 3 );
		add_filter( 'render_block_woocommerce/product-image', array( self::class, 'image' ), 10, 3 );
		add_filter( 'render_block_woocommerce/product-sale-badge', array( self::class, 'sale_badge' ), 10, 3 );
		add_filter( 'render_block_pergel/product-actions', array( self::class, 'actions' ), 10, 3 );
	}

	/**
	 * The card style for this page; a product category may choose its own.
	 */
	public static function style(): string {
		$style = Layout::get( 'shop.card_style' );
		return is_string( $style ) && in_array( $style, array( 'standard', 'boxed', 'overlay', 'plain' ), true ) ? $style : '';
	}

	/**
	 * The id of the product a block in a product grid stands for, or 0
	 * outside one.
	 *
	 * @param \WP_Block $instance Block instance.
	 */
	private static function product_id( \WP_Block $instance ): int {
		$context = $instance->context;
		$query   = $context['query'] ?? null;
		// The post title asks only for the query id, not the query.
		$in_grid = is_array( $query )
			? true === ( $query['isProductCollectionBlock'] ?? null )
			: isset( $context['queryId'] ) && 'product' === ( $context['postType'] ?? '' );
		return $in_grid ? max( 0, (int) ( $context['postId'] ?? 0 ) ) : 0;
	}

	/**
	 * The product a block in a product grid stands for, or null outside
	 * one. Loaded only when a setting needs it: listing pages run these
	 * hooks with the defaults too, and the budget counts their queries.
	 *
	 * @param \WP_Block $instance Block instance.
	 */
	private static function product( \WP_Block $instance ): ?\WC_Product {
		$id      = self::product_id( $instance );
		$product = $id > 0 ? wc_get_product( $id ) : null;
		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * The card style class on a product grid.
	 *
	 * @param string               $html  Rendered block.
	 * @param array<string, mixed> $block Parsed block.
	 */
	public static function grid( string $html, array $block ): string {
		$style = self::style();
		if ( '' === $style || true !== ( $block['attrs']['query']['isProductCollectionBlock'] ?? null ) ) {
			return $html;
		}
		$tags = new \WP_HTML_Tag_Processor( $html );
		if ( ! $tags->next_tag() ) {
			return $html;
		}
		$tags->add_class( 'pergel-cards' );
		$tags->add_class( "pergel-card-$style" );
		Layout::card_style();
		return $tags->get_updated_html();
	}

	/**
	 * Brand and category above the name, the rating under it.
	 *
	 * @param string    $html     Rendered title.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @phpstan-param array<string, mixed> $block
	 */
	public static function title( string $html, array $block, \WP_Block $instance ): string {
		$brand    = true === Settings::get( 'shop.card_brand' );
		$category = true === Settings::get( 'shop.card_category' );
		$rating   = true === Settings::get( 'shop.card_rating' );
		$id       = $brand || $category || $rating ? self::product_id( $instance ) : 0;
		if ( 0 === $id ) {
			return $html;
		}
		$meta = array();
		if ( $brand ) {
			$meta[] = self::term( $id, 'product_brand', false );
		}
		if ( $category ) {
			$meta[] = self::term( $id, 'product_cat', true );
		}
		$meta    = array_filter( $meta );
		$before  = $meta ? '<div class="pergel-card-meta">' . implode( '', $meta ) . '</div>' : '';
		$after   = '';
		$product = $rating ? self::product( $instance ) : null;
		if ( null !== $product && $product->get_review_count() > 0 ) {
			$after = ( new \WP_Block(
				array(
					'blockName'    => 'woocommerce/product-rating',
					'attrs'        => array(
						'isDescendentOfQueryLoop' => true,
						'className'               => 'pergel-card-rating',
					),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				),
				$instance->context
			) )->render();
		}
		if ( '' === $before && '' === $after ) {
			return $html;
		}
		Layout::card_style();
		return $before . $html . $after;
	}

	/**
	 * The first term of a product, as a link or as text. The default
	 * category is left out.
	 *
	 * @param int    $id       Product id.
	 * @param string $taxonomy Taxonomy.
	 * @param bool   $link     Whether to link to the term.
	 */
	private static function term( int $id, string $taxonomy, bool $link ): string {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}
		$terms   = get_the_terms( $id, $taxonomy );
		$default = 'product_cat' === $taxonomy ? (int) get_option( 'default_product_cat', 0 ) : 0;
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( $term->term_id === $default ) {
				continue;
			}
			$url = $link ? get_term_link( $term ) : '';
			return is_string( $url ) && '' !== $url
				? sprintf( '<a class="pergel-card-meta__item" href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $term->name ) )
				: sprintf( '<span class="pergel-card-meta__item">%s</span>', esc_html( $term->name ) );
		}
		return '';
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
		if ( true !== Settings::get( 'shop.card_discount' ) && true !== Settings::get( 'shop.card_stock' ) ) {
			return $html;
		}
		$product = self::product( $instance );
		if ( null === $product || '' === trim( $html ) ) {
			return $html;
		}
		$pills = '';
		if ( true === Settings::get( 'shop.card_discount' ) ) {
			$percent = self::discount( $product );
			if ( $percent > 0 ) {
				$pills .= sprintf(
					'<span class="pergel-card-pill is-discount">%1$s<span class="screen-reader-text"> %2$s</span></span>',
					/* translators: %d: discount in per cent. */
					esc_html( sprintf( __( '-%d%%', 'pergel-engine' ), $percent ) ),
					esc_html__( 'discount', 'pergel-engine' )
				);
			}
		}
		if ( true === Settings::get( 'shop.card_stock' ) ) {
			$pills .= self::stock( $product );
		}
		if ( '' === $pills ) {
			return $html;
		}
		Layout::card_style();
		return $html . '<div class="pergel-card-pills">' . $pills . '</div>';
	}

	/**
	 * The discount in whole per cent; for products with options, the
	 * largest one. 0 when the product is not on sale.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function discount( \WC_Product $product ): int {
		if ( ! $product->is_on_sale() ) {
			return 0;
		}
		$pairs = array();
		if ( $product instanceof \WC_Product_Variable ) {
			$prices = $product->get_variation_prices();
			foreach ( $prices['regular_price'] as $id => $regular ) {
				$pairs[] = array( (float) $regular, (float) ( $prices['sale_price'][ $id ] ?? $regular ) );
			}
		} else {
			$pairs[] = array( (float) $product->get_regular_price(), (float) $product->get_sale_price() );
		}
		$most = 0;
		foreach ( $pairs as [ $regular, $sale ] ) {
			if ( $regular > 0 && $sale > 0 && $sale < $regular ) {
				$most = max( $most, (int) round( ( $regular - $sale ) / $regular * 100 ) );
			}
		}
		return $most;
	}

	/**
	 * How many are left, or "Out of stock".
	 *
	 * @param \WC_Product $product Product.
	 */
	private static function stock( \WC_Product $product ): string {
		if ( ! $product->is_in_stock() ) {
			return sprintf( '<span class="pergel-card-pill is-out">%s</span>', esc_html__( 'Out of stock', 'pergel-engine' ) );
		}
		$left = $product->managing_stock() ? (int) $product->get_stock_quantity() : 0;
		if ( $left <= 0 ) {
			return '';
		}
		return sprintf(
			'<span class="pergel-card-pill is-stock">%s</span>',
			/* translators: %s: number of products left. */
			esc_html( sprintf( _n( '%s in stock', '%s in stock', $left, 'pergel-engine' ), number_format_i18n( $left ) ) )
		);
	}

	/**
	 * Badges on the picture, the second picture, and on the "buttons on
	 * the picture" card the wishlist and compare buttons.
	 *
	 * @param string    $html     Rendered picture.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @phpstan-param array<string, mixed> $block
	 */
	public static function image( string $html, array $block, \WP_Block $instance ): string {
		$id = '' === $html ? 0 : self::product_id( $instance );
		if ( 0 === $id ) {
			return $html;
		}
		$days    = Settings::get( 'shop.badge_new' );
		$needed  = 'template' !== Settings::get( 'shop.badge_sale' )
			|| ( is_int( $days ) && $days > 0 )
			|| true === Settings::get( 'shop.badge_featured' )
			|| true === Settings::get( 'shop.badge_sold_out' )
			|| true === Settings::get( 'shop.card_hover' );
		$product = $needed ? self::product( $instance ) : null;
		// The picture draws its own sale badge when it has no sale badge
		// block, so the setting applies here as well as in sale_badge().
		if ( null !== $product ) {
			$html = self::sale_text( $html, $product );
		}
		$changed = false;
		$badges  = self::badges( $id, $product );
		if ( '' !== $badges ) {
			$html    = self::after_opening( $html, 'wc-block-components-product-image__inner-container', $badges );
			$changed = true;
		}
		if ( null !== $product && true === Settings::get( 'shop.card_hover' ) ) {
			$second = self::second_picture( $product );
			if ( '' !== $second ) {
				$html    = (string) preg_replace( '#(<img\b[^>]*>)#', '$1' . str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $second ), $html, 1 );
				$html    = self::add_class( $html, 'has-pergel-second-picture' );
				$changed = true;
			}
		}
		if ( 'overlay' === self::style() ) {
			$actions = self::render_actions( $instance );
			if ( '' !== $actions ) {
				// After the link: buttons must not sit inside it.
				$end     = strrpos( $html, '</div>' );
				$html    = false === $end ? $html : substr( $html, 0, $end ) . $actions . substr( $html, $end );
				$changed = true;
			}
		}
		if ( $changed ) {
			Layout::card_style();
		}
		return $html;
	}

	/**
	 * Inserts markup right after the opening tag of the element with a
	 * class, or after the first tag.
	 *
	 * @param string $html   Markup.
	 * @param string $name   Class of the element.
	 * @param string $insert Markup to insert.
	 */
	private static function after_opening( string $html, string $name, string $insert ): string {
		$pattern = '#<[a-z]+\b[^>]*\bclass="[^"]*\b' . preg_quote( $name, '#' ) . '\b[^"]*"[^>]*>#';
		if ( ! preg_match( $pattern, $html, $match, PREG_OFFSET_CAPTURE ) ) {
			$pattern = '#^\s*<[a-z]+\b[^>]*>#';
			if ( ! preg_match( $pattern, $html, $match, PREG_OFFSET_CAPTURE ) ) {
				return $html;
			}
		}
		$end = $match[0][1] + strlen( $match[0][0] );
		return substr( $html, 0, $end ) . $insert . substr( $html, $end );
	}

	/**
	 * Adds a class to the first tag.
	 *
	 * @param string $html Markup.
	 * @param string $name Class name.
	 */
	private static function add_class( string $html, string $name ): string {
		$tags = new \WP_HTML_Tag_Processor( $html );
		if ( ! $tags->next_tag() ) {
			return $html;
		}
		$tags->add_class( $name );
		return $tags->get_updated_html();
	}

	/**
	 * The badges of a product: its own one (from the meta the main query
	 * has already loaded), then the ones the settings turn on, which need
	 * the product.
	 *
	 * @param int              $id      Product id.
	 * @param \WC_Product|null $product Product, when a setting needs it.
	 */
	public static function badges( int $id, ?\WC_Product $product ): string {
		$badges = array();
		$own    = trim( (string) get_post_meta( $id, self::BADGE_META, true ) );
		if ( '' !== $own ) {
			$badges['own'] = $own;
		}
		if ( null === $product ) {
			return self::badge_list( $badges );
		}
		$days = Settings::get( 'shop.badge_new' );
		if ( is_int( $days ) && $days > 0 ) {
			$created = $product->get_date_created();
			if ( null !== $created && $created->getTimestamp() >= time() - $days * DAY_IN_SECONDS ) {
				$badges['new'] = __( 'New', 'pergel-engine' );
			}
		}
		if ( true === Settings::get( 'shop.badge_featured' ) && $product->is_featured() ) {
			$badges['popular'] = __( 'Popular', 'pergel-engine' );
		}
		if ( true === Settings::get( 'shop.badge_sold_out' ) && ! $product->is_in_stock() ) {
			$badges['sold-out'] = __( 'Sold out', 'pergel-engine' );
		}
		return self::badge_list( $badges );
	}

	/**
	 * Badge markup.
	 *
	 * @param array<string, string> $badges Text by kind.
	 */
	private static function badge_list( array $badges ): string {
		if ( ! $badges ) {
			return '';
		}
		$html = '';
		foreach ( $badges as $kind => $text ) {
			$html .= sprintf( '<span class="pergel-badge is-%1$s">%2$s</span>', esc_attr( $kind ), esc_html( $text ) );
		}
		return '<span class="pergel-badges">' . $html . '</span>';
	}

	/**
	 * The first gallery picture, hidden from screen readers: it repeats
	 * the product.
	 *
	 * @param \WC_Product $product Product.
	 */
	private static function second_picture( \WC_Product $product ): string {
		$gallery = $product->get_gallery_image_ids();
		$id      = (int) ( $gallery[0] ?? 0 );
		if ( $id <= 0 ) {
			return '';
		}
		return wp_get_attachment_image(
			$id,
			'woocommerce_thumbnail',
			false,
			array(
				'class'       => 'pergel-second-picture',
				'alt'         => '',
				'loading'     => 'lazy',
				'decoding'    => 'async',
				'aria-hidden' => 'true',
			)
		);
	}

	/**
	 * The sale badge in per cent, or none.
	 *
	 * @param string    $html     Rendered sale badge.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @phpstan-param array<string, mixed> $block
	 */
	public static function sale_badge( string $html, array $block, \WP_Block $instance ): string {
		if ( 'template' === Settings::get( 'shop.badge_sale' ) ) {
			return $html;
		}
		$product = self::product( $instance );
		return null === $product ? $html : self::sale_text( $html, $product );
	}

	/**
	 * Every WooCommerce sale badge in some markup: its text in per cent, or
	 * gone.
	 *
	 * @param string      $html    Markup.
	 * @param \WC_Product $product Product.
	 */
	private static function sale_text( string $html, \WC_Product $product ): string {
		$mode = Settings::get( 'shop.badge_sale' );
		if ( ( 'none' !== $mode && 'percent' !== $mode ) || ! str_contains( $html, 'wc-block-components-product-sale-badge' ) ) {
			return $html;
		}
		if ( 'none' === $mode ) {
			// The badge holds only text spans.
			return (string) preg_replace( '#<div class="wc-block-components-product-sale-badge\b[^"]*"[^>]*>.*?</div>#s', '', $html );
		}
		$percent = self::discount( $product );
		if ( $percent <= 0 ) {
			return $html;
		}
		return (string) preg_replace(
			'#(<span class="wc-block-components-product-sale-badge__text"[^>]*>)[^<]*(</span>)#',
			'${1}' . esc_html( sprintf( /* translators: %d: discount in per cent. */ __( '-%d%%', 'pergel-engine' ), $percent ) ) . '${2}',
			$html
		);
	}

	/**
	 * On the "buttons on the picture" card the wishlist and compare buttons
	 * move into the picture (see image()).
	 *
	 * @param string    $html     Rendered buttons.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @phpstan-param array<string, mixed> $block
	 */
	public static function actions( string $html, array $block, \WP_Block $instance ): string {
		if ( empty( $block['attrs']['pergelOnPicture'] ) && 'overlay' === self::style() && 0 !== self::product_id( $instance ) ) {
			return '';
		}
		return $html;
	}

	/**
	 * The wishlist and compare buttons for the picture.
	 *
	 * @param \WP_Block $instance The picture block.
	 */
	private static function render_actions( \WP_Block $instance ): string {
		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( 'pergel/product-actions' ) ) {
			return '';
		}
		$html = ( new \WP_Block(
			array(
				'blockName'    => 'pergel/product-actions',
				'attrs'        => array(
					'pergelOnPicture' => true,
					'className'       => 'pergel-card-actions',
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			$instance->context
		) )->render();
		return trim( $html );
	}
}
