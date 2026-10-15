<?php
/**
 * Title: Call to action with image
 * Slug: pergel/cta-with-image
 * Categories: call-to-action
 * Keywords: cta, image, button
 * Viewport Width: 1400
 * Description: Image on one side, heading, text and a button on the other.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:media-text {"align":"wide","mediaPosition":"right","mediaType":"image","verticalAlignment":"center","imageFill":false} -->
<div class="wp-block-media-text alignwide has-media-on-the-right is-stacked-on-mobile is-vertically-aligned-center"><div class="wp-block-media-text__content"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Let us visit your site', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php esc_html_e( 'We look at the space, listen to your plans and leave you with a written proposal.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Arrange a visit', 'pergel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div><figure class="wp-block-media-text__media"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/landscape.svg' ) ); ?>" alt=""/></figure></div>
<!-- /wp:media-text --></section>
<!-- /wp:group -->
