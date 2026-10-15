<?php
/**
 * Title: 404 content
 * Slug: pergel/hidden-404
 * Inserter: no
 * Description: Heading, explanation and search form for the 404 template.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading"><?php echo esc_html_x( 'Page not found', '404 page heading', 'pergel' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'The page you are looking for has moved or no longer exists. Try searching for it.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:pattern {"slug":"pergel/hidden-search"} /-->
