<?php
/**
 * Title: Hero in the accent color
 * Slug: pergel/hero-accent
 * Categories: banner
 * Keywords: hero, color, campaign
 * Viewport Width: 1400
 * Description: Centered page title and buttons on the accent color, for campaign and landing pages.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"},"elements":{"link":{"color":{"text":"var:preset|color|on-accent"}}}},"backgroundColor":"accent","textColor":"on-accent","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull has-on-accent-color has-accent-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained","contentSize":"760px"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontWeight":"600","textAlign":"center"}},"fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size" style="font-weight:600;letter-spacing:0.08em;text-transform:uppercase"><?php esc_html_e( 'New this season', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"style":{"typography":{"textAlign":"center"}}} -->
<h1 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'Everything your project needs, from one team', 'pergel' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}},"fontSize":"large"} -->
<p class="has-text-align-center has-large-font-size"><?php esc_html_e( 'We plan, deliver and support the systems your daily work depends on, with clear prices and one team you can call.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"base","textColor":"accent"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-accent-color has-base-background-color has-text-color has-background wp-element-button" href="#"><?php esc_html_e( 'Request a quote', 'pergel' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Our services', 'pergel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
