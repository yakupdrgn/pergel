<?php
/**
 * Pergel's noindex in the robots tag the page prints.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Seo;

/**
 * Pages Pergel keeps out of search results (an ended flash sale, a shop
 * listing with a visitor's per_page) answer the pergel/seo/noindex filter.
 * The answer goes into the robots tag of whoever prints it: core's
 * wp_robots without an SEO plugin and with Yoast SEO (which merges
 * wp_robots into its own values); the plugin's own filter with Rank Math
 * and All in One SEO (they drop core's robots filters), The SEO Framework
 * and SEOPress (they print a tag of their own). The page keeps one robots
 * tag, and "noindex, follow" in it.
 */
final class Robots {

	/**
	 * Filter: whether Pergel keeps this page out of search results.
	 */
	public const FILTER = 'pergel/seo/noindex';

	/**
	 * Adds the filter of the plugin that prints the robots tag.
	 *
	 * @param string $plugin Plugin id from Detect, '' for none.
	 */
	public static function register( string $plugin ): void {
		switch ( $plugin ) {
			case 'rank-math':
				add_filter( 'rank_math/frontend/robots', array( self::class, 'rank_math' ), 20 );
				break;
			case 'aioseo':
				add_filter( 'aioseo_robots_meta', array( self::class, 'aioseo' ), 20 );
				break;
			case 'seopress':
				add_filter( 'seopress_titles_noindex_bypass', array( self::class, 'seopress' ), 20 );
				break;
			case 'tsf':
				add_filter( 'the_seo_framework_robots_meta_array', array( self::class, 'tsf' ), 20, 2 );
				break;
			default:
				// No plugin, Yoast SEO, and plugins Pergel does not know.
				add_filter( 'wp_robots', array( self::class, 'core' ) );
		}
	}

	/**
	 * Whether Pergel keeps the page shown out of search results.
	 */
	public static function noindex(): bool {
		/**
		 * Filters whether Pergel keeps the page shown out of search results
		 * ("noindex, follow"). Only true counts.
		 *
		 * @param bool $noindex False, or true from an earlier callback.
		 */
		return ! is_admin() && true === apply_filters( 'pergel/seo/noindex', false );
	}

	/**
	 * Filter for wp_robots.
	 *
	 * @param array<string, bool|string> $robots Directives.
	 * @return array<string, bool|string>
	 */
	public static function core( array $robots ): array {
		if ( self::noindex() ) {
			$robots['noindex'] = true;
			if ( empty( $robots['nofollow'] ) ) {
				$robots['follow'] = true;
			}
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}

	/**
	 * Filter for rank_math/frontend/robots: "index" holds index or noindex.
	 *
	 * @param mixed $robots Directives.
	 */
	public static function rank_math( mixed $robots ): mixed {
		if ( is_array( $robots ) && self::noindex() ) {
			$robots['index'] = 'noindex';
			if ( 'nofollow' !== ( $robots['follow'] ?? '' ) ) {
				$robots['follow'] = 'follow';
			}
		}
		return $robots;
	}

	/**
	 * Filter for aioseo_robots_meta.
	 *
	 * @param mixed $robots Directives.
	 */
	public static function aioseo( mixed $robots ): mixed {
		if ( is_array( $robots ) && self::noindex() ) {
			$robots['noindex'] = 'noindex';
		}
		return $robots;
	}

	/**
	 * Filter for seopress_titles_noindex_bypass: '1' is what its own
	 * settings give for noindex.
	 *
	 * @param mixed $noindex Noindex value so far.
	 */
	public static function seopress( mixed $noindex ): mixed {
		return self::noindex() ? '1' : $noindex;
	}

	/**
	 * Filter for the_seo_framework_robots_meta_array, for the page shown
	 * only (no query arguments); other calls ask about another page.
	 *
	 * @param mixed $robots Directives.
	 * @param mixed $args   Query arguments, null for the page shown.
	 */
	public static function tsf( mixed $robots, mixed $args = null ): mixed {
		if ( is_array( $robots ) && null === $args && self::noindex() ) {
			$robots['noindex'] = 'noindex';
		}
		return $robots;
	}
}
