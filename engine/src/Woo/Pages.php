<?php
/**
 * The cart, checkout and account pages in menus and the sitemap.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * The cart, checkout and account pages are never in the core sitemap:
 * WooCommerce keeps them out of search results but not out of
 * wp-sitemap.xml. When nothing sells to the visitor, the cart and checkout
 * pages also leave the menus: navigation links, page lists and classic
 * menus.
 */
final class Pages {

	/**
	 * Adds the hooks.
	 */
	public static function register(): void {
		add_filter( 'wp_sitemaps_posts_query_args', array( self::class, 'sitemap' ), 10, 2 );
		if ( ! is_admin() ) {
			add_filter( 'render_block_core/navigation-link', array( self::class, 'link' ), 10, 2 );
			add_filter( 'get_pages', array( self::class, 'pages' ) );
			add_filter( 'wp_nav_menu_objects', array( self::class, 'menu_items' ) );
		}
	}

	/**
	 * Page ids WooCommerce assigned for some roles.
	 *
	 * @param string[] $roles Page roles: cart, checkout, myaccount.
	 * @phpstan-param list<string> $roles
	 * @return list<int>
	 */
	public static function ids( array $roles ): array {
		$ids = array();
		foreach ( $roles as $role ) {
			$id = wc_get_page_id( $role );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * Pages the menus leave out for this visitor.
	 *
	 * @return list<int>
	 */
	private static function hidden(): array {
		return Modes::nothing_sells() ? self::ids( array( 'cart', 'checkout' ) ) : array();
	}

	/**
	 * Filter for wp_sitemaps_posts_query_args.
	 *
	 * @param array<string, mixed> $args      WP_Query arguments.
	 * @param string               $post_type Post type of the sitemap.
	 * @return array<string, mixed>
	 */
	public static function sitemap( array $args, string $post_type ): array {
		$ids = 'page' === $post_type ? self::ids( array( 'cart', 'checkout', 'myaccount' ) ) : array();
		if ( array() !== $ids ) {
			$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) && is_array( $args['post__not_in'] ) ? $args['post__not_in'] : array(), $ids ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Three ids; sitemap pages are cached by crawlers.
		}
		return $args;
	}

	/**
	 * Filter for a navigation link block.
	 *
	 * @param mixed $html  Block HTML.
	 * @param mixed $block Parsed block.
	 */
	public static function link( mixed $html, mixed $block ): mixed {
		$id = is_array( $block ) ? (int) ( $block['attrs']['id'] ?? 0 ) : 0;
		return $id > 0 && 'post-type' === ( $block['attrs']['kind'] ?? '' ) && in_array( $id, self::hidden(), true ) ? '' : $html;
	}

	/**
	 * Filter for get_pages, which the page list block uses.
	 *
	 * @param mixed $pages Pages.
	 */
	public static function pages( mixed $pages ): mixed {
		$hidden = is_array( $pages ) && array() !== $pages ? self::hidden() : array();
		if ( array() === $hidden ) {
			return $pages;
		}
		return array_values( array_filter( (array) $pages, static fn( $page ): bool => ! $page instanceof \WP_Post || ! in_array( $page->ID, $hidden, true ) ) );
	}

	/**
	 * Filter for wp_nav_menu_objects.
	 *
	 * @param mixed $items Menu items.
	 */
	public static function menu_items( mixed $items ): mixed {
		$hidden = is_array( $items ) && array() !== $items ? self::hidden() : array();
		if ( array() === $hidden ) {
			return $items;
		}
		return array_values(
			array_filter(
				(array) $items,
				static fn( $item ): bool => ! is_object( $item ) || 'page' !== ( $item->object ?? '' ) || ! in_array( (int) ( $item->object_id ?? 0 ), $hidden, true )
			)
		);
	}
}
