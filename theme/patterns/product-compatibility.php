<?php
/**
 * Title: Product compatibility
 * Slug: pergel/product-compatibility
 * Categories: pergel-product
 * Keywords: product, compatibility, models, table
 * Viewport Width: 1000
 * Description: A heading, a table of the models or systems the product works with, and a note.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<section class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Compatibility', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:table {"hasFixedLayout":false,"className":"is-style-pergel-specs"} -->
<figure class="wp-block-table is-style-pergel-specs"><table><thead><tr><th><?php esc_html_e( 'Model', 'pergel' ); ?></th><th><?php esc_html_e( 'Series', 'pergel' ); ?></th><th><?php esc_html_e( 'Compatible', 'pergel' ); ?></th></tr></thead><tbody><tr><td><?php esc_html_e( 'Model A', 'pergel' ); ?></td><td><?php esc_html_e( 'Series 100', 'pergel' ); ?></td><td><?php esc_html_e( 'Yes', 'pergel' ); ?></td></tr><tr><td><?php esc_html_e( 'Model B', 'pergel' ); ?></td><td><?php esc_html_e( 'Series 200', 'pergel' ); ?></td><td><?php esc_html_e( 'Yes', 'pergel' ); ?></td></tr><tr><td><?php esc_html_e( 'Model C', 'pergel' ); ?></td><td><?php esc_html_e( 'Series 300', 'pergel' ); ?></td><td><?php esc_html_e( 'With adapter', 'pergel' ); ?></td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Not sure your model is listed? Send us its name and we will check it for you.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></section>
<!-- /wp:group -->
