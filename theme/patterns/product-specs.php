<?php
/**
 * Title: Product specifications
 * Slug: pergel/product-specs
 * Categories: pergel-product
 * Keywords: product, specifications, technical data, table
 * Viewport Width: 1000
 * Description: A heading, a two-column table of technical data and an image beside it.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<section class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Specifications', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:columns {"style":{"spacing":{"blockGap":{"top":"var:preset|spacing|40","left":"var:preset|spacing|60"}}}} -->
<div class="wp-block-columns"><!-- wp:column {"width":"60%"} -->
<div class="wp-block-column" style="flex-basis:60%"><!-- wp:table {"hasFixedLayout":false,"className":"is-style-pergel-specs"} -->
<figure class="wp-block-table is-style-pergel-specs"><table><tbody><tr><td><?php esc_html_e( 'Material', 'pergel' ); ?></td><td><?php esc_html_e( 'Stainless steel', 'pergel' ); ?></td></tr><tr><td><?php esc_html_e( 'Dimensions', 'pergel' ); ?></td><td><?php esc_html_e( '120 x 80 x 40 mm', 'pergel' ); ?></td></tr><tr><td><?php esc_html_e( 'Weight', 'pergel' ); ?></td><td><?php esc_html_e( '450 g', 'pergel' ); ?></td></tr><tr><td><?php esc_html_e( 'Temperature range', 'pergel' ); ?></td><td><?php esc_html_e( '-20 to 120 C', 'pergel' ); ?></td></tr><tr><td><?php esc_html_e( 'Certification', 'pergel' ); ?></td><td><?php esc_html_e( 'CE', 'pergel' ); ?></td></tr></tbody></table></figure>
<!-- /wp:table --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"40%"} -->
<div class="wp-block-column" style="flex-basis:40%"><!-- wp:image {"aspectRatio":"1","scale":"cover","style":{"border":{"radius":"var(\u002d\u002dwp\u002d\u002dcustom\u002d\u002dradius\u002d\u002dcard)"}}} -->
<figure class="wp-block-image has-custom-border"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/placeholders/square.svg' ) ); ?>" alt="" style="border-radius:var(--wp--custom--radius--card);aspect-ratio:1;object-fit:cover"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></section>
<!-- /wp:group -->
