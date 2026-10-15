<?php
/**
 * Title: Key figures on a dark background
 * Slug: pergel/stats-inverse
 * Categories: pergel-stats
 * Keywords: numbers, counter, dark
 * Viewport Width: 1400
 * Description: Centered heading and four counting figures on a dark background.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"},"elements":{"link":{"color":{"text":"var:preset|color|base"}}}},"backgroundColor":"contrast","textColor":"base","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull has-base-color has-contrast-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained","contentSize":"720px"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontWeight":"600","textAlign":"center"}},"fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size" style="font-weight:600;letter-spacing:0.08em;text-transform:uppercase"><?php esc_html_e( 'In numbers', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"style":{"typography":{"textAlign":"center"}}} -->
<h2 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'Numbers we are proud of', 'pergel' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"grid","columnCount":4,"minimumColumnWidth":"8rem"}} -->
<div class="wp-block-group alignwide"><!-- wp:pergel/counter {"style":{"typography":{"textAlign":"center"}}} -->
<!-- wp:paragraph {"fontSize":"xx-large"} -->
<p class="has-xx-large-font-size">1200+</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Projects delivered', 'pergel' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:pergel/counter -->

<!-- wp:pergel/counter {"style":{"typography":{"textAlign":"center"}}} -->
<!-- wp:paragraph {"fontSize":"xx-large"} -->
<p class="has-xx-large-font-size">98%</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Clients who come back', 'pergel' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:pergel/counter -->

<!-- wp:pergel/counter {"style":{"typography":{"textAlign":"center"}}} -->
<!-- wp:paragraph {"fontSize":"xx-large"} -->
<p class="has-xx-large-font-size">45</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Team members', 'pergel' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:pergel/counter -->

<!-- wp:pergel/counter {"style":{"typography":{"textAlign":"center"}}} -->
<!-- wp:paragraph {"fontSize":"xx-large"} -->
<p class="has-xx-large-font-size">15</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Years of experience', 'pergel' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:pergel/counter --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
