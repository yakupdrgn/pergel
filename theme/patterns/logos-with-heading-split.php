<?php
/**
 * Title: Client logos beside a heading
 * Slug: pergel/logos-with-heading-split
 * Categories: pergel-logos
 * Keywords: logos, clients, columns
 * Viewport Width: 1400
 * Description: A heading on the left and a three by two grid of logos on the right.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:columns {"verticalAlignment":"center","align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|60","left":"var:preset|spacing|70"}}}} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"35%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:35%"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Over 300 companies rely on us', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php esc_html_e( 'From local shops to national chains.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"grid","columnCount":3,"minimumColumnWidth":"7rem"}} -->
<div class="wp-block-group"><!-- wp:image {"width":"160px","height":"40px","scale":"contain","align":"center"} -->
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
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></section>
<!-- /wp:group -->
