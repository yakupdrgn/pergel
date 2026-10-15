<?php
/**
 * Title: Contact details in cards
 * Slug: pergel/contact-details-cards
 * Categories: contact
 * Keywords: contact, phone, email
 * Viewport Width: 1400
 * Description: Centered heading and three cards for phone, email and address.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"backgroundColor":"surface","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull has-surface-background-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained","contentSize":"720px"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontWeight":"600","textAlign":"center"}},"textColor":"accent","fontSize":"small"} -->
<p class="has-text-align-center has-accent-color has-text-color has-small-font-size" style="font-weight:600;letter-spacing:0.08em;text-transform:uppercase"><?php esc_html_e( 'Contact', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"style":{"typography":{"textAlign":"center"}}} -->
<h2 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'Talk to our team', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}},"textColor":"muted","fontSize":"large"} -->
<p class="has-text-align-center has-muted-color has-text-color has-large-font-size"><?php esc_html_e( 'Tell us what you need. We reply within one working day.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"grid","columnCount":3,"minimumColumnWidth":"15rem"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"style":{"border":{"width":"1px","style":"solid","color":"var:preset|color|line","radius":"var(\u002d\u002dwp\u002d\u002dcustom\u002d\u002dradius\u002d\u002dcard)"},"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|30"}},"backgroundColor":"base","layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group has-border-color has-base-background-color has-background" style="border-color:var(--wp--preset--color--line);border-style:solid;border-width:1px;border-radius:var(--wp--custom--radius--card);padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size"><?php esc_html_e( 'Call us', 'pergel' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php esc_html_e( 'Weekdays from 9:00 to 18:00.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"pergel/brand","args":{"key":"phone"}}}},"style":{"typography":{"fontWeight":"600"}}} -->
<p style="font-weight:600">+90 212 000 00 00</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"border":{"width":"1px","style":"solid","color":"var:preset|color|line","radius":"var(\u002d\u002dwp\u002d\u002dcustom\u002d\u002dradius\u002d\u002dcard)"},"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|30"}},"backgroundColor":"base","layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group has-border-color has-base-background-color has-background" style="border-color:var(--wp--preset--color--line);border-style:solid;border-width:1px;border-radius:var(--wp--custom--radius--card);padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size"><?php esc_html_e( 'Write to us', 'pergel' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php esc_html_e( 'We answer within one working day.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"pergel/brand","args":{"key":"email"}}}},"style":{"typography":{"fontWeight":"600"}}} -->
<p style="font-weight:600"><?php echo esc_html_x( 'hello@example.com', 'sample email address', 'pergel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"border":{"width":"1px","style":"solid","color":"var:preset|color|line","radius":"var(\u002d\u002dwp\u002d\u002dcustom\u002d\u002dradius\u002d\u002dcard)"},"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|30"}},"backgroundColor":"base","layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group has-border-color has-base-background-color has-background" style="border-color:var(--wp--preset--color--line);border-style:solid;border-width:1px;border-radius:var(--wp--custom--radius--card);padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size"><?php esc_html_e( 'Visit us', 'pergel' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php esc_html_e( 'Please call ahead so we can welcome you.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"pergel/brand","args":{"key":"address"}}}},"style":{"typography":{"fontWeight":"600"}}} -->
<p style="font-weight:600"><?php echo esc_html_x( '1 Sample Street, 34000 Istanbul', 'sample address', 'pergel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
