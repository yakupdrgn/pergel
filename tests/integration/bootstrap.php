<?php
/**
 * Bootstrap for tests that need WordPress.
 *
 * Loads an installed site with Pergel active instead of the WordPress core
 * test library: that library needs its own database and does not support
 * PHPUnit 10. PERGEL_WP_DIR points at the site (CI: /tmp/pergel-wp, local:
 * C:/xampp/htdocs/pergel-tema). Tests restore whatever they change.
 *
 * @package Pergel
 */

declare( strict_types=1 );

$pergel_wp_dir = getenv( 'PERGEL_WP_DIR' );
if ( ! $pergel_wp_dir || ! is_file( "$pergel_wp_dir/wp-load.php" ) ) {
	fwrite( STDERR, "Set PERGEL_WP_DIR to a WordPress install with Pergel active.\n" );
	exit( 1 );
}

// wp-load.php expects a web request; these keep it quiet under the CLI.
$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';

require $pergel_wp_dir . '/wp-load.php';
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! class_exists( Pergel\Core\Plugin::class, false ) || ! in_array( 'settings', Pergel\Core\Plugin::loaded(), true ) ) {
	fwrite( STDERR, "Pergel Engine is not active in $pergel_wp_dir.\n" );
	exit( 1 );
}

// The classes the site loaded, before a test touched one (a test that names
// a constant loads its class; SellingTest asks what the site itself loads).
$GLOBALS['pergel_site_classes'] = get_declared_classes();
