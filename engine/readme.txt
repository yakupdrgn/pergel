=== Pergel Engine ===
Contributors: ariumlab
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
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
settings, tables, scheduled events, form messages and the meta it stored;
pages, products, menus, terms and media stay. Add
`define( 'PERGEL_KEEP_DATA', true );` to wp-config.php to keep everything.

== Changelog ==

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
