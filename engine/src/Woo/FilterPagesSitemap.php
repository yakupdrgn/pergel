<?php
/**
 * Filter pages in core's sitemap.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * Lists the filter pages (FilterPages) in core's sitemap when no SEO plugin
 * prints one: wp-sitemap-filterpages-1.xml.
 */
final class FilterPagesSitemap extends \WP_Sitemaps_Provider {

	/**
	 * Sets the name of the sitemap.
	 */
	public function __construct() {
		$this->name        = FilterPages::SITEMAP;
		$this->object_type = FilterPages::SITEMAP;
	}

	/**
	 * Addresses of one sitemap page.
	 *
	 * @param int    $page_num       Page number.
	 * @param string $object_subtype Not used.
	 * @return array<int, array<string, string>>
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		$size = (int) wp_sitemaps_get_max_urls( $this->object_type );
		$urls = array_slice( FilterPages::addresses(), max( 0, ( (int) $page_num - 1 ) * $size ), $size );
		return array_map( static fn ( string $url ): array => array( 'loc' => $url ), $urls );
	}

	/**
	 * Number of sitemap pages.
	 *
	 * @param string $object_subtype Not used.
	 * @return int
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		$count = count( FilterPages::addresses() );
		return 0 === $count ? 0 : (int) ceil( $count / max( 1, (int) wp_sitemaps_get_max_urls( $this->object_type ) ) );
	}
}
