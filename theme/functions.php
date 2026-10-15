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
