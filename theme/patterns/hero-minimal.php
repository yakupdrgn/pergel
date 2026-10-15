<?php
/**
 * Title: Hero, text only
 * Slug: pergel/hero-minimal
 * Categories: banner
 * Keywords: hero, minimal, statement
 * Viewport Width: 1400
 * Description: A large page title with a short line and a link, without images.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained","contentSize":"900px","justifyContent":"left"}} -->
<div class="wp-block-group alignwide"><!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"clamp(2.5rem, 1.6rem + 3.6vw, 4.5rem)","lineHeight":"1.05"}}} -->
<h1 class="wp-block-heading" style="font-size:clamp(2.5rem, 1.6rem + 3.6vw, 4.5rem);line-height:1.05"><?php esc_html_e( 'We make complex projects simple to run', 'pergel' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted","fontSize":"large"} -->
<p class="has-muted-color has-text-color has-large-font-size"><?php esc_html_e( 'Planning, delivery and support under one roof.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a href="#"><?php esc_html_e( 'Read how we work', 'pergel' ); ?></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
