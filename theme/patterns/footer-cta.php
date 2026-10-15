<?php
/**
 * Title: Footer with call to action
 * Slug: pergel/footer-cta
 * Categories: footer
 * Block Types: core/template-part/footer
 * Viewport Width: 1400
 * Description: A call-to-action band in the accent color above the site title and footer menu.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"footer","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|60"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<footer class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}},"border":{"radius":"var(--wp--custom--radius--card)"}},"backgroundColor":"accent","textColor":"on-accent","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide has-on-accent-color has-accent-background-color has-text-color has-background" style="border-radius:var(--wp--custom--radius--card);padding-top:var(--wp--preset--spacing--70);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--60)"><!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center"><?php echo esc_html_x( 'Have a project in mind?', 'footer call to action', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center"><?php echo esc_html_x( 'Tell us what you are building. We reply within one working day.', 'footer call to action', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"base","textColor":"contrast"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-base-background-color has-text-color has-background wp-element-button" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html_x( 'Get in touch', 'header button', 'pergel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:site-title {"level":0} /-->

<!-- wp:site-tagline {"fontSize":"small"} /--></div>
<!-- /wp:group -->

<!-- wp:navigation {"ariaLabel":"<?php echo esc_attr_x( 'Footer', 'footer menu label', 'pergel' ); ?>","overlayMenu":"never","fontSize":"small"} /--></div>
<!-- /wp:group --></footer>
<!-- /wp:group -->
