<?php
/**
 * Pergel theme bootstrap.
 *
 * The theme is presentation only: design lives in theme.json, templates and
 * patterns. Behaviour (blocks, SEO, AI, WooCommerce, content types) lives in
 * the pergel-engine plugin, so switching themes never loses data.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Theme;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the pattern categories that the theme's patterns/ use.
 */
function register_pattern_categories(): void {
	register_block_pattern_category(
		'pergel',
		array(
			'label'       => __( 'Pergel', 'pergel' ),
			'description' => __( 'Sections and full pages from the Pergel library.', 'pergel' ),
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\register_pattern_categories' );

/**
 * Loads the theme's per-block styles. Core prints each one only on pages
 * that show the block, and inlines it, since it is small.
 */
function enqueue_block_styles(): void {
	foreach ( array( 'site-logo' ) as $block ) {
		wp_enqueue_block_style(
			"core/{$block}",
			array(
				'handle' => "pergel-{$block}",
				'src'    => get_theme_file_uri( "assets/css/blocks/{$block}.css" ),
				'path'   => get_theme_file_path( "assets/css/blocks/{$block}.css" ),
				'ver'    => wp_get_theme()->get( 'Version' ),
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\enqueue_block_styles' );

/**
 * Uses front-page.html only when a static page is the front page.
 *
 * The front-page.html template shows the page's own content without a title, so the
 * page's first section (a hero) carries the H1. When the front page lists
 * posts, home.html is the right template; removing front-page from the
 * hierarchy also makes the Site Editor open home.html as the homepage.
 *
 * @param string[] $templates Template candidates.
 * @return string[]
 */
function front_page_hierarchy( array $templates ): array {
	if ( 'page' === get_option( 'show_on_front' ) ) {
		return $templates;
	}
	return array_values( array_diff( $templates, array( 'front-page.php', 'front-page' ) ) );
}
add_filter( 'frontpage_template_hierarchy', __NAMESPACE__ . '\front_page_hierarchy' );

/**
 * Replaces the Navigation block's page-list fallback with plain page links.
 *
 * A Navigation block with no menu falls back to a Page List, which core
 * renders as a list nested directly inside the navigation list
 * (ul > ul > li). That markup fails WCAG list rules. Top-level pages as
 * Navigation Link blocks render the same menu as valid markup. Sites with
 * a real menu are not affected.
 *
 * @param array<int, array<string, mixed>> $blocks Parsed fallback blocks.
 * @return array<int, array<string, mixed>>
 */
function navigation_fallback( array $blocks ): array {
	if ( 1 !== count( $blocks ) || 'core/page-list' !== ( $blocks[0]['blockName'] ?? null ) ) {
		return $blocks;
	}
	$pages = get_pages(
		array(
			'parent'      => 0,
			'sort_column' => 'menu_order,post_title',
			'number'      => 12,
		)
	);
	if ( false === $pages ) {
		return $blocks;
	}
	$links = array();
	foreach ( $pages as $page ) {
		$links[] = array(
			'blockName'    => 'core/navigation-link',
			'attrs'        => array(
				'label' => $page->post_title,
				'id'    => $page->ID,
				'url'   => get_permalink( $page ),
				'kind'  => 'post-type',
				'type'  => 'page',
			),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}
	return $links;
}
add_filter( 'block_core_navigation_render_fallback', __NAMESPACE__ . '\navigation_fallback' );
