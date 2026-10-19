<?php
/**
 * Title: FAQ with contact box
 * Slug: pergel/faq-with-contact
 * Categories: pergel-faq
 * Keywords: faq, contact, questions
 * Viewport Width: 1400
 * Description: Questions on the left and a box with a contact button on the right.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","className":"pergel-faq","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group pergel-faq alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained","contentSize":"720px","justifyContent":"left"}} -->
<div class="wp-block-group alignwide"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontWeight":"600"}},"textColor":"accent","fontSize":"small"} -->
<p class="has-accent-color has-text-color has-small-font-size" style="font-weight:600;letter-spacing:0.08em;text-transform:uppercase"><?php esc_html_e( 'FAQ', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Frequently asked questions', 'pergel' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|60","left":"var:preset|spacing|70"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:details -->
<details class="wp-block-details"><summary><?php esc_html_e( 'How quickly can you start?', 'pergel' ); ?></summary><!-- wp:paragraph -->
<p><?php esc_html_e( 'Most surveys happen within a week of your first call. Work usually starts two weeks after you approve the plan.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:separator {"opacity":"css","className":"is-style-wide","backgroundColor":"line"} -->
<hr class="wp-block-separator has-text-color has-line-color has-css-opacity has-line-background-color has-background is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:details -->
<details class="wp-block-details"><summary><?php esc_html_e( 'Is the price in the quote final?', 'pergel' ); ?></summary><!-- wp:paragraph -->
<p><?php esc_html_e( 'Yes. The quote is a fixed price for the work described. If you ask for changes, we price them before we do them.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:separator {"opacity":"css","className":"is-style-wide","backgroundColor":"line"} -->
<hr class="wp-block-separator has-text-color has-line-color has-css-opacity has-line-background-color has-background is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:details -->
<details class="wp-block-details"><summary><?php esc_html_e( 'Do you work outside office hours?', 'pergel' ); ?></summary><!-- wp:paragraph -->
<p><?php esc_html_e( 'Yes, at no extra cost for planned work. Emergency call-outs at night are included in the Premium plan.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:separator {"opacity":"css","className":"is-style-wide","backgroundColor":"line"} -->
<hr class="wp-block-separator has-text-color has-line-color has-css-opacity has-line-background-color has-background is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:details -->
<details class="wp-block-details"><summary><?php esc_html_e( 'What happens after the warranty ends?', 'pergel' ); ?></summary><!-- wp:paragraph -->
<p><?php esc_html_e( 'You can continue with a maintenance plan or call us when you need us. Your documents stay valid either way.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:separator {"opacity":"css","className":"is-style-wide","backgroundColor":"line"} -->
<hr class="wp-block-separator has-text-color has-line-color has-css-opacity has-line-background-color has-background is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:details -->
<details class="wp-block-details"><summary><?php esc_html_e( 'Can you work with our existing systems?', 'pergel' ); ?></summary><!-- wp:paragraph -->
<p><?php esc_html_e( 'In most cases, yes. We check compatibility during the survey and tell you before quoting.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:separator {"opacity":"css","className":"is-style-wide","backgroundColor":"line"} -->
<hr class="wp-block-separator has-text-color has-line-color has-css-opacity has-line-background-color has-background is-style-wide"/>
<!-- /wp:separator --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"35%"} -->
<div class="wp-block-column" style="flex-basis:35%"><!-- wp:group {"style":{"border":{"width":"1px","style":"solid","color":"var:preset|color|line","radius":"var(\u002d\u002dwp\u002d\u002dcustom\u002d\u002dradius\u002d\u002dcard)"},"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|30"}},"backgroundColor":"surface","layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group has-border-color has-surface-background-color has-background" style="border-color:var(--wp--preset--color--line);border-style:solid;border-width:1px;border-radius:var(--wp--custom--radius--card);padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php esc_html_e( 'Still have a question?', 'pergel' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php esc_html_e( 'Call or write to us. A real person answers.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Contact us', 'pergel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></section>
<!-- /wp:group -->
