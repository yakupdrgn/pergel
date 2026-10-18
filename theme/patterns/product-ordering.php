<?php
/**
 * Title: Product ordering
 * Slug: pergel/product-ordering
 * Categories: pergel-product
 * Keywords: product, ordering, sku, quote, variants
 * Viewport Width: 1000
 * Description: A heading, a table of order codes and pack sizes, and notes on ordering.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<section class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Ordering information', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:table {"hasFixedLayout":false,"className":"is-style-pergel-specs"} -->
<figure class="wp-block-table is-style-pergel-specs"><table><thead><tr><th><?php esc_html_e( 'Code', 'pergel' ); ?></th><th><?php esc_html_e( 'Description', 'pergel' ); ?></th><th><?php esc_html_e( 'Pack size', 'pergel' ); ?></th></tr></thead><tbody><tr><td><?php esc_html_e( 'P-100', 'pergel' ); ?></td><td><?php esc_html_e( 'Standard version', 'pergel' ); ?></td><td>1</td></tr><tr><td><?php esc_html_e( 'P-100-10', 'pergel' ); ?></td><td><?php esc_html_e( 'Standard version, pack of 10', 'pergel' ); ?></td><td>10</td></tr><tr><td><?php esc_html_e( 'P-100-S', 'pergel' ); ?></td><td><?php esc_html_e( 'Spare part set', 'pergel' ); ?></td><td>1</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Ask for a quote with the order codes you need; we answer within one working day.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Delivery times and prices depend on the quantity and the destination.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></section>
<!-- /wp:group -->
