<?php
/**
 * Title: Product comparison
 * Slug: pergel/product-compare
 * Categories: pergel-product
 * Keywords: product, compare, versions, table
 * Viewport Width: 1000
 * Description: A heading and a comparison table of the product's versions or similar products.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<section class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Compare versions', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:table {"hasFixedLayout":false,"className":"is-style-pergel-comparison"} -->
<figure class="wp-block-table is-style-pergel-comparison"><table><thead><tr><th></th><th><?php esc_html_e( 'Small', 'pergel' ); ?></th><th><?php esc_html_e( 'Medium', 'pergel' ); ?></th><th><?php esc_html_e( 'Large', 'pergel' ); ?></th></tr></thead><tbody><tr><td><?php esc_html_e( 'Capacity', 'pergel' ); ?></td><td><?php esc_html_e( '1 L', 'pergel' ); ?></td><td><?php esc_html_e( '2 L', 'pergel' ); ?></td><td><?php esc_html_e( '5 L', 'pergel' ); ?></td></tr><tr><td><?php esc_html_e( 'Accessories', 'pergel' ); ?></td><td><?php esc_html_e( 'Basic', 'pergel' ); ?></td><td><?php esc_html_e( 'Standard', 'pergel' ); ?></td><td><?php esc_html_e( 'Full', 'pergel' ); ?></td></tr><tr><td><?php esc_html_e( 'Warranty', 'pergel' ); ?></td><td><?php esc_html_e( '1 year', 'pergel' ); ?></td><td><?php esc_html_e( '2 years', 'pergel' ); ?></td><td><?php esc_html_e( '2 years', 'pergel' ); ?></td></tr></tbody></table></figure>
<!-- /wp:table --></section>
<!-- /wp:group -->
