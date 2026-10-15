<?php
/**
 * Checks theme/patterns/*.php against the pattern rules, without WordPress
 * (CI lint job), and counts the patterns per category.
 *
 * Usage: php tools/patterns.php [--check]
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Dev;

use Pergel\Patterns\Rules;

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require dirname( __DIR__ ) . '/engine/src/Patterns/Rules.php';

$pergel_files  = glob( dirname( __DIR__ ) . '/theme/patterns/*.php' );
$pergel_files  = false === $pergel_files ? array() : $pergel_files;
$pergel_failed = 0;
$pergel_counts = array();
$pergel_theme  = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/theme/theme.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
$pergel_preset = Rules::presets( is_array( $pergel_theme ) ? $pergel_theme : array() );

foreach ( $pergel_files as $pergel_file ) {
	$pergel_source = (string) file_get_contents( $pergel_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
	$pergel_errors = array_merge( Rules::check( basename( $pergel_file ), $pergel_source ), Rules::unknown_presets( $pergel_source, $pergel_preset ) );
	foreach ( $pergel_errors as $pergel_error ) {
		fwrite( STDERR, 'theme/patterns/' . basename( $pergel_file ) . ': ' . $pergel_error . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	}
	$pergel_failed += array() === $pergel_errors ? 0 : 1;
	$pergel_header  = Rules::header( $pergel_source );
	if ( 'no' !== strtolower( $pergel_header['Inserter'] ?? '' ) ) {
		foreach ( Rules::categories( $pergel_header ) as $pergel_category ) {
			$pergel_counts[ $pergel_category ] = ( $pergel_counts[ $pergel_category ] ?? 0 ) + 1;
		}
	}
}

ksort( $pergel_counts );
$pergel_summary = array();
foreach ( $pergel_counts as $pergel_category => $pergel_count ) {
	$pergel_summary[] = $pergel_category . ' ' . $pergel_count;
}
echo 'Patterns: ' . count( $pergel_files ) . ' files (' . implode( ', ', $pergel_summary ) . ').' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.

if ( $pergel_failed > 0 ) {
	fwrite( STDERR, $pergel_failed . " pattern file(s) break the rules (docs/ARCHITECTURE.md, Pattern'ler).\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	exit( 1 );
}
