<?php
/**
 * Title: Story with key figures
 * Slug: pergel/about-story-stats
 * Categories: about
 * Keywords: about, story, numbers
 * Viewport Width: 1400
 * Description: Company story on the left and four counting figures on the right.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|60","left":"var:preset|spacing|70"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"width":"50%"} -->
<div class="wp-block-column" style="flex-basis:50%"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontWeight":"600"}},"textColor":"accent","fontSize":"small"} -->
<p class="has-accent-color has-text-color has-small-font-size" style="font-weight:600;letter-spacing:0.08em;text-transform:uppercase"><?php esc_html_e( 'Our story', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Fifteen years of steady growth', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php esc_html_e( 'We started as a two-person workshop and grew into a team of engineers, planners and support staff. What has not changed is how we work: we listen first, explain our choices and stay for the long run.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php esc_html_e( 'Today we look after hundreds of sites, from small offices to large production halls, and most of our new work comes from people our clients recommend.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"grid","columnCount":2,"minimumColumnWidth":"8rem"}} -->
<div class="wp-block-group"><!-- wp:pergel/counter {"duration":0} -->
<!-- wp:paragraph {"fontSize":"xx-large"} -->
<p class="has-xx-large-font-size">2009</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php esc_html_e( 'Founded', 'pergel' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:pergel/counter -->

<!-- wp:pergel/counter -->
<!-- wp:paragraph {"fontSize":"xx-large"} -->
<p class="has-xx-large-font-size">45</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php esc_html_e( 'Team members', 'pergel' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:pergel/counter -->

<!-- wp:pergel/counter -->
<!-- wp:paragraph {"fontSize":"xx-large"} -->
<p class="has-xx-large-font-size">1200+</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php esc_html_e( 'Projects delivered', 'pergel' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:pergel/counter -->

<!-- wp:pergel/counter -->
<!-- wp:paragraph {"fontSize":"xx-large"} -->
<p class="has-xx-large-font-size">3</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php esc_html_e( 'Offices', 'pergel' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:pergel/counter --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></section>
<!-- /wp:group -->
