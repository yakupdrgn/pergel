<?php
/**
 * Title: No posts
 * Slug: pergel/hidden-no-results
 * Inserter: no
 * Description: Message shown when a post list is empty.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:paragraph -->
<p><?php esc_html_e( 'There are no posts here yet.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->
