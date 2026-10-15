<?php
/**
 * Title: Two projects, image and text
 * Slug: pergel/portfolio-split
 * Categories: portfolio
 * Keywords: portfolio, projects, alternating
 * Viewport Width: 1400
 * Description: Two projects with the image and text on alternating sides.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained","contentSize":"720px","justifyContent":"left"}} -->
<div class="wp-block-group alignwide"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontWeight":"600"}},"textColor":"accent","fontSize":"small"} -->
<p class="has-accent-color has-text-color has-small-font-size" style="font-weight:600;letter-spacing:0.08em;text-transform:uppercase"><?php esc_html_e( 'Our work', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Selected projects', 'pergel' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:media-text {"align":"wide","mediaType":"image","isStackedOnMobile":false,"verticalAlignment":"center","imageFill":false} -->
<div class="wp-block-media-text alignwide is-vertically-aligned-center"><figure class="wp-block-media-text__media"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/landscape.svg' ) ); ?>" alt=""/></figure><div class="wp-block-media-text__content"><!-- wp:paragraph {"textColor":"muted","fontSize":"small"} -->
<p class="has-muted-color has-text-color has-small-font-size"><?php esc_html_e( 'Healthcare', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php esc_html_e( 'City clinic upgrade', 'pergel' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php esc_html_e( 'Backup power and air handling for a clinic that never closes.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a href="#"><?php esc_html_e( 'View project', 'pergel' ); ?></a></p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:media-text -->

<!-- wp:media-text {"align":"wide","mediaPosition":"right","mediaType":"image","verticalAlignment":"center","imageFill":false} -->
<div class="wp-block-media-text alignwide has-media-on-the-right is-stacked-on-mobile is-vertically-aligned-center"><div class="wp-block-media-text__content"><!-- wp:paragraph {"textColor":"muted","fontSize":"small"} -->
<p class="has-muted-color has-text-color has-small-font-size"><?php esc_html_e( 'Industrial', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php esc_html_e( 'Riverside warehouse', 'pergel' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php esc_html_e( 'Full electrical installation for a 12,000 square metre logistics hall.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a href="#"><?php esc_html_e( 'View project', 'pergel' ); ?></a></p>
<!-- /wp:paragraph --></div><figure class="wp-block-media-text__media"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/landscape.svg' ) ); ?>" alt=""/></figure></div>
<!-- /wp:media-text --></section>
<!-- /wp:group -->
