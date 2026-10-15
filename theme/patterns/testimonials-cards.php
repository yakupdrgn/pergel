<?php
/**
 * Title: Testimonial cards
 * Slug: pergel/testimonials-cards
 * Categories: testimonials
 * Keywords: testimonials, cards, reviews
 * Viewport Width: 1400
 * Description: Four quotes in cards on a light background.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"backgroundColor":"surface","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull has-surface-background-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained","contentSize":"720px","justifyContent":"left"}} -->
<div class="wp-block-group alignwide"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontWeight":"600"}},"textColor":"accent","fontSize":"small"} -->
<p class="has-accent-color has-text-color has-small-font-size" style="font-weight:600;letter-spacing:0.08em;text-transform:uppercase"><?php esc_html_e( 'Testimonials', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'What our clients say', 'pergel' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"grid","columnCount":2,"minimumColumnWidth":"18rem"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"style":{"border":{"width":"1px","style":"solid","color":"var:preset|color|line","radius":"var(\u002d\u002dwp\u002d\u002dcustom\u002d\u002dradius\u002d\u002dcard)"},"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|40"}},"backgroundColor":"base","layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-group has-border-color has-base-background-color has-background" style="border-color:var(--wp--preset--color--line);border-style:solid;border-width:1px;border-radius:var(--wp--custom--radius--card);padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:quote {"style":{"border":{"left":{"width":"0px","style":"none"}},"spacing":{"padding":{"left":"0"}},"typography":{"fontStyle":"normal"}},"fontSize":"medium"} -->
<blockquote class="wp-block-quote has-medium-font-size" style="border-left-style:none;border-left-width:0px;padding-left:0;font-style:normal"><!-- wp:paragraph -->
<p><?php esc_html_e( 'They found the cause of a problem two other firms had missed, fixed it in a day and explained it so we could understand.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></blockquote>
<!-- /wp:quote -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:image {"width":"48px","height":"48px","scale":"cover","style":{"border":{"radius":"999px"}}} -->
<figure class="wp-block-image is-resized has-custom-border"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/avatar.svg' ) ); ?>" alt="" style="border-radius:999px;object-fit:cover;width:48px;height:48px"/></figure>
<!-- /wp:image -->

<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"fontWeight":"600"}}} -->
<p style="font-weight:600"><?php echo esc_html_x( 'Alex Morgan', 'sample person name', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"textColor":"muted","fontSize":"small"} -->
<p class="has-muted-color has-text-color has-small-font-size"><?php echo esc_html_x( 'Operations manager', 'job title', 'pergel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"border":{"width":"1px","style":"solid","color":"var:preset|color|line","radius":"var(\u002d\u002dwp\u002d\u002dcustom\u002d\u002dradius\u002d\u002dcard)"},"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|40"}},"backgroundColor":"base","layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-group has-border-color has-base-background-color has-background" style="border-color:var(--wp--preset--color--line);border-style:solid;border-width:1px;border-radius:var(--wp--custom--radius--card);padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:quote {"style":{"border":{"left":{"width":"0px","style":"none"}},"spacing":{"padding":{"left":"0"}},"typography":{"fontStyle":"normal"}},"fontSize":"medium"} -->
<blockquote class="wp-block-quote has-medium-font-size" style="border-left-style:none;border-left-width:0px;padding-left:0;font-style:normal"><!-- wp:paragraph -->
<p><?php esc_html_e( 'The quote was clear, the work finished on the agreed date and the site was cleaner than they found it.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></blockquote>
<!-- /wp:quote -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:image {"width":"48px","height":"48px","scale":"cover","style":{"border":{"radius":"999px"}}} -->
<figure class="wp-block-image is-resized has-custom-border"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/avatar.svg' ) ); ?>" alt="" style="border-radius:999px;object-fit:cover;width:48px;height:48px"/></figure>
<!-- /wp:image -->

<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"fontWeight":"600"}}} -->
<p style="font-weight:600"><?php echo esc_html_x( 'Sam Taylor', 'sample person name', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"textColor":"muted","fontSize":"small"} -->
<p class="has-muted-color has-text-color has-small-font-size"><?php echo esc_html_x( 'Office manager', 'job title', 'pergel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"border":{"width":"1px","style":"solid","color":"var:preset|color|line","radius":"var(\u002d\u002dwp\u002d\u002dcustom\u002d\u002dradius\u002d\u002dcard)"},"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|40"}},"backgroundColor":"base","layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-group has-border-color has-base-background-color has-background" style="border-color:var(--wp--preset--color--line);border-style:solid;border-width:1px;border-radius:var(--wp--custom--radius--card);padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:quote {"style":{"border":{"left":{"width":"0px","style":"none"}},"spacing":{"padding":{"left":"0"}},"typography":{"fontStyle":"normal"}},"fontSize":"medium"} -->
<blockquote class="wp-block-quote has-medium-font-size" style="border-left-style:none;border-left-width:0px;padding-left:0;font-style:normal"><!-- wp:paragraph -->
<p><?php esc_html_e( 'Three years into our contract and we have never waited more than a day for an engineer.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></blockquote>
<!-- /wp:quote -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:image {"width":"48px","height":"48px","scale":"cover","style":{"border":{"radius":"999px"}}} -->
<figure class="wp-block-image is-resized has-custom-border"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/avatar.svg' ) ); ?>" alt="" style="border-radius:999px;object-fit:cover;width:48px;height:48px"/></figure>
<!-- /wp:image -->

<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"fontWeight":"600"}}} -->
<p style="font-weight:600"><?php echo esc_html_x( 'Jordan Lee', 'sample person name', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"textColor":"muted","fontSize":"small"} -->
<p class="has-muted-color has-text-color has-small-font-size"><?php echo esc_html_x( 'Facilities director', 'job title', 'pergel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"border":{"width":"1px","style":"solid","color":"var:preset|color|line","radius":"var(\u002d\u002dwp\u002d\u002dcustom\u002d\u002dradius\u002d\u002dcard)"},"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|40"}},"backgroundColor":"base","layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-group has-border-color has-base-background-color has-background" style="border-color:var(--wp--preset--color--line);border-style:solid;border-width:1px;border-radius:var(--wp--custom--radius--card);padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:quote {"style":{"border":{"left":{"width":"0px","style":"none"}},"spacing":{"padding":{"left":"0"}},"typography":{"fontStyle":"normal"}},"fontSize":"medium"} -->
<blockquote class="wp-block-quote has-medium-font-size" style="border-left-style:none;border-left-width:0px;padding-left:0;font-style:normal"><!-- wp:paragraph -->
<p><?php esc_html_e( 'Good advice from the first call. They talked us out of the expensive option because we did not need it.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></blockquote>
<!-- /wp:quote -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
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
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
