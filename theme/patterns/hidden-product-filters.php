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
 * the store has, or for the attributes the engine names, in its order
 * (filter pergel/shop/filter_attributes); the Site Editor can change them. The engine may ask for
 * the brand and rating filters too (filter pergel/shop/filters); with the
 * brand filter, an attribute named like the brands is left out, so the
 * list does not show two "Brand" filters.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$pergel_extra      = (array) apply_filters( 'pergel/shop/filters', array() );
$pergel_brands     = in_array( 'brand', $pergel_extra, true ) ? get_taxonomy( 'product_brand' ) : false;
$pergel_attributes = function_exists( 'wc_get_attribute_taxonomies' ) ? array_values( wc_get_attribute_taxonomies() ) : array();
if ( $pergel_brands instanceof WP_Taxonomy ) {
	$pergel_names      = array_map( 'mb_strtolower', array( 'brand', (string) $pergel_brands->label, (string) $pergel_brands->labels->singular_name ) );
	$pergel_attributes = array_values(
		array_filter(
			$pergel_attributes,
			static fn ( stdClass $attribute ): bool => ! in_array( mb_strtolower( (string) $attribute->attribute_name ), $pergel_names, true ) && ! in_array( mb_strtolower( (string) $attribute->attribute_label ), $pergel_names, true )
		)
	);
}
$pergel_chosen     = apply_filters( 'pergel/shop/filter_attributes', null, $pergel_attributes );
$pergel_attributes = is_array( $pergel_chosen ) ? array_values( array_filter( $pergel_chosen, static fn ( mixed $attribute ): bool => $attribute instanceof stdClass ) ) : array_slice( $pergel_attributes, 0, 3 );
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
<div class="wp-block-buttons"><!-- wp:button {"className":"wc-block-product-filter-clear-button is-style-outline","style":{"dimensions":{"width":"100%"}}} -->
<div class="wp-block-button wc-block-product-filter-clear-button is-style-outline"><a class="wp-block-button__link wp-element-button"><?php echo esc_html_x( 'Clear filters', 'product filters', 'pergel' ); ?></a></div>
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
<?php if ( in_array( 'brand', $pergel_extra, true ) && taxonomy_exists( 'product_brand' ) ) : ?>

<!-- wp:woocommerce/product-filter-taxonomy {"taxonomy":"product_brand","showCounts":true} -->
<div class="wp-block-woocommerce-product-filter-taxonomy"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html_x( 'Brand', 'product filters', 'pergel' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:woocommerce/product-filter-checkbox-list -->
<div class="wp-block-woocommerce-product-filter-checkbox-list wc-block-product-filter-checkbox-list"></div>
<!-- /wp:woocommerce/product-filter-checkbox-list --></div>
<!-- /wp:woocommerce/product-filter-taxonomy -->
<?php endif; ?>

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
<?php endforeach; ?>
<?php if ( in_array( 'rating', $pergel_extra, true ) ) : ?>

<!-- wp:woocommerce/product-filter-rating {"showCounts":true} -->
<div class="wp-block-woocommerce-product-filter-rating"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html_x( 'Rating', 'product filters', 'pergel' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:woocommerce/product-filter-checkbox-list -->
<div class="wp-block-woocommerce-product-filter-checkbox-list wc-block-product-filter-checkbox-list"></div>
<!-- /wp:woocommerce/product-filter-checkbox-list --></div>
<!-- /wp:woocommerce/product-filter-rating -->
<?php endif; ?></div>
<!-- /wp:woocommerce/product-filters -->
