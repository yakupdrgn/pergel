<?php
/**
 * Title: Image mosaic
 * Slug: pergel/gallery-mosaic
 * Categories: gallery
 * Keywords: gallery, mosaic, images
 * Viewport Width: 1400
 * Description: One large image and four smaller ones in a mosaic.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"backgroundColor":"surface","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull has-surface-background-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained","contentSize":"720px","justifyContent":"left"}} -->
<div class="wp-block-group alignwide"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontWeight":"600"}},"textColor":"accent","fontSize":"small"} -->
<p class="has-accent-color has-text-color has-small-font-size" style="font-weight:600;letter-spacing:0.08em;text-transform:uppercase"><?php esc_html_e( 'Gallery', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Inside our projects', 'pergel' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"grid","columnCount":4,"minimumColumnWidth":"8rem"}} -->
<div class="wp-block-group alignwide"><!-- wp:image {"aspectRatio":"1","scale":"cover","style":{"border":{"radius":"var(\u002d\u002dwp\u002d\u002dcustom\u002d\u002dradius\u002d\u002dcard)"},"layout":{"columnSpan":2,"rowSpan":2}}} -->
<figure class="wp-block-image has-custom-border"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/square.svg' ) ); ?>" alt="" style="border-radius:var(--wp--custom--radius--card);aspect-ratio:1;object-fit:cover"/></figure>
<!-- /wp:image -->

<!-- wp:image {"aspectRatio":"1","scale":"cover","style":{"border":{"radius":"var(\u002d\u002dwp\u002d\u002dcustom\u002d\u002dradius\u002d\u002dcard)"}}} -->
<figure class="wp-block-image has-custom-border"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/square.svg' ) ); ?>" alt="" style="border-radius:var(--wp--custom--radius--card);aspect-ratio:1;object-fit:cover"/></figure>
<!-- /wp:image -->

<!-- wp:image {"aspectRatio":"1","scale":"cover","style":{"border":{"radius":"var(\u002d\u002dwp\u002d\u002dcustom\u002d\u002dradius\u002d\u002dcard)"}}} -->
<figure class="wp-block-image has-custom-border"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/square.svg' ) ); ?>" alt="" style="border-radius:var(--wp--custom--radius--card);aspect-ratio:1;object-fit:cover"/></figure>
<!-- /wp:image -->

<!-- wp:image {"aspectRatio":"1","scale":"cover","style":{"border":{"radius":"var(\u002d\u002dwp\u002d\u002dcustom\u002d\u002dradius\u002d\u002dcard)"}}} -->
<figure class="wp-block-image has-custom-border"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/square.svg' ) ); ?>" alt="" style="border-radius:var(--wp--custom--radius--card);aspect-ratio:1;object-fit:cover"/></figure>
<!-- /wp:image -->

<!-- wp:image {"aspectRatio":"1","scale":"cover","style":{"border":{"radius":"var(\u002d\u002dwp\u002d\u002dcustom\u002d\u002dradius\u002d\u002dcard)"}}} -->
<figure class="wp-block-image has-custom-border"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/square.svg' ) ); ?>" alt="" style="border-radius:var(--wp--custom--radius--card);aspect-ratio:1;object-fit:cover"/></figure>
<!-- /wp:image --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
