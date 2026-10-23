<?php
/**
 * Template: a flash sale.
 *
 * The product grid (class pergel-sale-products) is filled with the
 * campaign's products and left out once it has ended; the section with the
 * running campaigns (class pergel-sale-others) shows only then.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:template-part {"slug":"header","tagName":"div"} /-->

<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|50"}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--70)"><!-- wp:pergel/breadcrumb {"textColor":"muted","fontSize":"small"} /-->

<!-- wp:post-featured-image {"align":"wide","aspectRatio":"3/1","style":{"border":{"radius":"var(--wp--custom--radius--card)"}}} /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:post-title {"level":1} /-->

<!-- wp:pergel/sale-timer /-->

<!-- wp:post-content {"layout":{"type":"constrained"}} /--></div>
<!-- /wp:group -->

<!-- wp:woocommerce/product-collection {"queryId":2,"query":{"perPage":12,"pages":0,"offset":0,"postType":"product","order":"asc","orderBy":"title","search":"","exclude":[],"inherit":false,"taxQuery":{},"isProductCollectionBlock":true,"featured":false,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","outofstock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[],"filterable":false},"tagName":"div","displayLayout":{"type":"flex","columns":4,"shrinkColumns":true},"dimensions":{"widthType":"fill"},"queryContextIncludes":["collection"],"align":"wide","className":"pergel-sale-products"} -->
<div class="wp-block-woocommerce-product-collection alignwide pergel-sale-products"><!-- wp:woocommerce/product-template -->
<!-- wp:woocommerce/product-image {"imageSizing":"thumbnail","isDescendentOfQueryLoop":true,"aspectRatio":"1","scale":"contain","style":{"border":{"radius":"var(--wp--custom--radius--card)"}}} -->
<!-- wp:woocommerce/product-sale-badge {"isDescendentOfQueryLoop":true,"align":"right"} /-->
<!-- /wp:woocommerce/product-image -->

<!-- wp:post-title {"level":3,"isLink":true,"fontSize":"medium","__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->

<!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true} /-->

<!-- wp:woocommerce/product-button {"textAlign":"left","isDescendentOfQueryLoop":true,"fontSize":"small"} /-->
<!-- /wp:woocommerce/product-template -->

<!-- wp:query-pagination {"paginationArrow":"arrow","layout":{"type":"flex","justifyContent":"center"}} -->
<!-- wp:query-pagination-previous /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination -->

<!-- wp:woocommerce/product-collection-no-results -->
<!-- wp:paragraph -->
<p><?php esc_html_e( 'No products are in this campaign yet.', 'pergel-engine' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:woocommerce/product-collection-no-results --></div>
<!-- /wp:woocommerce/product-collection -->

<!-- wp:group {"align":"wide","className":"pergel-sale-others","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide pergel-sale-others"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Running and upcoming campaigns', 'pergel-engine' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:query {"queryId":3,"query":{"perPage":3,"pages":1,"offset":0,"postType":"pergel_sale","order":"asc","orderBy":"date","inherit":false}} -->
<div class="wp-block-query"><?php require __DIR__ . '/parts/sale-cards.php'; ?>

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p><?php esc_html_e( 'There is no flash sale right now. Have a look again soon.', 'pergel-engine' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query --></div>
<!-- /wp:group --></main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"div"} /-->
