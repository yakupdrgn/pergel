<?php
/**
 * Title: Product figure
 * Slug: pergel/product-figure
 * Categories: pergel-product
 * Keywords: product, image, diagram, figure
 * Viewport Width: 1000
 * Description: A heading, a wide image and a paragraph, for a product photo, a diagram or a drawing.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<section class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'How it works', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:image {"aspectRatio":"16/9","scale":"cover","style":{"border":{"radius":"var(\u002d\u002dwp\u002d\u002dcustom\u002d\u002dradius\u002d\u002dcard)"}}} -->
<figure class="wp-block-image has-custom-border"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/landscape.svg' ) ); ?>" alt="" style="border-radius:var(--wp--custom--radius--card);aspect-ratio:16/9;object-fit:cover"/></figure>
<!-- /wp:image -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Explain what the image shows: the parts, how they fit together and what the customer should notice.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></section>
<!-- /wp:group -->
