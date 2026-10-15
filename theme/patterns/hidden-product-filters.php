<?php
/**
 * Title: Product filters
 * Slug: pergel/hidden-product-filters
 * Inserter: no
 * Description: The shop's filters: the chosen filters, price, category, stock and the store's first attributes.
 *
 * WooCommerce shows these as a column on wide screens and behind a
 * "Filter products" button on narrow ones. An attribute filter needs the
 * attribute's ID, so one is added for each of the first three attributes
 * the store has; the Site Editor can change them.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$pergel_attributes = function_exists( 'wc_get_attribute_taxonomies' ) ? array_slice( array_values( wc_get_attribute_taxonomies() ), 0, 3 ) : array();
?>
<!-- wp:woocommerce/product-filters -->
<div class="wp-block-woocommerce-product-filters wc-block-product-filters"><!-- wp:heading {"className":"pergel-filters__title"} -->
<h2 class="wp-block-heading pergel-filters__title"><?php echo esc_html_x( 'Filters', 'product filters heading', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:woocommerce/product-filter-active -->
<div class="wp-block-woocommerce-product-filter-active"><!-- wp:woocommerce/product-filter-removable-chips -->
<div class="wp-block-woocommerce-product-filter-removable-chips wc-block-product-filter-removable-chips"></div>
<!-- /wp:woocommerce/product-filter-removable-chips -->

<!-- wp:woocommerce/product-filter-clear-button -->
<!-- wp:buttons {"layout":{"type":"flex","verticalAlignment":"stretched"}} -->
<div class="wp-block-buttons"><!-- wp:button {"width":100,"className":"wc-block-product-filter-clear-button is-style-outline"} -->
<div class="wp-block-button has-custom-width wp-block-button__width-100 wc-block-product-filter-clear-button is-style-outline"><a class="wp-block-button__link wp-element-button"><?php echo esc_html_x( 'Clear filters', 'product filters', 'pergel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
<!-- /wp:woocommerce/product-filter-clear-button --></div>
<!-- /wp:woocommerce/product-filter-active -->

<!-- wp:woocommerce/product-filter-price -->
<div class="wp-block-woocommerce-product-filter-price"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html_x( 'Price', 'product filters', 'pergel' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:woocommerce/product-filter-price-slider -->
<div class="wp-block-woocommerce-product-filter-price-slider wc-block-product-filter-price-slider"></div>
<!-- /wp:woocommerce/product-filter-price-slider --></div>
<!-- /wp:woocommerce/product-filter-price -->

<!-- wp:woocommerce/product-filter-taxonomy {"showCounts":true} -->
<div class="wp-block-woocommerce-product-filter-taxonomy"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html_x( 'Category', 'product filters', 'pergel' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:woocommerce/product-filter-checkbox-list -->
<div class="wp-block-woocommerce-product-filter-checkbox-list wc-block-product-filter-checkbox-list"></div>
<!-- /wp:woocommerce/product-filter-checkbox-list --></div>
<!-- /wp:woocommerce/product-filter-taxonomy -->

<!-- wp:woocommerce/product-filter-status {"showCounts":true} -->
<div class="wp-block-woocommerce-product-filter-status"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html_x( 'Availability', 'product filters', 'pergel' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:woocommerce/product-filter-checkbox-list -->
<div class="wp-block-woocommerce-product-filter-checkbox-list wc-block-product-filter-checkbox-list"></div>
<!-- /wp:woocommerce/product-filter-checkbox-list --></div>
<!-- /wp:woocommerce/product-filter-status -->
<?php foreach ( $pergel_attributes as $pergel_attribute ) : ?>

<!-- wp:woocommerce/product-filter-attribute {"attributeId":<?php echo (int) $pergel_attribute->attribute_id; ?>,"showCounts":true} -->
<div class="wp-block-woocommerce-product-filter-attribute"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html( $pergel_attribute->attribute_label ); ?></h3>
<!-- /wp:heading -->

<!-- wp:woocommerce/product-filter-checkbox-list -->
<div class="wp-block-woocommerce-product-filter-checkbox-list wc-block-product-filter-checkbox-list"></div>
<!-- /wp:woocommerce/product-filter-checkbox-list --></div>
<!-- /wp:woocommerce/product-filter-attribute -->
<?php endforeach; ?></div>
<!-- /wp:woocommerce/product-filters -->
