<?php
/**
 * Title: Services as an accordion
 * Slug: pergel/services-details
 * Categories: services
 * Keywords: services, accordion, details
 * Viewport Width: 1400
 * Description: Introduction on the left, services that open for more detail on the right.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|60","left":"var:preset|spacing|70"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"width":"35%"} -->
<div class="wp-block-column" style="flex-basis:35%"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontWeight":"600"}},"textColor":"accent","fontSize":"small"} -->
<p class="has-accent-color has-text-color has-small-font-size" style="font-weight:600;letter-spacing:0.08em;text-transform:uppercase"><?php esc_html_e( 'What we do', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Services in detail', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php esc_html_e( 'Open a service to see what it covers.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:details -->
<details class="wp-block-details"><summary><?php esc_html_e( 'Installation', 'pergel' ); ?></summary><!-- wp:paragraph -->
<p><?php esc_html_e( 'Planned, tidy installation by certified engineers, handed over with full documentation.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:separator {"opacity":"css","className":"is-style-wide","backgroundColor":"line"} -->
<hr class="wp-block-separator has-text-color has-line-color has-css-opacity has-line-background-color has-background is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:details -->
<details class="wp-block-details"><summary><?php esc_html_e( 'Maintenance', 'pergel' ); ?></summary><!-- wp:paragraph -->
<p><?php esc_html_e( 'Scheduled checks that catch problems early and keep your warranty valid.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:separator {"opacity":"css","className":"is-style-wide","backgroundColor":"line"} -->
<hr class="wp-block-separator has-text-color has-line-color has-css-opacity has-line-background-color has-background is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:details -->
<details class="wp-block-details"><summary><?php esc_html_e( 'Consulting', 'pergel' ); ?></summary><!-- wp:paragraph -->
<p><?php esc_html_e( 'An honest look at your needs before you spend, with options at three price levels.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:separator {"opacity":"css","className":"is-style-wide","backgroundColor":"line"} -->
<hr class="wp-block-separator has-text-color has-line-color has-css-opacity has-line-background-color has-background is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:details -->
<details class="wp-block-details"><summary><?php esc_html_e( 'Training', 'pergel' ); ?></summary><!-- wp:paragraph -->
<p><?php esc_html_e( 'Hands-on sessions so your own team can run the daily work with confidence.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:separator {"opacity":"css","className":"is-style-wide","backgroundColor":"line"} -->
<hr class="wp-block-separator has-text-color has-line-color has-css-opacity has-line-background-color has-background is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:details -->
<details class="wp-block-details"><summary><?php esc_html_e( 'Repairs', 'pergel' ); ?></summary><!-- wp:paragraph -->
<p><?php esc_html_e( 'Fast diagnosis and repair, with common spare parts kept in stock.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:separator {"opacity":"css","className":"is-style-wide","backgroundColor":"line"} -->
<hr class="wp-block-separator has-text-color has-line-color has-css-opacity has-line-background-color has-background is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:details -->
<details class="wp-block-details"><summary><?php esc_html_e( 'Project management', 'pergel' ); ?></summary><!-- wp:paragraph -->
<p><?php esc_html_e( 'One contact who plans the work, keeps the schedule and reports every week.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:separator {"opacity":"css","className":"is-style-wide","backgroundColor":"line"} -->
<hr class="wp-block-separator has-text-color has-line-color has-css-opacity has-line-background-color has-background is-style-wide"/>
<!-- /wp:separator --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></section>
<!-- /wp:group -->
