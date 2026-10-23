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
					'title'  => __( 'Header', 'pergel-engine' ),
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
					'fields' => array( 'filters', 'drawer_side', 'filters_sticky', 'filters_fold', 'filters_search', 'filter_brand', 'filter_rating' ),
				),
				'toolbar' => array(
					'title'  => __( 'View and paging', 'pergel-engine' ),
					'fields' => array( 'view', 'view_switch', 'per_page_menu', 'paging' ),
				),
				'header'  => array(
					'title'  => __( 'Above the products', 'pergel-engine' ),
					'fields' => array( 'banner', 'category_strip' ),
				),
				'cards'   => array(
					'title'  => __( 'Product cards', 'pergel-engine' ),
					'fields' => array( 'card_style', 'card_category', 'card_brand', 'card_rating', 'card_discount', 'card_stock', 'card_hover', 'card_countdown', 'card_stock_bar', 'quick_view', 'card_swatches' ),
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
					'fields' => array( 'product_banner', 'product_category', 'product_brand', 'product_discount', 'product_stock' ),
				),
				'buying'  => array(
					'title'  => __( 'Buying', 'pergel-engine' ),
					'fields' => array( 'product_buy_now', 'product_coupons', 'product_share' ),
				),
				'details' => array(
					'title'  => __( 'Under the product', 'pergel-engine' ),
					'fields' => array( 'details_style', 'details_phone', 'details_features', 'details_title', 'details_tab', 'details_title2', 'details_tab2', 'details_qa', 'details_rating' ),
				),
				'related' => array(
					'title'  => __( 'Related and recently viewed', 'pergel-engine' ),
					'fields' => array( 'related_style', 'related_count', 'related_title', 'related_compare', 'related_recent' ),
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
				'columns'          => array(
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
				'columns_tablet'   => array(
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
				'columns_phone'    => array(
					'label'   => __( 'Products per row on phones', 'pergel-engine' ),
					'help'    => __( 'Screens narrower than 600 pixels. Two per row shows more products; one shows larger pictures.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'auto' => __( 'Automatic', 'pergel-engine' ),
						'1'    => '1',
						'2'    => '2',
					),
				),
				'per_page'         => array(
					'label' => __( 'Products per page', 'pergel-engine' ),
					'help'  => __( 'From 1 to 96. 0 uses the WooCommerce setting.', 'pergel-engine' ),
				),
				'width'            => array(
					'label'   => __( 'Page width', 'pergel-engine' ),
					'help'    => __( 'Full width uses the whole screen, with a small margin at the sides.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'wide' => __( 'As in the template', 'pergel-engine' ),
						'full' => __( 'Full width', 'pergel-engine' ),
					),
				),
				'filters'          => array(
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
				'drawer_side'      => array(
					'label'   => __( 'Panel opens from', 'pergel-engine' ),
					'help'    => __( 'The side the filter panel slides in from, on phones too.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'left'  => __( 'Left', 'pergel-engine' ),
						'right' => __( 'Right', 'pergel-engine' ),
					),
				),
				'filters_sticky'   => array(
					'label'   => __( 'While scrolling', 'pergel-engine' ),
					'help'    => __( 'With "Stays in view" the filter column stops at the top of the screen while the products scroll. "Scrolls on its own" also gives a long filter column its own scroll bar.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'off'    => __( 'Scrolls with the page', 'pergel-engine' ),
						'sticky' => __( 'Stays in view', 'pergel-engine' ),
						'scroll' => __( 'Scrolls on its own', 'pergel-engine' ),
					),
				),
				'filters_fold'     => array(
					'label' => __( 'Filter groups open and close', 'pergel-engine' ),
					'help'  => __( 'Each filter heading becomes a button that hides or shows its options. All groups start open.', 'pergel-engine' ),
				),
				'filters_search'   => array(
					'label' => __( 'Search box in long filter lists', 'pergel-engine' ),
					'help'  => __( 'Lists with more than eight options, such as categories or brands, get a box that narrows them down as the visitor types.', 'pergel-engine' ),
				),
				'filter_brand'     => array(
					'label' => __( 'Brand filter', 'pergel-engine' ),
					'help'  => __( 'Filters by the WooCommerce brands (Products → Brands). Shown only while brands have products.', 'pergel-engine' ),
				),
				'filter_rating'    => array(
					'label' => __( 'Rating filter', 'pergel-engine' ),
					'help'  => __( 'Filters by the average customer rating. Shown only while rated products exist.', 'pergel-engine' ),
				),
				'view'             => array(
					'label'   => __( 'Products are shown as', 'pergel-engine' ),
					'help'    => __( 'The list shows each product in a row, with its picture beside the name, price and short description.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'grid' => __( 'Grid', 'pergel-engine' ),
						'list' => __( 'List', 'pergel-engine' ),
					),
				),
				'view_switch'      => array(
					'label' => __( 'Grid and list buttons', 'pergel-engine' ),
					'help'  => __( 'Visitors can switch between the grid and the list next to the sorting menu. Their choice is kept in their browser.', 'pergel-engine' ),
				),
				'per_page_menu'    => array(
					'label' => __( 'Products per page menu', 'pergel-engine' ),
					'help'  => __( 'Numbers visitors can choose from next to the sorting menu, separated by commas, e.g. 12, 24, 48. Up to six numbers from 1 to 96. Leave empty for no menu.', 'pergel-engine' ),
				),
				'paging'           => array(
					'label'   => __( 'More products', 'pergel-engine' ),
					'help'    => __( '"Load more" adds the next products under the ones on screen. "Load while scrolling" does it on its own when the visitor nears the end, and keeps the button for keyboard users. The page numbers stay in the page for search engines and without JavaScript.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'template' => __( 'Page numbers', 'pergel-engine' ),
						'more'     => __( 'Load more button', 'pergel-engine' ),
						'scroll'   => __( 'Load while scrolling', 'pergel-engine' ),
					),
				),
				'banner'           => array(
					'label' => __( 'Banner', 'pergel-engine' ),
					'help'  => __( 'A pattern shown above the products, e.g. a campaign. Make it under Appearance → Editor → Patterns.', 'pergel-engine' ),
				),
				'card_style'       => array(
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
				'card_category'    => array(
					'label' => __( 'Category on cards', 'pergel-engine' ),
					'help'  => __( "The product's category above its name, as a link.", 'pergel-engine' ),
				),
				'card_brand'       => array(
					'label' => __( 'Brand on cards', 'pergel-engine' ),
					'help'  => __( "The product's brand (Products → Brands) above its name.", 'pergel-engine' ),
				),
				'card_rating'      => array(
					'label' => __( 'Rating on cards', 'pergel-engine' ),
					'help'  => __( 'Stars and the number of reviews under the name, for products with reviews.', 'pergel-engine' ),
				),
				'card_discount'    => array(
					'label' => __( 'Discount on cards', 'pergel-engine' ),
					'help'  => __( 'The discount in per cent beside the price, e.g. -15%. For products with options, the largest one.', 'pergel-engine' ),
				),
				'card_stock'       => array(
					'label' => __( 'Stock on cards', 'pergel-engine' ),
					'help'  => __( 'How many are left, for products whose stock is counted, or "Out of stock", beside the price.', 'pergel-engine' ),
				),
				'card_hover'       => array(
					'label' => __( 'Second picture', 'pergel-engine' ),
					'help'  => __( 'The first gallery picture shows while the pointer is over the card. Not on touch screens.', 'pergel-engine' ),
				),
				'card_countdown'   => array(
					'label' => __( 'Sale countdown', 'pergel-engine' ),
					'help'  => __( 'Days, hours, minutes and seconds left on the picture, for products whose sale has an end date (Product data → General → Schedule).', 'pergel-engine' ),
				),
				'card_stock_bar'   => array(
					'label' => __( 'Stock bar', 'pergel-engine' ),
					'help'  => __( 'A bar with how many were sold and how many are left, for products whose stock is counted.', 'pergel-engine' ),
				),
				'quick_view'       => array(
					'label' => __( 'Quick view', 'pergel-engine' ),
					'help'  => __( 'A button on the picture opens the product in a window: pictures, price, short description and, for simple products, the add to cart button.', 'pergel-engine' ),
				),
				'badge_sale'       => array(
					'label'   => __( 'Sale badge', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'template' => __( 'As in the template', 'pergel-engine' ),
						'percent'  => __( 'Discount in per cent', 'pergel-engine' ),
						'none'     => __( 'None', 'pergel-engine' ),
					),
				),
				'badge_new'        => array(
					'label' => __( '"New" badge, days', 'pergel-engine' ),
					'help'  => __( 'Products published in the last this many days are marked new. 0 turns the badge off.', 'pergel-engine' ),
				),
				'badge_featured'   => array(
					'label' => __( '"Popular" badge', 'pergel-engine' ),
					'help'  => __( 'On featured products (the star in Products → All products).', 'pergel-engine' ),
				),
				'badge_sold_out'   => array(
					'label' => __( '"Sold out" badge', 'pergel-engine' ),
					'help'  => __( 'On products that are out of stock. A product can also show its own badge: Product data → General → Badge.', 'pergel-engine' ),
				),
				'category_strip'   => array(
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
				'product_layout'   => array(
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
				'product_thumbs'   => array(
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
				'product_sticky'   => array(
					'label' => __( 'Keep the details in view', 'pergel-engine' ),
					'help'  => __( 'The name, price and button stay on screen while the pictures scroll past. Wide screens only.', 'pergel-engine' ),
				),
				'product_zoom'     => array(
					'label' => __( 'Zoom on hover', 'pergel-engine' ),
					'help'  => __( 'The big picture zooms in under the pointer.', 'pergel-engine' ),
				),
				'product_badges'   => array(
					'label' => __( 'Badges on the pictures', 'pergel-engine' ),
					'help'  => __( 'The badges chosen for product cards (own badge, New, Popular, Sold out) on the big picture too.', 'pergel-engine' ),
				),
				'product_nav'      => array(
					'label' => __( 'Previous and next product', 'pergel-engine' ),
					'help'  => __( 'Arrows beside the breadcrumb to the previous and next product in the same category, and a link back to the category.', 'pergel-engine' ),
				),
				'product_banner'   => array(
					'label' => __( 'Banner', 'pergel-engine' ),
					'help'  => __( 'A pattern above the product name, e.g. an offer for registered customers. Make it under Appearance → Editor → Patterns.', 'pergel-engine' ),
				),
				'product_category' => array(
					'label' => __( 'Category above the name', 'pergel-engine' ),
					'help'  => __( "The product's category as a link.", 'pergel-engine' ),
				),
				'product_brand'    => array(
					'label' => __( 'Brand', 'pergel-engine' ),
					'help'  => __( "The product's brand (Products → Brands) under its name, with the brand's picture when it has one.", 'pergel-engine' ),
				),
				'product_discount' => array(
					'label' => __( 'Discount beside the price', 'pergel-engine' ),
					'help'  => __( 'The discount in per cent, e.g. -15%. For products with options, the largest one.', 'pergel-engine' ),
				),
				'product_stock'    => array(
					'label' => __( 'Stock beside the price', 'pergel-engine' ),
					'help'  => __( 'How many are left, for products whose stock is counted, or "Out of stock".', 'pergel-engine' ),
				),
				'product_buy_now'  => array(
					'label' => __( '"Buy now" button', 'pergel-engine' ),
					'help'  => __( 'Beside "Add to cart", and in the bar at the bottom of the screen: adds the product to the cart and goes straight to the checkout.', 'pergel-engine' ),
				),
				'product_coupons'  => array(
					'label' => __( 'Coupons', 'pergel-engine' ),
					'help'  => __( 'Coupons that apply to the product, under the add to cart button, with a button that copies the code. Choose them in Marketing → Coupons → Show on product pages.', 'pergel-engine' ),
				),
				'product_share'    => array(
					'label' => __( 'Share links', 'pergel-engine' ),
					'help'  => __( 'Links that share the product on Facebook, X, LinkedIn, WhatsApp, Pinterest and by email, and a button that copies its address. Plain links: no scripts from those sites.', 'pergel-engine' ),
				),
				'details_style'    => array(
					'label'   => __( 'Description and reviews', 'pergel-engine' ),
					'help'    => __( 'How the description, specifications, reviews and the tabs below show under the product. "Tabs" shows one section at a time; "All open" shows every section open. A category or a product can choose its own.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'template' => __( 'As in the template', 'pergel-engine' ),
						'tabs'     => __( 'Tabs', 'pergel-engine' ),
						'open'     => __( 'All open', 'pergel-engine' ),
					),
				),
				'details_phone'    => array(
					'label'   => __( 'Tabs on phones', 'pergel-engine' ),
					'help'    => __( 'With "Tabs": on narrow screens the sections open one under the other, or the tabs stay in a row that scrolls sideways.', 'pergel-engine' ),
					'choices' => array(
						'accordion' => __( 'One under the other', 'pergel-engine' ),
						'scroll'    => __( 'Tabs that scroll sideways', 'pergel-engine' ),
					),
				),
				'details_features' => array(
					'label' => __( 'Feature list', 'pergel-engine' ),
					'help'  => __( 'A product\'s own list of features, written in the "Product page sections" box of the product, shows as "Specifications"; the table of weight, size and attributes moves to "Additional information".', 'pergel-engine' ),
				),
				'details_title'    => array(
					'label' => __( 'Extra tab: title', 'pergel-engine' ),
					'help'  => __( 'e.g. Shipping and returns. Shown on every product page, with the pattern below as its content. A category can choose its own.', 'pergel-engine' ),
				),
				'details_tab'      => array(
					'label' => __( 'Extra tab: pattern', 'pergel-engine' ),
					'help'  => __( 'Make it under Appearance → Editor → Patterns.', 'pergel-engine' ),
				),
				'details_title2'   => array(
					'label' => __( 'Second extra tab: title', 'pergel-engine' ),
				),
				'details_tab2'     => array(
					'label' => __( 'Second extra tab: pattern', 'pergel-engine' ),
				),
				'details_qa'       => array(
					'label' => __( 'Questions and answers', 'pergel-engine' ),
					'help'  => __( 'Customers who are logged in can ask a question about the product. Questions wait under Products → Questions; one shows on the product page once you answer it, and the customer gets an email.', 'pergel-engine' ),
				),
				'details_rating'   => array(
					'label' => __( 'Rating summary', 'pergel-engine' ),
					'help'  => __( 'Above the reviews: the average rating and a bar for each number of stars.', 'pergel-engine' ),
				),
				'related_style'    => array(
					'label'   => __( 'Related products', 'pergel-engine' ),
					'help'    => __( 'Products from the same categories and tags, under the product. "Carousel" puts them in one row that scrolls sideways, with arrows. A category or a product can choose its own.', 'pergel-engine' ),
					'cards'   => true,
					'choices' => array(
						'template' => __( 'As in the template', 'pergel-engine' ),
						'grid'     => __( 'Grid', 'pergel-engine' ),
						'carousel' => __( 'Carousel', 'pergel-engine' ),
					),
				),
				'related_count'    => array(
					'label' => __( 'Related products: how many', 'pergel-engine' ),
					'help'  => __( '0 keeps the number the template shows.', 'pergel-engine' ),
				),
				'related_title'    => array(
					'label' => __( 'Related products: title', 'pergel-engine' ),
					'help'  => __( 'Empty keeps the template\'s title. A category can choose its own.', 'pergel-engine' ),
				),
				'related_compare'  => array(
					'label'   => __( 'Compare with similar products', 'pergel-engine' ),
					'help'    => __( 'A table above the related products: the product beside similar ones, with their pictures, prices, ratings, stock, size, weight and attributes. Similar ones are the product\'s upsells first, then products of its category with the nearest price.', 'pergel-engine' ),
					'choices' => array(
						'off' => __( 'Off', 'pergel-engine' ),
						'2'   => __( 'With 1 similar product', 'pergel-engine' ),
						'3'   => __( 'With 2 similar products', 'pergel-engine' ),
						'4'   => __( 'With 3 similar products', 'pergel-engine' ),
					),
				),
				'related_recent'   => array(
					'label'   => __( 'Recently viewed', 'pergel-engine' ),
					'help'    => __( 'The products a visitor looked at last, kept in their browser. "Remember" only notes them, for the Recently viewed block placed anywhere; "Remember and show" also shows them under the related products.', 'pergel-engine' ),
					'choices' => array(
						'off'   => __( 'Off', 'pergel-engine' ),
						'track' => __( 'Remember', 'pergel-engine' ),
						'show'  => __( 'Remember and show', 'pergel-engine' ),
					),
				),
				'card_swatches'    => array(
					'label' => __( 'Colours on cards', 'pergel-engine' ),
					'help'  => __( 'For products with options: the colours or pictures of the first attribute with the "Color / image" type, under the name. Choosing one shows that option\'s picture on the card.', 'pergel-engine' ),
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
			'out_of_range'    => __( 'The number is out of range.', 'pergel-engine' ),
			'too_many'        => __( 'There are too many items.', 'pergel-engine' ),
			'invalid_time'    => __( 'Enter a time such as 15:00.', 'pergel-engine' ),
			'invalid_date'    => __( 'Write each date as 2026-10-29, or a range as 2026-03-19/2026-03-22.', 'pergel-engine' ),
			'svg'             => __( 'Social networks do not show SVG images. Choose a JPG, PNG or WebP image.', 'pergel-engine' ),
			default           => __( 'The value has the wrong type.', 'pergel-engine' ),
		};
	}
}
