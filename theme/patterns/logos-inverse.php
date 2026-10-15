<?php
/**
 * Title: Client logos on a dark background
 * Slug: pergel/logos-inverse
 * Categories: pergel-logos
 * Keywords: logos, clients, dark
 * Viewport Width: 1400
 * Description: A short line and six client logos on a dark background.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|60"},"elements":{"link":{"color":{"text":"var:preset|color|base"}}}},"backgroundColor":"contrast","textColor":"base","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull has-base-color has-contrast-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)"><!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}}} -->
<p class="has-text-align-center"><?php esc_html_e( 'Trusted by teams across the region', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"grid","columnCount":6,"minimumColumnWidth":"8rem"}} -->
<div class="wp-block-group alignwide"><!-- wp:image {"width":"160px","height":"40px","scale":"contain","align":"center"} -->
<figure class="wp-block-image aligncenter is-resized"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/logo-1.svg' ) ); ?>" alt="" style="object-fit:contain;width:160px;height:40px"/></figure>
<!-- /wp:image -->

<!-- wp:image {"width":"160px","height":"40px","scale":"contain","align":"center"} -->
<figure class="wp-block-image aligncenter is-resized"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/logo-2.svg' ) ); ?>" alt="" style="object-fit:contain;width:160px;height:40px"/></figure>
<!-- /wp:image -->

<!-- wp:image {"width":"160px","height":"40px","scale":"contain","align":"center"} -->
<figure class="wp-block-image aligncenter is-resized"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/logo-3.svg' ) ); ?>" alt="" style="object-fit:contain;width:160px;height:40px"/></figure>
<!-- /wp:image -->

<!-- wp:image {"width":"160px","height":"40px","scale":"contain","align":"center"} -->
<figure class="wp-block-image aligncenter is-resized"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/logo-4.svg' ) ); ?>" alt="" style="object-fit:contain;width:160px;height:40px"/></figure>
<!-- /wp:image -->

<!-- wp:image {"width":"160px","height":"40px","scale":"contain","align":"center"} -->
<figure class="wp-block-image aligncenter is-resized"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/logo-5.svg' ) ); ?>" alt="" style="object-fit:contain;width:160px;height:40px"/></figure>
<!-- /wp:image -->

<!-- wp:image {"width":"160px","height":"40px","scale":"contain","align":"center"} -->
<figure class="wp-block-image aligncenter is-resized"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/logo-6.svg' ) ); ?>" alt="" style="object-fit:contain;width:160px;height:40px"/></figure>
<!-- /wp:image --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
