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
