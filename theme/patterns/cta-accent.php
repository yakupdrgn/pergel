<?php
/**
 * Title: Call to action on the accent color
 * Slug: pergel/cta-accent
 * Categories: call-to-action
 * Keywords: cta, button, highlight
 * Viewport Width: 1400
 * Description: Centered heading and button on the brand accent color.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"},"elements":{"link":{"color":{"text":"var:preset|color|on-accent"}}}},"backgroundColor":"accent","textColor":"on-accent","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull has-on-accent-color has-accent-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained","contentSize":"720px"}} -->
<div class="wp-block-group"><!-- wp:heading {"style":{"typography":{"textAlign":"center"}}} -->
<h2 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'Let us take this off your plate', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}},"fontSize":"large"} -->
<p class="has-text-align-center has-large-font-size"><?php esc_html_e( 'Send us the details today. An engineer will call you back.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"base","textColor":"accent"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-accent-color has-base-background-color has-text-color has-background wp-element-button" href="#"><?php esc_html_e( 'Get in touch', 'pergel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
