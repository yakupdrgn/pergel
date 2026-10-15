<?php
/**
 * Core sitemap extensions.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Seo;

use Pergel\Settings\Settings;

/**
 * Keeps the core sitemap (wp-sitemap.xml) in step with the SEO panel:
 * hidden posts are left out, entries get a last-modified date, and author
 * pages are listed only when the settings ask for them.
 */
final class Sitemap {

	/**
	 * Adds the sitemap hooks.
	 */
	public static function register(): void {
		add_filter( 'wp_sitemaps_add_provider', array( self::class, 'provider' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_query_args', array( self::class, 'query_args' ) );
		add_filter( 'wp_sitemaps_posts_entry', array( self::class, 'entry' ), 10, 2 );
	}

	/**
	 * Drops the users provider unless author pages are switched on.
	 *
	 * @param mixed  $provider Sitemap provider.
	 * @param string $name     Provider name.
	 */
	public static function provider( mixed $provider, string $name ): mixed {
		return 'users' === $name && true !== Settings::get( 'seo.sitemap_users' ) ? false : $provider;
	}

	/**
	 * Leaves out posts hidden from search engines.
	 *
	 * @param array<string, mixed> $args WP_Query arguments.
	 * @return array<string, mixed>
	 */
	public static function query_args( array $args ): array {
		$hidden = array(
			'relation' => 'OR',
			array(
				'key'     => Meta::NOINDEX,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => Meta::NOINDEX,
				'value'   => '1',
				'compare' => '!=',
			),
		);

		$args['meta_query'] = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- sitemap pages are cached by crawlers and proxies.
			? array(
				'relation' => 'AND',
				$args['meta_query'],
				$hidden,
			)
			: $hidden;
		return $args;
	}

	/**
	 * Adds the last-modified date.
	 *
	 * @param array<string, mixed> $entry Sitemap entry.
	 * @param \WP_Post             $post  Post.
	 * @return array<string, mixed>
	 */
	public static function entry( array $entry, \WP_Post $post ): array {
		$modified = get_post_modified_time( DATE_W3C, true, $post );
		if ( is_string( $modified ) && '' !== $modified ) {
			$entry['lastmod'] = $modified;
		}
		return $entry;
	}
}
