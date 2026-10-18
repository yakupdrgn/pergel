<?php
/**
 * Runs when the plugin is deleted under Plugins, not when it is
 * deactivated: removes what Pergel Engine stored (Core\Uninstall).
 * define( 'PERGEL_KEEP_DATA', true ) in wp-config.php keeps it.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel;

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// The plugin file is not loaded on uninstall; its autoloader, the same.
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

Core\Uninstall::run();
