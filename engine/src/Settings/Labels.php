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
					'fields' => array( 'brand', 'seo', 'performance', 'forms', 'search', 'woo' ),
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
					'fields' => array( 'logo_inverse' ),
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
				'speed'     => array(
					'title'  => __( 'Speed', 'pergel-engine' ),
					'fields' => array( 'shop_assets_only', 'no_cart_fragments' ),
				),
			),
		);
	}

	/**
	 * Field labels and help texts.
	 *
	 * @return array<string, array<string, array{label: string, help?: string, svg?: string, choices?: array<string, string>}>>
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
			),
			'brand'       => array(
				'name'         => array(
					'label' => __( 'Brand name', 'pergel-engine' ),
					'help'  => __( 'The name customers know. Leave empty to use the site title.', 'pergel-engine' ),
				),
				'legal_name'   => array( 'label' => __( 'Legal name', 'pergel-engine' ) ),
				'vat_id'       => array( 'label' => __( 'Tax number', 'pergel-engine' ) ),
				'email'        => array( 'label' => __( 'Email', 'pergel-engine' ) ),
				'phone'        => array(
					'label' => __( 'Phone', 'pergel-engine' ),
					'help'  => __( 'With the country code, e.g. +90 212 000 00 00.', 'pergel-engine' ),
				),
				'street'       => array( 'label' => __( 'Street address', 'pergel-engine' ) ),
				'locality'     => array( 'label' => __( 'City or district', 'pergel-engine' ) ),
				'region'       => array( 'label' => __( 'Region or province', 'pergel-engine' ) ),
				'postal_code'  => array( 'label' => __( 'Postal code', 'pergel-engine' ) ),
				'country'      => array(
					'label' => __( 'Country code', 'pergel-engine' ),
					'help'  => __( 'Two letters (ISO 3166-1), e.g. TR.', 'pergel-engine' ),
				),
				'social'       => array(
					'label' => __( 'Profile links', 'pergel-engine' ),
					'help'  => __( 'One full address per line (LinkedIn, Instagram, YouTube, ...). Up to 10.', 'pergel-engine' ),
				),
				'logo_inverse' => array(
					'label' => __( 'Logo for dark backgrounds', 'pergel-engine' ),
					'help'  => __( 'The main logo is the Site Logo in the Site Editor.', 'pergel-engine' ),
					'svg'   => __( 'SVG uploads are off on this site. The free Safe SVG plugin turns them on and cleans every SVG file.', 'pergel-engine' ),
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
		);
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
