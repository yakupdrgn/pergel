<?php
/**
 * Title: Product FAQ
 * Slug: pergel/product-faq
 * Categories: pergel-product
 * Keywords: product, faq, questions
 * Viewport Width: 1000
 * Description: A heading and questions about the product that open to show the answer; search engines read them as an FAQ.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","className":"pergel-faq","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<section class="wp-block-group pergel-faq" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Questions about this product', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:details -->
<details class="wp-block-details"><summary><?php esc_html_e( 'Is it delivered ready to use?', 'pergel' ); ?></summary><!-- wp:paragraph -->
<p><?php esc_html_e( 'Yes. It is tested before it leaves us and comes with everything needed to start.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:separator {"opacity":"css","className":"is-style-wide","backgroundColor":"line"} -->
<hr class="wp-block-separator has-text-color has-line-color has-css-opacity has-line-background-color has-background is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:details -->
<details class="wp-block-details"><summary><?php esc_html_e( 'Can I order spare parts?', 'pergel' ); ?></summary><!-- wp:paragraph -->
<p><?php esc_html_e( 'Yes. Spare parts are listed in the ordering section; ask us for anything not listed.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:separator {"opacity":"css","className":"is-style-wide","backgroundColor":"line"} -->
<hr class="wp-block-separator has-text-color has-line-color has-css-opacity has-line-background-color has-background is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:details -->
<details class="wp-block-details"><summary><?php esc_html_e( 'How long is the warranty?', 'pergel' ); ?></summary><!-- wp:paragraph -->
<p><?php esc_html_e( 'Two years from delivery, covering manufacturing faults.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:separator {"opacity":"css","className":"is-style-wide","backgroundColor":"line"} -->
<hr class="wp-block-separator has-text-color has-line-color has-css-opacity has-line-background-color has-background is-style-wide"/>
<!-- /wp:separator --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
