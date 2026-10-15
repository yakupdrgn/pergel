<?php
/**
 * Title: Process as a timeline
 * Slug: pergel/process-timeline
 * Categories: pergel-process
 * Keywords: process, timeline, steps
 * Viewport Width: 1400
 * Description: Centered heading and the steps on a vertical timeline.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained","contentSize":"720px"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontWeight":"600","textAlign":"center"}},"textColor":"accent","fontSize":"small"} -->
<p class="has-text-align-center has-accent-color has-text-color has-small-font-size" style="font-weight:600;letter-spacing:0.08em;text-transform:uppercase"><?php esc_html_e( 'How we work', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"style":{"typography":{"textAlign":"center"}}} -->
<h2 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'From first call to long-term care', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}},"textColor":"muted","fontSize":"large"} -->
<p class="has-text-align-center has-muted-color has-text-color has-large-font-size"><?php esc_html_e( 'A simple process with no surprises. You always know the next step.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"constrained","contentSize":"720px"}} -->
<div class="wp-block-group"><!-- wp:pergel/timeline -->
<!-- wp:pergel/timeline-item -->
<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size"><?php esc_html_e( 'Week 1', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php esc_html_e( 'Call and survey', 'pergel' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'We talk through your needs and visit the site to see the space.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:pergel/timeline-item -->

<!-- wp:pergel/timeline-item -->
<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size"><?php esc_html_e( 'Week 2', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php esc_html_e( 'Plan and price', 'pergel' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'You get a written plan with a fixed price and a clear schedule.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:pergel/timeline-item -->

<!-- wp:pergel/timeline-item -->
<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size"><?php esc_html_e( 'Weeks 3 to 6', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php esc_html_e( 'Delivery', 'pergel' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Our engineers do the work, keeping the site tidy and you informed.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:pergel/timeline-item -->

<!-- wp:pergel/timeline-item -->
<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size"><?php esc_html_e( 'Week 7 and after', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php esc_html_e( 'Handover and care', 'pergel' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'We hand over the documents, train your team and stay on call.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:pergel/timeline-item -->
<!-- /wp:pergel/timeline --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
