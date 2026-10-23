<?php
/**
 * What deleting the plugin removes.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Core;

use Pergel\Forms\Inbox;
use Pergel\Package\Importer;
use Pergel\Search\Index;
use Pergel\Seo\Meta;
use Pergel\Settings\Settings;
use Pergel\Starters\Shop;
use Pergel\Starters\Starter;
use Pergel\Woo\Delivery;
use Pergel\Woo\FlashSalePrices;
use Pergel\Woo\FlashSales;
use Pergel\Woo\Lists;
use Pergel\Woo\Modes;
use Pergel\Woo\SizeGuide;
use Pergel\Woo\StockAlert;
use Pergel\Woo\StockAlertStore;

/**
 * Deleting the plugin (uninstall.php) removes what it stored on each site:
 * the settings, its tables (search index, stock alerts), scheduled events,
 * limit counters, form messages, flash sales (their prices go back first)
 * and the meta it keeps on posts, products,
 * terms and users (SEO title and description, sales modes, delivery days,
 * size guides, wishlists, starter and package marks). WordPress asks
 * "delete Pergel Engine and its data?" first. Pages, products, menus,
 * terms and media that a starter or package made stay: they are the site's
 * content, and so does a child theme a package wrote.
 *
 * PERGEL_KEEP_DATA set to true in wp-config.php keeps everything, e.g. to
 * put the plugin back from a zip.
 */
final class Uninstall {

	private const OPTIONS = array(
		Settings::OPTION,
		Modes::INDEX,
		Shop::ATTRIBUTES_OPTION,
		FlashSales::RULES,
		'woocommerce_' . StockAlert::EMAIL_CONFIRM . '_settings',
		'woocommerce_' . StockAlert::EMAIL_BACK . '_settings',
	);

	private const HOOKS = array( Index::BUILD_HOOK, Index::TERM_HOOK, StockAlert::SEND_HOOK );

	private const POST_META = array(
		Meta::TITLE,
		Meta::DESCRIPTION,
		Meta::NOINDEX,
		Modes::POST_META,
		SizeGuide::META,
		Importer::META,
		Importer::KEY,
		Importer::SOURCE,
		Importer::SAVED,
		Importer::FILE,
		Starter::META,
		Starter::META_KEY,
		FlashSalePrices::META,
	);

	private const TERM_META = array( Modes::TERM_META, Delivery::META, SizeGuide::META, Starter::META, Starter::META_KEY );

	private const USER_META = array( Lists::META );

	/**
	 * Form messages deleted per query.
	 */
	private const BATCH = 100;

	/**
	 * Removes the data of every site, unless PERGEL_KEEP_DATA is true.
	 */
	public static function run(): void {
		if ( defined( 'PERGEL_KEEP_DATA' ) && true === PERGEL_KEEP_DATA ) {
			return;
		}
		if ( ! is_multisite() ) {
			self::site();
			return;
		}
		foreach ( get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		) as $site ) {
			switch_to_blog( (int) $site );
			self::site();
			restore_current_blog();
		}
	}

	/**
	 * Removes the data of the current site.
	 */
	public static function site(): void {
		global $wpdb;
		Index::uninstall();
		StockAlertStore::uninstall();
		// Before the product meta goes: it holds the prices to put back.
		FlashSalePrices::uninstall();
		foreach ( self::HOOKS as $hook ) {
			wp_unschedule_hook( $hook );
		}
		foreach ( self::OPTIONS as $option ) {
			delete_option( $option );
		}
		// Limit counters (Core\Visitor, abilities). In an object cache they expire on their own.
		$counters = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_pergel_' ) . '%', $wpdb->esc_like( '_transient_timeout_pergel_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uninstall; nothing to cache.
		foreach ( $counters as $counter ) {
			delete_option( (string) $counter );
		}

		// The post type is not registered now, so the ids come from the table.
		do {
			$ids     = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID LIMIT %d", Inbox::TYPE, self::BATCH ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uninstall; nothing to cache.
			$found   = count( $ids );
			$deleted = 0;
			foreach ( $ids as $id ) {
				$deleted += wp_delete_post( (int) $id, true ) ? 1 : 0;
			}
			// A message a filter keeps from deletion would come back in every batch.
		} while ( self::BATCH === $found && $deleted > 0 );

		foreach ( self::POST_META as $key ) {
			delete_metadata( 'post', 0, $key, '', true );
		}
		foreach ( self::TERM_META as $key ) {
			delete_metadata( 'term', 0, $key, '', true );
		}
		foreach ( self::USER_META as $key ) {
			delete_metadata( 'user', 0, $key, '', true );
		}
	}
}
