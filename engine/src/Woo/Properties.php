<?php
/**
 * Product specifications in the structured data.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Seo\Facts;

/**
 * The rows of a product's Specifications tables become additionalProperty
 * values on its Product node: WooCommerce's own, and the one an SEO plugin
 * prints instead. A node that already lists properties keeps them.
 */
final class Properties {

	/**
	 * PropertyValue nodes by product id.
	 *
	 * @var array<int, list<array{'@type': string, name: string, value: string}>>
	 */
	private static array $values = array();

	/**
	 * Adds the hooks.
	 */
	public static function register(): void {
		add_filter( 'woocommerce_structured_data_product', array( self::class, 'structured_data' ), 30, 2 );
		add_filter( 'rank_math/json_ld', array( self::class, 'graph' ), 110 );
		add_filter( 'wpseo_schema_graph', array( self::class, 'graph' ), 110 );
		add_filter( 'aioseo_schema_output', array( self::class, 'graph' ), 110 );
	}

	/**
	 * Filter for woocommerce_structured_data_product.
	 *
	 * @param mixed $markup  Product node.
	 * @param mixed $product Product.
	 */
	public static function structured_data( mixed $markup, mixed $product ): mixed {
		return $product instanceof \WC_Product ? Facts::add_properties( $markup, self::values( $product->get_id() ) ) : $markup;
	}

	/**
	 * Graph filter of an SEO plugin, on a product's page.
	 *
	 * @param mixed $graph Graph nodes.
	 */
	public static function graph( mixed $graph ): mixed {
		return is_singular( 'product' ) ? Facts::add_properties( $graph, self::values( get_queried_object_id() ) ) : $graph;
	}

	/**
	 * PropertyValue nodes of a product.
	 *
	 * @param int $id Product id.
	 * @return list<array{'@type': string, name: string, value: string}>
	 */
	public static function values( int $id ): array {
		if ( ! isset( self::$values[ $id ] ) ) {
			$post                = get_post( $id );
			self::$values[ $id ] = $post instanceof \WP_Post && 'product' === $post->post_type && ! post_password_required( $post ) && str_contains( $post->post_content, Facts::SPECS )
				? Facts::property_values( Facts::properties( parse_blocks( $post->post_content ) ) )
				: array();
		}
		return self::$values[ $id ];
	}

	/**
	 * Drops the cached values. For tests.
	 */
	public static function flush(): void {
		self::$values = array();
	}
}
