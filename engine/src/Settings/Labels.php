<?php
/**
 * Human-readable text for the settings schema.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Settings;

/**
 * Tab titles, sections, field labels and error messages. Kept apart from
 * Schema so the schema stays pure PHP; only the settings screen loads this.
 */
final class Labels {

	/**
	 * Tabs, one per schema group.
	 *
	 * @return array<string, array{title: string, description: string}>
	 */
	public static function tabs(): array {
		return array(
			'modules'     => array(
				'title'       => __( 'Modules', 'pergel-engine' ),
				'description' => __( 'Switch off what this site does not use. A switched-off module loads no code.', 'pergel-engine' ),
			),
			'brand'       => array(
				'title'       => __( 'Brand', 'pergel-engine' ),
				'description' => __( 'Company details, entered once. Blocks show them through the Pergel Brand source, and search engines receive them as Organization data.', 'pergel-engine' ),
			),
			'seo'         => array(
				'title'       => __( 'SEO', 'pergel-engine' ),
				'description' => __( 'Used only while no SEO plugin is active. With Rank Math, Yoast SEO, All in One SEO, SEOPress or The SEO Framework, Pergel hands over and only adds the brand details to its data.', 'pergel-engine' ),
			),
			'performance' => array(
				'title'       => __( 'Performance', 'pergel-engine' ),
				'description' => __( 'Front-end changes made while the Performance module is on. The admin area is not affected.', 'pergel-engine' ),
			),
			'forms'       => array(
				'title'       => __( 'Forms', 'pergel-engine' ),
				'description' => __( 'Where the messages from the Form block go. Every message is also kept under Pergel → Form messages, so none is lost when an email does not arrive.', 'pergel-engine' ),
			),
			'search'      => array(
				'title'       => __( 'Search', 'pergel-engine' ),
				'description' => __( 'Pergel keeps its own search index: it finds words as you type them, with or without Turkish letters and accents, and ranks titles, SKUs and categories above the text. It is updated whenever content is saved.', 'pergel-engine' ),
			),
			'woo'         => array(
				'title'       => __( 'Shop', 'pergel-engine' ),
				'description' => __( 'How Pergel works with WooCommerce. This tab is shown while WooCommerce is active.', 'pergel-engine' ),
			),
			'shop'        => array(
				'title'       => __( 'Shop layout', 'pergel-engine' ),
				'description' => __( 'How the shop, product category, product tag and product search pages list products. A product category can choose its own layout on its edit screen; its subcategories follow it. "As in the template" and "Automatic" keep the layout of the theme template.', 'pergel-engine' ),
			),
		);
	}

	/**
	 * The Shop tab's menu: the shop and shop layout groups together, split
	 * into pages listed on the left. Each page shows sections of
	 * sections() as "group.section", and saves only its own fields.
	 *
	 * @return array<string, array{title: string, description: string, parts: list<string>}>
	 */
	public static function menu(): array {
		return array(
			'general'  => array(
				'title'       => __( 'General', 'pergel-engine' ),
				'description' => __( 'How Pergel works with WooCommerce: selling, product enquiries, the wishlist and compare lists, and speed.', 'pergel-engine' ),
				'parts'       => array( 'woo.selling', 'woo.enquiries', 'woo.lists', 'woo.speed' ),
			),
			'header'   => array(
				'title'       => __( 'Header', 'pergel-engine' ),
				'description' => __( 'The top of every page: the cart in the theme headers, or a store header with a top bar, a product search and a menu bar.', 'pergel-engine' ),
				'parts'       => array( 'shop.store', 'shop.topbar', 'shop.mainrow', 'shop.menubar', 'shop.skin', 'woo.header' ),
			),
			'listing'  => array(
				'title'       => __( 'Shop page', 'pergel-engine' ),
				'description' => __( 'How the shop, product category, product tag and product search pages list products. A product category can choose its own layout on its edit screen; its subcategories follow it. "As in the template" and "Automatic" keep the layout of the theme template.', 'pergel-engine' ),
				'parts'       => array( 'shop.grid', 'shop.page', 'shop.toolbar', 'shop.tools', 'shop.header' ),
			),
			'filters'  => array(
				'title'       => __( 'Filters', 'pergel-engine' ),
				'description' => __( 'The filters beside the products on the shop and category pages.', 'pergel-engine' ),
				'parts'       => array( 'shop.filters', 'shop.refine' ),
			),
			'cards'    => array(
				'title'       => __( 'Product cards', 'pergel-engine' ),
				'description' => __( 'The cards of every product grid: the shop, categories, related products and the grids on other pages.', 'pergel-engine' ),
				'parts'       => array( 'shop.cards', 'shop.look', 'shop.badges' ),
			),
			'product'  => array(
				'title'       => __( 'Product page', 'pergel-engine' ),
				'description' => __( 'The top of the product page: the pictures, the details beside them and buying. A product category and a product can choose some of these for themselves.', 'pergel-engine' ),
				'parts'       => array( 'shop.product', 'shop.summary', 'shop.boxes', 'shop.buying', 'shop.styling', 'woo.product', 'shop.sticky' ),
			),
			'details'  => array(
				'title'       => __( 'Under the product', 'pergel-engine' ),
				'description' => __( 'The description, specifications, questions and reviews under the product, then related and recently viewed products.', 'pergel-engine' ),
				'parts'       => array( 'shop.details', 'shop.side', 'shop.tablook', 'shop.related' ),
			),
			'sales'    => array(
				'title'       => __( 'Flash sales', 'pergel-engine' ),
				'description' => __( 'Campaigns with a start and an end, under Products → Flash sales.', 'pergel-engine' ),
				'parts'       => array( 'woo.sales' ),
			),
			'delivery' => array(
				'title'       => __( 'Delivery and shipping', 'pergel-engine' ),
				'description' => __( 'The delivery date estimate and the free shipping progress.', 'pergel-engine' ),
				'parts'       => array( 'woo.delivery', 'woo.shipping' ),
			),
			'phones'   => array(
				'title'       => __( 'Bar on phones', 'pergel-engine' ),
				'description' => __( 'The bar at the bottom of the screen on phones.', 'pergel-engine' ),
				'parts'       => array( 'woo.phones' ),
			),
			'presets'  => array(
				'title'       => __( 'Ready-made layouts', 'pergel-engine' ),
				'description' => __( 'Set many shop settings in one go. You see every change before it is made, and can undo it.', 'pergel-engine' ),
				'parts'       => array(),
			),
		);
	}

	/**
	 * Sections of each tab and the fields they hold, in display order.
	 * Schema fields missing here are shown in a last "Other" section.
	 *
	 * @return array<string, array<string, array{title: string, fields: list<string>}>>
	 */
	public static function sections(): array {
		return array(
			'modules'     => array(
				'modules' => array(
					'title'  => __( 'Modules', 'pergel-engine' ),
					'fields' => array( 'brand', 'seo', 'performance', 'forms', 'search', 'woo', 'abilities' ),
				),
			),
			'brand'       => array(
				'company' => array(
					'title'  => __( 'Company', 'pergel-engine' ),
					'fields' => array( 'name', 'legal_name', 'vat_id' ),
				),
				'contact' => array(
					'title'  => __( 'Contact', 'pergel-engine' ),
					'fields' => array( 'email', 'phone' ),
				),
				'address' => array(
					'title'  => __( 'Address', 'pergel-engine' ),
					'fields' => array( 'street', 'locality', 'region', 'postal_code', 'country' ),
				),
				'online'  => array(
					'title'  => __( 'Social accounts', 'pergel-engine' ),
					'fields' => array( 'social' ),
				),
				'logos'   => array(
					'title'  => __( 'Logos', 'pergel-engine' ),
					'fields' => array( 'logo_inverse', 'logo_has_name' ),
				),
			),
			'seo'         => array(
				'sharing' => array(
					'title'  => __( 'Sharing', 'pergel-engine' ),
					'fields' => array( 'og_image', 'twitter_site' ),
				),
				'sitemap' => array(
					'title'  => __( 'Sitemap', 'pergel-engine' ),
					'fields' => array( 'sitemap_users' ),
				),
			),
			'performance' => array(
				'remove' => array(
					'title'  => __( 'Remove', 'pergel-engine' ),
					'fields' => array( 'disable_emoji', 'remove_oembed_links', 'dequeue_dashicons' ),
				),
				'load'   => array(
					'title'  => __( 'Load early', 'pergel-engine' ),
					'fields' => array( 'preload_fonts' ),
				),
			),
			'forms'       => array(
				'delivery' => array(
					'title'  => __( 'Delivery', 'pergel-engine' ),
					'fields' => array( 'recipient' ),
				),
			),
			'search'      => array(
				'live' => array(
					'title'  => __( 'Suggestions while typing', 'pergel-engine' ),
					'fields' => array( 'live', 'live_limit' ),
				),
			),
			'woo'         => array(
				'selling'   => array(
					'title'  => __( 'Selling', 'pergel-engine' ),
					'fields' => array( 'sales_mode' ),
				),
				'enquiries' => array(
					'title'  => __( 'Product enquiries', 'pergel-engine' ),
					'fields' => array( 'whatsapp', 'contact_url' ),
				),
				'lists'     => array(
					'title'  => __( 'Wishlist and compare', 'pergel-engine' ),
					'fields' => array( 'wishlist', 'wishlist_url', 'compare', 'compare_url' ),
				),
				'product'   => array(
					'title'  => __( 'Product page', 'pergel-engine' ),
					'fields' => array( 'swatches', 'sticky_cart', 'stock_alert', 'size_guide', 'bought_together' ),
				),
				'delivery'  => array(
					'title'  => __( 'Delivery estimate', 'pergel-engine' ),
					'fields' => array( 'delivery', 'delivery_cutoff', 'delivery_handling', 'delivery_min', 'delivery_max', 'delivery_days', 'delivery_holidays' ),
				),
				'header'    => array(
					'title'  => __( 'Theme headers', 'pergel-engine' ),
					'fields' => array( 'mini_cart' ),
				),
				'phones'    => array(
					'title'  => __( 'Bar at the bottom of phones', 'pergel-engine' ),
					'fields' => array( 'bottom_bar', 'bar_shop', 'bar_filter', 'bar_search', 'bar_wishlist', 'bar_account', 'bar_cart' ),
				),
				'shipping'  => array(
					'title'  => __( 'Free shipping progress', 'pergel-engine' ),
					'fields' => array( 'shipping_mini', 'shipping_cart', 'shipping_product', 'shipping_checkout', 'shipping_min' ),
				),
				'sales'     => array(
					'title'  => __( 'Flash sales', 'pergel-engine' ),
					'fields' => array( 'flash_sales', 'sales_slug', 'sales_product', 'sales_card', 'sales_band' ),
				),
				'speed'     => array(
					'title'  => __( 'Speed', 'pergel-engine' ),
					'fields' => array( 'shop_assets_only', 'no_cart_fragments' ),
				),
			),
			'shop'        => array(
				'grid'    => array(
					'title'  => __( 'Product grid', 'pergel-engine' ),
					'fields' => array( 'columns', 'columns_tablet', 'columns_phone', 'per_page' ),
				),
				'page'    => array(
					'title'  => __( 'Page', 'pergel-engine' ),
					'fields' => array( 'width' ),
				),
				'filters' => array(
					'title'  => __( 'Filters', 'pergel-engine' ),
					'fields' => array( 'filters', 'drawer_side', 'filters_sticky', 'filter_title', 'filter_icon', 'filters_fold', 'filters_search', 'filter_tree', 'filter_more', 'filter_rows', 'filter_attributes', 'filter_order', 'filter_stock', 'filter_brand', 'filter_rating' ),
				),
				'refine'  => array(
					'title'  => __( 'Filter look', 'pergel-engine' ),
					'fields' => array( 'filter_bg', 'filter_edge', 'filter_edge_color', 'filter_radius', 'filter_padding', 'filter_head_size', 'filter_head_weight', 'filter_title_look', 'filter_divider', 'filter_text_size', 'filter_counts', 'filter_accent', 'filter_input_size', 'filter_input_edge', 'filter_input_color', 'filter_swatch', 'filter_star_size', 'filter_star_color' ),
				),
				'toolbar' => array(
					'title'  => __( 'View and paging', 'pergel-engine' ),
					'fields' => array( 'view', 'view_switch', 'per_page_menu', 'paging' ),
				),
				'tools'   => array(
					'title'  => __( 'Toolbar and page numbers look', 'pergel-engine' ),
					'fields' => array( 'toolbar_bg', 'toolbar_edge', 'toolbar_edge_color', 'toolbar_radius', 'toolbar_padding', 'control_radius', 'control_height', 'control_edge', 'control_edge_color', 'paging_shape', 'paging_size', 'paging_bg', 'paging_current' ),
				),
				'header'  => array(
					'title'  => __( 'Above the products', 'pergel-engine' ),
					'fields' => array( 'banner', 'category_strip', 'strip_count', 'strip_picture' ),
				),
				'store'   => array(
					'title'  => __( 'Store header', 'pergel-engine' ),
					'fields' => array( 'header_type', 'header_on' ),
				),
				'topbar'  => array(
					'title'  => __( 'Top bar', 'pergel-engine' ),
					'fields' => array( 'header_top', 'header_top_items', 'header_text', 'header_top_menu' ),
				),
				'mainrow' => array(
					'title'  => __( 'Header row', 'pergel-engine' ),
					'fields' => array( 'header_items', 'header_categories', 'header_call', 'header_button' ),
				),
				'menubar' => array(
					'title'  => __( 'Menu bar', 'pergel-engine' ),
					'fields' => array( 'header_bar', 'header_menu', 'header_bar_text', 'header_bar_url' ),
				),
				'skin'    => array(
					'title'  => __( 'Store header look', 'pergel-engine' ),
					'fields' => array( 'header_top_bg', 'header_top_color', 'header_bg', 'header_padding', 'search_radius', 'search_button', 'header_bar_bg', 'header_bar_color', 'header_bar_height' ),
				),
				'cards'   => array(
					'title'  => __( 'Product cards', 'pergel-engine' ),
					'fields' => array( 'card_style', 'card_items', 'card_category', 'card_brand', 'card_rating', 'card_discount', 'card_stock', 'card_hover', 'card_countdown', 'card_stock_bar', 'quick_view', 'card_swatches', 'card_actions', 'card_quote' ),
				),
				'look'    => array(
					'title'  => __( 'Card look', 'pergel-engine' ),
					'fields' => array( 'card_gap', 'card_bg', 'card_border', 'card_border_color', 'card_radius', 'card_padding', 'card_hover_look', 'card_image_bg', 'card_image_radius', 'card_align', 'card_title_size', 'card_title_weight', 'card_title_lines', 'card_meta_color', 'card_button', 'card_button_color', 'card_button_shape', 'card_button_width', 'card_button_height' ),
				),
				'badges'  => array(
					'title'  => __( 'Badges', 'pergel-engine' ),
					'fields' => array( 'badge_sale', 'badge_new', 'badge_featured', 'badge_sold_out' ),
				),
				'product' => array(
					'title'  => __( 'Product page', 'pergel-engine' ),
					'fields' => array( 'product_layout', 'product_thumbs', 'product_sticky', 'product_zoom', 'product_badges', 'product_nav' ),
				),
				'summary' => array(
					'title'  => __( 'Beside the pictures', 'pergel-engine' ),
					'fields' => array( 'summary_items', 'product_banner', 'product_category', 'product_brand', 'product_discount', 'product_stock' ),
				),
				'boxes'   => array(
					'title'  => __( 'Boxes in the summary', 'pergel-engine' ),
					'fields' => array( 'also_count', 'also_title', 'facts_rows', 'trust_image', 'trust_text', 'box_bg', 'box_edge', 'box_radius', 'box_padding' ),
				),
				'buying'  => array(
					'title'  => __( 'Buying', 'pergel-engine' ),
					'fields' => array( 'product_buy_now', 'product_coupons', 'product_share' ),
				),
				'styling' => array(
					'title'  => __( 'Product page look', 'pergel-engine' ),
					'fields' => array( 'picture_bg', 'picture_edge', 'picture_edge_color', 'picture_radius', 'thumb_radius', 'thumb_active', 'title_size', 'title_weight', 'price_size', 'price_weight', 'price_color', 'buy_look', 'buy_color', 'buy_shape', 'buy_height', 'qty_shape' ),
				),
				'details' => array(
					'title'  => __( 'Under the product', 'pergel-engine' ),
					'fields' => array( 'details_style', 'details_split', 'details_phone', 'details_features', 'details_title', 'details_tab', 'details_title2', 'details_tab2', 'details_qa', 'details_rating', 'details_specs' ),
				),
				'side'    => array(
					'title'  => __( 'Beside the sections', 'pergel-engine' ),
					'fields' => array( 'details_side', 'details_side_count', 'details_banner' ),
				),
				'tablook' => array(
					'title'  => __( 'Tab look', 'pergel-engine' ),
					'fields' => array( 'tab_look', 'tab_size', 'tab_weight', 'tab_color', 'tab_active', 'tab_radius', 'tab_panel_bg', 'tab_edge_color', 'tab_padding', 'tab_area_bg' ),
				),
				'related' => array(
					'title'  => __( 'Related and recently viewed', 'pergel-engine' ),
					'fields' => array( 'related_style', 'related_count', 'related_title', 'related_compare', 'related_recent', 'related_banner', 'related_bg', 'related_edge', 'related_radius', 'related_padding' ),
				),
				'sticky'  => array(
					'title'  => __( 'Sticky bar look', 'pergel-engine' ),
					'fields' => array( 'sticky_bg', 'sticky_padding', 'sticky_image', 'sticky_text', 'sticky_button', 'sticky_shape' ),
				),
			),
		);
	}

	/**
	 * Field labels and help texts.
	 *
	 * With cards, the choices are shown as pictures (Pictures) instead of a
	 * select box.
	 *
	 * @return array<string, array<string, array{label: string, help?: string, svg?: string, choices?: array<int|string, string>, cards?: bool}>>
	 */
	public static function fields(): array {
		return array(
			'modules'     => array(
				'brand'       => array(
					'label' => __( 'Brand', 'pergel-engine' ),
					'help'  => __( 'Brand details in blocks (Block Bindings) and in structured data.', 'pergel-engine' ),
				),
				'seo'         => array(
					'label' => __( 'SEO', 'pergel-engine' ),
					'help'  => __( 'Hands over to an active SEO plugin and adds the brand details to its data. Without one, Pergel prints the meta tags and structured data itself.', 'pergel-engine' ),
				),
				'performance' => array(
					'label' => __( 'Performance', 'pergel-engine' ),
					'help'  => __( 'Removes front-end assets that most sites do not need.', 'pergel-engine' ),
				),
				'forms'       => array(
					'label' => __( 'Forms', 'pergel-engine' ),
					'help'  => __( 'The contact and quote Form block: messages are emailed and kept in the admin area.', 'pergel-engine' ),
				),
				'search'      => array(
					'label' => __( 'Search', 'pergel-engine' ),
					'help'  => __( 'Own search index for the search page, the product search and the suggestions while typing. Off: WordPress searches the texts itself, more slowly and without Turkish letter matching.', 'pergel-engine' ),
				),
				'woo'         => array(
					'label' => __( 'Shop', 'pergel-engine' ),
					'help'  => __( 'WooCommerce support. It loads only while WooCommerce is active.', 'pergel-engine' ),
				),
				'abilities'   => array(
					'label' => __( 'AI access', 'pergel-engine' ),
					'help'  => __( 'Lets AI agents read the patterns, pages, design tokens and settings through the WordPress Abilities API and an MCP server, as a logged-in user with the right permissions and at most 60 requests a minute. Nothing is changed through it.', 'pergel-engine' ),
				),
			),
			'brand'       => array(
				'name'          => array(
					'label' => __( 'Brand name', 'pergel-engine' ),
					'help'  => __( 'The name customers know. Leave empty to use the site title.', 'pergel-engine' ),
				),
				'legal_name'    => array( 'label' => __( 'Legal name', 'pergel-engine' ) ),
				'vat_id'        => array( 'label' => __( 'Tax number', 'pergel-engine' ) ),
				'email'         => array( 'label' => __( 'Email', 'pergel-engine' ) ),
				'phone'         => array(
					'label' => __( 'Phone', 'pergel-engine' ),
					'help'  => __( 'With the country code, e.g. +90 212 000 00 00.', 'pergel-engine' ),
				),
				'street'        => array( 'label' => __( 'Street address', 'pergel-engine' ) ),
				'locality'      => array( 'label' => __( 'City or district', 'pergel-engine' ) ),
				'region'        => array( 'label' => __( 'Region or province', 'pergel-engine' ) ),
				'postal_code'   => array( 'label' => __( 'Postal code', 'pergel-engine' ) ),
				'country'       => array(
					'label' => __( 'Country code', 'pergel-engine' ),
					'help'  => __( 'Two letters (ISO 3166-1), e.g. TR.', 'pergel-engine' ),
				),
				'social'        => array(
					'label' => __( 'Profile links', 'pergel-engine' ),
					'help'  => __( 'One full address per line (LinkedIn, Instagram, YouTube, ...). Up to 10.', 'pergel-engine' ),
				),
				'logo_inverse'  => array(
					'label' => __( 'Logo for dark backgrounds', 'pergel-engine' ),
					'help'  => __( 'The main logo is the Site Logo in the Site Editor.', 'pergel-engine' ),
					'svg'   => __( 'SVG uploads are off on this site. The free Safe SVG plugin turns them on and cleans every SVG file.', 'pergel-engine' ),
				),
				'logo_has_name' => array(
					'label' => __( 'The logo shows the name', 'pergel-engine' ),
					'help'  => __( 'Hides the site title next to the Site Logo in the header. Leave it off for a logo that is only a mark.', 'pergel-engine' ),
				),
			),
			'seo'         => array(
				'og_image'      => array(
					'label' => __( 'Default share image', 'pergel-engine' ),
					'help'  => __( 'Shown when a shared page has no featured image. JPG, PNG or WebP; 1200 × 630 pixels works everywhere.', 'pergel-engine' ),
				),
				'twitter_site'  => array(
					'label' => __( 'X (Twitter) username', 'pergel-engine' ),
					'help'  => __( 'For example @ariumlab.', 'pergel-engine' ),
				),
				'sitemap_users' => array(
					'label' => __( 'List author pages in the sitemap', 'pergel-engine' ),
					'help'  => __( 'Off for most company sites: author pages repeat the blog.', 'pergel-engine' ),
				),
			),
			'performance' => array(
				'disable_emoji'       => array(
					'label' => __( 'Remove the emoji script', 'pergel-engine' ),
					'help'  => __( 'Browsers draw emoji themselves. Feeds and emails keep the image fallback.', 'pergel-engine' ),
				),
				'remove_oembed_links' => array(
					'label' => __( 'Remove oEmbed discovery links', 'pergel-engine' ),
					'help'  => __( 'Other sites can no longer turn a pasted page address into an embed card. Embeds within this site keep working.', 'pergel-engine' ),
				),
				'dequeue_dashicons'   => array(
					'label' => __( 'No Dashicons for visitors', 'pergel-engine' ),
					'help'  => __( 'For visitors who are not logged in. A plugin style that needs the icon font still loads it.', 'pergel-engine' ),
				),
				'preload_fonts'       => array(
					'label' => __( 'Preload the style variation fonts', 'pergel-engine' ),
					'help'  => __( 'The basic Latin files of the body and heading fonts start downloading with the page.', 'pergel-engine' ),
				),
			),
			'forms'       => array(
				'recipient' => array(
					'label' => __( 'Send messages to', 'pergel-engine' ),
					'help'  => __( 'Leave empty to use the administration email address of the site.', 'pergel-engine' ),
				),
			),
			'search'      => array(
				'live'       => array(
					'label' => __( 'Suggest results under the search field', 'pergel-engine' ),
					'help'  => __( 'Every Search block shows matching categories, the first results with a small image (and the price, where products are sold) and a link to all results. A Search block for products suggests products only.', 'pergel-engine' ),
				),
				'live_limit' => array(
					'label' => __( 'Results in the suggestions', 'pergel-engine' ),
					'help'  => __( 'From 3 to 12.', 'pergel-engine' ),
				),
			),
			'woo'         => array(
				'sales_mode'        => array(
					'label'   => __( 'Sales mode', 'pergel-engine' ),
					'help'    => __( 'For the whole shop. A product category or a single product can choose its own mode; when a product is in several categories, the strictest one applies. Hidden prices are left out of the page, the shop data and the structured data, and the products cannot be added to the cart.', 'pergel-engine' ),
					'choices' => array(
						'sale'    => __( 'Sale: prices and the cart for everyone', 'pergel-engine' ),
						'login'   => __( 'Prices for logged-in customers only', 'pergel-engine' ),
						'quote'   => __( 'Quote: no prices, customers ask for an offer', 'pergel-engine' ),
						'catalog' => __( 'Catalog: products only, no prices', 'pergel-engine' ),
					),
				),
				'whatsapp'          => array(
					'label' => __( 'WhatsApp number', 'pergel-engine' ),
					'help'  => __( 'With the country code, e.g. +90 532 000 00 00. The Product enquiry block links to a chat with this number. Its phone link uses the phone number of the brand.', 'pergel-engine' ),
				),
				'contact_url'       => array(
					'label' => __( 'Contact page', 'pergel-engine' ),
					'help'  => __( 'Address of the page the Product enquiry block links to, e.g. the contact page.', 'pergel-engine' ),
				),
				'mini_cart'         => array(
					'label' => __( 'Show the mini cart in the header', 'pergel-engine' ),
					'help'  => __( 'Adds the cart button after the menu in the theme headers. It shows the number of items and opens the cart in a side panel.', 'pergel-engine' ),
				),
				'shop_assets_only'  => array(
					'label' => __( 'Load classic WooCommerce files only where they are used', 'pergel-engine' ),
					'help'  => __( 'The classic WooCommerce styles and jQuery scripts load on account pages, on pages with a WooCommerce shortcode and with classic templates. The theme shop templates are made of blocks, which bring their own files.', 'pergel-engine' ),
				),
				'no_cart_fragments' => array(
					'label' => __( 'Turn off cart fragments', 'pergel-engine' ),
					'help'  => __( 'An old script that asks the server for the cart on every page view. The mini cart block does not need it.', 'pergel-engine' ),
				),
				'wishlist'          => array(
					'label' => __( 'Wishlist', 'pergel-engine' ),
					'help'  => __( 'Adds a heart button to product cards and product pages. Visitors keep their list in the browser; a logged-in customer\'s list is kept in their account.', 'pergel-engine' ),
				),
				'wishlist_url'      => array(
					'label' => __( 'Wishlist page', 'pergel-engine' ),
					'help'  => __( 'Address of the page that shows the list with the Wishlist block. With an address, the theme headers link to it and show the number of saved products.', 'pergel-engine' ),
				),
				'compare'           => array(
					'label' => __( 'Compare', 'pergel-engine' ),
					'help'  => __( 'Adds a compare button to product cards and product pages. Up to four products, kept in the visitor\'s browser.', 'pergel-engine' ),
				),
				'compare_url'       => array(
					'label' => __( 'Compare page', 'pergel-engine' ),
					'help'  => __( 'Address of the page that shows the products side by side with the Compare block. With an address, the theme headers link to it.', 'pergel-engine' ),
				),
				'swatches'          => array(
					'label' => __( 'Theme style for product options', 'pergel-engine' ),
					'help'  => __( 'Gives the colour swatches and option buttons of variable products the theme\'s look: larger touch targets, a clear ring on the chosen and the focused option, unavailable options crossed out, and the chosen colour\'s name under the swatches. For colour swatches, turn on "Color swatches for attributes" under WooCommerce → Settings → Advanced → Features and give the attribute the "Color / image" type.', 'pergel-engine' ),
				),
				'sticky_cart'       => array(
					'label' => __( 'Sticky add-to-cart bar', 'pergel-engine' ),
					'help'  => __( 'When the add-to-cart button scrolls out of view on a product page, a bar with the product\'s image, name, price and button appears at the bottom of the screen. For a product with options it first takes the visitor to the options.', 'pergel-engine' ),
				),
				'stock_alert'       => array(
					'label' => __( 'Back-in-stock alerts', 'pergel-engine' ),
					'help'  => __( 'Sold-out products and options show a form where visitors leave their email address; when the product is back in stock, each address gets one email. Visitors who are not logged in first confirm their address with a link we email them. The addresses are listed under WooCommerce → Stock alerts, and both emails can be edited under WooCommerce → Settings → Emails.', 'pergel-engine' ),
				),
				'size_guide'        => array(
					'label' => __( 'Size guide', 'pergel-engine' ),
					'help'  => __( 'A "Size guide" button above the add-to-cart button opens the guide in a window. A guide is a synced pattern made under Appearance → Editor → Patterns; choose it on a product category (its subcategories and products follow) or on a product, under Product data → General. Products without a guide show nothing.', 'pergel-engine' ),
				),
				'bought_together'   => array(
					'label' => __( 'Frequently bought together', 'pergel-engine' ),
					'help'  => __( 'Under the add-to-cart button, the product and up to three of its cross-sells (Product data → Linked products) with checkboxes, a total and one button that adds the chosen ones to the cart. Cross-sells with options get a link to their page. Each product keeps its own price.', 'pergel-engine' ),
				),
				'bottom_bar'        => array(
					'label' => __( 'Show the bar on phones', 'pergel-engine' ),
					'help'  => __( 'A row of shortcuts fixed to the bottom of narrow screens, on every page but the checkout. On product pages the sticky add-to-cart bar sits above it.', 'pergel-engine' ),
				),
				'bar_shop'          => array(
					'label' => __( 'Shop', 'pergel-engine' ),
				),
				'bar_filter'        => array(
					'label' => __( 'Filter', 'pergel-engine' ),
					'help'  => __( 'Only on shop pages whose filters open in a panel on phones.', 'pergel-engine' ),
				),
				'bar_search'        => array(
					'label' => __( 'Search', 'pergel-engine' ),
					'help'  => __( 'Opens a product search box.', 'pergel-engine' ),
				),
				'bar_wishlist'      => array(
					'label' => __( 'Wishlist', 'pergel-engine' ),
					'help'  => __( 'With the number of saved products. Needs the wishlist and its page, above.', 'pergel-engine' ),
				),
				'bar_account'       => array(
					'label' => __( 'My account', 'pergel-engine' ),
				),
				'bar_cart'          => array(
					'label' => __( 'Cart', 'pergel-engine' ),
					'help'  => __( 'With the number of products in the cart.', 'pergel-engine' ),
				),
				'shipping_mini'     => array(
					'label' => __( 'In the mini cart', 'pergel-engine' ),
					'help'  => __( 'How much more the customer needs to spend for free shipping, with a bar that fills up. It follows the cart as products are added or removed.', 'pergel-engine' ),
				),
				'shipping_cart'     => array(
					'label' => __( 'On the cart page', 'pergel-engine' ),
				),
				'shipping_product'  => array(
					'label' => __( 'On product pages', 'pergel-engine' ),
					'help'  => __( 'Under the add-to-cart button.', 'pergel-engine' ),
				),
				'shipping_checkout' => array(
					'label' => __( 'On the checkout page', 'pergel-engine' ),
				),
				'shipping_min'      => array(
					'label' => __( 'Free shipping from', 'pergel-engine' ),
					'help'  => __( 'The amount comes from the "Free shipping" method of the customer\'s shipping zone (WooCommerce → Settings → Shipping) when it asks for a minimum order amount. This amount is used where there is no such method; 0 shows nothing there.', 'pergel-engine' ),
				),
				'flash_sales'       => array(
					'label' => __( 'Flash sale campaigns', 'pergel-engine' ),
					'help'  => __( 'Adds Products → Flash sales. A campaign has a start, an end, a discount and its products or categories; while it runs, its price is the WooCommerce sale price of the products, and afterwards their own prices come back. A list page shows the running and upcoming campaigns, and each campaign has a page with a countdown and its products.', 'pergel-engine' ),
				),
				'sales_slug'        => array(
					'label' => __( 'Address of the campaign pages', 'pergel-engine' ),
					'help'  => __( 'The part after the site address, such as "flash-sale" for /flash-sale/. Leave empty for the default in the site language.', 'pergel-engine' ),
				),
				'sales_product'     => array(
					'label' => __( 'Campaign box on product pages', 'pergel-engine' ),
					'help'  => __( 'Under the price of a product in a running campaign: its name, the discount and a countdown to the end, with a link to the campaign page.', 'pergel-engine' ),
				),
				'sales_card'        => array(
					'label' => __( '"Flash sale" badge on product cards', 'pergel-engine' ),
					'help'  => __( 'With the other badges on the picture. The sale countdown of the cards (Shop → Product cards) counts down to the end of the campaign.', 'pergel-engine' ),
				),
				'sales_band'        => array(
					'label' => __( 'Band above the products', 'pergel-engine' ),
					'help'  => __( 'On the shop and category pages while a campaign runs: its name, the discount, a countdown and a link. A category page shows a campaign of that category first, else the one that ends soonest.', 'pergel-engine' ),
				),
				'delivery'          => array(
					'label' => __( 'Show the estimated delivery date', 'pergel-engine' ),
					'help'  => __( 'Product pages show a date range under the add-to-cart button, and before the cut-off time on a working day also that an order ships today. It is worked out in the visitor\'s browser from the settings below, so it stays right on cached pages. Not shown for virtual, sold-out or hidden products.', 'pergel-engine' ),
				),
				'delivery_cutoff'   => array(
					'label' => __( 'Order cut-off time', 'pergel-engine' ),
					'help'  => __( 'Orders placed on a working day before this time count as placed that day; later ones count from the next working day. In the site\'s time zone (Settings → General). Leave empty for no cut-off.', 'pergel-engine' ),
				),
				'delivery_handling' => array(
					'label' => __( 'Days to prepare an order', 'pergel-engine' ),
					'help'  => __( 'Working days from the order until it is handed to the carrier. 0: it ships the same day.', 'pergel-engine' ),
				),
				'delivery_min'      => array(
					'label' => __( 'Shortest delivery time', 'pergel-engine' ),
					'help'  => __( 'Working days the carrier needs at best. A shipping class can set its own delivery time under WooCommerce → Settings → Shipping → Classes.', 'pergel-engine' ),
				),
				'delivery_max'      => array(
					'label' => __( 'Longest delivery time', 'pergel-engine' ),
					'help'  => __( 'Working days the carrier needs at most.', 'pergel-engine' ),
				),
				'delivery_days'     => array(
					'label'   => __( 'Working days', 'pergel-engine' ),
					'help'    => __( 'The days orders are prepared, shipped and delivered.', 'pergel-engine' ),
					'choices' => array(
						'mon_fri'   => __( 'Monday to Friday', 'pergel-engine' ),
						'mon_sat'   => __( 'Monday to Saturday', 'pergel-engine' ),
						'every_day' => __( 'Every day', 'pergel-engine' ),
					),
				),
				'delivery_holidays' => array(
					'label' => __( 'Days off', 'pergel-engine' ),
					'help'  => __( 'Public holidays and other days without shipping. One per line, as 2026-10-29, or a range as 2026-03-19/2026-03-22. Up to 40 lines.', 'pergel-engine' ),
				),
			),
			'shop'        => array(
				'columns'            => array(
					'label'   => __( 'Products per row', 'pergel-engine' ),
					'help'    => __( 'On wide screens. Narrower screens show fewer products per row unless you choose a number below.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'template' => __( 'As in the template', 'pergel-engine' ),
						'2'        => '2',
						'3'        => '3',
						'4'        => '4',
						'5'        => '5',
						'6'        => '6',
					),
				),
				'columns_tablet'     => array(
					'label'   => __( 'Products per row on tablets', 'pergel-engine' ),
					'help'    => __( 'Screens from 600 to 1023 pixels wide.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'auto' => __( 'Automatic', 'pergel-engine' ),
						'1'    => '1',
						'2'    => '2',
						'3'    => '3',
						'4'    => '4',
					),
				),
				'columns_phone'      => array(
					'label'   => __( 'Products per row on phones', 'pergel-engine' ),
					'help'    => __( 'Screens narrower than 600 pixels. Two per row shows more products; one shows larger pictures.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'auto' => __( 'Automatic', 'pergel-engine' ),
						'1'    => '1',
						'2'    => '2',
					),
				),
				'per_page'           => array(
					'label' => __( 'Products per page', 'pergel-engine' ),
					'help'  => __( 'From 1 to 96. 0 uses the WooCommerce setting.', 'pergel-engine' ),
				),
				'width'              => array(
					'label'   => __( 'Page width', 'pergel-engine' ),
					'help'    => __( 'Full width uses the whole screen, with a small margin at the sides.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'wide' => __( 'As in the template', 'pergel-engine' ),
						'full' => __( 'Full width', 'pergel-engine' ),
					),
				),
				'filters'            => array(
					'label'   => __( 'Filter area', 'pergel-engine' ),
					'help'    => __( 'Where the filters sit next to the products on wide screens. A panel hides them behind a "Filter products" button above the products, as on phones. Phones always use the panel.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'template' => __( 'As in the template', 'pergel-engine' ),
						'left'     => __( 'Left', 'pergel-engine' ),
						'right'    => __( 'Right', 'pergel-engine' ),
						'drawer'   => __( 'Panel', 'pergel-engine' ),
						'none'     => __( 'No filters', 'pergel-engine' ),
					),
				),
				'drawer_side'        => array(
					'label'   => __( 'Panel opens from', 'pergel-engine' ),
					'help'    => __( 'The side the filter panel slides in from, on phones too.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'left'  => __( 'Left', 'pergel-engine' ),
						'right' => __( 'Right', 'pergel-engine' ),
					),
				),
				'filters_sticky'     => array(
					'label'   => __( 'While scrolling', 'pergel-engine' ),
					'help'    => __( 'With "Stays in view" the filter column stops at the top of the screen while the products scroll. "Scrolls on its own" also gives a long filter column its own scroll bar.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'off'    => __( 'Scrolls with the page', 'pergel-engine' ),
						'sticky' => __( 'Stays in view', 'pergel-engine' ),
						'scroll' => __( 'Scrolls on its own', 'pergel-engine' ),
					),
				),
				'filters_fold'       => array(
					'label' => __( 'Filter groups open and close', 'pergel-engine' ),
					'help'  => __( 'Each filter heading becomes a button that hides or shows its options. All groups start open.', 'pergel-engine' ),
				),
				'filter_tree'        => array(
					'label' => __( 'Subcategories start closed', 'pergel-engine' ),
					'help'  => __( 'In the category filter, a category with subcategories gets an arrow that opens them. A ticked subcategory keeps its parent open, and a search looks through all of them.', 'pergel-engine' ),
				),
				'filters_search'     => array(
					'label' => __( 'Search box in long filter lists', 'pergel-engine' ),
					'help'  => __( 'Lists with more than eight options, such as categories or brands, get a box that narrows them down as the visitor types.', 'pergel-engine' ),
				),
				'filter_order'       => array(
					'label'   => __( 'Order of the options', 'pergel-engine' ),
					'help'    => __( 'In the category, brand and attribute filters. "The store\'s own order" is the order set by dragging under Products → Categories and in each attribute\'s terms.', 'pergel-engine' ),
					'choices' => array(
						'template'   => __( 'As the template (most products first)', 'pergel-engine' ),
						'menu_order' => __( 'The store\'s own order', 'pergel-engine' ),
						'name'       => __( 'By name', 'pergel-engine' ),
						'count'      => __( 'Most products first', 'pergel-engine' ),
					),
				),
				'filter_attributes'  => array(
					'label' => __( 'Attribute filters', 'pergel-engine' ),
					'help'  => __( 'The product attributes to filter by, in this order, separated by commas, e.g. features, volume, wells, color (the attribute slugs, under Products → Attributes). Leave empty for the store\'s first three attributes.', 'pergel-engine' ),
				),
				'filter_stock'       => array(
					'label' => __( 'Availability filter', 'pergel-engine' ),
					'help'  => __( 'Filters by stock: in stock, out of stock, on backorder. A shop that only takes quote requests may not need it.', 'pergel-engine' ),
				),
				'filter_brand'       => array(
					'label' => __( 'Brand filter', 'pergel-engine' ),
					'help'  => __( 'Filters by the WooCommerce brands (Products → Brands). Shown only while brands have products.', 'pergel-engine' ),
				),
				'filter_rating'      => array(
					'label' => __( 'Rating filter', 'pergel-engine' ),
					'help'  => __( 'Filters by the average customer rating. Shown only while rated products exist.', 'pergel-engine' ),
				),
				'filter_title'       => array(
					'label' => __( 'Filter box title', 'pergel-engine' ),
					'help'  => __( 'The heading above the filters, such as "Filter & Refine". Empty keeps the template\'s.', 'pergel-engine' ),
				),
				'filter_icon'        => array(
					'label' => __( 'Icon before the filter box title', 'pergel-engine' ),
				),
				'filter_more'        => array(
					'label'   => __( 'Long filter lists', 'pergel-engine' ),
					'help'    => __( 'With "A few rows and a More button" a long list shows its first rows, and a rounded More button below them opens the rest.', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the template ("Show more" link)', 'pergel-engine' ),
						'pill'     => __( 'A few rows and a More button', 'pergel-engine' ),
					),
				),
				'filter_rows'        => array(
					'label' => __( 'Rows before More', 'pergel-engine' ),
					'help'  => __( 'How many options a long list shows before its More button.', 'pergel-engine' ),
				),
				'view'               => array(
					'label'   => __( 'Products are shown as', 'pergel-engine' ),
					'help'    => __( 'The list shows each product in a row, with its picture beside the name, price and short description.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'grid' => __( 'Grid', 'pergel-engine' ),
						'list' => __( 'List', 'pergel-engine' ),
					),
				),
				'view_switch'        => array(
					'label' => __( 'Grid and list buttons', 'pergel-engine' ),
					'help'  => __( 'Visitors can switch between the grid and the list next to the sorting menu. Their choice is kept in their browser.', 'pergel-engine' ),
				),
				'per_page_menu'      => array(
					'label' => __( 'Products per page menu', 'pergel-engine' ),
					'help'  => __( 'Numbers visitors can choose from next to the sorting menu, separated by commas, e.g. 12, 24, 48. Up to six numbers from 1 to 96. Leave empty for no menu. Not shown while products load as the visitor scrolls.', 'pergel-engine' ),
				),
				'paging'             => array(
					'label'   => __( 'More products', 'pergel-engine' ),
					'help'    => __( '"Load more" adds the next products under the ones on screen. "Load while scrolling" does it on its own when the visitor nears the end, and keeps the button for keyboard users. The page numbers stay in the page for search engines and without JavaScript.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'template' => __( 'Page numbers', 'pergel-engine' ),
						'more'     => __( 'Load more button', 'pergel-engine' ),
						'scroll'   => __( 'Load while scrolling', 'pergel-engine' ),
					),
				),
				'banner'             => array(
					'label' => __( 'Banner', 'pergel-engine' ),
					'help'  => __( 'A pattern shown above the products, e.g. a campaign. Make it under Appearance → Editor → Patterns.', 'pergel-engine' ),
				),
				'card_style'         => array(
					'label'   => __( 'Card style', 'pergel-engine' ),
					'help'    => __( 'For the products in every product grid: the shop, categories, and grids on other pages such as "New arrivals". "Buttons on the picture" puts the wishlist and compare buttons on the product picture; they show when the pointer is over the card, and always on touch screens.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'template' => __( 'As in the template', 'pergel-engine' ),
						'standard' => __( 'Standard', 'pergel-engine' ),
						'boxed'    => __( 'Boxed', 'pergel-engine' ),
						'overlay'  => __( 'Buttons on the picture', 'pergel-engine' ),
						'plain'    => __( 'Plain, centred', 'pergel-engine' ),
					),
				),
				'card_category'      => array(
					'label' => __( 'Category on cards', 'pergel-engine' ),
					'help'  => __( "The product's category above its name, as a link.", 'pergel-engine' ),
				),
				'card_brand'         => array(
					'label' => __( 'Brand on cards', 'pergel-engine' ),
					'help'  => __( "The product's brand (Products → Brands) above its name.", 'pergel-engine' ),
				),
				'card_rating'        => array(
					'label' => __( 'Rating on cards', 'pergel-engine' ),
					'help'  => __( 'Stars and the number of reviews under the name, for products with reviews.', 'pergel-engine' ),
				),
				'card_discount'      => array(
					'label' => __( 'Discount on cards', 'pergel-engine' ),
					'help'  => __( 'The discount in per cent beside the price, e.g. -15%. For products with options, the largest one.', 'pergel-engine' ),
				),
				'card_stock'         => array(
					'label' => __( 'Stock on cards', 'pergel-engine' ),
					'help'  => __( 'How many are left, for products whose stock is counted, or "Out of stock", beside the price.', 'pergel-engine' ),
				),
				'card_hover'         => array(
					'label' => __( 'Second picture', 'pergel-engine' ),
					'help'  => __( 'The first gallery picture shows while the pointer is over the card. Not on touch screens.', 'pergel-engine' ),
				),
				'card_countdown'     => array(
					'label' => __( 'Sale countdown', 'pergel-engine' ),
					'help'  => __( 'Days, hours, minutes and seconds left on the picture, for products whose sale has an end date (Product data → General → Schedule).', 'pergel-engine' ),
				),
				'card_stock_bar'     => array(
					'label' => __( 'Stock bar', 'pergel-engine' ),
					'help'  => __( 'A bar with how many were sold and how many are left, for products whose stock is counted.', 'pergel-engine' ),
				),
				'quick_view'         => array(
					'label' => __( 'Quick view', 'pergel-engine' ),
					'help'  => __( 'A button on the picture opens the product in a window: pictures, price, short description and, for simple products, the add to cart button.', 'pergel-engine' ),
				),
				'badge_sale'         => array(
					'label'   => __( 'Sale badge', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'template' => __( 'As in the template', 'pergel-engine' ),
						'percent'  => __( 'Discount in per cent', 'pergel-engine' ),
						'none'     => __( 'None', 'pergel-engine' ),
					),
				),
				'badge_new'          => array(
					'label' => __( '"New" badge, days', 'pergel-engine' ),
					'help'  => __( 'Products published in the last this many days are marked new. 0 turns the badge off.', 'pergel-engine' ),
				),
				'badge_featured'     => array(
					'label' => __( '"Popular" badge', 'pergel-engine' ),
					'help'  => __( 'On featured products (the star in Products → All products).', 'pergel-engine' ),
				),
				'badge_sold_out'     => array(
					'label' => __( '"Sold out" badge', 'pergel-engine' ),
					'help'  => __( 'On products that are out of stock. A product can also show its own badge: Product data → General → Badge.', 'pergel-engine' ),
				),
				'category_strip'     => array(
					'label'   => __( 'Category strip', 'pergel-engine' ),
					'help'    => __( 'A row of category links above the products: the main categories in the shop, the subcategories in a category. Pictures come from the category image. The same cards can go on any page with the Category cards block.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'none'   => __( 'None', 'pergel-engine' ),
						'images' => __( 'Round pictures', 'pergel-engine' ),
						'boxes'  => __( 'Boxes', 'pergel-engine' ),
						'list'   => __( 'Boxes with subcategories', 'pergel-engine' ),
						'names'  => __( 'Names only', 'pergel-engine' ),
					),
				),
				'strip_count'        => array(
					'label'   => __( 'Categories in a row', 'pergel-engine' ),
					'help'    => __( 'How many share the row on wide screens; more scroll sideways. Fewer on tablets and phones.', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the style', 'pergel-engine' ),
						'4'        => '4',
						'5'        => '5',
						'6'        => '6',
						'7'        => '7',
						'8'        => '8',
						'9'        => '9',
						'10'       => '10',
					),
				),
				'strip_picture'      => array(
					'label'   => __( 'Category pictures', 'pergel-engine' ),
					'help'    => __( 'For "Round pictures". "Square" fills the tile with the picture on the card picture background, the name under it.', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the style', 'pergel-engine' ),
						'square'   => __( 'Square', 'pergel-engine' ),
					),
				),
				'product_layout'     => array(
					'label'   => __( 'Layout', 'pergel-engine' ),
					'help'    => __( 'Where the pictures go on the product page. "Three columns" adds a column of recommended products: the product\'s upsells, else related products. A category or a product can choose its own.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'template' => __( 'As in the template', 'pergel-engine' ),
						'left'     => __( 'Pictures on the left', 'pergel-engine' ),
						'right'    => __( 'Pictures on the right', 'pergel-engine' ),
						'three'    => __( 'Three columns', 'pergel-engine' ),
					),
				),
				'product_thumbs'     => array(
					'label'   => __( 'Small pictures', 'pergel-engine' ),
					'help'    => __( 'The row of small pictures that picks the big one. "All pictures" shows every picture one under the other, two in a row after the first.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'template' => __( 'As in the template', 'pergel-engine' ),
						'left'     => __( 'Beside the picture', 'pergel-engine' ),
						'bottom'   => __( 'Under the picture', 'pergel-engine' ),
						'none'     => __( 'None', 'pergel-engine' ),
						'grid'     => __( 'All pictures', 'pergel-engine' ),
					),
				),
				'product_sticky'     => array(
					'label' => __( 'Keep the details in view', 'pergel-engine' ),
					'help'  => __( 'The name, price and button stay on screen while the pictures scroll past. When the details are longer than the pictures, the pictures stay instead. Wide screens only.', 'pergel-engine' ),
				),
				'product_zoom'       => array(
					'label' => __( 'Zoom on hover', 'pergel-engine' ),
					'help'  => __( 'The big picture zooms in under the pointer.', 'pergel-engine' ),
				),
				'product_badges'     => array(
					'label' => __( 'Badges on the pictures', 'pergel-engine' ),
					'help'  => __( 'The badges chosen for product cards (own badge, New, Popular, Sold out) on the big picture too.', 'pergel-engine' ),
				),
				'product_nav'        => array(
					'label' => __( 'Previous and next product', 'pergel-engine' ),
					'help'  => __( 'Arrows beside the breadcrumb to the previous and next product in the same category, and a link back to the category.', 'pergel-engine' ),
				),
				'product_banner'     => array(
					'label' => __( 'Banner', 'pergel-engine' ),
					'help'  => __( 'A pattern above the product name, e.g. an offer for registered customers. Make it under Appearance → Editor → Patterns.', 'pergel-engine' ),
				),
				'product_category'   => array(
					'label' => __( 'Category above the name', 'pergel-engine' ),
					'help'  => __( "The product's category as a link.", 'pergel-engine' ),
				),
				'product_brand'      => array(
					'label' => __( 'Brand', 'pergel-engine' ),
					'help'  => __( "The product's brand (Products → Brands) under its name, with the brand's picture when it has one.", 'pergel-engine' ),
				),
				'product_discount'   => array(
					'label' => __( 'Discount beside the price', 'pergel-engine' ),
					'help'  => __( 'The discount in per cent, e.g. -15%. For products with options, the largest one.', 'pergel-engine' ),
				),
				'product_stock'      => array(
					'label' => __( 'Stock beside the price', 'pergel-engine' ),
					'help'  => __( 'How many are left, for products whose stock is counted, or "Out of stock".', 'pergel-engine' ),
				),
				'summary_items'      => array(
					'label'   => __( 'What the summary shows', 'pergel-engine' ),
					'help'    => __( 'Tick what the product page shows beside the pictures and put it in order with the arrows. An item the template keeps in another group stays in that group. A ticked item the template lacks is added; the buying form and the details list can only be shown or hidden.', 'pergel-engine' ),
					'items'   => 'summary',
					'choices' => array(
						'category' => __( 'Category', 'pergel-engine' ),
						'title'    => __( 'Product name', 'pergel-engine' ),
						'rating'   => __( 'Rating', 'pergel-engine' ),
						'price'    => __( 'Price', 'pergel-engine' ),
						'excerpt'  => __( 'Short description', 'pergel-engine' ),
						'sku'      => __( 'SKU', 'pergel-engine' ),
						'stock'    => __( 'Stock', 'pergel-engine' ),
						'buying'   => __( 'Buying form', 'pergel-engine' ),
						'cta'      => __( 'Enquiry buttons', 'pergel-engine' ),
						'meta'     => __( 'Details list (SKU, categories, tags)', 'pergel-engine' ),
						'also'     => __( 'Box: you may also like', 'pergel-engine' ),
						'facts'    => __( 'Box: product facts', 'pergel-engine' ),
						'trust'    => __( 'Box: safe payment', 'pergel-engine' ),
					),
				),
				'also_count'         => array(
					'label' => __( '"You may also like": how many', 'pergel-engine' ),
					'help'  => __( 'The upsells first, then products of the same category with the nearest price. Tick the box in "What the summary shows".', 'pergel-engine' ),
				),
				'also_title'         => array(
					'label'       => __( '"You may also like": title', 'pergel-engine' ),
					'placeholder' => __( 'You may also like', 'pergel-engine' ),
				),
				'facts_rows'         => array(
					'label'   => __( 'Product facts', 'pergel-engine' ),
					'help'    => __( 'The rows of the facts box, in order. A row the product has no value for is left out.', 'pergel-engine' ),
					'items'   => 'facts',
					'choices' => array(
						'category' => __( 'Categories', 'pergel-engine' ),
						'tag'      => __( 'Tags', 'pergel-engine' ),
						'brand'    => __( 'Brands', 'pergel-engine' ),
						'sku'      => __( 'SKU', 'pergel-engine' ),
						'stock'    => __( 'Availability', 'pergel-engine' ),
						'type'     => __( 'Product type', 'pergel-engine' ),
						'weight'   => __( 'Weight', 'pergel-engine' ),
						'size'     => __( 'Dimensions', 'pergel-engine' ),
					),
				),
				'trust_image'        => array(
					'label' => __( 'Safe payment: picture', 'pergel-engine' ),
					'help'  => __( 'E.g. the logos of the cards you take, as one picture. Its alternative text is read out.', 'pergel-engine' ),
				),
				'trust_text'         => array(
					'label'       => __( 'Safe payment: text', 'pergel-engine' ),
					'help'        => __( 'Under the picture. A shop that only takes quote requests can say something else here, e.g. the warranty.', 'pergel-engine' ),
					'placeholder' => __( 'Guaranteed safe and secure checkout', 'pergel-engine' ),
				),
				'box_bg'             => array(
					'label' => __( 'Box background', 'pergel-engine' ),
					'help'  => __( 'Empty uses the surface colour.', 'pergel-engine' ),
				),
				'box_edge'           => array(
					'label' => __( 'Box edge', 'pergel-engine' ),
					'help'  => __( 'Empty draws no edge.', 'pergel-engine' ),
				),
				'box_radius'         => array(
					'label' => __( 'Box corners', 'pergel-engine' ),
					'help'  => __( 'In pixels; empty is 12.', 'pergel-engine' ),
				),
				'box_padding'        => array(
					'label' => __( 'Space inside the boxes', 'pergel-engine' ),
					'help'  => __( 'In pixels.', 'pergel-engine' ),
				),
				'product_buy_now'    => array(
					'label' => __( '"Buy now" button', 'pergel-engine' ),
					'help'  => __( 'Beside "Add to cart", and in the bar at the bottom of the screen: adds the product to the cart and goes straight to the checkout.', 'pergel-engine' ),
				),
				'product_coupons'    => array(
					'label' => __( 'Coupons', 'pergel-engine' ),
					'help'  => __( 'Coupons that apply to the product, under the add to cart button, with a button that copies the code. Choose them in Marketing → Coupons → Show on product pages.', 'pergel-engine' ),
				),
				'product_share'      => array(
					'label' => __( 'Share links', 'pergel-engine' ),
					'help'  => __( 'Links that share the product on Facebook, X, LinkedIn, WhatsApp, Pinterest and by email, and a button that copies its address. Plain links: no scripts from those sites.', 'pergel-engine' ),
				),
				'details_style'      => array(
					'label'   => __( 'Description and reviews', 'pergel-engine' ),
					'help'    => __( 'How the description, specifications, reviews and the tabs below show under the product. "Tabs" shows one section at a time; "All open" shows every section open. A category or a product can choose its own.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'template' => __( 'As in the template', 'pergel-engine' ),
						'tabs'     => __( 'Tabs', 'pergel-engine' ),
						'open'     => __( 'All open', 'pergel-engine' ),
					),
				),
				'details_split'      => array(
					'label' => __( 'Description in sections by its headings', 'pergel-engine' ),
					'help'  => __( 'Each H2 heading of the product description starts a section of its own, named after it, in place of the one Description section; with "Tabs", a tab each. Content before the first heading stays under "Description". The text stays one description in the editor. A category or a product can choose its own.', 'pergel-engine' ),
				),
				'details_phone'      => array(
					'label'   => __( 'Tabs on phones', 'pergel-engine' ),
					'help'    => __( 'With "Tabs": on narrow screens the sections open one under the other, or the tabs stay in a row that scrolls sideways.', 'pergel-engine' ),
					'choices' => array(
						'accordion' => __( 'One under the other', 'pergel-engine' ),
						'scroll'    => __( 'Tabs that scroll sideways', 'pergel-engine' ),
					),
				),
				'details_features'   => array(
					'label' => __( 'Feature list', 'pergel-engine' ),
					'help'  => __( 'A product\'s own list of features, written in the "Product page sections" box of the product, shows as "Specifications"; the table of weight, size and attributes moves to "Additional information".', 'pergel-engine' ),
				),
				'details_title'      => array(
					'label' => __( 'Extra tab: title', 'pergel-engine' ),
					'help'  => __( 'e.g. Shipping and returns. Shown on every product page, with the pattern below as its content. A category can choose its own.', 'pergel-engine' ),
				),
				'details_tab'        => array(
					'label' => __( 'Extra tab: pattern', 'pergel-engine' ),
					'help'  => __( 'Make it under Appearance → Editor → Patterns.', 'pergel-engine' ),
				),
				'details_title2'     => array(
					'label' => __( 'Second extra tab: title', 'pergel-engine' ),
				),
				'details_tab2'       => array(
					'label' => __( 'Second extra tab: pattern', 'pergel-engine' ),
				),
				'details_qa'         => array(
					'label' => __( 'Questions and answers', 'pergel-engine' ),
					'help'  => __( 'Customers who are logged in can ask a question about the product. Questions wait under Products → Questions; one shows on the product page once you answer it, and the customer gets an email.', 'pergel-engine' ),
				),
				'details_rating'     => array(
					'label' => __( 'Rating summary', 'pergel-engine' ),
					'help'  => __( 'Above the reviews: the average rating and a bar for each number of stars.', 'pergel-engine' ),
				),
				'details_specs'      => array(
					'label'   => __( 'Attributes table', 'pergel-engine' ),
					'help'    => __( 'WooCommerce\'s table of the product\'s attributes, weight and size. "Specifications" draws it as the Table block\'s Specifications style: a line under each row, the names muted. "WooCommerce classic": dotted lines, the names bold, the values in italics and every other row shaded.', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the style', 'pergel-engine' ),
						'specs'    => __( 'Specifications', 'pergel-engine' ),
						'classic'  => __( 'WooCommerce classic', 'pergel-engine' ),
					),
				),
				'details_side'       => array(
					'label'   => __( 'Column beside the sections', 'pergel-engine' ),
					'help'    => __( 'A narrow column on the right of the sections on wide screens, under them on narrow ones. Recommended products are the upsells, then products of the same category with the nearest price. Recently viewed products show once the visitor has viewed some; with both, they are two tabs.', 'pergel-engine' ),
					'choices' => array(
						'none'        => __( 'None', 'pergel-engine' ),
						'both'        => __( 'Recently viewed and recommended', 'pergel-engine' ),
						'recent'      => __( 'Recently viewed', 'pergel-engine' ),
						'recommended' => __( 'Recommended', 'pergel-engine' ),
					),
				),
				'details_side_count' => array(
					'label' => __( 'Products in the column', 'pergel-engine' ),
				),
				'details_banner'     => array(
					'label' => __( 'Banner in the column', 'pergel-engine' ),
					'help'  => __( 'A pattern under the products of the column, e.g. a campaign. It also shows without them. Make it under Appearance → Editor → Patterns.', 'pergel-engine' ),
				),
				'tab_look'           => array(
					'label'   => __( 'Tab style', 'pergel-engine' ),
					'help'    => __( 'For the "Tabs" style of the sections. "Folders" joins the open tab to a card holding its section; "Buttons" makes each tab a rounded button.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'line'   => __( 'Underline', 'pergel-engine' ),
						'folder' => __( 'Folders', 'pergel-engine' ),
						'pills'  => __( 'Buttons', 'pergel-engine' ),
					),
				),
				'tab_size'           => array(
					'label' => __( 'Tab text size', 'pergel-engine' ),
					'help'  => __( 'In pixels.', 'pergel-engine' ),
				),
				'tab_weight'         => array(
					'label'   => __( 'Tab text weight', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the template', 'pergel-engine' ),
						'400'      => __( 'Regular', 'pergel-engine' ),
						'500'      => __( 'Medium', 'pergel-engine' ),
						'600'      => __( 'Semibold', 'pergel-engine' ),
						'700'      => __( 'Bold', 'pergel-engine' ),
					),
				),
				'tab_color'          => array(
					'label' => __( 'Tab text colour', 'pergel-engine' ),
				),
				'tab_active'         => array(
					'label' => __( 'Open tab colour', 'pergel-engine' ),
					'help'  => __( 'Empty uses the accent colour.', 'pergel-engine' ),
				),
				'tab_radius'         => array(
					'label' => __( 'Tab corners', 'pergel-engine' ),
					'help'  => __( 'How round the tabs and the card are, in pixels.', 'pergel-engine' ),
				),
				'tab_panel_bg'       => array(
					'label' => __( 'Section card background', 'pergel-engine' ),
					'help'  => __( 'Behind the open section. Any card value puts the sections in a card, in every tab style.', 'pergel-engine' ),
				),
				'tab_edge_color'     => array(
					'label' => __( 'Section card edge', 'pergel-engine' ),
				),
				'tab_padding'        => array(
					'label' => __( 'Space inside the card', 'pergel-engine' ),
					'help'  => __( 'In pixels.', 'pergel-engine' ),
				),
				'tab_area_bg'        => array(
					'label' => __( 'Background around the sections', 'pergel-engine' ),
					'help'  => __( 'Behind the tabs, the card and the column beside them.', 'pergel-engine' ),
				),
				'related_style'      => array(
					'label'   => __( 'Related products', 'pergel-engine' ),
					'help'    => __( 'Products from the same categories and tags, under the product. "Carousel" puts them in one row that scrolls sideways, with arrows. A category or a product can choose its own.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'template' => __( 'As in the template', 'pergel-engine' ),
						'grid'     => __( 'Grid', 'pergel-engine' ),
						'carousel' => __( 'Carousel', 'pergel-engine' ),
					),
				),
				'related_count'      => array(
					'label' => __( 'Related products: how many', 'pergel-engine' ),
					'help'  => __( '0 keeps the number the template shows.', 'pergel-engine' ),
				),
				'related_title'      => array(
					'label' => __( 'Related products: title', 'pergel-engine' ),
					'help'  => __( 'Empty keeps the template\'s title. A category can choose its own.', 'pergel-engine' ),
				),
				'related_compare'    => array(
					'label'   => __( 'Compare with similar products', 'pergel-engine' ),
					'help'    => __( 'A table above the related products: the product beside similar ones, with their pictures, prices, ratings, stock, size, weight and attributes. Similar ones are the product\'s upsells first, then products of its category with the nearest price.', 'pergel-engine' ),
					'choices' => array(
						'off' => __( 'Off', 'pergel-engine' ),
						'2'   => __( 'With 1 similar product', 'pergel-engine' ),
						'3'   => __( 'With 2 similar products', 'pergel-engine' ),
						'4'   => __( 'With 3 similar products', 'pergel-engine' ),
					),
				),
				'related_recent'     => array(
					'label'   => __( 'Recently viewed', 'pergel-engine' ),
					'help'    => __( 'The products a visitor looked at last, kept in their browser. "Remember" only notes them, for the Recently viewed block placed anywhere; "Remember and show" also shows them under the related products.', 'pergel-engine' ),
					'choices' => array(
						'off'   => __( 'Off', 'pergel-engine' ),
						'track' => __( 'Remember', 'pergel-engine' ),
						'show'  => __( 'Remember and show', 'pergel-engine' ),
					),
				),
				'related_banner'     => array(
					'label' => __( 'Banner beside the related products', 'pergel-engine' ),
					'help'  => __( 'A pattern as the last card of the related products, in the place of one product, e.g. a brand or a campaign. Make it under Appearance → Editor → Patterns.', 'pergel-engine' ),
				),
				'related_bg'         => array(
					'label' => __( 'Box around the related products', 'pergel-engine' ),
					'help'  => __( 'A background puts the related products, their title and the banner in a box.', 'pergel-engine' ),
				),
				'related_edge'       => array(
					'label' => __( 'Box edge', 'pergel-engine' ),
				),
				'related_radius'     => array(
					'label' => __( 'Box corners', 'pergel-engine' ),
					'help'  => __( 'In pixels.', 'pergel-engine' ),
				),
				'related_padding'    => array(
					'label' => __( 'Space inside the box', 'pergel-engine' ),
					'help'  => __( 'In pixels.', 'pergel-engine' ),
				),
				'sticky_bg'          => array(
					'label' => __( 'Bar background', 'pergel-engine' ),
					'help'  => __( 'For the sticky add-to-cart bar turned on above. Empty uses the base colour.', 'pergel-engine' ),
				),
				'sticky_padding'     => array(
					'label' => __( 'Space above and below', 'pergel-engine' ),
					'help'  => __( 'In pixels.', 'pergel-engine' ),
				),
				'sticky_image'       => array(
					'label' => __( 'Picture size', 'pergel-engine' ),
					'help'  => __( 'In pixels; phones keep a smaller one.', 'pergel-engine' ),
				),
				'sticky_text'        => array(
					'label' => __( 'Short description in the bar', 'pergel-engine' ),
					'help'  => __( 'Two lines of it under the name, on wide screens.', 'pergel-engine' ),
				),
				'sticky_button'      => array(
					'label'   => __( 'Bar button', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the template', 'pergel-engine' ),
						'filled'   => __( 'Filled', 'pergel-engine' ),
						'outline'  => __( 'Outlined', 'pergel-engine' ),
					),
				),
				'sticky_shape'       => array(
					'label'   => __( 'Bar button shape', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the template', 'pergel-engine' ),
						'pill'     => __( 'Pill', 'pergel-engine' ),
						'rounded'  => __( 'Rounded', 'pergel-engine' ),
						'square'   => __( 'Square', 'pergel-engine' ),
					),
				),
				'card_swatches'      => array(
					'label' => __( 'Colours on cards', 'pergel-engine' ),
					'help'  => __( 'For products with options: the colours or pictures of the first attribute with the "Color / image" type, under the name. Choosing one shows that option\'s picture on the card.', 'pergel-engine' ),
				),
				'card_actions'       => array(
					'label'   => __( 'Wishlist and compare on cards', 'pergel-engine' ),
					'help'    => __( 'When the wishlist or compare is on. "Text links" puts "Add to compare" and "Add to wishlist" under the card\'s button.', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'Round icon buttons', 'pergel-engine' ),
						'links'    => __( 'Text links', 'pergel-engine' ),
					),
				),
				'card_items'         => array(
					'label'   => __( 'What a card shows', 'pergel-engine' ),
					'help'    => __( 'Tick what each card shows and put it in order with the arrows. An item the template keeps in another group, such as the picture in some themes, stays in that group. A ticked item the template lacks is added.', 'pergel-engine' ),
					'items'   => 'card',
					'choices' => array(
						'image'   => __( 'Picture', 'pergel-engine' ),
						'title'   => __( 'Product name', 'pergel-engine' ),
						'rating'  => __( 'Rating', 'pergel-engine' ),
						'price'   => __( 'Price', 'pergel-engine' ),
						'excerpt' => __( 'Short description', 'pergel-engine' ),
						'sku'     => __( 'SKU', 'pergel-engine' ),
						'stock'   => __( 'Stock', 'pergel-engine' ),
						'button'  => __( 'Button', 'pergel-engine' ),
						'actions' => __( 'Wishlist and compare buttons', 'pergel-engine' ),
					),
				),
				'card_quote'         => array(
					'label' => __( '"Request a quote" on cards', 'pergel-engine' ),
					'help'  => __( 'For products sold on request (Quote sales mode): the card button says "Request a quote" and opens the quote form on the product page, instead of "Read more".', 'pergel-engine' ),
				),
				'card_gap'           => array(
					'label' => __( 'Space between cards', 'pergel-engine' ),
					'help'  => __( 'In pixels. Empty keeps the space of the template.', 'pergel-engine' ),
				),
				'card_bg'            => array(
					'label' => __( 'Card background', 'pergel-engine' ),
				),
				'card_border'        => array(
					'label' => __( 'Card edge', 'pergel-engine' ),
					'help'  => __( 'The width of the line around each card, in pixels; 0 for none.', 'pergel-engine' ),
				),
				'card_border_color'  => array(
					'label' => __( 'Edge colour', 'pergel-engine' ),
				),
				'card_radius'        => array(
					'label' => __( 'Card corners', 'pergel-engine' ),
					'help'  => __( 'How round the corners are, in pixels; 0 for square.', 'pergel-engine' ),
				),
				'card_padding'       => array(
					'label' => __( 'Space inside the card', 'pergel-engine' ),
					'help'  => __( 'Between the edge and the picture and text, in pixels.', 'pergel-engine' ),
				),
				'card_hover_look'    => array(
					'label'   => __( 'Pointer over a card', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the card style', 'pergel-engine' ),
						'none'     => __( 'No change', 'pergel-engine' ),
						'shadow'   => __( 'Shadow', 'pergel-engine' ),
						'accent'   => __( 'Accent edge and shadow', 'pergel-engine' ),
						'lift'     => __( 'Lifts up', 'pergel-engine' ),
					),
				),
				'card_image_bg'      => array(
					'label' => __( 'Picture background', 'pergel-engine' ),
					'help'  => __( 'Behind product pictures with an empty or white background.', 'pergel-engine' ),
				),
				'card_image_radius'  => array(
					'label' => __( 'Picture corners', 'pergel-engine' ),
				),
				'card_align'         => array(
					'label'   => __( 'Text alignment', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the card style', 'pergel-engine' ),
						'start'    => __( 'Left', 'pergel-engine' ),
						'center'   => __( 'Centred', 'pergel-engine' ),
					),
				),
				'card_title_size'    => array(
					'label' => __( 'Name size', 'pergel-engine' ),
					'help'  => __( 'The font size of the product name, in pixels.', 'pergel-engine' ),
				),
				'card_title_weight'  => array(
					'label'   => __( 'Name weight', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the card style', 'pergel-engine' ),
						'400'      => __( 'Regular', 'pergel-engine' ),
						'500'      => __( 'Medium', 'pergel-engine' ),
						'600'      => __( 'Semibold', 'pergel-engine' ),
						'700'      => __( 'Bold', 'pergel-engine' ),
					),
				),
				'card_title_lines'   => array(
					'label'   => __( 'Product name lines', 'pergel-engine' ),
					'help'    => __( 'A longer name ends with "…".', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As many as it needs', 'pergel-engine' ),
						'1'        => __( 'One', 'pergel-engine' ),
						'2'        => __( 'Two', 'pergel-engine' ),
						'3'        => __( 'Three', 'pergel-engine' ),
					),
				),
				'card_meta_color'    => array(
					'label' => __( 'Category and brand colour', 'pergel-engine' ),
				),
				'card_button'        => array(
					'label'   => __( 'Button', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the card style', 'pergel-engine' ),
						'filled'   => __( 'Filled', 'pergel-engine' ),
						'outline'  => __( 'Outlined', 'pergel-engine' ),
						'text'     => __( 'Text link', 'pergel-engine' ),
					),
				),
				'card_button_color'  => array(
					'label' => __( 'Button colour', 'pergel-engine' ),
					'help'  => __( 'The fill of a filled button, the line and text of an outlined one. Empty uses the accent colour.', 'pergel-engine' ),
				),
				'card_button_shape'  => array(
					'label'   => __( 'Button shape', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the card style', 'pergel-engine' ),
						'pill'     => __( 'Pill', 'pergel-engine' ),
						'rounded'  => __( 'Rounded', 'pergel-engine' ),
						'square'   => __( 'Square', 'pergel-engine' ),
					),
				),
				'card_button_width'  => array(
					'label'   => __( 'Button width', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the card style', 'pergel-engine' ),
						'full'     => __( 'Across the card', 'pergel-engine' ),
						'auto'     => __( 'As wide as its text', 'pergel-engine' ),
					),
				),
				'card_button_height' => array(
					'label' => __( 'Button height', 'pergel-engine' ),
					'help'  => __( 'In pixels.', 'pergel-engine' ),
				),
				'filter_bg'          => array(
					'label' => __( 'Filter box background', 'pergel-engine' ),
					'help'  => __( 'On wide screens, where the filters sit beside the products.', 'pergel-engine' ),
				),
				'filter_edge'        => array(
					'label' => __( 'Filter box edge', 'pergel-engine' ),
					'help'  => __( 'The width of the line around the filters, in pixels; 0 for none.', 'pergel-engine' ),
				),
				'filter_edge_color'  => array(
					'label' => __( 'Filter box edge colour', 'pergel-engine' ),
				),
				'filter_radius'      => array(
					'label' => __( 'Filter box corners', 'pergel-engine' ),
					'help'  => __( 'In pixels.', 'pergel-engine' ),
				),
				'filter_padding'     => array(
					'label' => __( 'Space inside the filter box', 'pergel-engine' ),
					'help'  => __( 'In pixels.', 'pergel-engine' ),
				),
				'filter_head_size'   => array(
					'label' => __( 'Filter heading size', 'pergel-engine' ),
					'help'  => __( 'The headings of the filters (Category, Price, ...), in pixels.', 'pergel-engine' ),
				),
				'filter_head_weight' => array(
					'label'   => __( 'Filter heading weight', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the template', 'pergel-engine' ),
						'400'      => __( 'Regular', 'pergel-engine' ),
						'500'      => __( 'Medium', 'pergel-engine' ),
						'600'      => __( 'Semibold', 'pergel-engine' ),
						'700'      => __( 'Bold', 'pergel-engine' ),
					),
				),
				'filter_title_look'  => array(
					'label'   => __( 'Filter box title', 'pergel-engine' ),
					'help'    => __( 'With "A tab on the box" the title sits on the box\'s top edge like a folder tab, in the box\'s background, edge and corners. On wide screens.', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the template', 'pergel-engine' ),
						'tab'      => __( 'A tab on the box', 'pergel-engine' ),
					),
				),
				'filter_divider'     => array(
					'label'   => __( 'Between the filters', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the template', 'pergel-engine' ),
						'none'     => __( 'Space only', 'pergel-engine' ),
						'line'     => __( 'A line', 'pergel-engine' ),
					),
				),
				'filter_text_size'   => array(
					'label' => __( 'Option text size', 'pergel-engine' ),
					'help'  => __( 'The options of each filter, in pixels.', 'pergel-engine' ),
				),
				'filter_accent'      => array(
					'label' => __( 'Filter colour', 'pergel-engine' ),
					'help'  => __( 'Ticked boxes, the price slider and the chosen filters above the list. Empty uses the accent colour.', 'pergel-engine' ),
				),
				'filter_input_size'  => array(
					'label' => __( 'Filter search box height', 'pergel-engine' ),
					'help'  => __( 'The box that narrows down a long list, in pixels. Its corners follow "Menu and button corners".', 'pergel-engine' ),
				),
				'filter_input_edge'  => array(
					'label' => __( 'Filter search box edge', 'pergel-engine' ),
					'help'  => __( 'In pixels; 0 for none.', 'pergel-engine' ),
				),
				'filter_input_color' => array(
					'label' => __( 'Filter search box edge colour', 'pergel-engine' ),
				),
				'filter_counts'      => array(
					'label'   => __( 'Product counts', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'Beside the name', 'pergel-engine' ),
						'right'    => __( 'At the end of the row', 'pergel-engine' ),
					),
				),
				'filter_swatch'      => array(
					'label' => __( 'Colour dot size', 'pergel-engine' ),
					'help'  => __( 'The dots of colour attributes (attributes of the "Visual" type with a colour for each term), in pixels.', 'pergel-engine' ),
				),
				'filter_star_size'   => array(
					'label' => __( 'Star size in the rating filter', 'pergel-engine' ),
					'help'  => __( 'In pixels.', 'pergel-engine' ),
				),
				'filter_star_color'  => array(
					'label' => __( 'Star colour in the rating filter', 'pergel-engine' ),
					'help'  => __( 'Empty keeps the text colour.', 'pergel-engine' ),
				),
				'toolbar_bg'         => array(
					'label' => __( 'Toolbar background', 'pergel-engine' ),
					'help'  => __( 'The row above the products with the result count and the sorting.', 'pergel-engine' ),
				),
				'toolbar_edge'       => array(
					'label' => __( 'Toolbar edge', 'pergel-engine' ),
					'help'  => __( 'The width of the line around the toolbar, in pixels; 0 for none.', 'pergel-engine' ),
				),
				'toolbar_edge_color' => array(
					'label' => __( 'Toolbar edge colour', 'pergel-engine' ),
				),
				'toolbar_radius'     => array(
					'label' => __( 'Toolbar corners', 'pergel-engine' ),
					'help'  => __( 'In pixels.', 'pergel-engine' ),
				),
				'toolbar_padding'    => array(
					'label' => __( 'Space inside the toolbar', 'pergel-engine' ),
					'help'  => __( 'In pixels.', 'pergel-engine' ),
				),
				'control_radius'     => array(
					'label' => __( 'Menu and button corners', 'pergel-engine' ),
					'help'  => __( 'The sorting and products per page menus, the grid and list buttons, a search box in the toolbar and the filter search box, in pixels.', 'pergel-engine' ),
				),
				'control_height'     => array(
					'label' => __( 'Menu and button height', 'pergel-engine' ),
					'help'  => __( 'The sorting and products per page menus, the grid and list buttons and a search box in the toolbar, in pixels.', 'pergel-engine' ),
				),
				'control_edge'       => array(
					'label' => __( 'Menu and button edge', 'pergel-engine' ),
					'help'  => __( 'In pixels; 0 for none.', 'pergel-engine' ),
				),
				'control_edge_color' => array(
					'label' => __( 'Menu and button edge colour', 'pergel-engine' ),
				),
				'paging_shape'       => array(
					'label'   => __( 'Page number shape', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the template', 'pergel-engine' ),
						'square'   => __( 'Square', 'pergel-engine' ),
						'rounded'  => __( 'Rounded', 'pergel-engine' ),
						'circle'   => __( 'Circle', 'pergel-engine' ),
					),
				),
				'paging_size'        => array(
					'label' => __( 'Page number size', 'pergel-engine' ),
					'help'  => __( 'The width and height of each page number, in pixels.', 'pergel-engine' ),
				),
				'paging_bg'          => array(
					'label' => __( 'Page number background', 'pergel-engine' ),
				),
				'paging_current'     => array(
					'label' => __( 'Current page colour', 'pergel-engine' ),
					'help'  => __( 'Empty uses the accent colour.', 'pergel-engine' ),
				),
				'header_type'        => array(
					'label'   => __( 'Header', 'pergel-engine' ),
					'help'    => __( 'The store header has a top bar, a row with the logo, a product search with categories, contact, account and cart, and a bar with the menu; each part is set below. The theme\'s header is the Header template part under Appearance → Editor. A sticky header stays sticky.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'theme' => __( 'The theme\'s header', 'pergel-engine' ),
						'store' => __( 'Store header', 'pergel-engine' ),
					),
				),
				'header_on'          => array(
					'label'   => __( 'Show the store header on', 'pergel-engine' ),
					'help'    => __( 'Shop pages are the shop, product categories, products, the cart, the checkout and the account. Other pages keep the theme\'s header.', 'pergel-engine' ),
					'choices' => array(
						'shop' => __( 'Shop pages', 'pergel-engine' ),
						'site' => __( 'Every page', 'pergel-engine' ),
					),
				),
				'header_top'         => array(
					'label' => __( 'Show the top bar', 'pergel-engine' ),
					'help'  => __( 'A thin bar above the header row. It is hidden on phones.', 'pergel-engine' ),
				),
				'header_top_items'   => array(
					'label'   => __( 'What the top bar shows', 'pergel-engine' ),
					'help'    => __( 'Tick what the bar shows and put it in order with the arrows. The e-mail address, phone number and address come from the Brand tab; the menu sits on the right.', 'pergel-engine' ),
					'items'   => 'top',
					'choices' => array(
						'text'    => __( 'Announcement', 'pergel-engine' ),
						'email'   => __( 'E-mail address', 'pergel-engine' ),
						'phone'   => __( 'Phone number', 'pergel-engine' ),
						'address' => __( 'Address', 'pergel-engine' ),
						'menu'    => __( 'Menu', 'pergel-engine' ),
					),
				),
				'header_text'        => array(
					'label' => __( 'Announcement', 'pergel-engine' ),
					'help'  => __( 'Empty shows the site tagline.', 'pergel-engine' ),
				),
				'header_top_menu'    => array(
					'label' => __( 'Top bar menu', 'pergel-engine' ),
					'help'  => __( 'A menu made under Appearance → Editor → Navigation, e.g. with links to delivery and order tracking.', 'pergel-engine' ),
				),
				'header_items'       => array(
					'label'   => __( 'What the header row shows', 'pergel-engine' ),
					'help'    => __( 'Tick what the row shows and put it in order with the arrows. Without the menu bar, the menu follows the logo. The cart shows where something sells, and not on the cart and checkout pages.', 'pergel-engine' ),
					'items'   => 'row',
					'choices' => array(
						'logo'    => __( 'Logo', 'pergel-engine' ),
						'search'  => __( 'Product search', 'pergel-engine' ),
						'contact' => __( 'Phone number', 'pergel-engine' ),
						'account' => __( 'Account', 'pergel-engine' ),
						'lists'   => __( 'Wishlist and compare', 'pergel-engine' ),
						'cart'    => __( 'Mini cart', 'pergel-engine' ),
						'button'  => __( 'Button', 'pergel-engine' ),
					),
				),
				'header_categories'  => array(
					'label' => __( 'Category list in the search', 'pergel-engine' ),
					'help'  => __( 'Visitors can search one top-level product category.', 'pergel-engine' ),
				),
				'header_call'        => array(
					'label' => __( 'Text above the phone number', 'pergel-engine' ),
					'help'  => __( 'Empty shows "Call us". The number comes from the Brand tab.', 'pergel-engine' ),
				),
				'header_button'      => array(
					'label' => __( 'Button text', 'pergel-engine' ),
					'help'  => __( 'Empty shows "Contact us". The button opens the contact page set under General → Product enquiries, and shows only with one.', 'pergel-engine' ),
				),
				'header_bar'         => array(
					'label' => __( 'Put the menu on its own bar', 'pergel-engine' ),
					'help'  => __( 'A coloured bar under the header row. Off puts the menu after the logo.', 'pergel-engine' ),
				),
				'header_menu'        => array(
					'label' => __( 'Menu', 'pergel-engine' ),
					'help'  => __( 'Empty shows the site\'s menu, as the theme\'s headers do.', 'pergel-engine' ),
					'none'  => __( 'The site\'s menu', 'pergel-engine' ),
				),
				'header_bar_text'    => array(
					'label' => __( 'Link on the right of the bar', 'pergel-engine' ),
					'help'  => __( 'E.g. "Today\'s deals". It shows with a link address.', 'pergel-engine' ),
				),
				'header_bar_url'     => array(
					'label' => __( 'Link address', 'pergel-engine' ),
				),
				'header_top_bg'      => array(
					'label' => __( 'Top bar background', 'pergel-engine' ),
					'help'  => __( 'Empty uses the dark text colour.', 'pergel-engine' ),
				),
				'header_top_color'   => array(
					'label' => __( 'Top bar text', 'pergel-engine' ),
					'help'  => __( 'Empty uses the background colour.', 'pergel-engine' ),
				),
				'header_bg'          => array(
					'label' => __( 'Header row background', 'pergel-engine' ),
				),
				'header_padding'     => array(
					'label' => __( 'Space above and below the header row', 'pergel-engine' ),
					'help'  => __( 'In pixels.', 'pergel-engine' ),
				),
				'search_radius'      => array(
					'label' => __( 'Search box corners', 'pergel-engine' ),
					'help'  => __( 'How round the corners of the field, the category list and the button are, in pixels.', 'pergel-engine' ),
				),
				'search_button'      => array(
					'label' => __( 'Search button colour', 'pergel-engine' ),
					'help'  => __( 'Empty uses the dark text colour.', 'pergel-engine' ),
				),
				'header_bar_bg'      => array(
					'label' => __( 'Menu bar background', 'pergel-engine' ),
					'help'  => __( 'Empty uses the accent colour.', 'pergel-engine' ),
				),
				'header_bar_color'   => array(
					'label' => __( 'Menu bar text', 'pergel-engine' ),
					'help'  => __( 'Empty uses the text colour for the accent colour.', 'pergel-engine' ),
				),
				'header_bar_height'  => array(
					'label' => __( 'Menu bar height', 'pergel-engine' ),
					'help'  => __( 'In pixels.', 'pergel-engine' ),
				),
				'picture_bg'         => array(
					'label' => __( 'Picture background', 'pergel-engine' ),
					'help'  => __( 'Behind the large picture, seen around pictures with an empty background.', 'pergel-engine' ),
				),
				'picture_edge'       => array(
					'label' => __( 'Picture edge', 'pergel-engine' ),
					'help'  => __( 'The width of the line around the large picture, in pixels; 0 for none.', 'pergel-engine' ),
				),
				'picture_edge_color' => array(
					'label' => __( 'Picture edge colour', 'pergel-engine' ),
				),
				'picture_radius'     => array(
					'label' => __( 'Picture corners', 'pergel-engine' ),
					'help'  => __( 'In pixels.', 'pergel-engine' ),
				),
				'thumb_radius'       => array(
					'label' => __( 'Small picture corners', 'pergel-engine' ),
					'help'  => __( 'In pixels.', 'pergel-engine' ),
				),
				'thumb_active'       => array(
					'label' => __( 'Chosen small picture', 'pergel-engine' ),
					'help'  => __( 'A line in this colour around the small picture shown large.', 'pergel-engine' ),
				),
				'title_size'         => array(
					'label' => __( 'Product name size', 'pergel-engine' ),
					'help'  => __( 'In pixels.', 'pergel-engine' ),
				),
				'title_weight'       => array(
					'label'   => __( 'Product name weight', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the template', 'pergel-engine' ),
						'400'      => __( 'Regular', 'pergel-engine' ),
						'500'      => __( 'Medium', 'pergel-engine' ),
						'600'      => __( 'Semibold', 'pergel-engine' ),
						'700'      => __( 'Bold', 'pergel-engine' ),
					),
				),
				'price_size'         => array(
					'label' => __( 'Price size', 'pergel-engine' ),
					'help'  => __( 'In pixels.', 'pergel-engine' ),
				),
				'price_weight'       => array(
					'label'   => __( 'Price weight', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the template', 'pergel-engine' ),
						'400'      => __( 'Regular', 'pergel-engine' ),
						'500'      => __( 'Medium', 'pergel-engine' ),
						'600'      => __( 'Semibold', 'pergel-engine' ),
						'700'      => __( 'Bold', 'pergel-engine' ),
					),
				),
				'price_color'        => array(
					'label' => __( 'Price colour', 'pergel-engine' ),
				),
				'buy_look'           => array(
					'label'   => __( 'Add to cart button', 'pergel-engine' ),
					'help'    => __( 'Buy now keeps its own look; the shape and height below are for both buttons.', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the template', 'pergel-engine' ),
						'filled'   => __( 'Filled', 'pergel-engine' ),
						'outline'  => __( 'Outlined', 'pergel-engine' ),
					),
				),
				'buy_color'          => array(
					'label' => __( 'Add to cart colour', 'pergel-engine' ),
					'help'  => __( 'Empty uses the accent colour.', 'pergel-engine' ),
				),
				'buy_shape'          => array(
					'label'   => __( 'Buying button shape', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the template', 'pergel-engine' ),
						'pill'     => __( 'Pill', 'pergel-engine' ),
						'rounded'  => __( 'Rounded', 'pergel-engine' ),
						'square'   => __( 'Square', 'pergel-engine' ),
					),
				),
				'buy_height'         => array(
					'label' => __( 'Buying button height', 'pergel-engine' ),
					'help'  => __( 'In pixels; the quantity box takes the same height.', 'pergel-engine' ),
				),
				'qty_shape'          => array(
					'label'   => __( 'Quantity box shape', 'pergel-engine' ),
					'choices' => array(
						'template' => __( 'As the template', 'pergel-engine' ),
						'pill'     => __( 'Pill', 'pergel-engine' ),
						'rounded'  => __( 'Rounded', 'pergel-engine' ),
						'square'   => __( 'Square', 'pergel-engine' ),
					),
				),
			),
		);
	}

	/**
	 * Text of the "inherit" choice on a level's own settings.
	 *
	 * @param string $level "category" or "product".
	 */
	public static function inherit( string $level ): string {
		return 'product' === $level
			? __( 'Same as its category or the shop', 'pergel-engine' )
			: __( 'Same as the parent category or the shop', 'pergel-engine' );
	}

	/**
	 * Message for a schema error code.
	 *
	 * @param string $code Error code from Schema::field().
	 */
	public static function problem( string $code ): string {
		return match ( $code ) {
			'too_long'        => __( 'The value is too long.', 'pergel-engine' ),
			'invalid_email'   => __( 'Enter a valid email address.', 'pergel-engine' ),
			'invalid_url'     => __( 'Enter full web addresses that start with https:// or http://.', 'pergel-engine' ),
			'invalid_tel'     => __( 'Use digits, spaces, + ( ) - and . only.', 'pergel-engine' ),
			'invalid_country' => __( 'Use a two-letter country code such as TR.', 'pergel-engine' ),
			'invalid_choice'  => __( 'Choose one of the listed options.', 'pergel-engine' ),
			'invalid_color'   => __( 'Choose a colour from the list or a custom colour.', 'pergel-engine' ),
			'out_of_range'    => __( 'The number is out of range.', 'pergel-engine' ),
			'too_many'        => __( 'There are too many items.', 'pergel-engine' ),
			'invalid_time'    => __( 'Enter a time such as 15:00.', 'pergel-engine' ),
			'invalid_date'    => __( 'Write each date as 2026-10-29, or a range as 2026-03-19/2026-03-22.', 'pergel-engine' ),
			'svg'             => __( 'Social networks do not show SVG images. Choose a JPG, PNG or WebP image.', 'pergel-engine' ),
			default           => __( 'The value has the wrong type.', 'pergel-engine' ),
		};
	}
}
