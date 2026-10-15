<?php
/**
 * Title: Call to action with phone number
 * Slug: pergel/cta-phone
 * Categories: call-to-action
 * Keywords: cta, phone, call
 * Viewport Width: 1400
 * Description: Heading with a call button filled from the brand settings.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"backgroundColor":"surface","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull has-surface-background-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained","contentSize":"720px"}} -->
<div class="wp-block-group"><!-- wp:heading {"style":{"typography":{"textAlign":"center"}}} -->
<h2 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'Prefer to talk it through?', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}},"textColor":"muted","fontSize":"large"} -->
<p class="has-text-align-center has-muted-color has-text-color has-large-font-size"><?php esc_html_e( 'Call us on weekdays between 9:00 and 18:00.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"metadata":{"bindings":{"text":{"source":"pergel/brand","args":{"key":"phone"}},"url":{"source":"pergel/brand","args":{"key":"phone_url"}}}}} -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="tel:+902120000000">+90 212 000 00 00</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
