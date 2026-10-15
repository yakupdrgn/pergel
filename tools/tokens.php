<?php
/**
 * Builds or checks theme.json and style variations from design tokens
 * without WordPress (CI lint job). On a site, use `wp pergel tokens build`.
 *
 * Usage: php tools/tokens.php [--check] [theme-dir] [parent-theme-dir]
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Dev;

use Pergel\Tokens\Compiler;
use Pergel\Tokens\Invalid;

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require dirname( __DIR__ ) . '/engine/src/Tokens/Invalid.php';
require dirname( __DIR__ ) . '/engine/src/Tokens/Css.php';
require dirname( __DIR__ ) . '/engine/src/Tokens/Compiler.php';

$pergel_args  = array_slice( $argv, 1 );
$pergel_check = in_array( '--check', $pergel_args, true );
$pergel_dirs  = array_values( array_filter( $pergel_args, static fn ( string $arg ): bool => ! str_starts_with( $arg, '--' ) ) );
$pergel_theme = $pergel_dirs[0] ?? dirname( __DIR__ ) . '/theme';

try {
	$pergel_changed = Compiler::sync( $pergel_theme, Compiler::build( $pergel_theme, $pergel_dirs[1] ?? null ), $pergel_check );
} catch ( Invalid $e ) {
	fwrite( STDERR, $e->getMessage() . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	exit( 1 );
}

foreach ( $pergel_changed as $pergel_file ) {
	echo ( $pergel_check ? 'out of date: ' : 'written: ' ) . $pergel_file . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
}
if ( $pergel_check && array() !== $pergel_changed ) {
	fwrite( STDERR, "Run `wp pergel tokens build` (or php tools/tokens.php) and commit the result.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output.
	exit( 1 );
}
echo array() === $pergel_changed ? "Tokens: up to date.\n" : 'Tokens: ' . count( $pergel_changed ) . " file(s) written.\n";
