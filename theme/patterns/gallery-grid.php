<?php
/**
 * Title: Image gallery
 * Slug: pergel/gallery-grid
 * Categories: gallery
 * Keywords: gallery, images, photos
 * Viewport Width: 1400
 * Description: Heading and six images in three columns.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained","contentSize":"720px"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontWeight":"600","textAlign":"center"}},"textColor":"accent","fontSize":"small"} -->
<p class="has-text-align-center has-accent-color has-text-color has-small-font-size" style="font-weight:600;letter-spacing:0.08em;text-transform:uppercase"><?php esc_html_e( 'Gallery', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"style":{"typography":{"textAlign":"center"}}} -->
<h2 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'A look at our work', 'pergel' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:gallery {"columns":3,"linkTo":"none","align":"wide"} -->
<figure class="wp-block-gallery alignwide has-nested-images columns-3 is-cropped"><!-- wp:image {"linkDestination":"none"} -->
<figure class="wp-block-image"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/landscape.svg' ) ); ?>" alt=""/></figure>
<!-- /wp:image -->

<!-- wp:image {"linkDestination":"none"} -->
<figure class="wp-block-image"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/portrait.svg' ) ); ?>" alt=""/></figure>
<!-- /wp:image -->

<!-- wp:image {"linkDestination":"none"} -->
<figure class="wp-block-image"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/square.svg' ) ); ?>" alt=""/></figure>
<!-- /wp:image -->

<!-- wp:image {"linkDestination":"none"} -->
<figure class="wp-block-image"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/wide.svg' ) ); ?>" alt=""/></figure>
<!-- /wp:image -->

<!-- wp:image {"linkDestination":"none"} -->
<figure class="wp-block-image"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/landscape.svg' ) ); ?>" alt=""/></figure>
<!-- /wp:image -->

<!-- wp:image {"linkDestination":"none"} -->
<figure class="wp-block-image"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/square.svg' ) ); ?>" alt=""/></figure>
<!-- /wp:image --></figure>
<!-- /wp:gallery --></section>
<!-- /wp:group -->
