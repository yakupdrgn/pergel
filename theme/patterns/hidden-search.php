<?php
/**
 * Title: Search form
 * Slug: pergel/hidden-search
 * Inserter: no
 * Description: Search form with translated label and button text.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:search {"label":"<?php echo esc_attr_x( 'Search', 'search form label', 'pergel' ); ?>","showLabel":false,"placeholder":"<?php echo esc_attr_x( 'Search…', 'search input placeholder', 'pergel' ); ?>","buttonText":"<?php echo esc_attr_x( 'Search', 'search button text', 'pergel' ); ?>"} /-->
