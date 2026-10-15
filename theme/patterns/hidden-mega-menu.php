<?php
/**
 * Title: Mega menu panel
 * Slug: pergel/hidden-mega-menu
 * Inserter: no
 * Description: Starting content of the mega menu panel: pages, topics and recent posts.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|60"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"fontSize":"small","style":{"typography":{"fontWeight":"600"}}} -->
<p class="has-small-font-size" style="font-weight:600"><?php echo esc_html_x( 'Pages', 'mega menu column title', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:page-list /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"fontSize":"small","style":{"typography":{"fontWeight":"600"}}} -->
<p class="has-small-font-size" style="font-weight:600"><?php echo esc_html_x( 'Topics', 'mega menu column title', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:categories /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"fontSize":"small","style":{"typography":{"fontWeight":"600"}}} -->
<p class="has-small-font-size" style="font-weight:600"><?php echo esc_html_x( 'Recent posts', 'mega menu column title', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:latest-posts {"postsToShow":3} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
