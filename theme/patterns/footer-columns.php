<?php
/**
 * Title: Footer with columns
 * Slug: pergel/footer-columns
 * Categories: footer
 * Block Types: core/template-part/footer
 * Viewport Width: 1400
 * Description: Site title and tagline beside lists of pages, recent posts and categories.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"footer","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|60"}},"backgroundColor":"surface","layout":{"type":"constrained"}} -->
<footer class="wp-block-group alignfull has-surface-background-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|60"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"width":"34%"} -->
<div class="wp-block-column" style="flex-basis:34%"><!-- wp:site-title {"level":0} /-->

<!-- wp:site-tagline {"fontSize":"small"} /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"fontSize":"medium"} -->
<h2 class="wp-block-heading has-medium-font-size"><?php echo esc_html_x( 'Pages', 'footer column heading', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:page-list {"fontSize":"small"} /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"fontSize":"medium"} -->
<h2 class="wp-block-heading has-medium-font-size"><?php echo esc_html_x( 'Recent posts', 'footer column heading', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:latest-posts {"postsToShow":3,"fontSize":"small"} /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"fontSize":"medium"} -->
<h2 class="wp-block-heading has-medium-font-size"><?php echo esc_html_x( 'Topics', 'footer column heading', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:categories {"fontSize":"small"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|40"}},"border":{"top":{"color":"var:preset|color|border","width":"1px","style":"solid"}}},"fontSize":"small","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group alignwide has-small-font-size" style="border-top-color:var(--wp--preset--color--border);border-top-style:solid;border-top-width:1px;padding-top:var(--wp--preset--spacing--40)"><!-- wp:site-title {"level":0,"fontSize":"small"} /-->

<!-- wp:paragraph -->
<p><?php /* translators: %s: link to WordPress.org */ printf( esc_html__( 'Proudly powered by %s', 'pergel' ), '<a href="https://wordpress.org/">WordPress</a>' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></footer>
<!-- /wp:group -->
