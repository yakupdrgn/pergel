<?php
/**
 * Title: Blog heading
 * Slug: pergel/hidden-blog-heading
 * Inserter: no
 * Description: H1 for the post list: the posts page title, or a default when posts are on the front page.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

// WordPress ignores page_for_posts unless the front page is a static page.
$pergel_posts_page = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_for_posts' ) : 0;
$pergel_title      = $pergel_posts_page ? get_the_title( $pergel_posts_page ) : '';
if ( '' === $pergel_title ) {
	$pergel_title = _x( 'Latest posts', 'blog heading', 'pergel' );
}
?>
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading"><?php echo esc_html( $pergel_title ); ?></h1>
<!-- /wp:heading -->
