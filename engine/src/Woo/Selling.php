<?php
/**
 * Prices and the cart under the sales modes.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * Where a product is not sold to the visitor (see Modes), its prices are
 * removed at the data level: the product getters, the variation price
 * ranges, the price HTML, the Store API, WooCommerce's structured data and
 * the graph of an SEO plugin all receive no price, and the product cannot
 * be added to the cart. Listings that hide prices drop the price filter and
 * the price sorting, so prices cannot be read back by narrowing a list.
 * Products that are not for sale stay out of the Google for WooCommerce
 * feed.
 */
final class Selling {

	/**
	 * Price properties with view-context getters.
	 */
	private const PROPS = array( 'price', 'regular_price', 'sale_price' );

	/**
	 * Request parameters that filter or sort by price.
	 */
	private const PRICE_PARAMS = array( 'min_price', 'max_price' );

	/**
	 * Adds the hooks.
	 */
	public static function register(): void {
		add_filter( 'woocommerce_is_purchasable', array( self::class, 'purchasable' ), 20, 2 );
		add_filter( 'woocommerce_variation_is_purchasable', array( self::class, 'purchasable' ), 20, 2 );
		foreach ( self::PROPS as $prop ) {
			add_filter( "woocommerce_product_get_{$prop}", array( self::class, 'price' ), 20, 2 );
			add_filter( "woocommerce_product_variation_get_{$prop}", array( self::class, 'price' ), 20, 2 );
			add_filter( "woocommerce_variation_prices_{$prop}", array( self::class, 'variation_price' ), 20, 3 );
		}
		add_filter( 'woocommerce_get_variation_prices_hash', array( self::class, 'prices_hash' ), 20, 2 );
		add_filter( 'woocommerce_get_price_html', array( self::class, 'price_html' ), 20, 2 );
		add_filter( 'woocommerce_variation_is_visible', array( self::class, 'variation_visible' ), 20, 4 );
		add_filter( 'woocommerce_structured_data_product', array( self::class, 'structured_data' ), 20, 2 );
		// The Product nodes of SEO plugins that print their own.
		add_filter( 'rank_math/json_ld', array( self::class, 'graph' ), 120 );
		add_filter( 'wpseo_schema_graph', array( self::class, 'graph' ), 120 );
		add_filter( 'aioseo_schema_output', array( self::class, 'graph' ), 120 );
		add_filter( 'woocommerce_gla_get_sync_ready_products_pre_filter', array( self::class, 'feed' ) );
		add_filter( 'rest_request_before_callbacks', array( self::class, 'store_api' ), 10, 3 );
		if ( ! is_admin() ) {
			add_action( 'parse_request', array( self::class, 'request' ) );
			add_filter( 'woocommerce_default_catalog_orderby', array( self::class, 'default_orderby' ), 20 );
			add_filter( 'woocommerce_catalog_orderby', array( self::class, 'orderby_options' ), 20 );
			add_filter( 'render_block_woocommerce/product-filter-price', array( self::class, 'price_filter' ), 20 );
			add_filter( 'render_block_woocommerce/price-filter', array( self::class, 'price_filter' ), 20 );
			add_filter( 'render_block_woocommerce/add-to-cart-with-options', array( self::class, 'cart_form' ), 20, 3 );
			add_filter( 'render_block_woocommerce/add-to-cart-form', array( self::class, 'cart_form' ), 20, 3 );
		}
	}

	/**
	 * Filter for woocommerce_is_purchasable and the variation filter.
	 *
	 * @param mixed $purchasable Whether the product can be bought.
	 * @param mixed $product     Product.
	 */
	public static function purchasable( mixed $purchasable, mixed $product ): mixed {
		return $product instanceof \WC_Product && Modes::hidden( $product ) ? false : $purchasable;
	}

	/**
	 * Filter for the view-context price getters.
	 *
	 * @param mixed $price   Price.
	 * @param mixed $product Product.
	 */
	public static function price( mixed $price, mixed $product ): mixed {
		return $product instanceof \WC_Product && Modes::hidden( $product ) ? '' : $price;
	}

	/**
	 * Filter for the prices of a variable product's price range. A variation
	 * without a price is left out of the range.
	 *
	 * @param mixed $price     Price.
	 * @param mixed $variation Variation.
	 * @param mixed $product   Variable product.
	 */
	public static function variation_price( mixed $price, mixed $variation, mixed $product ): mixed {
		return $product instanceof \WC_Product && Modes::hidden( $product ) ? '' : $price;
	}

	/**
	 * Keeps the cached price range of a hidden product apart from the
	 * visible one.
	 *
	 * @param mixed $hash    Parts of the cache key.
	 * @param mixed $product Variable product.
	 */
	public static function prices_hash( mixed $hash, mixed $product ): mixed {
		if ( is_array( $hash ) && $product instanceof \WC_Product && Modes::hidden( $product ) ) {
			$hash[] = 'pergel-no-price';
		}
		return $hash;
	}

	/**
	 * Filter for woocommerce_get_price_html.
	 *
	 * @param mixed $html    Price HTML.
	 * @param mixed $product Product.
	 */
	public static function price_html( mixed $html, mixed $product ): mixed {
		return $product instanceof \WC_Product && Modes::hidden( $product ) ? '' : $html;
	}

	/**
	 * A variation without a price is hidden by WooCommerce; under a mode
	 * that hides prices it stays selectable.
	 *
	 * @param mixed $visible   Whether the variation is shown.
	 * @param mixed $id        Variation id.
	 * @param mixed $parent_id Product id.
	 * @param mixed $variation Variation.
	 */
	public static function variation_visible( mixed $visible, mixed $id, mixed $parent_id, mixed $variation ): mixed {
		if ( $variation instanceof \WC_Product && Modes::hidden( $variation ) ) {
			return 'publish' === get_post_status( $variation->get_id() );
		}
		return $visible;
	}

	/**
	 * Filter for woocommerce_structured_data_product: no offers.
	 *
	 * @param mixed $markup  Product node.
	 * @param mixed $product Product.
	 */
	public static function structured_data( mixed $markup, mixed $product ): mixed {
		return $product instanceof \WC_Product && Modes::hidden( $product ) ? self::without_offers( $markup ) : $markup;
	}

	/**
	 * Graph filter of an SEO plugin: no offers on a hidden product's page.
	 *
	 * @param mixed $graph Graph nodes.
	 */
	public static function graph( mixed $graph ): mixed {
		if ( ! is_array( $graph ) || ! is_singular( 'product' ) ) {
			return $graph;
		}
		$product = wc_get_product( get_queried_object_id() );
		return $product instanceof \WC_Product && Modes::hidden( $product ) ? self::without_offers( $graph ) : $graph;
	}

	/**
	 * Removes every offers property.
	 *
	 * @param mixed $data Structured data.
	 */
	public static function without_offers( mixed $data ): mixed {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		unset( $data['offers'] );
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = self::without_offers( $value );
			}
		}
		return $data;
	}

	/**
	 * Google for WooCommerce: only products that are for sale to everyone.
	 * Products sent before their mode changed stay in Merchant Center until
	 * they are removed there.
	 *
	 * @param mixed $products Products about to be synced.
	 */
	public static function feed( mixed $products ): mixed {
		if ( ! is_array( $products ) ) {
			return $products;
		}
		return array_values(
			array_filter(
				$products,
				static fn( $product ): bool => ! $product instanceof \WC_Product || Modes::SALE === Modes::of( $product )
			)
		);
	}

	/**
	 * Whether the listing of this request hides prices: the product
	 * category on screen, or the shop.
	 *
	 * @param string $category Category slug; '' for the queried one.
	 */
	public static function listing_hidden( string $category = '' ): bool {
		if ( ! Modes::enforced() ) {
			return false;
		}
		if ( '' === $category && is_product_category() ) {
			$term     = get_queried_object();
			$category = $term instanceof \WP_Term ? $term->slug : '';
		}
		return ! Modes::sells( '' === $category ? Modes::site() : Modes::of_category( $category ) );
	}

	/**
	 * Drops the price filter and the price sorting from the request before
	 * WooCommerce reads them.
	 *
	 * @param \WP $wp Current request.
	 */
	public static function request( \WP $wp ): void {
		$category = $wp->query_vars['product_cat'] ?? '';
		$category = is_string( $category ) ? basename( $category ) : '';
		if ( ! self::listing_hidden( $category ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Removes, reads nothing.
		foreach ( self::PRICE_PARAMS as $param ) {
			unset( $wp->query_vars[ $param ], $_GET[ $param ], $_REQUEST[ $param ] );
		}
		if ( isset( $_GET['orderby'] ) && is_string( $_GET['orderby'] ) && str_starts_with( $_GET['orderby'], 'price' ) ) {
			unset( $_GET['orderby'], $_REQUEST['orderby'] );
		}
		// phpcs:enable
	}

	/**
	 * A price sorting chosen as the shop default falls back to the custom
	 * order where prices are hidden.
	 *
	 * @param mixed $orderby Default sorting.
	 */
	public static function default_orderby( mixed $orderby ): mixed {
		return is_string( $orderby ) && str_starts_with( $orderby, 'price' ) && self::listing_hidden() ? 'menu_order' : $orderby;
	}

	/**
	 * Removes the price sortings from the sorting menu.
	 *
	 * @param mixed $options Sorting options.
	 */
	public static function orderby_options( mixed $options ): mixed {
		if ( is_array( $options ) && self::listing_hidden() ) {
			unset( $options['price'], $options['price-desc'] );
		}
		return $options;
	}

	/**
	 * Hides the price filter blocks.
	 *
	 * @param mixed $html Block HTML.
	 */
	public static function price_filter( mixed $html ): mixed {
		return self::listing_hidden() ? '' : $html;
	}

	/**
	 * Removes the add to cart form of a product that is not sold to the
	 * visitor. Without a button it would be an empty form, and a variable
	 * product would be shown as out of stock, as none of its variations can
	 * be bought.
	 *
	 * @param mixed $html     Block HTML.
	 * @param mixed $block    Parsed block.
	 * @param mixed $instance Block instance.
	 */
	public static function cart_form( mixed $html, mixed $block, mixed $instance = null ): mixed {
		$id = $instance instanceof \WP_Block ? (int) ( $instance->context['postId'] ?? 0 ) : 0;
		$id = $id > 0 ? $id : (int) get_the_ID();
		return $id > 0 && 'product' === get_post_type( $id ) && Modes::hidden( $id ) ? '' : $html;
	}

	/**
	 * Store API product lists: no price filter, price sorting or price
	 * range while the shop hides prices.
	 *
	 * @param mixed            $response Response so far; passed through.
	 * @param mixed            $handler  Route handler.
	 * @param \WP_REST_Request $request  Request.
	 */
	public static function store_api( mixed $response, mixed $handler, \WP_REST_Request $request ): mixed {
		$route = $request->get_route();
		if ( ! str_starts_with( $route, '/wc/store/' ) || ! str_contains( $route, '/products' ) || ! self::listing_hidden() ) {
			return $response;
		}
		foreach ( self::PRICE_PARAMS as $param ) {
			$request->set_param( $param, null );
		}
		$request->set_param( 'calculate_price_range', false );
		if ( 'price' === $request->get_param( 'orderby' ) ) {
			$request->set_param( 'orderby', 'menu_order' );
		}
		return $response;
	}
}
