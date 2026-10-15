<?php
/**
 * Title: Single testimonial
 * Slug: pergel/testimonial-single-quote
 * Categories: testimonials
 * Keywords: testimonial, quote, review
 * Viewport Width: 1400
 * Description: One quote with the client name and role.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"layout":{"type":"constrained","contentSize":"720px"}} -->
<div class="wp-block-group"><!-- wp:quote {"fontSize":"large"} -->
<blockquote class="wp-block-quote has-large-font-size"><!-- wp:paragraph -->
<p><?php esc_html_e( 'They found the cause of a problem two other firms had missed, fixed it in a day and explained it so we could understand.', 'pergel' ); ?></p>
<!-- /wp:paragraph --><cite><?php echo esc_html_x( 'Alex Morgan', 'sample person name', 'pergel' ); ?>, <?php echo esc_html_x( 'Operations manager', 'job title', 'pergel' ); ?></cite></blockquote>
<!-- /wp:quote --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
