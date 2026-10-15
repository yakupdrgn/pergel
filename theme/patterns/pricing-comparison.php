<?php
/**
 * Title: Plan comparison table
 * Slug: pergel/pricing-comparison
 * Categories: pergel-pricing
 * Keywords: pricing, comparison, table
 * Viewport Width: 1400
 * Description: A table comparing what each plan includes, row by row.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained","contentSize":"720px"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontWeight":"600","textAlign":"center"}},"textColor":"accent","fontSize":"small"} -->
<p class="has-text-align-center has-accent-color has-text-color has-small-font-size" style="font-weight:600;letter-spacing:0.08em;text-transform:uppercase"><?php esc_html_e( 'Compare plans', 'pergel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"style":{"typography":{"textAlign":"center"}}} -->
<h2 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'What each plan includes', 'pergel' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:table {"hasFixedLayout":false,"align":"wide","className":"is-style-pergel-comparison"} -->
<figure class="wp-block-table alignwide is-style-pergel-comparison"><table><thead><tr><th></th><th><?php esc_html_e( 'Basic', 'pergel' ); ?></th><th><?php esc_html_e( 'Standard', 'pergel' ); ?></th><th><?php esc_html_e( 'Premium', 'pergel' ); ?></th></tr></thead><tbody><tr><td><?php esc_html_e( 'Inspections per year', 'pergel' ); ?></td><td>1</td><td>2</td><td>4</td></tr><tr><td><?php esc_html_e( 'Phone support', 'pergel' ); ?></td><td><?php esc_html_e( 'Weekdays', 'pergel' ); ?></td><td><?php esc_html_e( 'Weekdays', 'pergel' ); ?></td><td><?php esc_html_e( 'Around the clock', 'pergel' ); ?></td></tr><tr><td><?php esc_html_e( 'Email support', 'pergel' ); ?></td><td><?php esc_html_e( 'No', 'pergel' ); ?></td><td><?php esc_html_e( 'Yes', 'pergel' ); ?></td><td><?php esc_html_e( 'Yes', 'pergel' ); ?></td></tr><tr><td><?php esc_html_e( 'Response time', 'pergel' ); ?></td><td><?php esc_html_e( '2 working days', 'pergel' ); ?></td><td><?php esc_html_e( '1 working day', 'pergel' ); ?></td><td><?php esc_html_e( 'Same day', 'pergel' ); ?></td></tr><tr><td><?php esc_html_e( 'Spare parts at cost', 'pergel' ); ?></td><td><?php esc_html_e( 'No', 'pergel' ); ?></td><td><?php esc_html_e( 'Yes', 'pergel' ); ?></td><td><?php esc_html_e( 'Yes', 'pergel' ); ?></td></tr><tr><td><?php esc_html_e( 'Named engineer', 'pergel' ); ?></td><td><?php esc_html_e( 'No', 'pergel' ); ?></td><td><?php esc_html_e( 'No', 'pergel' ); ?></td><td><?php esc_html_e( 'Yes', 'pergel' ); ?></td></tr></tbody><tfoot><tr><td><?php esc_html_e( 'Monthly price', 'pergel' ); ?></td><td><?php echo esc_html_x( '$49', 'sample price', 'pergel' ); ?></td><td><?php echo esc_html_x( '$99', 'sample price', 'pergel' ); ?></td><td><?php echo esc_html_x( '$199', 'sample price', 'pergel' ); ?></td></tr></tfoot></table></figure>
<!-- /wp:table --></section>
<!-- /wp:group -->
