<?php
/**
 * Title: Moving client logos
 * Slug: pergel/logos-marquee
 * Categories: pergel-logos
 * Keywords: logos, marquee, scrolling
 * Viewport Width: 1400
 * Description: Client logos that scroll slowly sideways; they stand still for visitors who prefer less motion.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)"><!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}},"textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color"><?php esc_html_e( 'Trusted by teams across the region', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:pergel/logo-marquee {"align":"wide"} -->
<!-- wp:image {"width":"160px","height":"40px","scale":"contain"} -->
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
<!-- /wp:image -->

<!-- wp:image {"width":"160px","height":"40px","scale":"contain"} -->
<figure class="wp-block-image is-resized"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/logo-6.svg' ) ); ?>" alt="" style="object-fit:contain;width:160px;height:40px"/></figure>
<!-- /wp:image -->
<!-- /wp:pergel/logo-marquee --></section>
<!-- /wp:group -->
