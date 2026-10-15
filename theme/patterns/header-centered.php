<?php
/**
 * Title: Centered header
 * Slug: pergel/header-centered
 * Categories: header
 * Block Types: core/template-part/header
 * Viewport Width: 1400
 * Description: Logo and site title centered above a centered menu, with a bottom border.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"header","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|40"}},"border":{"bottom":{"color":"var:preset|color|line","width":"1px","style":"solid"}}},"layout":{"type":"constrained"}} -->
<header class="wp-block-group alignfull" style="border-bottom-color:var(--wp--preset--color--line);border-bottom-style:solid;border-bottom-width:1px;padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--40)"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"center"}} -->
<div class="wp-block-group"><!-- wp:site-logo /-->

<!-- wp:site-title {"level":0,"textAlign":"center"} /--></div>
<!-- /wp:group -->

<!-- wp:navigation {"ariaLabel":"<?php echo esc_attr_x( 'Primary', 'header menu label', 'pergel' ); ?>","overlayMenu":"mobile","overlayBackgroundColor":"base","overlayTextColor":"contrast","layout":{"type":"flex","justifyContent":"center"}} /--></div>
<!-- /wp:group --></header>
<!-- /wp:group -->
