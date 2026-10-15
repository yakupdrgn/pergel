<?php
/**
 * Title: Hero with client logos
 * Slug: pergel/hero-logos
 * Categories: banner
 * Keywords: hero, logos, clients, trust
 * Viewport Width: 1400
 * Description: Centered page title and buttons, then a row of client logos.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained","contentSize":"760px"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":1,"style":{"typography":{"textAlign":"center"}}} -->
<h1 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'Precise work for teams that cannot afford mistakes', 'pergel' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}},"textColor":"muted","fontSize":"large"} -->
<p class="has-text-align-center has-muted-color has-text-color has-large-font-size"><?php esc_html_e( 'We plan, deliver and support the systems your daily work depends on, with clear prices and one team you can call.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Request a quote', 'pergel' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Our services', 'pergel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|40","padding":{"top":"var:preset|spacing|50"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--50)"><!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}},"textColor":"muted","fontSize":"small"} -->
<p class="has-text-align-center has-muted-color has-text-color has-small-font-size"><?php esc_html_e( 'Trusted by teams at', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|60"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"center"}} -->
<div class="wp-block-group"><!-- wp:image {"width":"160px","height":"40px","scale":"contain"} -->
<figure class="wp-block-image is-resized"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/logo-1.svg' ) ); ?>" alt="" style="object-fit:contain;width:160px;height:40px"/></figure>
<!-- /wp:image -->

<!-- wp:image {"width":"160px","height":"40px","scale":"contain"} -->
<figure class="wp-block-image is-resized"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/logo-2.svg' ) ); ?>" alt="" style="object-fit:contain;width:160px;height:40px"/></figure>
<!-- /wp:image -->

<!-- wp:image {"width":"160px","height":"40px","scale":"contain"} -->
<figure class="wp-block-image is-resized"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/logo-3.svg' ) ); ?>" alt="" style="object-fit:contain;width:160px;height:40px"/></figure>
<!-- /wp:image -->

<!-- wp:image {"width":"160px","height":"40px","scale":"contain"} -->
<figure class="wp-block-image is-resized"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/logo-4.svg' ) ); ?>" alt="" style="object-fit:contain;width:160px;height:40px"/></figure>
<!-- /wp:image -->

<!-- wp:image {"width":"160px","height":"40px","scale":"contain"} -->
<figure class="wp-block-image is-resized"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/logo-5.svg' ) ); ?>" alt="" style="object-fit:contain;width:160px;height:40px"/></figure>
<!-- /wp:image --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
