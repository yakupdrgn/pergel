<?php
/**
 * Title: Product reviews
 * Slug: pergel/hidden-product-reviews
 * Inserter: no
 * Description: The product's reviews with a title and the review form, after the content of a product with sections; nothing shows when reviews are off.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:woocommerce/product-reviews {"align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|70"}}}} -->
<div class="wp-block-woocommerce-product-reviews alignwide" style="margin-top:var(--wp--preset--spacing--60);margin-bottom:var(--wp--preset--spacing--70)"><!-- wp:woocommerce/product-reviews-title {"showProductTitle":false} /-->

<!-- wp:woocommerce/product-review-template -->
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
<!-- /wp:woocommerce/product-reviews -->
