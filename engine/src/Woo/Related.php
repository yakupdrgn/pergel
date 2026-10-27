<?php
/**
 * Related products, the comparison with similar products and recently
 * viewed ones.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

/**
 * Applies the "Related and recently viewed" settings to the related
 * products of the product page (the Product Collection block with
 * WooCommerce's "related" collection), while the template renders; the
 * template stays a plain block template (K10):
 *
 * - how many related products show and their title;
 * - "Grid" or "Carousel": the carousel is WooCommerce's own layout, built
 *   as its editor builds it (the heading and the previous / next buttons in
 *   a row, the products in one scrolling row);
 * - the comparison table above them: the product beside its upsells, then
 *   products of its category with the nearest price;
 * - the products the visitor viewed last, below them. Their IDs stay in the
 *   visitor's browser; the script asks the route below for their cards, so a
 *   cached page works. The Recently viewed block shows the same list
 *   anywhere.
 *
 * Without a related products block in the template, the table and the
 * recently viewed list have nothing to stand beside; the block still works.
 * Layout loads this class only on product pages where one of these
 * settings differs from its default, in the block, and in the route.
 */
final class Related {

	use LookValues;

	public const COLLECTION = 'woocommerce/product-collection/related';

	public const BLOCK = 'pergel/recently-viewed';

	/**
	 * Most products the browser remembers and the route draws.
	 */
	public const LIMIT = 12;

	/**
	 * WooCommerce's previous / next buttons, which its carousel uses.
	 */
	private const BUTTONS = 'woocommerce/product-gallery-large-image-next-previous';

	/**
	 * Products the similar ones are picked from, nearest price first.
	 */
	private const CANDIDATES = 24;

	/**
	 * The product on screen; 0 elsewhere.
	 *
	 * @var int
	 */
	private static int $product = 0;

	/**
	 * Adds the hooks. Called on wp.
	 *
	 * @param int $product The product on screen.
	 */
	public static function register( int $product ): void {
		self::$product = $product;
		add_filter( 'render_block_data', array( self::class, 'collection' ) );
		add_filter( 'render_block_woocommerce/product-collection', array( self::class, 'around' ), 10, 2 );
		if ( self::banner_id() > 0 ) {
			add_filter( 'render_block_woocommerce/product-template', array( self::class, 'banner' ), 10, 3 );
		}
		if ( 'off' !== Settings::get( 'shop.related_recent' ) ) {
			self::script();
		}
	}

	/**
	 * Whether a parsed block is the related products of the product on
	 * screen.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 */
	private static function related( array $block ): bool {
		return 'woocommerce/product-collection' === ( $block['blockName'] ?? '' )
			&& self::COLLECTION === ( $block['attrs']['collection'] ?? '' )
			&& self::$product > 0
			&& get_queried_object_id() === self::$product;
	}

	/**
	 * The number, the title and the layout, in the block before it renders.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return array<string, mixed>
	 */
	public static function collection( array $block ): array {
		if ( ! self::related( $block ) ) {
			return $block;
		}
		$count = Layout::get( 'shop.related_count' );
		$query = is_array( $block['attrs']['query'] ?? null ) ? $block['attrs']['query'] : array();
		if ( is_int( $count ) && $count > 0 ) {
			$query['perPage'] = $count;
		}
		// The banner takes the place of one product.
		if ( self::banner_id() > 0 ) {
			$query['perPage'] = max( 1, (int) ( $query['perPage'] ?? 4 ) - 1 );
		}
		if ( $query ) {
			$block['attrs']['query'] = $query;
		}
		$title = Layout::get( 'shop.related_title' );
		if ( is_string( $title ) && '' !== $title ) {
			$block = self::retitle( $block, $title );
		}
		$style  = Layout::get( 'shop.related_style' );
		$layout = is_array( $block['attrs']['displayLayout'] ?? null ) ? $block['attrs']['displayLayout'] : array();
		if ( 'grid' === $style ) {
			$block['attrs']['displayLayout'] = array_merge(
				array(
					'columns'       => 4,
					'shrinkColumns' => true,
				),
				$layout,
				array( 'type' => 'flex' )
			);
		} elseif ( 'carousel' === $style && 'carousel' !== ( $layout['type'] ?? '' ) ) {
			$block['attrs']['displayLayout'] = array_merge( array( 'columns' => 4 ), $layout, array( 'type' => 'carousel' ) );
			$block                           = self::carousel( $block );
		}
		return $block;
	}

	/**
	 * The first heading in the block with another text.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @param string               $title New title.
	 * @return array<string, mixed>
	 */
	private static function retitle( array $block, string $title ): array {
		foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $index => $inner ) {
			if ( ! is_array( $inner ) || 'woocommerce/product-template' === ( $inner['blockName'] ?? '' ) ) {
				continue;
			}
			if ( 'core/heading' === ( $inner['blockName'] ?? '' ) ) {
				$text = static fn ( mixed $html ): mixed => is_string( $html ) ? preg_replace( '#(<h[1-6]\b[^>]*>).*?(</h[1-6]>)#s', '${1}' . addcslashes( esc_html( $title ), '\\$' ) . '${2}', $html, 1 ) : $html;

				$inner['innerHTML']             = $text( $inner['innerHTML'] ?? '' );
				$inner['innerContent']          = array_map( $text, (array) ( $inner['innerContent'] ?? array() ) );
				$block['innerBlocks'][ $index ] = $inner;
				return $block;
			}
			$changed = self::retitle( $inner, $title );
			if ( $changed !== $inner ) {
				$block['innerBlocks'][ $index ] = $changed;
				return $block;
			}
		}
		return $block;
	}

	/**
	 * WooCommerce's carousel, as its editor makes it when the layout is
	 * switched: the products in one row, the heading beside the previous /
	 * next buttons, no page numbers.
	 *
	 * @param array<string, mixed> $block Parsed related products block.
	 * @return array<string, mixed>
	 */
	private static function carousel( array $block ): array {
		$buttons = array(
			'blockName'    => self::BUTTONS,
			'attrs'        => array(
				'layout' => array(
					'type'     => 'flex',
					'flexWrap' => 'nowrap',
				),
			),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
		$header  = false;
		$inner   = array();
		$content = array();
		$slots   = (array) ( $block['innerContent'] ?? array() );
		$index   = 0;
		foreach ( $slots as $slot ) {
			if ( null !== $slot ) {
				$content[] = $slot;
				continue;
			}
			$child = $block['innerBlocks'][ $index++ ] ?? null;
			if ( ! is_array( $child ) || 'core/query-pagination' === ( $child['blockName'] ?? '' ) ) {
				continue;
			}
			$name = $child['blockName'] ?? '';
			if ( 'woocommerce/product-template' === $name ) {
				$child['attrs']['layout'] = array(
					'type'              => 'flex',
					'justifyContent'    => 'left',
					'verticalAlignment' => 'top',
					'flexWrap'          => 'nowrap',
					'orientation'       => 'horizontal',
				);
				if ( ! $header ) {
					$inner[]   = self::group( array( $buttons ), array( 'justifyContent' => 'right' ) );
					$content[] = null;
					$header    = true;
				}
			} elseif ( 'core/heading' === $name && ! $header ) {
				$child  = self::group( array( $child, $buttons ), array( 'justifyContent' => 'space-between' ), 'wc-product-collection-carousel-header' );
				$header = true;
			}
			$inner[]   = $child;
			$content[] = null;
		}
		$block['innerBlocks']  = $inner;
		$block['innerContent'] = $content;
		return $block;
	}

	/**
	 * A row group around blocks.
	 *
	 * @param list<array<string, mixed>> $blocks Parsed blocks.
	 * @param array<string, string>      $layout Layout settings beside the row.
	 * @param string                     $extra  Extra class.
	 * @return array<string, mixed>
	 */
	private static function group( array $blocks, array $layout, string $extra = '' ): array {
		$attrs = array(
			'layout' => array(
				'type'              => 'flex',
				'flexWrap'          => 'nowrap',
				'verticalAlignment' => 'center',
			) + $layout,
		);
		if ( '' !== $extra ) {
			$attrs['className'] = $extra;
		}
		$open = '<div class="wp-block-group' . ( '' === $extra ? '' : ' ' . $extra ) . '">';
		return array(
			'blockName'    => 'core/group',
			'attrs'        => $attrs,
			'innerBlocks'  => $blocks,
			'innerHTML'    => $open . '</div>',
			'innerContent' => array_merge( array( $open ), array_fill( 0, count( $blocks ), null ), array( '</div>' ) ),
		);
	}

	/**
	 * The comparison table before the related products and the recently
	 * viewed ones after them.
	 *
	 * @param string               $html  Rendered block.
	 * @param array<string, mixed> $block Parsed block.
	 */
	public static function around( string $html, array $block ): string {
		if ( ! self::related( $block ) ) {
			return $html;
		}
		$html    = self::box( $html );
		$product = wc_get_product( self::$product );
		$columns = Layout::get( 'shop.related_compare' );
		if ( $product instanceof \WC_Product && is_string( $columns ) && ctype_digit( $columns ) ) {
			$html = self::compare( $product, (int) $columns ) . $html;
		}
		global $_wp_current_template_content;
		$placed = is_string( $_wp_current_template_content ) && str_contains( $_wp_current_template_content, 'wp:' . self::BLOCK );
		if ( 'show' === Settings::get( 'shop.related_recent' ) && ! $placed ) {
			$html .= render_block(
				array(
					'blockName'    => self::BLOCK,
					'attrs'        => array(
						'align' => 'wide',
						'style' => array( 'spacing' => array( 'margin' => array( 'top' => 'var:preset|spacing|60' ) ) ),
					),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				)
			);
		}
		return $html;
	}

	/**
	 * The box around the related products (shop.related_bg, _edge,
	 * _radius, _padding): a class and the values that are set, as
	 * --pergel-related-* custom properties on the block.
	 *
	 * @param string $html Rendered block.
	 */
	private static function box( string $html ): string {
		$values = array(
			'bg'     => self::color( 'related_bg' ),
			'edge'   => self::color( 'related_edge' ),
			'radius' => self::px( 'related_radius' ),
			'pad'    => self::px( 'related_padding' ),
		);
		$style  = '';
		foreach ( $values as $name => $value ) {
			if ( '' !== $value ) {
				$style .= "--pergel-related-$name:$value;";
			}
		}
		$tags = new \WP_HTML_Tag_Processor( $html );
		if ( '' === $style || ! $tags->next_tag() ) {
			return $html;
		}
		$tags->add_class( 'pergel-related-box' );
		$tags->set_attribute( 'style', $style . (string) $tags->get_attribute( 'style' ) );
		wp_enqueue_style( Layout::RELATED_STYLE );
		return $tags->get_updated_html();
	}

	/**
	 * The chosen banner pattern; 0 for none.
	 */
	private static function banner_id(): int {
		$id = Layout::get( 'shop.related_banner' );
		return is_int( $id ) ? $id : 0;
	}

	/**
	 * The banner as the last card of the related products: a pattern, as
	 * wide as a card. Nothing when it is gone or not published.
	 *
	 * @param string    $html     Rendered product template (its list).
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @phpstan-param array<string, mixed> $block
	 */
	public static function banner( string $html, array $block, \WP_Block $instance ): string {
		static $drawing = false;
		$end            = strrpos( $html, '</ul>' );
		if ( $drawing || false === $end || self::COLLECTION !== ( $instance->context['collection'] ?? '' ) || get_queried_object_id() !== self::$product ) {
			return $html;
		}
		$post = get_post( self::banner_id() );
		if ( ! $post instanceof \WP_Post || 'wp_block' !== $post->post_type || 'publish' !== $post->post_status ) {
			return $html;
		}
		$drawing = true;
		$inside  = do_blocks( $post->post_content );
		$drawing = false;
		if ( '' === trim( $inside ) ) {
			return $html;
		}
		wp_enqueue_style( Layout::RELATED_STYLE );
		return substr( $html, 0, $end ) . '<li class="pergel-related-banner">' . $inside . '</li>' . substr( $html, $end );
	}

	/**
	 * The product beside similar ones, in a table: a column per product, a
	 * row per property. A row no product has a value for is left out.
	 *
	 * @param \WC_Product $product The product on screen.
	 * @param int         $columns Products in the table, the product's own among them.
	 */
	public static function compare( \WC_Product $product, int $columns ): string {
		$others = self::similar( $product, max( 1, $columns - 1 ) );
		if ( array() === $others ) {
			return '';
		}
		wp_enqueue_style( Layout::RELATED_STYLE );
		$products = array_merge( array( $product ), $others );
		$rows     = array(
			__( 'Price', 'pergel-engine' )        => static fn ( \WC_Product $item ): string => self::price( $item ),
			__( 'Rating', 'pergel-engine' )       => static fn ( \WC_Product $item ): string => ListView::rating( $item ),
			__( 'Availability', 'pergel-engine' ) => static fn ( \WC_Product $item ): string => esc_html( ListView::availability( $item ) ),
			__( 'Dimensions', 'pergel-engine' )   => static fn ( \WC_Product $item ): string => $item->has_dimensions() && is_array( $item->get_dimensions( false ) ) ? esc_html( wc_format_dimensions( $item->get_dimensions( false ) ) ) : '',
			__( 'Weight', 'pergel-engine' )       => static fn ( \WC_Product $item ): string => $item->has_weight() ? esc_html( wc_format_weight( (float) $item->get_weight() ) ) : '',
		);
		foreach ( $product->get_attributes() as $name => $attribute ) {
			if ( $attribute instanceof \WC_Product_Attribute && $attribute->get_visible() ) {
				$rows[ wc_attribute_label( $attribute->get_name(), $product ) ] = static fn ( \WC_Product $item ): string => esc_html( (string) $item->get_attribute( (string) $name ) );
			}
		}

		$id   = 'pergel-compare-' . $product->get_id();
		$head = '<tr><td></td>';
		$buy  = '<tr><th scope="row"><span class="pergel-sr">' . esc_html__( 'Buy', 'pergel-engine' ) . '</span></th>';
		foreach ( $products as $item ) {
			$current = $item->get_id() === $product->get_id();
			$link    = (string) get_permalink( $item->get_id() );
			$image   = $item->get_image(
				'woocommerce_thumbnail',
				array(
					'alt'     => '',
					'loading' => 'lazy',
				)
			);
			$name    = $current
				? '<span class="pergel-compare__name">' . esc_html( $item->get_name() ) . '</span><span class="pergel-compare__this">' . esc_html__( 'This product', 'pergel-engine' ) . '</span>'
				: '<a class="pergel-compare__name" href="' . esc_url( $link ) . '">' . esc_html( $item->get_name() ) . '</a>';
			$head   .= '<th scope="col"' . ( $current ? ' class="is-current"' : '' ) . '>'
				. ( $current ? '<span class="pergel-compare__image">' . $image . '</span>' : '<a class="pergel-compare__image" href="' . esc_url( $link ) . '" tabindex="-1" aria-hidden="true">' . $image . '</a>' )
				. $name . '</th>';
			$buy    .= '<td' . ( $current ? ' class="is-current"' : '' ) . '>' . ( $current ? '' : ListView::buy( $item ) ) . '</td>';
		}

		$body = '';
		foreach ( $rows as $label => $value ) {
			$cells = array_map( $value, $products );
			if ( array() === array_filter( $cells, static fn ( string $cell ): bool => '' !== trim( wp_strip_all_tags( $cell ) ) ) ) {
				continue;
			}
			$body .= '<tr><th scope="row">' . esc_html( (string) $label ) . '</th><td class="is-current">' . implode( '</td><td>', $cells ) . '</td></tr>';
		}

		return '<section class="pergel-compare alignwide" aria-labelledby="' . esc_attr( $id ) . '">'
			. '<h2 id="' . esc_attr( $id ) . '" class="pergel-compare__title">' . esc_html__( 'Compare with similar products', 'pergel-engine' ) . '</h2>'
			. '<div class="pergel-compare__scroll" role="region" tabindex="0" aria-label="' . esc_attr__( 'Product comparison, scrolls sideways', 'pergel-engine' ) . '">'
			. '<table class="pergel-compare__table" style="--pergel-compare-columns:' . count( $products ) . '"><caption class="pergel-sr">' . esc_html__( 'Product comparison', 'pergel-engine' ) . '</caption>'
			. '<thead>' . $head . '</tr></thead><tbody>' . $body . $buy . '</tr></tbody></table></div></section>';
	}

	/**
	 * The price with the discount beside it.
	 *
	 * @param \WC_Product $product Product.
	 */
	private static function price( \WC_Product $product ): string {
		$price = (string) $product->get_price_html();
		if ( '' === $price ) {
			return '';
		}
		$percent = Cards::discount( $product );
		if ( $percent <= 0 ) {
			return Price::html( $price );
		}
		return Price::html( $price ) . sprintf(
			' <span class="pergel-compare__discount">%1$s<span class="pergel-sr"> %2$s</span></span>',
			/* translators: %d: discount in per cent. */
			esc_html( sprintf( __( '-%d%%', 'pergel-engine' ), $percent ) ),
			esc_html__( 'discount', 'pergel-engine' )
		);
	}

	/**
	 * Products similar to the one on screen: its upsells first, then
	 * products of its category (the one the breadcrumb shows) with the
	 * nearest price. Only ones a visitor can see and buy.
	 *
	 * @param \WC_Product $product The product on screen.
	 * @param int         $limit   Most products.
	 * @return list<\WC_Product>
	 */
	public static function similar( \WC_Product $product, int $limit ): array {
		$list = array();
		$skip = array( $product->get_id() => true );
		foreach ( $product->get_upsell_ids() as $id ) {
			$other = isset( $skip[ $id ] ) ? null : wc_get_product( $id );
			if ( $other instanceof \WC_Product && self::shown( $other ) ) {
				$list[]                   = $other;
				$skip[ $other->get_id() ] = true;
			}
			if ( count( $list ) >= $limit ) {
				return $list;
			}
		}
		$term = Layout::product_category( $product->get_id() );
		if ( ! $term instanceof \WP_Term ) {
			return $list;
		}
		// The products already chosen are left out here, not in the query.
		$found = wc_get_products(
			array(
				'status'       => 'publish',
				'visibility'   => 'catalog',
				'stock_status' => 'instock',
				'category'     => array( $term->slug ),
				'limit'        => self::CANDIDATES + count( $skip ),
				'orderby'      => 'date',
				'order'        => 'DESC',
			)
		);
		$price = (float) $product->get_price();
		$near  = array();
		foreach ( is_array( $found ) ? $found : array() as $other ) {
			if ( $other instanceof \WC_Product && ! isset( $skip[ $other->get_id() ] ) && self::shown( $other ) ) {
				$near[] = $other;
			}
		}
		usort( $near, static fn ( \WC_Product $a, \WC_Product $b ): int => abs( (float) $a->get_price() - $price ) <=> abs( (float) $b->get_price() - $price ) );
		return array_merge( $list, array_slice( $near, 0, $limit - count( $list ) ) );
	}

	/**
	 * Whether a visitor can see a product and it is in stock.
	 *
	 * @param \WC_Product $product Product.
	 */
	private static function shown( \WC_Product $product ): bool {
		return 'publish' === $product->get_status() && $product->is_visible() && $product->is_in_stock();
	}

	/**
	 * The Recently viewed block: an empty box the script fills with the
	 * products the visitor viewed last, other than the one on screen;
	 * hidden while there are none.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public static function block( array $attributes ): string {
		if ( ! Layout::recent_on() || wp_is_serving_rest_request() ) {
			return wp_is_serving_rest_request()
				? '<div ' . get_block_wrapper_attributes() . '><p>' . esc_html__( 'Shown to visitors who have viewed products: the ones they looked at last. Turn it on with "Recently viewed" under Pergel → Settings → Shop.', 'pergel-engine' ) . '</p></div>'
				: '';
		}
		self::script();
		$count = is_int( $attributes['count'] ?? null ) ? max( 1, min( self::LIMIT, $attributes['count'] ) ) : 4;
		$title = is_string( $attributes['title'] ?? null ) && '' !== trim( $attributes['title'] ) ? $attributes['title'] : __( 'Recently viewed', 'pergel-engine' );
		$id    = wp_unique_id( 'pergel-recent-' );
		return '<section ' . get_block_wrapper_attributes(
			array(
				'aria-labelledby'    => $id,
				'data-pergel-recent' => (string) $count,
			)
		) . ' hidden><h2 id="' . esc_attr( $id ) . '" class="wp-block-pergel-recently-viewed__title">' . esc_html( $title ) . '</h2>'
			. '<div class="wp-block-pergel-recently-viewed__list"></div></section>';
	}

	/**
	 * Loads the script, which remembers the product on screen and fills the
	 * Recently viewed blocks.
	 */
	private static function script(): void {
		wp_enqueue_script_module( Layout::RECENT_SCRIPT );
		if ( ! has_filter( 'script_module_data_' . Layout::RECENT_SCRIPT, array( self::class, 'data' ) ) ) {
			add_filter( 'script_module_data_' . Layout::RECENT_SCRIPT, array( self::class, 'data' ) );
		}
	}

	/**
	 * What the script needs: the route, the product on screen (0 elsewhere)
	 * and how many products to remember.
	 *
	 * @param array<string, mixed> $data Module data.
	 * @return array<string, mixed>
	 */
	public static function data( array $data ): array {
		$product = is_singular( 'product' ) ? get_queried_object_id() : 0;
		return array_merge(
			$data,
			array(
				'url'     => rest_url( 'pergel/v1/recently-viewed' ),
				'product' => $product,
				'limit'   => self::LIMIT,
			)
		);
	}

	/**
	 * Registers the route. Layout calls it on rest_api_init while the
	 * setting is on.
	 */
	public static function routes(): void {
		register_rest_route(
			'pergel/v1',
			'/recently-viewed',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'ids'   => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[0-9,]*$',
					),
					'count' => array(
						'type'    => 'integer',
						'default' => 4,
						'minimum' => 1,
						'maximum' => self::LIMIT,
					),
				),
			)
		);
	}

	/**
	 * GET pergel/v1/recently-viewed: cards of the products given, in their
	 * order, without the ones a visitor cannot see.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 */
	public static function show( \WP_REST_Request $request ): \WP_REST_Response {
		$ids      = array_slice( array_values( array_unique( array_filter( array_map( 'intval', explode( ',', (string) $request->get_param( 'ids' ) ) ) ) ) ), 0, self::LIMIT );
		$count    = (int) $request->get_param( 'count' );
		$products = array();
		foreach ( $ids as $id ) {
			$product = 'product' === get_post_type( $id ) ? wc_get_product( $id ) : null;
			if ( $product instanceof \WC_Product && 'publish' === $product->get_status() && $product->is_visible() ) {
				$products[] = $product;
			}
		}
		return new \WP_REST_Response(
			array(
				'ids'  => array_map( static fn ( \WC_Product $product ): int => $product->get_id(), $products ),
				'html' => self::cards( array_slice( $products, 0, $count ) ),
			)
		);
	}

	/**
	 * Small product cards: picture, name, price.
	 *
	 * @param array<int, \WC_Product> $products Products.
	 * @phpstan-param list<\WC_Product> $products
	 */
	public static function cards( array $products ): string {
		if ( array() === $products ) {
			return '';
		}
		$items = '';
		foreach ( $products as $product ) {
			$link   = (string) get_permalink( $product->get_id() );
			$price  = (string) $product->get_price_html();
			$items .= '<li class="wp-block-pergel-recently-viewed__item">'
				. '<a class="wp-block-pergel-recently-viewed__image" href="' . esc_url( $link ) . '" tabindex="-1" aria-hidden="true">'
				. $product->get_image(
					'woocommerce_thumbnail',
					array(
						'alt'     => '',
						'loading' => 'lazy',
					)
				) . '</a>'
				. '<a class="wp-block-pergel-recently-viewed__name" href="' . esc_url( $link ) . '">' . esc_html( $product->get_name() ) . '</a>'
				. ( '' !== $price ? '<span class="wp-block-pergel-recently-viewed__price">' . Price::html( $price ) . '</span>' : '' )
				. '</li>';
		}
		return '<ul class="wp-block-pergel-recently-viewed__items" role="list">' . $items . '</ul>';
	}
}
