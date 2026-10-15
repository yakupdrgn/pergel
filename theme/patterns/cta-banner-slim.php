<?php
/**
 * Title: Slim call-to-action bar
 * Slug: pergel/cta-banner-slim
 * Categories: call-to-action
 * Keywords: cta, banner, bar, strip
 * Viewport Width: 1400
 * Description: A narrow bar with one line of text and a button, for use between sections.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|60"},"elements":{"link":{"color":{"text":"var:preset|color|on-accent"}}}},"backgroundColor":"accent","textColor":"on-accent","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull has-on-accent-color has-accent-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group alignwide"><!-- wp:paragraph {"style":{"typography":{"fontWeight":"600"}},"fontSize":"large"} -->
<p class="has-large-font-size" style="font-weight:600"><?php esc_html_e( 'Planning a project this year? Get a free first survey.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"base","textColor":"accent"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-accent-color has-base-background-color has-text-color has-background wp-element-button" href="#"><?php esc_html_e( 'Book a survey', 'pergel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
