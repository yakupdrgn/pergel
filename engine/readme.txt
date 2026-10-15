=== Pergel Engine ===
Contributors: ariumlab
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
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

== Changelog ==

= 0.1.0 =
* Initial skeleton: module registry and `wp pergel info`.
