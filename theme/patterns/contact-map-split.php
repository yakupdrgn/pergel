<?php
/**
 * Title: Contact details with map
 * Slug: pergel/contact-map-split
 * Categories: contact
 * Keywords: contact, map, address
 * Viewport Width: 1400
 * Description: Contact details on the left and a map of the brand address on the right.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:columns {"verticalAlignment":"center","align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|60","left":"var:preset|spacing|70"}}}} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"40%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:40%"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained","contentSize":"720px","justifyContent":"left"}} -->
<div class="wp-block-group alignwide"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontWeight":"600"}},"textColor":"accent","fontSize":"small"} -->
<p class="has-accent-color has-text-color has-small-font-size" style="font-weight:600;letter-spacing:0.08em;text-transform:uppercase"><?php esc_html_e( 'Contact', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Talk to our team', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted","fontSize":"large"} -->
<p class="has-muted-color has-text-color has-large-font-size"><?php esc_html_e( 'Tell us what you need. We reply within one working day.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"textColor":"muted","fontSize":"small"} -->
<p class="has-muted-color has-text-color has-small-font-size"><?php esc_html_e( 'Phone', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"pergel/brand","args":{"key":"phone"}}}}} -->
<p>+90 212 000 00 00</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"textColor":"muted","fontSize":"small"} -->
<p class="has-muted-color has-text-color has-small-font-size"><?php esc_html_e( 'Email', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"pergel/brand","args":{"key":"email"}}}}} -->
<p><?php echo esc_html_x( 'hello@example.com', 'sample email address', 'pergel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"textColor":"muted","fontSize":"small"} -->
<p class="has-muted-color has-text-color has-small-font-size"><?php esc_html_e( 'Address', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"pergel/brand","args":{"key":"address"}}}}} -->
<p><?php echo esc_html_x( '1 Sample Street, 34000 Istanbul', 'sample address', 'pergel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"textColor":"muted","fontSize":"small"} -->
<p class="has-muted-color has-text-color has-small-font-size"><?php esc_html_e( 'Opening hours', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Monday to Friday, 9:00 to 18:00', 'pergel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:pergel/map -->
<!-- wp:image {"aspectRatio":"3/2","scale":"cover","style":{"border":{"radius":"var(\u002d\u002dwp\u002d\u002dcustom\u002d\u002dradius\u002d\u002dcard)"}}} -->
<figure class="wp-block-image has-custom-border"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/landscape.svg' ) ); ?>" alt="" style="border-radius:var(--wp--custom--radius--card);aspect-ratio:3/2;object-fit:cover"/></figure>
<!-- /wp:image -->
<!-- /wp:pergel/map --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></section>
<!-- /wp:group -->
