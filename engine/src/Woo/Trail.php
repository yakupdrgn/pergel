<?php
/**
 * Shop steps in Pergel's breadcrumb trail.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Seo\Head;

/**
 * Products and product categories, tags and attributes sit under the shop
 * page: Home › Shop › Category › Product. A product takes the deepest of its
 * categories, the one WooCommerce's own breadcrumb picks. When the shop is
 * the front page, Home is the shop and the step is left out.
 */
final class Trail {

	/**
	 * Hooks the trail filter.
	 */
	public static function register(): void {
		add_filter( 'pergel/seo/crumbs', array( self::class, 'crumbs' ) );
	}

	/**
	 * Filter for pergel/seo/crumbs.
	 *
	 * @param mixed $crumbs Steps, home first.
	 * @return mixed
	 */
	public static function crumbs( mixed $crumbs ): mixed {
		if ( ! is_array( $crumbs ) || count( $crumbs ) < 2 ) {
			return $crumbs;
		}
		$home = $crumbs[0];
		$last = $crumbs[ count( $crumbs ) - 1 ];
		$shop = self::shop();

		if ( is_singular( 'product' ) ) {
			return array_merge( array( $home ), $shop, self::category_trail( (int) get_queried_object_id() ), array( $last ) );
		}
		if ( is_product_taxonomy() ) {
			return array_merge( array( $home ), $shop, array_slice( $crumbs, 1 ) );
		}
		return $crumbs;
	}

	/**
	 * The shop page as a step, or nothing when it is missing or the front page.
	 *
	 * @return list<array{name: string, url: string}>
	 */
	private static function shop(): array {
		$id = wc_get_page_id( 'shop' );
		if ( $id <= 0 || 'publish' !== get_post_status( $id ) || (int) get_option( 'page_on_front' ) === $id ) {
			return array();
		}
		return array(
			array(
				'name' => get_the_title( $id ),
				'url'  => (string) get_permalink( $id ),
			),
		);
	}

	/**
	 * The product's deepest category with its parents.
	 *
	 * @param int $product Product id.
	 * @return list<array{name: string, url: string}>
	 */
	private static function category_trail( int $product ): array {
		$terms = wc_get_product_terms(
			$product,
			'product_cat',
			/** This filter is documented in WooCommerce's includes/class-wc-breadcrumb.php. */
			apply_filters(
				'woocommerce_breadcrumb_product_terms_args', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's filter, so the trail matches its breadcrumb.
				array(
					'orderby' => 'parent',
					'order'   => 'DESC',
				)
			)
		);
		$term = $terms[0] ?? null;
		return $term instanceof \WP_Term ? Head::term_trail( $term ) : array();
	}
}
