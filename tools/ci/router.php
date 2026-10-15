<?php
/**
 * Router for PHP's built-in server in CI.
 *
 * Usage: php -S 127.0.0.1:8080 -t <wp-dir> tools/ci/router.php
 *
 * Existing files and directories are served as they are, everything else goes
 * to WordPress (pretty permalinks). Static text assets are gzipped, as every
 * production host does, so the budget's transfer sizes stay comparable.
 *
 * @package Pergel
 */

declare( strict_types=1 );

// phpcs:disable WordPress.Security.ValidatedSanitizedInput, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- dev-only router, runs outside WordPress.

$pergel_root = (string) $_SERVER['DOCUMENT_ROOT'];
$pergel_path = rawurldecode( (string) parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
$pergel_file = $pergel_root . $pergel_path;

if ( '/' !== $pergel_path && ( is_file( $pergel_file ) || is_file( rtrim( $pergel_file, '/' ) . '/index.php' ) ) ) {
	$pergel_types = array(
		'js'   => 'text/javascript',
		'mjs'  => 'text/javascript',
		'css'  => 'text/css',
		'svg'  => 'image/svg+xml',
		'json' => 'application/json',
	);
	$pergel_ext   = strtolower( pathinfo( $pergel_file, PATHINFO_EXTENSION ) );
	$pergel_gzip  = str_contains( (string) ( $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '' ), 'gzip' );
	if ( ! is_file( $pergel_file ) || ! isset( $pergel_types[ $pergel_ext ] ) || ! $pergel_gzip ) {
		return false; // The built-in server handles PHP, directories and binary files.
	}
	header( 'Content-Type: ' . $pergel_types[ $pergel_ext ] . '; charset=utf-8' );
	header( 'Content-Encoding: gzip' );
	header( 'Vary: Accept-Encoding' );
	echo gzencode( (string) file_get_contents( $pergel_file ), 6 );
	return true;
}

$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $pergel_root . '/index.php';
require $pergel_root . '/index.php';
