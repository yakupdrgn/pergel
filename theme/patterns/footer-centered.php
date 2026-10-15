<?php
/**
 * Title: Centered footer
 * Slug: pergel/footer-centered
 * Categories: footer
 * Block Types: core/template-part/footer
 * Viewport Width: 1400
 * Description: Logo, site title, tagline and footer menu stacked in the center.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"footer","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|60"}}},"backgroundColor":"surface","layout":{"type":"constrained"}} -->
<footer class="wp-block-group alignfull has-surface-background-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
<div class="wp-block-group"><!-- wp:site-logo /-->

<!-- wp:site-title {"level":0,"textAlign":"center"} /-->

<!-- wp:site-tagline {"textAlign":"center","fontSize":"small"} /-->

<!-- wp:navigation {"ariaLabel":"<?php echo esc_attr_x( 'Footer', 'footer menu label', 'pergel' ); ?>","overlayMenu":"never","fontSize":"small","layout":{"type":"flex","justifyContent":"center"}} /--></div>
<!-- /wp:group --></footer>
<!-- /wp:group -->
