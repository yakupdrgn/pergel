<?php
/**
 * Title: No search results
 * Slug: pergel/hidden-search-no-results
 * Inserter: no
 * Description: Message shown when a search finds nothing.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:paragraph -->
<p><?php esc_html_e( 'Nothing matched your search. Try different or fewer words.', 'pergel' ); ?></p>
<!-- /wp:paragraph -->
