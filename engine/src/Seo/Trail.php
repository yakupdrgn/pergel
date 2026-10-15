<?php
/**
 * The breadcrumb trail of the current page.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Seo;

/**
 * One trail for the visible breadcrumb and the BreadcrumbList (plan K8).
 *
 * With an SEO plugin that builds a trail (Rank Math, Yoast, AIOSEO, The SEO
 * Framework) the trail is the plugin's, read from the same list the plugin
 * turns into its BreadcrumbList. Without one, it is Pergel's own, the one
 * Head prints. SEOPress (free) builds none, so Pergel's trail is used and
 * the breadcrumb block adds the BreadcrumbList itself.
 *
 * Every source goes through clean(), which keeps the steps that the
 * plugins put in their structured data: a name, a link and not hidden.
 */
final class Trail {

	/**
	 * Plugins whose trail and BreadcrumbList Pergel reads.
	 */
	public const PLUGINS = array( 'rank-math', 'yoast', 'aioseo', 'tsf' );

	/**
	 * Hooks needed by one plugin's trail. Rank Math builds its trail (and
	 * its BreadcrumbList) only for themes that declare support, and leaves
	 * some current pages (the posts page, for one) without a link, which
	 * drops them from its BreadcrumbList; they get the page's address.
	 *
	 * @param string $plugin Plugin id from Detect.
	 */
	public static function register( string $plugin ): void {
		if ( 'rank-math' === $plugin ) {
			add_action(
				'after_setup_theme',
				static function (): void {
					add_theme_support( 'rank-math-breadcrumbs' );
				}
			);
			add_filter(
				'rank_math/frontend/breadcrumb/items',
				static fn ( mixed $crumbs ): mixed => is_array( $crumbs ) && ! is_404() ? self::link_last( $crumbs, Head::page()['url'] ) : $crumbs
			);
		}
	}

	/**
	 * Gives the last Rank Math crumb the current page's address when it has
	 * a name but no link and is not hidden from the structured data.
	 *
	 * @param array<mixed> $crumbs Rank Math crumbs: [ name, link, 'hide_in_schema' => bool ].
	 * @param string       $url    Current page address.
	 * @return array<mixed>
	 */
	public static function link_last( array $crumbs, string $url ): array {
		$key  = array_key_last( $crumbs );
		$last = null === $key ? null : $crumbs[ $key ];
		if ( '' === $url || ! is_array( $last ) || ! empty( $last['hide_in_schema'] ) || ! empty( $last[1] ) || empty( $last[0] ) ) {
			return $crumbs;
		}
		$last[1]        = $url;
		$crumbs[ $key ] = $last;
		return $crumbs;
	}

	/**
	 * The trail of the current page, home first, or an empty list when it
	 * has fewer than two steps.
	 *
	 * @return list<array{name: string, url: string}>
	 */
	public static function current(): array {
		$plugin = Detect::active();
		$items  = in_array( $plugin, self::PLUGINS, true ) ? self::from_plugin( $plugin ) : Head::page()['crumbs'];
		return self::clean( $items );
	}

	/**
	 * Whether the page's structured data already has this trail: the
	 * plugin's BreadcrumbList, or the one in Pergel's own graph.
	 */
	public static function in_schema(): bool {
		$plugin = Detect::active();
		return '' === $plugin || in_array( $plugin, self::PLUGINS, true );
	}

	/**
	 * Keeps the steps with a name and a link that are not hidden from the
	 * structured data, with HTML entities and tags taken out of the names.
	 *
	 * @param array<mixed> $items Steps with name, url and an optional hidden flag.
	 * @return list<array{name: string, url: string}>
	 */
	public static function clean( array $items ): array {
		$trail = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! empty( $item['hidden'] ) ) {
				continue;
			}
			$name = is_string( $item['name'] ?? null ) ? $item['name'] : '';
			$name = trim( html_entity_decode( strip_tags( $name ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Pure function, unit-tested without WordPress; names carry no script or style.
			$url  = is_string( $item['url'] ?? null ) ? trim( $item['url'] ) : '';
			if ( '' !== $name && '' !== $url ) {
				$trail[] = array(
					'name' => $name,
					'url'  => $url,
				);
			}
		}
		return count( $trail ) < 2 ? array() : $trail;
	}

	/**
	 * The plugin's trail as name/url/hidden steps.
	 *
	 * @param string $plugin Plugin id.
	 * @return list<array{name: mixed, url: mixed, hidden?: bool}>
	 */
	private static function from_plugin( string $plugin ): array {
		$items = array();
		switch ( $plugin ) {
			case 'rank-math':
				$trail = class_exists( '\RankMath\Frontend\Breadcrumbs' ) ? \RankMath\Frontend\Breadcrumbs::get() : false;
				foreach ( $trail ? (array) $trail->get_crumbs() : array() as $crumb ) {
					$items[] = array(
						'name'   => $crumb[0] ?? '',
						'url'    => $crumb[1] ?? '',
						'hidden' => ! empty( $crumb['hide_in_schema'] ),
					);
				}
				break;
			case 'yoast':
				$meta = function_exists( 'YoastSEO' ) && ! is_404() ? YoastSEO()->meta->for_current_page() : false;
				foreach ( $meta ? (array) $meta->breadcrumbs : array() as $crumb ) {
					// Yoast prints no BreadcrumbList when a step lacks either key.
					if ( ! is_array( $crumb ) || ! array_key_exists( 'url', $crumb ) || ! array_key_exists( 'text', $crumb ) ) {
						return array();
					}
					$items[] = array(
						'name'   => $crumb['text'],
						'url'    => $crumb['url'],
						'hidden' => ! empty( $crumb['hide_in_schema'] ),
					);
				}
				break;
			case 'aioseo':
				$crumbs = function_exists( 'aioseo' ) ? aioseo()->breadcrumbs->frontend->getBreadcrumbs() : array();
				foreach ( is_array( $crumbs ) ? $crumbs : array() as $crumb ) {
					$items[] = array(
						'name' => $crumb['label'] ?? '',
						'url'  => is_scalar( $crumb['link'] ?? null ) ? (string) $crumb['link'] : '',
					);
				}
				break;
			case 'tsf':
				$crumbs = class_exists( '\The_SEO_Framework\Meta\Breadcrumbs' ) ? \The_SEO_Framework\Meta\Breadcrumbs::get_breadcrumb_list() : array();
				foreach ( is_array( $crumbs ) ? $crumbs : array() as $crumb ) {
					$items[] = array(
						'name' => $crumb['name'] ?? '',
						'url'  => $crumb['url'] ?? '',
					);
				}
				break;
		}
		return $items;
	}
}
