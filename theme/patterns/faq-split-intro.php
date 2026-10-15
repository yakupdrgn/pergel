<?php
/**
 * Title: FAQ with introduction
 * Slug: pergel/faq-split-intro
 * Categories: pergel-faq
 * Keywords: faq, questions, accordion
 * Viewport Width: 1400
 * Description: Heading and contact link on the left, questions on the right.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|60","left":"var:preset|spacing|70"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"width":"35%"} -->
<div class="wp-block-column" style="flex-basis:35%"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontWeight":"600"}},"textColor":"accent","fontSize":"small"} -->
<p class="has-accent-color has-text-color has-small-font-size" style="font-weight:600;letter-spacing:0.08em;text-transform:uppercase"><?php esc_html_e( 'FAQ', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Questions we hear most', 'pergel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php esc_html_e( 'Cannot find your answer? Our team replies within one working day.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a href="#"><?php esc_html_e( 'Ask a question', 'pergel' ); ?></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column -->
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
<!-- /wp:separator -->

<!-- wp:details -->
<details class="wp-block-details"><summary><?php esc_html_e( 'How do I pay?', 'pergel' ); ?></summary><!-- wp:paragraph -->
<p><?php esc_html_e( 'By bank transfer. Projects are paid in two parts; plans are paid monthly or yearly.', 'pergel' ); ?></p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:separator {"opacity":"css","className":"is-style-wide","backgroundColor":"line"} -->
<hr class="wp-block-separator has-text-color has-line-color has-css-opacity has-line-background-color has-background is-style-wide"/>
<!-- /wp:separator --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></section>
<!-- /wp:group -->
