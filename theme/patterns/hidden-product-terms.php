<?php
/**
 * Title: Product categories and tags
 * Slug: pergel/hidden-product-terms
 * Inserter: no
 * Description: The product's categories and tags, under the add to cart form.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:post-terms {"term":"product_cat","prefix":"<?php echo esc_attr_x( 'Category: ', 'product terms prefix', 'pergel' ); ?>"} /-->

<!-- wp:post-terms {"term":"product_tag","prefix":"<?php echo esc_attr_x( 'Tags: ', 'product terms prefix', 'pergel' ); ?>"} /-->
