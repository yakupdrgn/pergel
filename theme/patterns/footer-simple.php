<?php
/**
 * Title: Simple footer
 * Slug: pergel/footer-simple
 * Categories: footer
 * Block Types: core/template-part/footer
 * Viewport Width: 1400
 * Description: Site title and tagline on the left, footer menu on the right.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"footer","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"backgroundColor":"surface","layout":{"type":"constrained"}} -->
<footer class="wp-block-group alignfull has-surface-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:site-title {"level":0} /-->

<!-- wp:site-tagline {"fontSize":"small"} /--></div>
<!-- /wp:group -->

<!-- wp:navigation {"ariaLabel":"<?php echo esc_attr_x( 'Footer', 'footer menu label', 'pergel' ); ?>","overlayMenu":"never","fontSize":"small"} /--></div>
<!-- /wp:group --></footer>
<!-- /wp:group -->
