=== Pergel Engine ===
Contributors: ariumlab
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.4.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Blocks, SEO and AI layers, WooCommerce features and content types for the Pergel theme.

== Description ==

Pergel Engine holds everything that must survive a theme change. Modules load
only when they apply to the current request: CLI code only under WP-CLI,
admin screens only in wp-admin, WooCommerce code only when WooCommerce is
active.

When an SEO plugin (Rank Math, Yoast SEO, All in One SEO, SEOPress, The SEO
Framework) is active, Pergel defers to it and adds its own data to that
plugin's schema graph instead of printing a second one.

Deactivating the plugin keeps its data. Deleting it under Plugins removes its
settings, tables, scheduled events, form messages, product questions and
the meta it stored (flash sale prices go back first);
pages, products, menus, terms and media stay. Add
`define( 'PERGEL_KEEP_DATA', true );` to wp-config.php to keep everything.

== Changelog ==

= 1.4.7 =
* Shop filters: new setting "Order of the options" (Shop pages → Filters): the store's own order, by name or most products first, for the category, brand and attribute filters. A list whose options all show no longer gets a More button.

= 1.4.6 =
* Shop filters: new setting "Attribute filters" (Shop pages → Filters) chooses which product attributes the filter box shows, in which order, instead of the store's first three.

= 1.4.5 =
* Shop filters: child categories sit under their parent in the category filter. New setting "Subcategories start closed" (Shop pages → Filters) puts them under an arrow on their parent. After "Clear filters" a long list no longer shows two search boxes and two More buttons. A long list shows its first rows with the More button below them, not over a half-faded row.
* Shop filters: a filter with one option that every product has, such as a store's only brand, is left out. New setting "Availability filter" turns the stock filter off.
* Shop toolbar: no products per page menu while products load as the visitor scrolls.

= 1.4.4 =
* Product page: "Keep the details in view" keeps the shorter column in view. When the details are longer than the pictures, the pictures stay on screen instead of leaving empty space under them.

= 1.4.3 =
* Project packages (format 4): a media.yaml entry with "library: true" puts the file in the media library even when no field uses it, so a theme can find it by name; "poster" gives a video its featured image. Packages may carry short mp4 or webm clips this way (at most 8 MB, checked by content). Export writes them back.

= 1.4.2 =
* Product page: the attributes table can also take the look of WooCommerce's classic table (Shop settings, "Under the product" → "Attributes table" → "WooCommerce classic"): dotted row lines, bold names, values in italics, every other row shaded. The Unimart preset chooses it.

= 1.4.1 =
* Product page: the attributes table (weight, size and the product's attributes) can take the Table block's Specifications style (Shop settings, "Under the product" → "Attributes table"). Off by default.
* Project packages: a child theme's own picture left in its pattern (an icon from the child theme's folder) is no longer reported as sample text; a value still replaces it.

= 1.4.0 =
* Flash sale campaigns (Products → Flash sales; off until Pergel → Settings → Shop → Flash sales turns them on): a start, an end, a discount and the products or categories. While a campaign runs, its price is written as WooCommerce's own sale price and the earlier one comes back at the end. A box with a countdown on the product page, a card badge, a band on shop pages, a list page, a "Flash sale list" query and a "Running flash sale" block. Products without a price (quote or catalogue mode) are left out.
* "Request a quote" on product cards for products in quote mode; it opens the product page's quote form.
* The Shop settings are pages with a menu on the left, and a Preview button shows unsaved changes on a real page beside the settings.
* Look settings, each one "as the style" by default: product cards (background, edge, corners, padding, hover, picture, name, category, button), the filter box (title text and icon, a tab on the box, groups, search box, counts, colour dots, stars, a "More" button for long lists), the toolbar menus and buttons, page numbers, the category strip (tiles in a row, square pictures) and the product page (pictures, name, price, buying, tabs).
* Choose and order what product cards and the product summary show; new summary boxes: "You may also like", a list of product facts and a secure-checkout box.
* A store header in place of the theme's header (search with categories, account, wishlist, compare, cart, a button and a menu bar).
* Under the product: the description can be split into a tab per H2 heading, a column beside the sections (recently viewed, recommended, a banner), a banner beside the related products and the look of the sticky bar.
* Wishlist and compare buttons on cards can be text links under the card button.
* SEO: pages Pergel keeps out of search results (ended flash sales, shop pages with a visitor's own products-per-page) now say "noindex" in the robots tag of Rank Math, All in One SEO, The SEO Framework and SEOPress too.
* Deleting the plugin also removes the shop layout, badge, section, coupon and question data.

= 1.3.0 =
* Shop layout settings (Pergel → Settings → Shop layout), each one off or "as in the template" by default, most of them also for a product category or a single product: columns per screen size, products per page and page width; the filter area on the left, right or in a panel, sticky, with folding groups, a search in long lists and brand and rating filters; a grid / list switch, a products-per-page menu, "Load more" or loading on scroll; a banner pattern and a strip of subcategories above the products.
* Product cards: card styles, category, brand, rating, discount and stock pills, badges (sale as a percentage, new, featured, sold out, a product's own text), the second picture on hover, a sale countdown, a stock bar, quick view, colour swatches. New block: Category cards.
* Product pages: pictures on the left, on the right or in three columns, small pictures beside, under or in a grid, details that stay in view, zoom, badges, category, brand, discount and stock beside the pictures, a campaign pattern, previous and next product; "Buy now", coupons chosen for product pages with a copy button, share links.
* Under the product: tabs or all sections open, a product's own feature list and tab, two pattern tabs, questions and answers from logged-in customers (Products → Questions, with an email when answered) and a rating summary.
* Related products as a grid or carousel, their number and title; a comparison with up to three similar products; recently viewed products, kept in the visitor's browser. New block: Recently viewed products.
* A bar of shop shortcuts at the bottom of phones, and free shipping progress in the mini cart, the cart, the checkout and on product pages.
* "Unimart layout": one button that previews and applies all of the above as one arrangement, with Undo.
* Project packages: content files can use the child theme's own patterns, and an import writes the SEO title and description into the active SEO plugin too (Rank Math, Yoast, SEOPress, The SEO Framework, All in One SEO).
* Fixed: review stars showed as letters while the classic WooCommerce styles were left out.

= 1.2.2 =
* Project packages: `logo` sets the Site Logo (package format 3). A logo may be an SVG file when the site takes SVG uploads (the Safe SVG plugin cleans it; run WP-CLI as an administrator). An export writes the logo back when the package made it.
* A package child theme takes the style rules of its brand tokens (such as uppercase buttons), as style variations do.
* New brand setting "The logo shows the name": hides the site title next to the Site Logo in the header.

= 1.2.1 =
* Project packages: a section can set its width with `align: wide`, `full` or `none`, as the editor's width control does. An export writes it back for a section whose width differs from its pattern.

= 1.2.0 =
* Form block: up to ten extra fields of your own (short text, long text, phone, list), sent with the message under their labels. The message field can be made optional, for a quote form whose own fields already say what is needed.
* Project packages: `adopt: true` takes over a page that already sits at a package address, keeping its ID, comments and links; without content the page keeps its own. A `form` field sets the form block from the package, and Markdown headings take anchors (`## Warranty {#warranty}`).
* Root-relative links in menus, Markdown and buttons use the site address, so a package works on a site in a subdirectory.
* Fixed: importing over an existing page or shop page no longer empties its content, and an attachment at a page address is reported instead of moving the page to a new address. A required question no longer gets a second full stop in its error message.

= 1.1.0 =
* Product pages built from sections: an FAQ section adds FAQPage schema and a specifications table adds the product's additionalProperty values, both joined to the SEO plugin's graph when one is active.
* Project packages, format 2: `products/<SKU>.md` fills an existing product found by its SKU (description, template, and optionally the name, short description, price and SEO fields). A package never creates a product, and `--prune` releases one instead of deleting it. `wp pergel export` writes the products a package owns.
* Package fields: tables (`head` and `rows`) and file links.

= 1.0.0 =
* First stable release.
* 23 blocks: breadcrumb, carousel, counter, form, logo marquee, map, mega menu, pricing table, table of contents, timeline and WooCommerce blocks (bought together, delivery estimate, size guide, sticky add to cart, stock alert and more).
* Brand settings with Block Bindings, SEO output that defers to Rank Math, Yoast SEO, All in One SEO, SEOPress and The SEO Framework, and a performance module.
* Own search index: prefix matching with Turkish folding, products with their SKUs and attributes, live suggestions on the Search block.
* WooCommerce: sales modes per product or category (sale, quote, catalog, prices after login), wishlists and compare lists, back-in-stock e-mails, swatches and a mini cart.
* Forms with an inbox and spam limits; corporate and shop starter sites; project packages imported with `wp pergel import`; WordPress Abilities for AI clients.
* `wp pergel` commands for settings, modules, starters, search, packages and patterns.
* Deleting the plugin removes its data; `PERGEL_KEEP_DATA` keeps it.
* Turkish translation included.

= 0.1.0 =
* Initial skeleton: module registry and `wp pergel info`.
