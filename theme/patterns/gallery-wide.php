<?php
/**
 * Title: Full width image strip
 * Slug: pergel/gallery-wide
 * Categories: gallery
 * Keywords: gallery, images, full width
 * Viewport Width: 1400
 * Description: Four images edge to edge across the full width.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull" style="padding-top:0;padding-bottom:0"><!-- wp:gallery {"columns":4,"linkTo":"none","align":"full","style":{"spacing":{"blockGap":{"top":"0","left":"0"}}}} -->
<figure class="wp-block-gallery alignfull has-nested-images columns-4 is-cropped"><!-- wp:image {"linkDestination":"none"} -->
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
<!-- /wp:image --></figure>
<!-- /wp:gallery --></section>
<!-- /wp:group -->
