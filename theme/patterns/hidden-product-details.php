<?php
/**
 * Title: Product details
 * Slug: pergel/hidden-product-details
 * Inserter: no
 * Description: The product's description, specifications and reviews in a block accordion, under the product summary.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:woocommerce/product-details {"align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|70"}}}} -->
<div class="wp-block-woocommerce-product-details alignwide" style="margin-top:var(--wp--preset--spacing--60);margin-bottom:var(--wp--preset--spacing--70)"><!-- wp:accordion {"headingLevel":2} -->
<div role="group" class="wp-block-accordion"><!-- wp:accordion-item {"openByDefault":true} -->
<div class="wp-block-accordion-item is-open"><!-- wp:accordion-heading {"level":2} -->
<h2 class="wp-block-accordion-heading has-icon has-icon-right"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title"><?php echo esc_html_x( 'Description', 'product details', 'pergel' ); ?></span><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h2>
<!-- /wp:accordion-heading -->

<!-- wp:accordion-panel -->
<div role="region" class="wp-block-accordion-panel"><!-- wp:woocommerce/product-description /--></div>
<!-- /wp:accordion-panel --></div>
<!-- /wp:accordion-item -->

<!-- wp:accordion-item -->
<div class="wp-block-accordion-item"><!-- wp:accordion-heading {"level":2} -->
<h2 class="wp-block-accordion-heading has-icon has-icon-right"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title"><?php echo esc_html_x( 'Specifications', 'product details', 'pergel' ); ?></span><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h2>
<!-- /wp:accordion-heading -->

<!-- wp:accordion-panel -->
<div role="region" class="wp-block-accordion-panel"><!-- wp:woocommerce/product-specifications /--></div>
<!-- /wp:accordion-panel --></div>
<!-- /wp:accordion-item -->

<!-- wp:accordion-item -->
<div class="wp-block-accordion-item"><!-- wp:accordion-heading {"level":2} -->
<h2 class="wp-block-accordion-heading has-icon has-icon-right"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title"><?php echo esc_html_x( 'Reviews', 'product details', 'pergel' ); ?></span><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h2>
<!-- /wp:accordion-heading -->

<!-- wp:accordion-panel -->
<div role="region" class="wp-block-accordion-panel"><!-- wp:woocommerce/product-reviews -->
<div class="wp-block-woocommerce-product-reviews"><!-- wp:woocommerce/product-review-template -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group"><!-- wp:woocommerce/product-review-author-name {"fontSize":"small"} /-->

<!-- wp:woocommerce/product-review-rating /--></div>
<!-- /wp:group -->

<!-- wp:woocommerce/product-review-date {"fontSize":"small"} /-->

<!-- wp:woocommerce/product-review-content /-->
<!-- /wp:woocommerce/product-review-template -->

<!-- wp:woocommerce/product-reviews-pagination -->
<!-- wp:woocommerce/product-reviews-pagination-previous /-->

<!-- wp:woocommerce/product-reviews-pagination-numbers /-->

<!-- wp:woocommerce/product-reviews-pagination-next /-->
<!-- /wp:woocommerce/product-reviews-pagination -->

<!-- wp:woocommerce/product-review-form /--></div>
<!-- /wp:woocommerce/product-reviews --></div>
<!-- /wp:accordion-panel --></div>
<!-- /wp:accordion-item --></div>
<!-- /wp:accordion --></div>
<!-- /wp:woocommerce/product-details -->
