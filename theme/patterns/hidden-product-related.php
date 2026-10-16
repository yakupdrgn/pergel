<?php
/**
 * Title: Related products
 * Slug: pergel/hidden-product-related
 * Inserter: no
 * Description: Products from the same categories and tags, as cards like the shop's, under the product details.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:woocommerce/product-collection {"queryId":1,"query":{"perPage":4,"pages":1,"offset":0,"postType":"product","order":"asc","orderBy":"title","search":"","exclude":[],"inherit":false,"taxQuery":{},"isProductCollectionBlock":true,"featured":false,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[],"filterable":false,"relatedBy":{"categories":true,"tags":true}},"displayLayout":{"type":"flex","columns":4,"shrinkColumns":true},"dimensions":{"widthType":"fill"},"collection":"woocommerce/product-collection/related","hideControls":["inherit"],"queryContextIncludes":["collection"],"align":"wide"} -->
<div class="wp-block-woocommerce-product-collection alignwide"><!-- wp:heading {"fontSize":"large"} -->
<h2 class="wp-block-heading has-large-font-size"><?php echo esc_html_x( 'Related products', 'product page', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:woocommerce/product-template -->
<!-- wp:woocommerce/product-image {"showSaleBadge":false,"imageSizing":"thumbnail","isDescendentOfQueryLoop":true,"aspectRatio":"1","scale":"contain","style":{"border":{"radius":"var(--wp--custom--radius--card)"}}} -->
<!-- wp:woocommerce/product-sale-badge {"isDescendentOfQueryLoop":true,"align":"right"} /-->
<!-- /wp:woocommerce/product-image -->

<!-- wp:post-title {"level":3,"isLink":true,"fontSize":"medium","__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->

<!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true} /-->

<!-- wp:woocommerce/product-button {"textAlign":"left","isDescendentOfQueryLoop":true,"fontSize":"small"} /-->
<!-- /wp:woocommerce/product-template --></div>
<!-- /wp:woocommerce/product-collection -->
