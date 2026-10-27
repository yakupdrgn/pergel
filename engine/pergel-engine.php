<?php
/**
 * Plugin Name:       Pergel Engine
 * Plugin URI:        https://ariumlab.com/pergel/
 * Description:       Blocks, SEO and AI layers, WooCommerce features and content types for the Pergel theme. Works with any block theme.
 * Version:           1.4.9
 * Requires at least: 7.1
 * Requires PHP:      8.1
 * Author:            Ariumlab
 * Author URI:        https://ariumlab.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pergel-engine
 * Domain Path:       /languages
 * Update URI:        false
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.4.9';
const FILE    = __FILE__;

/*
 * PSR-4 autoloader for Pergel\ => src/. One string operation and one
 * require per class; with OPcache this costs the same as a Composer
 * classmap and needs no vendor/ directory on the server.
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		if ( ! str_starts_with( $class_name, __NAMESPACE__ . '\\' ) ) {
			return;
		}
		$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( __NAMESPACE__ ) + 1 ) ) . '.php';
		if ( is_file( $file ) ) {
			require $file;
		}
	}
);

/*
 * Bundled translations (languages/). This only records the folder: WordPress
 * reads a language file the first time a string of the domain is needed.
 */
// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Not hosted on WordPress.org; the translations ship in languages/.
load_plugin_textdomain( 'pergel-engine', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

register_activation_hook( __FILE__, array( Settings\Settings::class, 'install' ) );
add_action( 'plugins_loaded', array( Core\Plugin::class, 'boot' ), 5 );
