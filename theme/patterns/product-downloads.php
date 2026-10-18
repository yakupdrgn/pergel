<?php
/**
 * Title: Product downloads
 * Slug: pergel/product-downloads
 * Categories: pergel-product
 * Keywords: product, downloads, data sheet, manual, pdf
 * Viewport Width: 1000
 * Description: A heading and the product's documents, such as a data sheet or a manual, to download.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<section class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Downloads', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"border":{"width":"1px","style":"solid","color":"var:preset|color|line","radius":"var(\u002d\u002dwp\u002d\u002dcustom\u002d\u002dradius\u002d\u002dcard)"},"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group has-border-color" style="border-color:var(--wp--preset--color--line);border-style:solid;border-width:1px;border-radius:var(--wp--custom--radius--card);padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--40)"><!-- wp:file {"href":"#"} -->
<div class="wp-block-file"><a id="wp-block-file--media-pergel-datasheet" href="#"><?php esc_html_e( 'Data sheet', 'pergel' ); ?></a><a href="#" class="wp-block-file__button wp-element-button" download aria-describedby="wp-block-file--media-pergel-datasheet"><?php esc_html_e( 'Download', 'pergel' ); ?></a></div>
<!-- /wp:file --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"border":{"width":"1px","style":"solid","color":"var:preset|color|line","radius":"var(\u002d\u002dwp\u002d\u002dcustom\u002d\u002dradius\u002d\u002dcard)"},"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group has-border-color" style="border-color:var(--wp--preset--color--line);border-style:solid;border-width:1px;border-radius:var(--wp--custom--radius--card);padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--40)"><!-- wp:file {"href":"#"} -->
<div class="wp-block-file"><a id="wp-block-file--media-pergel-manual" href="#"><?php esc_html_e( 'User manual', 'pergel' ); ?></a><a href="#" class="wp-block-file__button wp-element-button" download aria-describedby="wp-block-file--media-pergel-manual"><?php esc_html_e( 'Download', 'pergel' ); ?></a></div>
<!-- /wp:file --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
