<?php
/**
 * The pergel_settings schema: one definition for defaults, sanitizing and REST.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Settings;

/**
 * Describes every Pergel setting and turns untrusted input into a value that
 * matches it.
 *
 * Pure PHP (no WordPress calls) so the rules can be unit tested. Field
 * labels and error messages live in the settings screen; here a problem is
 * only a field path and an error code.
 *
 * @phpstan-type Field array{type: string, default?: mixed, max?: int, min?: int, options?: list<string>, svg?: bool, levels?: list<string>}
 * @phpstan-type Problem array{field: string, code: string}
 */
final class Schema {

	/**
	 * Default maximum length per string type: characters for text and
	 * textarea, bytes for the rest (URLs, e-mail addresses and phone numbers
	 * are ASCII or travel percent-encoded, so bytes keep the option small).
	 */
	private const MAX = array(
		'text'     => 200,
		'textarea' => 1000,
		'email'    => 254,
		'url'      => 500,
		'tel'      => 32,
	);

	/**
	 * Default maximum number of items in a urls field.
	 */
	private const MAX_ITEMS = 10;

	/**
	 * Every group and field.
	 *
	 * Types: bool, text, textarea, email, url, tel, country (ISO 3166-1
	 * alpha-2), enum (options), int (min, max), image (attachment id, 0 =
	 * none), urls (list of URLs, max = item count), time ("HH:MM", 24-hour),
	 * dates (list of "YYYY-MM-DD" days or "YYYY-MM-DD/YYYY-MM-DD" ranges,
	 * max = item count), pattern (synced pattern id, 0 = none).
	 *
	 * levels: where else the field can be set ("category", "product"); the
	 * most specific value wins, see level_values() and Pergel\Woo\Layout.
	 *
	 * @return array<string, array<string, Field>>
	 */
	public static function groups(): array {
		return array(
			// Module switches. Modules that are not listed always load.
			'modules'     => array(
				'brand'       => array(
					'type'    => 'bool',
					'default' => true,
				),
				'seo'         => array(
					'type'    => 'bool',
					'default' => true,
				),
				'performance' => array(
					'type'    => 'bool',
					'default' => true,
				),
				'forms'       => array(
					'type'    => 'bool',
					'default' => true,
				),
				'search'      => array(
					'type'    => 'bool',
					'default' => true,
				),
				'woo'         => array(
					'type'    => 'bool',
					'default' => true,
				),
				'abilities'   => array(
					'type'    => 'bool',
					'default' => false,
				),
			),
			// Company details: block bindings and Organization schema.
			'brand'       => array(
				'name'          => array(
					'type' => 'text',
					'max'  => 120,
				),
				'legal_name'    => array( 'type' => 'text' ),
				'email'         => array( 'type' => 'email' ),
				'phone'         => array( 'type' => 'tel' ),
				'street'        => array( 'type' => 'text' ),
				'locality'      => array(
					'type' => 'text',
					'max'  => 100,
				),
				'region'        => array(
					'type' => 'text',
					'max'  => 100,
				),
				'postal_code'   => array(
					'type' => 'text',
					'max'  => 20,
				),
				'country'       => array( 'type' => 'country' ),
				'vat_id'        => array(
					'type' => 'text',
					'max'  => 40,
				),
				'logo_inverse'  => array( 'type' => 'image' ),
				'logo_has_name' => array(
					'type'    => 'bool',
					'default' => false,
				),
				'social'        => array( 'type' => 'urls' ),
			),
			// Used only when no SEO plugin is active.
			'seo'         => array(
				// Social networks show no SVG.
				'og_image'      => array(
					'type' => 'image',
					'svg'  => false,
				),
				'twitter_site'  => array(
					'type' => 'text',
					'max'  => 16,
				),
				'sitemap_users' => array(
					'type'    => 'bool',
					'default' => false,
				),
			),
			// Front-end trimming, used while the performance module is on.
			'performance' => array(
				'disable_emoji'       => array(
					'type'    => 'bool',
					'default' => true,
				),
				'remove_oembed_links' => array(
					'type'    => 'bool',
					'default' => true,
				),
				'dequeue_dashicons'   => array(
					'type'    => 'bool',
					'default' => true,
				),
				'preload_fonts'       => array(
					'type'    => 'bool',
					'default' => true,
				),
			),
			// Contact and quote forms.
			'forms'       => array(
				'recipient' => array( 'type' => 'email' ),
			),
			// Own search index and live suggestions.
			'search'      => array(
				'live'       => array(
					'type'    => 'bool',
					'default' => true,
				),
				'live_limit' => array(
					'type'    => 'int',
					'min'     => 3,
					'max'     => 12,
					'default' => 6,
				),
			),
			// WooCommerce; the tab is shown only while it is active.
			'woo'         => array(
				// Most open first; see Pergel\Woo\Modes.
				'sales_mode'        => array(
					'type'    => 'enum',
					'options' => array( 'sale', 'login', 'quote', 'catalog' ),
					'default' => 'sale',
				),
				// Product enquiry block; the phone number is brand.phone.
				'whatsapp'          => array( 'type' => 'tel' ),
				'contact_url'       => array( 'type' => 'url' ),
				'mini_cart'         => array(
					'type'    => 'bool',
					'default' => true,
				),
				'shop_assets_only'  => array(
					'type'    => 'bool',
					'default' => true,
				),
				'no_cart_fragments' => array(
					'type'    => 'bool',
					'default' => true,
				),
				// Wishlist and compare; see Pergel\Woo\Lists.
				'wishlist'          => array(
					'type'    => 'bool',
					'default' => false,
				),
				'wishlist_url'      => array( 'type' => 'url' ),
				'compare'           => array(
					'type'    => 'bool',
					'default' => false,
				),
				'compare_url'       => array( 'type' => 'url' ),
				// Product page; see Pergel\Woo\Swatches and Pergel\Woo\StickyCart.
				'swatches'          => array(
					'type'    => 'bool',
					'default' => false,
				),
				'sticky_cart'       => array(
					'type'    => 'bool',
					'default' => false,
				),
				// Delivery estimate; see Pergel\Woo\Delivery. Days are working days.
				'delivery'          => array(
					'type'    => 'bool',
					'default' => false,
				),
				'delivery_cutoff'   => array(
					'type'    => 'time',
					'default' => '15:00',
				),
				'delivery_handling' => array(
					'type'    => 'int',
					'min'     => 0,
					'max'     => 30,
					'default' => 1,
				),
				'delivery_min'      => array(
					'type'    => 'int',
					'min'     => 0,
					'max'     => 60,
					'default' => 1,
				),
				'delivery_max'      => array(
					'type'    => 'int',
					'min'     => 0,
					'max'     => 60,
					'default' => 3,
				),
				'delivery_days'     => array(
					'type'    => 'enum',
					'options' => array( 'mon_fri', 'mon_sat', 'every_day' ),
					'default' => 'mon_fri',
				),
				'delivery_holidays' => array(
					'type' => 'dates',
					'max'  => 40,
				),
				// Back-in-stock alerts; see Pergel\Woo\StockAlert.
				'stock_alert'       => array(
					'type'    => 'bool',
					'default' => false,
				),
				// See Pergel\Woo\SizeGuide and Pergel\Woo\BoughtTogether.
				'size_guide'        => array(
					'type'    => 'bool',
					'default' => false,
				),
				'bought_together'   => array(
					'type'    => 'bool',
					'default' => false,
				),
				// Shortcuts at the bottom of phone screens; see
				// Pergel\Woo\BottomBar. The items count only while it is on.
				'bottom_bar'        => array(
					'type'    => 'bool',
					'default' => false,
				),
				'bar_shop'          => array(
					'type'    => 'bool',
					'default' => true,
				),
				'bar_filter'        => array(
					'type'    => 'bool',
					'default' => true,
				),
				'bar_search'        => array(
					'type'    => 'bool',
					'default' => true,
				),
				'bar_wishlist'      => array(
					'type'    => 'bool',
					'default' => true,
				),
				'bar_account'       => array(
					'type'    => 'bool',
					'default' => true,
				),
				'bar_cart'          => array(
					'type'    => 'bool',
					'default' => true,
				),
				// Free shipping progress; see Pergel\Woo\FreeShipping. The
				// amount is used where WooCommerce has no free shipping method
				// with a minimum; 0 leaves it to WooCommerce.
				'shipping_mini'     => array(
					'type'    => 'bool',
					'default' => false,
				),
				'shipping_cart'     => array(
					'type'    => 'bool',
					'default' => false,
				),
				'shipping_product'  => array(
					'type'    => 'bool',
					'default' => false,
				),
				'shipping_checkout' => array(
					'type'    => 'bool',
					'default' => false,
				),
				'shipping_min'      => array(
					'type'    => 'int',
					'min'     => 0,
					'max'     => 1000000,
					'default' => 0,
				),
				// Flash sale campaigns; see Pergel\Woo\FlashSales. An empty
				// address part uses the translated "flash-sale".
				'flash_sales'       => array(
					'type'    => 'bool',
					'default' => false,
				),
				'sales_slug'        => array(
					'type' => 'text',
					'max'  => 40,
				),
				'sales_product'     => array(
					'type'    => 'bool',
					'default' => true,
				),
				'sales_card'        => array(
					'type'    => 'bool',
					'default' => true,
				),
				'sales_band'        => array(
					'type'    => 'bool',
					'default' => false,
				),
			),
			// Shop listing look; see Pergel\Woo\Layout. "template", "auto",
			// "wide" and 0 keep the template's and WooCommerce's own layout,
			// so the defaults change nothing.
			'shop'        => array(
				'columns'            => array(
					'type'    => 'enum',
					'options' => array( 'template', '2', '3', '4', '5', '6' ),
					'default' => 'template',
					'levels'  => array( 'category' ),
				),
				'columns_tablet'     => array(
					'type'    => 'enum',
					'options' => array( 'auto', '1', '2', '3', '4' ),
					'default' => 'auto',
					'levels'  => array( 'category' ),
				),
				'columns_phone'      => array(
					'type'    => 'enum',
					'options' => array( 'auto', '1', '2' ),
					'default' => 'auto',
					'levels'  => array( 'category' ),
				),
				'per_page'           => array(
					'type'    => 'int',
					'min'     => 0,
					'max'     => 96,
					'default' => 0,
					'levels'  => array( 'category' ),
				),
				'width'              => array(
					'type'    => 'enum',
					'options' => array( 'wide', 'full' ),
					'default' => 'wide',
					'levels'  => array( 'category' ),
				),
				// Filters; see Pergel\Woo\Filters.
				'filters'            => array(
					'type'    => 'enum',
					'options' => array( 'template', 'left', 'right', 'drawer', 'none' ),
					'default' => 'template',
					'levels'  => array( 'category' ),
				),
				'drawer_side'        => array(
					'type'    => 'enum',
					'options' => array( 'left', 'right' ),
					'default' => 'left',
					'levels'  => array( 'category' ),
				),
				'filters_sticky'     => array(
					'type'    => 'enum',
					'options' => array( 'off', 'sticky', 'scroll' ),
					'default' => 'off',
				),
				'filters_fold'       => array(
					'type'    => 'bool',
					'default' => false,
				),
				'filters_search'     => array(
					'type'    => 'bool',
					'default' => false,
				),
				'filter_brand'       => array(
					'type'    => 'bool',
					'default' => false,
				),
				'filter_rating'      => array(
					'type'    => 'bool',
					'default' => false,
				),
				// Toolbar, list view and paging; see Pergel\Woo\Toolbar.
				'view'               => array(
					'type'    => 'enum',
					'options' => array( 'grid', 'list' ),
					'default' => 'grid',
					'levels'  => array( 'category' ),
				),
				'view_switch'        => array(
					'type'    => 'bool',
					'default' => false,
				),
				'per_page_menu'      => array(
					'type' => 'text',
					'max'  => 40,
				),
				'paging'             => array(
					'type'    => 'enum',
					'options' => array( 'template', 'more', 'scroll' ),
					'default' => 'template',
					'levels'  => array( 'category' ),
				),
				// Above the products; see Pergel\Woo\ShopHeader.
				'banner'             => array(
					'type'   => 'pattern',
					'levels' => array( 'category' ),
				),
				'category_strip'     => array(
					'type'    => 'enum',
					'options' => array( 'none', 'images', 'boxes', 'list', 'names' ),
					'default' => 'none',
					'levels'  => array( 'category' ),
				),
				// Product cards in every product grid; see Pergel\Woo\Cards.
				'card_style'         => array(
					'type'    => 'enum',
					'options' => array( 'template', 'standard', 'boxed', 'overlay', 'plain' ),
					'default' => 'template',
					'levels'  => array( 'category' ),
				),
				'card_category'      => array(
					'type'    => 'bool',
					'default' => false,
				),
				'card_brand'         => array(
					'type'    => 'bool',
					'default' => false,
				),
				'card_rating'        => array(
					'type'    => 'bool',
					'default' => false,
				),
				'card_discount'      => array(
					'type'    => 'bool',
					'default' => false,
				),
				'card_stock'         => array(
					'type'    => 'bool',
					'default' => false,
				),
				'card_hover'         => array(
					'type'    => 'bool',
					'default' => false,
				),
				'card_countdown'     => array(
					'type'    => 'bool',
					'default' => false,
				),
				'card_stock_bar'     => array(
					'type'    => 'bool',
					'default' => false,
				),
				'quick_view'         => array(
					'type'    => 'bool',
					'default' => false,
				),
				'card_swatches'      => array(
					'type'    => 'bool',
					'default' => false,
				),
				'card_quote'         => array(
					'type'    => 'bool',
					'default' => false,
				),
				// How the cards look; empty or "As the card style" leaves the
				// style's own look. See Pergel\Woo\CardLook.
				'card_gap'           => array(
					'type' => 'length',
					'min'  => 0,
					'max'  => 64,
				),
				'card_bg'            => array( 'type' => 'color' ),
				'card_border'        => array(
					'type' => 'length',
					'min'  => 0,
					'max'  => 8,
				),
				'card_border_color'  => array( 'type' => 'color' ),
				'card_radius'        => array(
					'type' => 'length',
					'min'  => 0,
					'max'  => 40,
				),
				'card_padding'       => array(
					'type' => 'length',
					'min'  => 0,
					'max'  => 40,
				),
				'card_hover_look'    => array(
					'type'    => 'enum',
					'options' => array( 'template', 'none', 'shadow', 'accent', 'lift' ),
					'default' => 'template',
				),
				'card_image_bg'      => array( 'type' => 'color' ),
				'card_image_radius'  => array(
					'type' => 'length',
					'min'  => 0,
					'max'  => 40,
				),
				'card_align'         => array(
					'type'    => 'enum',
					'options' => array( 'template', 'start', 'center' ),
					'default' => 'template',
				),
				'card_title_size'    => array(
					'type' => 'length',
					'min'  => 10,
					'max'  => 32,
				),
				'card_title_weight'  => array(
					'type'    => 'enum',
					'options' => array( 'template', '400', '500', '600', '700' ),
					'default' => 'template',
				),
				'card_meta_color'    => array( 'type' => 'color' ),
				'card_button'        => array(
					'type'    => 'enum',
					'options' => array( 'template', 'filled', 'outline', 'text' ),
					'default' => 'template',
				),
				'card_button_color'  => array( 'type' => 'color' ),
				'card_button_shape'  => array(
					'type'    => 'enum',
					'options' => array( 'template', 'pill', 'rounded', 'square' ),
					'default' => 'template',
				),
				'card_button_width'  => array(
					'type'    => 'enum',
					'options' => array( 'template', 'full', 'auto' ),
					'default' => 'template',
				),
				'card_button_height' => array(
					'type' => 'length',
					'min'  => 24,
					'max'  => 64,
				),
				// The filter box, the toolbar and the page numbers; empty or
				// "As the template" leaves their own look. See Pergel\Woo\ShopLook.
				'filter_bg'          => array( 'type' => 'color' ),
				'filter_edge'        => array(
					'type' => 'length',
					'min'  => 0,
					'max'  => 8,
				),
				'filter_edge_color'  => array( 'type' => 'color' ),
				'filter_radius'      => array(
					'type' => 'length',
					'min'  => 0,
					'max'  => 40,
				),
				'filter_padding'     => array(
					'type' => 'length',
					'min'  => 0,
					'max'  => 48,
				),
				'filter_head_size'   => array(
					'type' => 'length',
					'min'  => 12,
					'max'  => 32,
				),
				'filter_head_weight' => array(
					'type'    => 'enum',
					'options' => array( 'template', '400', '500', '600', '700' ),
					'default' => 'template',
				),
				'filter_divider'     => array(
					'type'    => 'enum',
					'options' => array( 'template', 'none', 'line' ),
					'default' => 'template',
				),
				'filter_text_size'   => array(
					'type' => 'length',
					'min'  => 12,
					'max'  => 20,
				),
				'filter_accent'      => array( 'type' => 'color' ),
				'toolbar_bg'         => array( 'type' => 'color' ),
				'toolbar_edge'       => array(
					'type' => 'length',
					'min'  => 0,
					'max'  => 8,
				),
				'toolbar_edge_color' => array( 'type' => 'color' ),
				'toolbar_radius'     => array(
					'type' => 'length',
					'min'  => 0,
					'max'  => 40,
				),
				'toolbar_padding'    => array(
					'type' => 'length',
					'min'  => 0,
					'max'  => 32,
				),
				'control_radius'     => array(
					'type' => 'length',
					'min'  => 0,
					'max'  => 24,
				),
				'paging_shape'       => array(
					'type'    => 'enum',
					'options' => array( 'template', 'square', 'rounded', 'circle' ),
					'default' => 'template',
				),
				'paging_size'        => array(
					'type' => 'length',
					'min'  => 28,
					'max'  => 64,
				),
				'paging_bg'          => array( 'type' => 'color' ),
				'paging_current'     => array( 'type' => 'color' ),
				// How the top of the product page looks; empty or "As the
				// template" leaves the template's look. See Pergel\Woo\ProductLook.
				'picture_bg'         => array( 'type' => 'color' ),
				'picture_edge'       => array(
					'type' => 'length',
					'min'  => 0,
					'max'  => 8,
				),
				'picture_edge_color' => array( 'type' => 'color' ),
				'picture_radius'     => array(
					'type' => 'length',
					'min'  => 0,
					'max'  => 40,
				),
				'thumb_radius'       => array(
					'type' => 'length',
					'min'  => 0,
					'max'  => 40,
				),
				'thumb_active'       => array( 'type' => 'color' ),
				'title_size'         => array(
					'type' => 'length',
					'min'  => 16,
					'max'  => 64,
				),
				'title_weight'       => array(
					'type'    => 'enum',
					'options' => array( 'template', '400', '500', '600', '700' ),
					'default' => 'template',
				),
				'price_size'         => array(
					'type' => 'length',
					'min'  => 14,
					'max'  => 48,
				),
				'price_weight'       => array(
					'type'    => 'enum',
					'options' => array( 'template', '400', '500', '600', '700' ),
					'default' => 'template',
				),
				'price_color'        => array( 'type' => 'color' ),
				'buy_look'           => array(
					'type'    => 'enum',
					'options' => array( 'template', 'filled', 'outline' ),
					'default' => 'template',
				),
				'buy_color'          => array( 'type' => 'color' ),
				'buy_shape'          => array(
					'type'    => 'enum',
					'options' => array( 'template', 'pill', 'rounded', 'square' ),
					'default' => 'template',
				),
				'buy_height'         => array(
					'type' => 'length',
					'min'  => 32,
					'max'  => 72,
				),
				'qty_shape'          => array(
					'type'    => 'enum',
					'options' => array( 'template', 'pill', 'rounded', 'square' ),
					'default' => 'template',
				),
				'badge_sale'         => array(
					'type'    => 'enum',
					'options' => array( 'template', 'percent', 'none' ),
					'default' => 'template',
				),
				'badge_new'          => array(
					'type'    => 'int',
					'min'     => 0,
					'max'     => 90,
					'default' => 0,
				),
				'badge_featured'     => array(
					'type'    => 'bool',
					'default' => false,
				),
				'badge_sold_out'     => array(
					'type'    => 'bool',
					'default' => false,
				),
				// Product page; see Pergel\Woo\ProductPage. A category and a
				// product may choose the layout, pictures and banner.
				'product_layout'     => array(
					'type'    => 'enum',
					'options' => array( 'template', 'left', 'right', 'three' ),
					'default' => 'template',
					'levels'  => array( 'category', 'product' ),
				),
				'product_thumbs'     => array(
					'type'    => 'enum',
					'options' => array( 'template', 'left', 'bottom', 'none', 'grid' ),
					'default' => 'template',
					'levels'  => array( 'category', 'product' ),
				),
				'product_sticky'     => array(
					'type'    => 'bool',
					'default' => false,
					'levels'  => array( 'category', 'product' ),
				),
				'product_zoom'       => array(
					'type'    => 'bool',
					'default' => true,
				),
				'product_badges'     => array(
					'type'    => 'bool',
					'default' => false,
				),
				'product_banner'     => array(
					'type'   => 'pattern',
					'levels' => array( 'category', 'product' ),
				),
				'product_category'   => array(
					'type'    => 'bool',
					'default' => false,
				),
				'product_brand'      => array(
					'type'    => 'bool',
					'default' => false,
				),
				'product_discount'   => array(
					'type'    => 'bool',
					'default' => false,
				),
				'product_stock'      => array(
					'type'    => 'bool',
					'default' => false,
				),
				'product_nav'        => array(
					'type'    => 'bool',
					'default' => false,
				),
				'product_buy_now'    => array(
					'type'    => 'bool',
					'default' => false,
					'levels'  => array( 'category', 'product' ),
				),
				'product_coupons'    => array(
					'type'    => 'bool',
					'default' => false,
				),
				'product_share'      => array(
					'type'    => 'bool',
					'default' => false,
				),
				// Description, specifications, reviews and extra tabs under the
				// product; see Pergel\Woo\Details.
				'details_style'      => array(
					'type'    => 'enum',
					'options' => array( 'template', 'tabs', 'open' ),
					'default' => 'template',
					'levels'  => array( 'category', 'product' ),
				),
				'details_phone'      => array(
					'type'    => 'enum',
					'options' => array( 'accordion', 'scroll' ),
					'default' => 'accordion',
				),
				'details_features'   => array(
					'type'    => 'bool',
					'default' => false,
				),
				'details_title'      => array(
					'type'   => 'text',
					'max'    => 40,
					'levels' => array( 'category' ),
				),
				'details_tab'        => array(
					'type'   => 'pattern',
					'levels' => array( 'category' ),
				),
				'details_title2'     => array(
					'type'   => 'text',
					'max'    => 40,
					'levels' => array( 'category' ),
				),
				'details_tab2'       => array(
					'type'   => 'pattern',
					'levels' => array( 'category' ),
				),
				'details_qa'         => array(
					'type'    => 'bool',
					'default' => false,
				),
				'details_rating'     => array(
					'type'    => 'bool',
					'default' => false,
				),
				// Related products, the comparison with similar products and
				// recently viewed ones; see Pergel\Woo\Related.
				'related_style'      => array(
					'type'    => 'enum',
					'options' => array( 'template', 'grid', 'carousel' ),
					'default' => 'template',
					'levels'  => array( 'category', 'product' ),
				),
				'related_count'      => array(
					'type'    => 'int',
					'min'     => 0,
					'max'     => 12,
					'default' => 0,
					'levels'  => array( 'category' ),
				),
				'related_title'      => array(
					'type'   => 'text',
					'max'    => 40,
					'levels' => array( 'category' ),
				),
				'related_compare'    => array(
					'type'    => 'enum',
					'options' => array( 'off', '2', '3', '4' ),
					'default' => 'off',
					'levels'  => array( 'category', 'product' ),
				),
				'related_recent'     => array(
					'type'    => 'enum',
					'options' => array( 'off', 'track', 'show' ),
					'default' => 'off',
				),
			),
		);
	}

	/**
	 * Fields a level (a product category, a product) may set for itself.
	 *
	 * @param string $level "category" or "product".
	 * @return array<string, array<string, Field>> Group => key => field.
	 */
	public static function level_fields( string $level ): array {
		$fields = array();
		foreach ( self::groups() as $group => $group_fields ) {
			foreach ( $group_fields as $key => $field ) {
				if ( in_array( $level, $field['levels'] ?? array(), true ) ) {
					$fields[ $group ][ $key ] = $field;
				}
			}
		}
		return $fields;
	}

	/**
	 * Cleans the settings a category or product keeps for itself. Only the
	 * level's fields survive; an empty or invalid value means "inherit" and
	 * is dropped, so the stored array holds real choices only.
	 *
	 * @param mixed  $input Untrusted "group.key" => value map.
	 * @param string $level "category" or "product".
	 * @return array<string, mixed>
	 */
	public static function level_values( mixed $input, string $level ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}
		$values = array();
		foreach ( self::level_fields( $level ) as $group => $fields ) {
			foreach ( $fields as $key => $field ) {
				$raw = $input[ "$group.$key" ] ?? '';
				if ( '' === $raw ) {
					continue;
				}
				$result = self::field( $field, $raw );
				if ( is_array( $result ) ) {
					$values[ "$group.$key" ] = $result[0];
				}
			}
		}
		return $values;
	}

	/**
	 * Default value of a field.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @phpstan-param Field $field
	 */
	public static function field_default( array $field ): mixed {
		if ( array_key_exists( 'default', $field ) ) {
			return $field['default'];
		}
		return match ( $field['type'] ) {
			'bool'          => false,
			'int', 'image', 'pattern' => 0,
			'urls', 'dates' => array(),
			default         => '',
		};
	}

	/**
	 * The complete default settings.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function defaults(): array {
		$defaults = array();
		foreach ( self::groups() as $group => $fields ) {
			foreach ( $fields as $key => $field ) {
				$defaults[ $group ][ $key ] = self::field_default( $field );
			}
		}
		return $defaults;
	}

	/**
	 * Checks input against the schema.
	 *
	 * Missing groups and fields keep their value from $base, so a caller can
	 * send only what changes. Unknown keys are dropped. An invalid value also
	 * keeps its $base value and adds a problem.
	 *
	 * @param mixed                               $input Untrusted input (form, REST, CLI, stored option).
	 * @param array<string, array<string, mixed>> $base  Current settings; defaults() when null.
	 * @return array{value: array<string, array<string, mixed>>, problems: list<Problem>}
	 */
	public static function sanitize( mixed $input, ?array $base = null ): array {
		$defaults = self::defaults();
		$value    = array();
		$problems = array();

		if ( ! is_array( $input ) ) {
			$problems[] = array(
				'field' => '',
				'code'  => 'invalid_type',
			);
			$input      = array();
		}

		foreach ( self::groups() as $group => $fields ) {
			$group_input = $input[ $group ] ?? array();
			if ( ! is_array( $group_input ) ) {
				$problems[]  = array(
					'field' => $group,
					'code'  => 'invalid_type',
				);
				$group_input = array();
			}
			foreach ( $fields as $key => $field ) {
				$current = $base[ $group ][ $key ] ?? $defaults[ $group ][ $key ];
				if ( ! array_key_exists( $key, $group_input ) ) {
					$value[ $group ][ $key ] = $current;
					continue;
				}
				$result = self::field( $field, $group_input[ $key ] );
				if ( is_string( $result ) ) {
					$problems[]              = array(
						'field' => "$group.$key",
						'code'  => $result,
					);
					$value[ $group ][ $key ] = $current;
				} else {
					$value[ $group ][ $key ] = $result[0];
				}
			}
		}

		return array(
			'value'    => $value,
			'problems' => $problems,
		);
	}

	/**
	 * Brings a stored option into shape: anything invalid becomes the default.
	 *
	 * @param mixed $stored Value read from the database.
	 * @return array<string, array<string, mixed>>
	 */
	public static function normalize( mixed $stored ): array {
		return self::sanitize( is_array( $stored ) ? $stored : array() )['value'];
	}

	/**
	 * Sanitizes one field value.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $raw   Untrusted value.
	 * @phpstan-param Field $field
	 * @return array{0: mixed}|string The clean value wrapped in an array, or an error code.
	 */
	public static function field( array $field, mixed $raw ): array|string {
		$type = $field['type'];
		$max  = $field['max'] ?? self::MAX[ $type ] ?? 0;

		switch ( $type ) {
			case 'bool':
				$bool = is_bool( $raw ) ? $raw : filter_var( $raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
				return null === $bool ? 'invalid_type' : array( $bool );

			case 'int':
			case 'image':
			case 'pattern':
				$int = is_int( $raw ) ? $raw : filter_var( $raw, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE );
				if ( null === $int ) {
					return 'invalid_type';
				}
				$id  = 'int' !== $type;
				$min = $id ? 0 : ( $field['min'] ?? PHP_INT_MIN );
				$top = $id ? PHP_INT_MAX : ( $field['max'] ?? PHP_INT_MAX );
				return $int < $min || $int > $top ? 'out_of_range' : array( $int );

			case 'enum':
				$options = $field['options'] ?? array();
				return is_string( $raw ) && in_array( $raw, $options, true ) ? array( $raw ) : 'invalid_choice';

			case 'length':
				if ( null === $raw || '' === $raw ) {
					return array( '' );
				}
				$int = is_int( $raw ) ? $raw : filter_var( $raw, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE );
				if ( null === $int ) {
					return 'invalid_type';
				}
				return $int < ( $field['min'] ?? 0 ) || $int > ( $field['max'] ?? PHP_INT_MAX ) ? 'out_of_range' : array( $int );

			case 'color':
				return self::color( $raw );

			case 'urls':
				return self::urls( $raw, $field['max'] ?? self::MAX_ITEMS );

			case 'dates':
				return self::dates( $raw, $field['max'] ?? self::MAX_ITEMS );

			case 'time':
				$time = self::text( $raw, false );
				if ( null === $time ) {
					return 'invalid_type';
				}
				if ( '' === $time ) {
					return array( '' );
				}
				if ( ! preg_match( '/^([01]?[0-9]|2[0-3])[:.]([0-5][0-9])$/', $time, $parts ) ) {
					return 'invalid_time';
				}
				return array( sprintf( '%02d:%s', (int) $parts[1], $parts[2] ) );
		}

		// Too long even with every character four bytes and tags around them: refused before the cleaning.
		if ( is_string( $raw ) && $max > 0 && strlen( $raw ) > 8 * $max ) {
			return 'too_long';
		}
		$text = self::text( $raw, 'textarea' === $type );
		if ( null === $text ) {
			return 'invalid_type';
		}
		$length = in_array( $type, array( 'text', 'textarea' ), true ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
		if ( $max > 0 && $length > $max ) {
			return 'too_long';
		}
		if ( '' === $text ) {
			return array( '' );
		}

		switch ( $type ) {
			case 'email':
				return false === filter_var( $text, FILTER_VALIDATE_EMAIL ) ? 'invalid_email' : array( $text );
			case 'url':
				return self::is_url( $text ) ? array( $text ) : 'invalid_url';
			case 'tel':
				$digits = preg_match_all( '/[0-9]/', $text );
				return preg_match( '/^\+?[0-9 ().\-]+$/', $text ) && $digits >= 3 ? array( $text ) : 'invalid_tel';
			case 'country':
				$code = strtoupper( $text );
				return preg_match( '/^[A-Z]{2}$/', $code ) ? array( $code ) : 'invalid_country';
		}
		return array( $text );
	}

	/**
	 * Plain text from untrusted input: no tags, no control characters,
	 * single spaces (newlines kept for multi-line fields), trimmed.
	 *
	 * @param mixed $raw       Untrusted value.
	 * @param bool  $multiline Keep line breaks.
	 * @return string|null Null when the input is not text or not valid UTF-8.
	 */
	private static function text( mixed $raw, bool $multiline ): ?string {
		if ( is_int( $raw ) || is_float( $raw ) ) {
			$raw = (string) $raw;
		}
		if ( ! is_string( $raw ) || ! mb_check_encoding( $raw, 'UTF-8' ) ) {
			return null;
		}
		// Like wp_strip_all_tags(), in pure PHP so it is unit tested without WordPress.
		$text = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', str_replace( "\r\n", "\n", $raw ) );
		$text = strip_tags( $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- see above.
		if ( $multiline ) {
			$lines = array_map(
				static fn ( string $line ): string => trim( (string) preg_replace( '/[\p{Cc}\s]+/u', ' ', $line ) ),
				explode( "\n", $text )
			);
			return trim( (string) preg_replace( "/\n{3,}/", "\n\n", implode( "\n", $lines ) ) );
		}
		return trim( (string) preg_replace( '/[\p{Cc}\s]+/u', ' ', $text ) );
	}

	/**
	 * Whether a string is an absolute http(s) URL that is safe to store.
	 *
	 * @param string $url Candidate URL, already trimmed.
	 */
	public static function is_url( string $url ): bool {
		if ( preg_match( '/[\s<>"\'`\\\\]/u', $url ) ) {
			return false;
		}
		$parts = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure PHP on purpose.
		return is_array( $parts )
			&& in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true )
			&& '' !== ( $parts['host'] ?? '' );
	}

	/**
	 * A list of URLs from an array or a newline-separated string. Empty
	 * lines and duplicates are dropped.
	 *
	 * @param mixed $raw       Untrusted value.
	 * @param int   $max_items Item limit.
	 * @return array{0: list<string>}|string
	 */
	private static function urls( mixed $raw, int $max_items ): array|string {
		if ( is_string( $raw ) ) {
			$raw = explode( "\n", str_replace( "\r\n", "\n", $raw ) );
		}
		if ( ! is_array( $raw ) ) {
			return 'invalid_type';
		}
		$urls = array();
		foreach ( $raw as $item ) {
			$url = self::field( array( 'type' => 'url' ), $item );
			if ( is_string( $url ) ) {
				return $url;
			}
			if ( '' !== $url[0] && ! in_array( $url[0], $urls, true ) ) {
				$urls[] = $url[0];
			}
		}
		return count( $urls ) > $max_items ? 'too_many' : array( $urls );
	}

	/**
	 * A list of days ("YYYY-MM-DD") and ranges ("YYYY-MM-DD/YYYY-MM-DD")
	 * from an array or a newline-separated string, sorted. A range may also
	 * be written with " - " or "..". Empty lines and duplicates are dropped;
	 * a range that ends before it starts is turned around.
	 *
	 * @param mixed $raw       Untrusted value.
	 * @param int   $max_items Item limit.
	 * @return array{0: list<string>}|string
	 */
	private static function dates( mixed $raw, int $max_items ): array|string {
		if ( is_string( $raw ) ) {
			$raw = explode( "\n", str_replace( "\r\n", "\n", $raw ) );
		}
		if ( ! is_array( $raw ) ) {
			return 'invalid_type';
		}
		$dates = array();
		foreach ( $raw as $item ) {
			$line = self::text( $item, false );
			if ( null === $line ) {
				return 'invalid_type';
			}
			if ( '' === $line ) {
				continue;
			}
			$ends = preg_split( '/\s*(?:\/|\.\.|\s[-–]\s)\s*/u', $line );
			if ( ! is_array( $ends ) || count( $ends ) > 2 ) {
				return 'invalid_date';
			}
			foreach ( $ends as $end ) {
				if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $end, $parts ) || ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
					return 'invalid_date';
				}
			}
			sort( $ends );
			$date = 2 === count( $ends ) && $ends[0] !== $ends[1] ? $ends[0] . '/' . $ends[1] : $ends[0];
			if ( ! in_array( $date, $dates, true ) ) {
				$dates[] = $date;
			}
		}
		sort( $dates );
		return count( $dates ) > $max_items ? 'too_many' : array( $dates );
	}

	/**
	 * A colour: '' (not set), a palette slug, or #rrggbb. The settings
	 * screen sends a pick (a slug or "custom") and a picked colour.
	 *
	 * @param mixed $raw Untrusted value.
	 * @return array{0: string}|string The clean value wrapped in an array, or an error code.
	 */
	private static function color( mixed $raw ): array|string {
		if ( is_array( $raw ) ) {
			$pick = $raw['pick'] ?? '';
			$raw  = 'custom' === $pick ? ( $raw['hex'] ?? '' ) : $pick;
		}
		if ( ! is_string( $raw ) ) {
			return 'invalid_type';
		}
		$color = strtolower( trim( $raw ) );
		if ( '' === $color || preg_match( '/^#[0-9a-f]{6}$/', $color ) || preg_match( '/^[a-z0-9][a-z0-9-]{0,39}$/', $color ) ) {
			return array( $color );
		}
		return 'invalid_color';
	}

	/**
	 * JSON Schema for register_setting( show_in_rest ). Empty strings are
	 * valid everywhere, because an empty field means "not set".
	 *
	 * @return array<string, mixed>
	 */
	public static function json_schema(): array {
		$properties = array();
		foreach ( self::groups() as $group => $fields ) {
			$group_properties = array();
			foreach ( $fields as $key => $field ) {
				$group_properties[ $key ] = self::field_json_schema( $field );
			}
			$properties[ $group ] = array(
				'type'                 => 'object',
				'properties'           => $group_properties,
				'additionalProperties' => false,
			);
		}
		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
		);
	}

	/**
	 * JSON Schema of one field.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @phpstan-param Field $field
	 * @return array<string, mixed>
	 */
	private static function field_json_schema( array $field ): array {
		$type   = $field['type'];
		$max    = $field['max'] ?? self::MAX[ $type ] ?? 0;
		$schema = match ( $type ) {
			'bool'    => array( 'type' => 'boolean' ),
			'int'     => array_filter(
				array(
					'type'    => 'integer',
					'minimum' => $field['min'] ?? null,
					'maximum' => $field['max'] ?? null,
				),
				static fn ( $v ): bool => null !== $v
			),
			'image', 'pattern' => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
			'enum'    => array(
				'type' => 'string',
				'enum' => $field['options'] ?? array(),
			),
			'length'  => array(
				'type'  => array( 'string', 'integer' ),
				'anyOf' => array(
					array(
						'type' => 'string',
						'enum' => array( '' ),
					),
					array(
						'type'    => 'integer',
						'minimum' => $field['min'] ?? 0,
						'maximum' => $field['max'] ?? PHP_INT_MAX,
					),
				),
			),
			'color'   => array(
				'type'    => 'string',
				'pattern' => '^(#[0-9a-fA-F]{6}|[a-z0-9][a-z0-9-]{0,39})?$',
			),
			'urls'    => array(
				'type'     => 'array',
				'items'    => array(
					'type'      => 'string',
					'format'    => 'uri',
					'maxLength' => self::MAX['url'],
				),
				'maxItems' => $field['max'] ?? self::MAX_ITEMS,
			),
			'dates'   => array(
				'type'     => 'array',
				'items'    => array(
					'type'    => 'string',
					'pattern' => '^\d{4}-\d{2}-\d{2}(/\d{4}-\d{2}-\d{2})?$',
				),
				'maxItems' => $field['max'] ?? self::MAX_ITEMS,
			),
			'time'    => array(
				'type'    => 'string',
				'pattern' => '^(([01][0-9]|2[0-3]):[0-5][0-9])?$',
			),
			'email'   => self::empty_or(
				array(
					'type'      => 'string',
					'format'    => 'email',
					'maxLength' => $max,
				)
			),
			'url'     => self::empty_or(
				array(
					'type'      => 'string',
					'format'    => 'uri',
					'maxLength' => $max,
				)
			),
			'tel'     => array(
				'type'      => 'string',
				'pattern'   => '^(\+?[0-9 ().\-]*)$',
				'maxLength' => $max,
			),
			'country' => array(
				'type'    => 'string',
				'pattern' => '^([A-Za-z]{2})?$',
			),
			default   => array(
				'type'      => 'string',
				'maxLength' => $max,
			),
		};
		$schema['default'] = self::field_default( $field );
		return $schema;
	}

	/**
	 * Allows an empty string next to a formatted one.
	 *
	 * @param array<string, mixed> $schema Schema for a non-empty value.
	 * @return array<string, mixed>
	 */
	private static function empty_or( array $schema ): array {
		return array(
			'type'  => 'string',
			'anyOf' => array(
				array(
					'type' => 'string',
					'enum' => array( '' ),
				),
				$schema,
			),
		);
	}
}
