<?php
/**
 * `wp pergel` commands.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Cli;

use Pergel\Core\Plugin;
use WP_CLI;

use const Pergel\VERSION;

/**
 * Manages the Pergel system: info, project packages, design tokens.
 */
final class Command {

	/**
	 * Shows engine and theme versions and the modules loaded for this request.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp pergel info
	 *     wp pergel info --format=json
	 *
	 * @param string[]              $args       Positional arguments (unused).
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function info( array $args, array $assoc_args ): void {
		unset( $args );
		$theme = wp_get_theme();
		$rows  = array(
			array(
				'key'   => 'engine',
				'value' => VERSION,
			),
			array(
				'key'   => 'theme',
				'value' => $theme->get_stylesheet() . ' ' . $theme->get( 'Version' ),
			),
			array(
				'key'   => 'parent_theme',
				'value' => $theme->parent() ? $theme->get_template() : '-',
			),
			array(
				'key'   => 'wordpress',
				'value' => get_bloginfo( 'version' ),
			),
			array(
				'key'   => 'php',
				'value' => PHP_VERSION,
			),
			array(
				'key'   => 'modules',
				'value' => implode( ', ', Plugin::loaded() ),
			),
		);

		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'key', 'value' ) );
	}
}
