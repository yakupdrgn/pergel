<?php
/**
 * Template part: flash sale cards in a Query Loop.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:post-template {"layout":{"type":"grid","minimumColumnWidth":"18rem"}} -->
<!-- wp:group {"tagName":"article","style":{"spacing":{"blockGap":"var:preset|spacing|30","padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|40","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}},"border":{"width":"1px","radius":"var(--wp--custom--radius--card)"}},"borderColor":"line","layout":{"type":"flex","orientation":"vertical","flexWrap":"nowrap","justifyContent":"stretch"}} -->
<article class="wp-block-group has-border-color has-line-border-color" style="border-width:1px;border-radius:var(--wp--custom--radius--card);padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--30)"><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9","style":{"border":{"radius":"var(--wp--custom--radius--card)"}}} /-->

<!-- wp:pergel/sale-timer {"countdown":false,"fontSize":"small"} /-->

<!-- wp:post-title {"level":2,"isLink":true,"fontSize":"large"} /-->

<!-- wp:post-excerpt {"excerptLength":24} /-->

<!-- wp:read-more {"content":"<?php echo esc_attr( __( 'See the products', 'pergel-engine' ) ); ?>"} /--></article>
<!-- /wp:group -->
<!-- /wp:post-template -->
