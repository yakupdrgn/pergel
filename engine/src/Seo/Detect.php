<?php
/**
 * Which SEO plugin, if any, owns the head of the page.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Seo;

/**
 * Finds an active SEO plugin by the constant it defines when its main file
 * loads. Plugins are loaded before plugins_loaded, so the answer is final
 * by the time the engine boots.
 */
final class Detect {

	/**
	 * Supported plugins: id => constant. The first match wins.
	 */
	public const PLUGINS = array(
		'rank-math' => 'RANK_MATH_VERSION',
		'yoast'     => 'WPSEO_VERSION',
		'aioseo'    => 'AIOSEO_FILE',
		'seopress'  => 'SEOPRESS_VERSION',
		'tsf'       => 'THE_SEO_FRAMEWORK_VERSION',
	);

	/**
	 * Id of the active SEO plugin, or '' when there is none.
	 */
	public static function active(): string {
		$active = '';
		foreach ( self::PLUGINS as $id => $constant ) {
			if ( defined( $constant ) ) {
				$active = $id;
				break;
			}
		}

		/**
		 * Filters the SEO plugin Pergel hands over to.
		 *
		 * Return '' to make Pergel print the tags itself, or one of the
		 * ids in Detect::PLUGINS. Another non-empty value makes Pergel step
		 * back without adding anything, for SEO plugins it does not know.
		 *
		 * @param string $active Plugin id or ''.
		 */
		$filtered = apply_filters( 'pergel/seo/plugin', $active );
		return is_string( $filtered ) ? $filtered : $active;
	}

	/**
	 * Display name of a plugin id.
	 *
	 * @param string $id Plugin id.
	 */
	public static function name( string $id ): string {
		return match ( $id ) {
			'rank-math' => 'Rank Math',
			'yoast'     => 'Yoast SEO',
			'aioseo'    => 'All in One SEO',
			'seopress'  => 'SEOPress',
			'tsf'       => 'The SEO Framework',
			default     => $id,
		};
	}
}
