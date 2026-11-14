<?php
/**
 * Listing pages past the last one.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Seo;

/**
 * Sends a listing page past the last one (…/page/3/ of a category with
 * products for two pages) to the last page, 301, instead of a 404. The
 * listing shrinks when products leave it or more fit on a page, and its
 * old page addresses stay in search results and links. Module loads this
 * class only on such a 404; redirects of an SEO plugin run before it (on
 * `wp`).
 */
final class Paging {

	/**
	 * Filter: the address a page past the last one is sent to.
	 */
	public const FILTER = 'pergel/seo/past_last_page';

	/**
	 * Redirects and stops when the 404 shown is a page past the last one.
	 */
	public static function redirect(): void {
		$to = self::target();
		if ( '' !== $to && wp_safe_redirect( $to, 301, 'Pergel' ) ) {
			exit;
		}
	}

	/**
	 * The address of the listing's last page when the main query is a 404
	 * past it; '' otherwise (no such listing, or one without any posts).
	 */
	public static function target(): string {
		global $wp_query;
		if ( ! $wp_query instanceof \WP_Query || ! $wp_query->is_404() ) {
			return '';
		}
		$paged = (int) $wp_query->get( 'paged' );
		if ( $paged < 2 ) {
			return '';
		}
		$last = self::last_page( $wp_query );
		if ( $last < 1 || $last >= $paged ) {
			return '';
		}
		$to = (string) get_pagenum_link( $last, false );

		/**
		 * Filters the address a listing page past the last one is sent to.
		 *
		 * @param string $to    Address of the last page; '' keeps the 404.
		 * @param int    $paged Page number asked for.
		 * @param int    $last  Number of the last page.
		 */
		$to = apply_filters( 'pergel/seo/past_last_page', $to, $paged, $last );
		return is_string( $to ) ? $to : '';
	}

	/**
	 * The number of pages the listing has, counted with its first page; 0
	 * when it has no posts or is not split into pages. Core does not count
	 * the posts of a page past the last one (found_posts stays 0), so the
	 * same query runs again for ids.
	 *
	 * @param \WP_Query $query The main query, after it was set to 404.
	 */
	private static function last_page( \WP_Query $query ): int {
		$vars = $query->query_vars;
		$size = (int) ( $vars['posts_per_page'] ?? 0 );
		if ( $size < 1 ) {
			return 0;
		}
		$count = new \WP_Query(
			array(
				'paged'                  => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'cache_results'          => false,
			) + $vars
		);
		return (int) ceil( $count->found_posts / $size );
	}
}
