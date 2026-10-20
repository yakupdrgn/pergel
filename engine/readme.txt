=== Pergel Engine ===
Contributors: ariumlab
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.2.2
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
