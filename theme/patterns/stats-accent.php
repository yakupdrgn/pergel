<?php
/**
 * Title: Key figures on the accent color
 * Slug: pergel/stats-accent
 * Categories: pergel-stats
 * Keywords: numbers, counter, highlight
 * Viewport Width: 1400
 * Description: Four counting figures on the brand accent color.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|60"},"elements":{"link":{"color":{"text":"var:preset|color|on-accent"}}}},"backgroundColor":"accent","textColor":"on-accent","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull has-on-accent-color has-accent-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"grid","columnCount":4,"minimumColumnWidth":"8rem"}} -->
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
