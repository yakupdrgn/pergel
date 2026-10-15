<?php
/**
 * Title: Header with search
 * Slug: pergel/header-search
 * Categories: header
 * Block Types: core/template-part/header
 * Viewport Width: 1400
 * Description: Logo and site title, menu and a search button that opens a search field.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"header","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} -->
<header class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)"><!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:site-logo {"width":40} /-->

<!-- wp:site-title {"level":0} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
<div class="wp-block-group"><!-- wp:navigation {"ariaLabel":"<?php echo esc_attr_x( 'Primary', 'header menu label', 'pergel' ); ?>","overlayMenu":"mobile","overlayBackgroundColor":"base","overlayTextColor":"contrast"} /-->

<!-- wp:search {"label":"<?php echo esc_attr_x( 'Search', 'search form label', 'pergel' ); ?>","showLabel":false,"placeholder":"<?php echo esc_attr_x( 'Search…', 'search input placeholder', 'pergel' ); ?>","buttonText":"<?php echo esc_attr_x( 'Search', 'search button text', 'pergel' ); ?>","buttonPosition":"button-only","buttonUseIcon":true,"isSearchFieldHidden":true} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group --></header>
<!-- /wp:group -->
