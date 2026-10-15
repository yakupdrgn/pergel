<?php
/**
 * `wp pergel modules` commands.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Cli;

use Pergel\Core\Plugin;
use Pergel\Settings\Schema;
use Pergel\Settings\Settings;
use WP_CLI;

/**
 * Lists the engine's modules and turns them on or off.
 *
 * Only modules with a switch on the settings screen can be turned off. A
 * change applies from the next request.
 */
final class ModulesCommand {

	/**
	 * Lists every module, its switch and whether it loaded for this command.
	 *
	 * "always" modules have no switch. Some modules load only in a context
	 * (Cli only under WP-CLI, Performance never in wp-admin), so "loaded" can
	 * differ from what a web request loads.
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
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp pergel modules list
	 *
	 * @subcommand list
	 *
	 * @param string[]              $args       Positional arguments (unused).
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function list_( array $args, array $assoc_args ): void {
		unset( $args );
		$switches = Schema::groups()['modules'];
		$loaded   = Plugin::loaded();
		$rows     = array();
		foreach ( Plugin::modules() as $class_name ) {
			$id     = $class_name::id();
			$rows[] = array(
				'module' => $id,
				'switch' => isset( $switches[ $id ] ) ? ( Settings::module_enabled( $id ) ? 'on' : 'off' ) : 'always',
				'loaded' => in_array( $id, $loaded, true ) ? 'yes' : 'no',
			);
		}
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'module', 'switch', 'loaded' ) );
	}

	/**
	 * Turns modules on.
	 *
	 * ## OPTIONS
	 *
	 * <module>...
	 * : Module ids, e.g. seo performance.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pergel modules enable performance
	 *
	 * @param string[] $args Module ids.
	 */
	public function enable( array $args ): void {
		self::toggle( $args, true );
	}

	/**
	 * Turns modules off. A switched-off module loads no code.
	 *
	 * ## OPTIONS
	 *
	 * <module>...
	 * : Module ids, e.g. seo performance.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pergel modules disable brand performance
	 *
	 * @param string[] $args Module ids.
	 */
	public function disable( array $args ): void {
		self::toggle( $args, false );
	}

	/**
	 * Saves the switches, or stops when an id has none.
	 *
	 * @param string[] $ids Module ids.
	 * @param bool     $on  New state.
	 */
	private static function toggle( array $ids, bool $on ): void {
		$switches = Schema::groups()['modules'];
		$unknown  = array_diff( $ids, array_keys( $switches ) );
		if ( $unknown ) {
			WP_CLI::error( 'No switch for: ' . implode( ', ', $unknown ) . '. Switchable modules: ' . implode( ', ', array_keys( $switches ) ) . '.' );
		}
		Settings::update( array( 'modules' => array_fill_keys( $ids, $on ) ) );
		WP_CLI::success( sprintf( '%s %s.', implode( ', ', $ids ), $on ? 'enabled' : 'disabled' ) );
	}
}
