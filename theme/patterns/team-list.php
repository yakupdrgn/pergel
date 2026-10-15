<?php
/**
 * Title: Team as a list
 * Slug: pergel/team-list
 * Categories: team
 * Keywords: team, list, people
 * Viewport Width: 1400
 * Description: People listed in rows with a small photo, name and role.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained","contentSize":"720px","justifyContent":"left"}} -->
<div class="wp-block-group alignwide"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontWeight":"600"}},"textColor":"accent","fontSize":"small"} -->
<p class="has-accent-color has-text-color has-small-font-size" style="font-weight:600;letter-spacing:0.08em;text-transform:uppercase"><?php esc_html_e( 'Our team', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'The people you will work with', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted","fontSize":"large"} -->
<p class="has-muted-color has-text-color has-large-font-size"><?php esc_html_e( 'A small, experienced team. You will know who is coming and who to call.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained","contentSize":"720px","justifyContent":"left"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:image {"width":"56px","height":"56px","scale":"cover","style":{"border":{"radius":"999px"}}} -->
<figure class="wp-block-image is-resized has-custom-border"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/avatar.svg' ) ); ?>" alt="" style="border-radius:999px;object-fit:cover;width:56px;height:56px"/></figure>
<!-- /wp:image -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size"><?php echo esc_html_x( 'Alex Morgan', 'sample person name', 'pergel' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted","fontSize":"small"} -->
<p class="has-muted-color has-text-color has-small-font-size"><?php echo esc_html_x( 'Founder', 'job title', 'pergel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:separator {"opacity":"css","className":"is-style-wide","backgroundColor":"line"} -->
<hr class="wp-block-separator has-text-color has-line-color has-css-opacity has-line-background-color has-background is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:image {"width":"56px","height":"56px","scale":"cover","style":{"border":{"radius":"999px"}}} -->
<figure class="wp-block-image is-resized has-custom-border"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/avatar.svg' ) ); ?>" alt="" style="border-radius:999px;object-fit:cover;width:56px;height:56px"/></figure>
<!-- /wp:image -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size"><?php echo esc_html_x( 'Sam Taylor', 'sample person name', 'pergel' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted","fontSize":"small"} -->
<p class="has-muted-color has-text-color has-small-font-size"><?php echo esc_html_x( 'Head of engineering', 'job title', 'pergel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:separator {"opacity":"css","className":"is-style-wide","backgroundColor":"line"} -->
<hr class="wp-block-separator has-text-color has-line-color has-css-opacity has-line-background-color has-background is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:image {"width":"56px","height":"56px","scale":"cover","style":{"border":{"radius":"999px"}}} -->
<figure class="wp-block-image is-resized has-custom-border"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/avatar.svg' ) ); ?>" alt="" style="border-radius:999px;object-fit:cover;width:56px;height:56px"/></figure>
<!-- /wp:image -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size"><?php echo esc_html_x( 'Jordan Lee', 'sample person name', 'pergel' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted","fontSize":"small"} -->
<p class="has-muted-color has-text-color has-small-font-size"><?php echo esc_html_x( 'Project manager', 'job title', 'pergel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:separator {"opacity":"css","className":"is-style-wide","backgroundColor":"line"} -->
<hr class="wp-block-separator has-text-color has-line-color has-css-opacity has-line-background-color has-background is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:image {"width":"56px","height":"56px","scale":"cover","style":{"border":{"radius":"999px"}}} -->
<figure class="wp-block-image is-resized has-custom-border"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/avatar.svg' ) ); ?>" alt="" style="border-radius:999px;object-fit:cover;width:56px;height:56px"/></figure>
<!-- /wp:image -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size"><?php echo esc_html_x( 'Robin Clarke', 'sample person name', 'pergel' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted","fontSize":"small"} -->
<p class="has-muted-color has-text-color has-small-font-size"><?php echo esc_html_x( 'Support lead', 'job title', 'pergel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:separator {"opacity":"css","className":"is-style-wide","backgroundColor":"line"} -->
<hr class="wp-block-separator has-text-color has-line-color has-css-opacity has-line-background-color has-background is-style-wide"/>
<!-- /wp:separator --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
