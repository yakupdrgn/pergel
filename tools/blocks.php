<?php
/**
 * Checks engine/blocks/<slug>/block.json against the block rules and writes
 * engine/blocks/manifest.php, without WordPress (CI lint job).
 *
 * Usage: php tools/blocks.php [--check]
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Dev;

use Pergel\Blocks\Manifest;

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require dirname( __DIR__ ) . '/engine/src/Blocks/Manifest.php';

$pergel_check = in_array( '--check', array_slice( $argv, 1 ), true );
$pergel_dir   = dirname( __DIR__ ) . '/engine/blocks';

try {
	$pergel_changed = Manifest::sync( $pergel_dir, $pergel_check );
	$pergel_count   = count( Manifest::build( $pergel_dir ) );
} catch ( \RuntimeException $e ) {
	fwrite( STDERR, $e->getMessage() . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	exit( 1 );
}

if ( $pergel_check && $pergel_changed ) {
	fwrite( STDERR, "engine/blocks/manifest.php is out of date. Run php tools/blocks.php and commit the result.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output.
	exit( 1 );
}
echo 'Blocks: ' . $pergel_count . ( $pergel_changed ? ', manifest written.' : ', manifest up to date.' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
