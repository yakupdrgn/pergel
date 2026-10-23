<?php
/**
 * The shop module.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Pergel\Core;
use Pergel\Settings\Settings;

/**
 * Pergel's WooCommerce support. Loads only while WooCommerce is active, so
 * a site without a shop runs none of this code.
 */
final class Module implements Core\Module {

	/**
	 * {@inheritDoc}
	 */
	public static function id(): string {
		return 'woo';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function should_load(): bool {
		return self::active();
	}

	/**
	 * Whether WooCommerce is active. Plugins are all loaded by the time the
	 * engine boots, so the class check is enough.
	 */
	public static function active(): bool {
		return class_exists( 'WooCommerce', false );
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'before_woocommerce_init', array( self::class, 'declare_compatibility' ) );
		Modes::register();
		Fields::register();
		Layout::register();
		Selling::register();
		Pages::register();
		MiniCart::register();
		Trail::register();
		Sections::register();
		Properties::register();
		// Read here, so a shop without the lists does not load their class.
		if ( true === Settings::get( 'woo.wishlist' ) || true === Settings::get( 'woo.compare' ) ) {
			Lists::register();
		}
		// Also with the wishlist off, so a saved list stays exportable and erasable.
		add_filter( 'wp_privacy_personal_data_exporters', array( Lists::class, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( Lists::class, 'erasers' ) );
		if ( true === Settings::get( 'woo.swatches' ) ) {
			Swatches::register();
		}
		if ( true === Settings::get( 'woo.size_guide' ) ) {
			SizeGuide::register();
		}
		// Before the bar, so the estimate comes right after the add-to-cart form.
		if ( true === Settings::get( 'woo.delivery' ) ) {
			Delivery::register();
		}
		if ( true === Settings::get( 'woo.stock_alert' ) ) {
			StockAlert::register();
		}
		// Also with the alerts off, so the stored addresses stay listed and erasable.
		if ( StockAlertStore::installed() ) {
			StockAlertStore::register();
		}
		if ( true === Settings::get( 'woo.bought_together' ) ) {
			BoughtTogether::register();
		}
		if ( true === Settings::get( 'woo.sticky_cart' ) ) {
			StickyCart::register();
		}
		if ( true === Settings::get( 'woo.bottom_bar' ) && ! is_admin() ) {
			BottomBar::register();
		}
		// Read here, so a shop without the bars does not load their class.
		foreach ( array( 'mini', 'cart', 'product', 'checkout' ) as $place ) {
			if ( true === Settings::get( "woo.shipping_$place" ) ) {
				FreeShipping::register();
				break;
			}
		}
		// The Product enquiry block's quote form.
		add_filter( 'pergel/forms/subject_fields', array( Quote::class, 'fields' ), 10, 3 );
		add_filter( 'pergel/forms/subject_values', array( Quote::class, 'values' ), 10, 3 );
		add_action( 'pergel/forms/received', array( Quote::class, 'received' ), 10, 4 );
		if ( ! is_admin() ) {
			Markup::register();
			add_filter( 'woocommerce_enqueue_styles', array( Assets::class, 'styles' ) );
			add_action( 'wp_enqueue_scripts', array( Assets::class, 'scripts' ), 100 );
		}
	}

	/**
	 * Tells WooCommerce that the engine works with order tables (HPOS) and
	 * with the cart and checkout blocks.
	 */
	public static function declare_compatibility(): void {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return;
		}
		FeaturesUtil::declare_compatibility( 'custom_order_tables', \Pergel\FILE, true );
		FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', \Pergel\FILE, true );
	}
}
