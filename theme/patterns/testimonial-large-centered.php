<?php
/**
 * Title: Large centered testimonial
 * Slug: pergel/testimonial-large-centered
 * Categories: testimonials
 * Keywords: testimonial, quote, large
 * Viewport Width: 1400
 * Description: One large centered quote with the client photo and name below.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"backgroundColor":"surface","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull has-surface-background-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"constrained","contentSize":"840px"}} -->
<div class="wp-block-group"><!-- wp:quote {"textAlign":"center","style":{"border":{"left":{"width":"0px","style":"none"}},"spacing":{"padding":{"left":"0"}},"typography":{"fontStyle":"normal"}},"fontSize":"x-large"} -->
<blockquote class="wp-block-quote has-text-align-center has-x-large-font-size" style="border-left-style:none;border-left-width:0px;padding-left:0;font-style:normal"><!-- wp:paragraph -->
<p><?php esc_html_e( 'Good advice from the first call. They talked us out of the expensive option because we did not need it.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></blockquote>
<!-- /wp:quote -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center","justifyContent":"center"}} -->
<div class="wp-block-group"><!-- wp:image {"width":"48px","height":"48px","scale":"cover","style":{"border":{"radius":"999px"}}} -->
<figure class="wp-block-image is-resized has-custom-border"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/avatar.svg' ) ); ?>" alt="" style="border-radius:999px;object-fit:cover;width:48px;height:48px"/></figure>
<!-- /wp:image -->

<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"fontWeight":"600"}}} -->
<p style="font-weight:600"><?php echo esc_html_x( 'Robin Clarke', 'sample person name', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"textColor":"muted","fontSize":"small"} -->
<p class="has-muted-color has-text-color has-small-font-size"><?php echo esc_html_x( 'Owner', 'job title', 'pergel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
