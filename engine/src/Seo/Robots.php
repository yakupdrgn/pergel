<?php
/**
 * Pergel's noindex in the robots tag the page prints.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Seo;

/**
 * Writes "noindex, follow" into the directives of whoever prints the
 * robots tag; Module hooks these in and loads this class only on a page
 * Pergel keeps out of search results (Module::noindex()).
 */
final class Robots {

	/**
	 * Core's wp_robots, without an SEO plugin and with Yoast SEO.
	 *
	 * @param mixed $robots Directives.
	 */
	public static function core( mixed $robots ): mixed {
		if ( is_array( $robots ) ) {
			$robots['noindex'] = true;
			if ( empty( $robots['nofollow'] ) ) {
				$robots['follow'] = true;
			}
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}

	/**
	 * Rank Math: "index" holds index or noindex.
	 *
	 * @param mixed $robots Directives.
	 */
	public static function rank_math( mixed $robots ): mixed {
		if ( is_array( $robots ) ) {
			$robots['index'] = 'noindex';
			if ( 'nofollow' !== ( $robots['follow'] ?? '' ) ) {
				$robots['follow'] = 'follow';
			}
		}
		return $robots;
	}

	/**
	 * All in One SEO.
	 *
	 * @param mixed $robots Directives.
	 */
	public static function aioseo( mixed $robots ): mixed {
		if ( is_array( $robots ) ) {
			$robots['noindex'] = 'noindex';
		}
		return $robots;
	}

	/**
	 * SEOPress: '1' is what its own settings give for noindex.
	 */
	public static function seopress(): string {
		return '1';
	}

	/**
	 * The SEO Framework, for the page shown only (no query arguments);
	 * other calls ask about another page.
	 *
	 * @param mixed $robots Directives.
	 * @param mixed $args   Query arguments, null for the page shown.
	 */
	public static function tsf( mixed $robots, mixed $args = null ): mixed {
		if ( is_array( $robots ) && null === $args ) {
			$robots['noindex'] = 'noindex';
		}
		return $robots;
	}
}
