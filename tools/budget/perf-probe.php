<?php
/**
 * Pergel performance probe (development and CI only, never shipped).
 *
 * Both tools/dev/link.ps1 and tools/ci/setup-wp.sh load this file as a
 * must-use plugin. It does nothing unless the request carries one of its
 * headers:
 *
 * - X-Pergel-Perf: at shutdown, appends one JSON line with the request's
 *   server-side cost to PERGEL_PERF_LOG (default wp-content/pergel-perf.jsonl).
 *   tools/budget/measure.mjs reads that log.
 * - X-Pergel-Variation: <slug>: renders the page with styles/<slug>.json
 *   applied, without saving anything, so each style variation can be measured.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Dev;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- dev-only probe; values are written to a local log, never output.

if ( ! empty( $_SERVER['HTTP_X_PERGEL_VARIATION'] ) ) {
	$pergel_variation = (string) preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $_SERVER['HTTP_X_PERGEL_VARIATION'] ) );
	add_filter(
		'wp_theme_json_data_theme',
		static function ( \WP_Theme_JSON_Data $data ) use ( $pergel_variation ): \WP_Theme_JSON_Data {
			$file      = get_theme_file_path( "styles/$pergel_variation.json" );
			$variation = is_file( $file ) ? wp_json_file_decode( $file, array( 'associative' => true ) ) : null;
			return is_array( $variation ) ? $data->update_with( $variation ) : $data;
		}
	);
}

if ( empty( $_SERVER['HTTP_X_PERGEL_PERF'] ) ) {
	return;
}

if ( ! defined( 'SAVEQUERIES' ) ) {
	define( 'SAVEQUERIES', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- core constant.
}

/**
 * Normalises a path for prefix comparison (slashes, case, resolved links).
 *
 * @param string $path File or directory path.
 */
function perf_norm( string $path ): string {
	$real = realpath( $path );
	return strtolower( str_replace( '\\', '/', false === $real ? $path : $real ) );
}

/**
 * Collects the request's metrics and appends them to the log.
 */
function perf_log(): void {
	global $wpdb;

	$roots = array_unique(
		array(
			perf_norm( get_template_directory() ) . '/',
			perf_norm( get_stylesheet_directory() ) . '/',
			perf_norm( WP_PLUGIN_DIR . '/pergel-engine' ) . '/',
		)
	);

	$files  = get_included_files();
	$pergel = 0;
	foreach ( $files as $file ) {
		$file = perf_norm( $file );
		foreach ( $roots as $root ) {
			if ( str_starts_with( $file, $root ) ) {
				++$pergel;
				break;
			}
		}
	}

	$query_ms = 0.0;
	$writes   = array();
	foreach ( (array) $wpdb->queries as $query ) {
		$query_ms += (float) $query[1];
		$sql       = ltrim( (string) $query[0] );
		if ( preg_match( '/^(INSERT|UPDATE|DELETE|REPLACE)\b/i', $sql ) ) {
			$writes[] = substr( (string) preg_replace( '/\s+/', ' ', $sql ), 0, 160 );
		}
	}

	$row = array(
		'tag'          => substr( (string) $_SERVER['HTTP_X_PERGEL_PERF'], 0, 80 ),
		'uri'          => (string) ( $_SERVER['REQUEST_URI'] ?? '' ),
		'status'       => http_response_code(),
		'theme'        => get_stylesheet(),
		'ms'           => round( ( microtime( true ) - (float) $_SERVER['REQUEST_TIME_FLOAT'] ) * 1000, 1 ),
		'queries'      => (int) $wpdb->num_queries,
		'query_ms'     => round( $query_ms * 1000, 1 ),
		'writes'       => $writes,
		'files'        => count( $files ),
		'pergel_files' => $pergel,
		'peak_mb'      => round( memory_get_peak_usage( true ) / 1048576, 1 ),
	);

	$log = defined( 'PERGEL_PERF_LOG' ) ? (string) PERGEL_PERF_LOG : WP_CONTENT_DIR . '/pergel-perf.jsonl';
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- append-only dev log.
	file_put_contents( $log, wp_json_encode( $row ) . "\n", FILE_APPEND | LOCK_EX );
}
add_action( 'shutdown', __NAMESPACE__ . '\perf_log', PHP_INT_MAX );
