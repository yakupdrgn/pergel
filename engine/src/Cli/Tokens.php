<?php
/**
 * `wp pergel tokens` commands.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Cli;

use Pergel\Tokens\Compiler;
use Pergel\Tokens\Invalid;
use WP_CLI;

/**
 * Compiles design tokens into theme.json and style variations (docs/TOKENS.md).
 */
final class Tokens {

	/**
	 * Builds theme.json presets and styles/<slug>.json from tokens/*.json.
	 *
	 * Child themes inherit the parent theme's tokens/default.json and only
	 * list what they change.
	 *
	 * ## OPTIONS
	 *
	 * [--theme=<stylesheet>]
	 * : Theme to build. Defaults to the active theme.
	 *
	 * [--check]
	 * : Do not write; exit with an error if any output file is out of date.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pergel tokens build
	 *     wp pergel tokens build --theme=ariumlab --check
	 *
	 * @param string[]                   $args       Positional arguments (unused).
	 * @param array<string, string|bool> $assoc_args Associative arguments.
	 */
	public function build( array $args, array $assoc_args ): void {
		unset( $args );
		$theme = wp_get_theme( isset( $assoc_args['theme'] ) ? (string) $assoc_args['theme'] : '' );
		if ( ! $theme->exists() ) {
			WP_CLI::error( 'Theme not found.' );
		}
		$dir    = $theme->get_stylesheet_directory();
		$parent = $theme->parent() ? $theme->get_template_directory() : null;
		$check  = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'check', false );

		try {
			$changed = Compiler::sync( $dir, Compiler::build( $dir, $parent ), $check );
		} catch ( Invalid $e ) {
			foreach ( $e->problems as $problem ) {
				WP_CLI::warning( $problem );
			}
			WP_CLI::error( sprintf( '%d token problem(s); nothing was written.', count( $e->problems ) ) );
		}

		foreach ( $changed as $file ) {
			WP_CLI::log( ( $check ? 'out of date: ' : 'written: ' ) . $file );
		}
		if ( $check && array() !== $changed ) {
			WP_CLI::error( 'Run `wp pergel tokens build` and commit the result.' );
		}
		WP_CLI::success( array() === $changed ? 'Up to date.' : sprintf( '%d file(s) written.', count( $changed ) ) );
	}
}
