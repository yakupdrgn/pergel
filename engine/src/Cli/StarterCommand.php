<?php
/**
 * `wp pergel starter` commands.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Cli;

use InvalidArgumentException;
use Pergel\Starters\Starter;
use WP_CLI;

/**
 * Installs and removes starter sites: pages built from Pergel's full-page
 * patterns, a main menu and the front page and posts page settings.
 */
final class StarterCommand {

	private const COLUMNS = array( 'action', 'type', 'key', 'id', 'title' );

	/**
	 * Lists the starters that come with Pergel.
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
	 *     wp pergel starter list
	 *
	 * @subcommand list
	 *
	 * @param string[]              $args       Positional arguments (unused).
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function list_( array $args, array $assoc_args ): void {
		unset( $args );
		$rows = array();
		foreach ( Starter::available() as $name => $starter ) {
			$rows[] = array(
				'name'        => $name,
				'title'       => $starter->title(),
				'description' => $starter->description(),
			);
		}
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'name', 'title', 'description' ) );
	}

	/**
	 * Makes a starter's pages, menu and reading settings.
	 *
	 * Running it again finds what it made before and changes nothing (or,
	 * with --reset, puts those pages and the menu back to the starter's
	 * content). Page titles and addresses follow the site language where the
	 * starter has them, English otherwise.
	 *
	 * ## OPTIONS
	 *
	 * <starter>
	 * : Starter name from `wp pergel starter list`, or a folder with a starter.json.
	 *
	 * [--dry-run]
	 * : Show what would happen without saving anything.
	 *
	 * [--reset]
	 * : Rewrite pages and the menu this starter made before, losing edits to them.
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
	 *     wp pergel starter install corporate --dry-run
	 *     wp pergel starter install corporate
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function install( array $args, array $assoc_args ): void {
		$dry_run = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		try {
			$starter = Starter::load( $args[0] ?? '' );
			$rows    = $starter->install( $dry_run, (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'reset', false ) );
		} catch ( InvalidArgumentException $e ) {
			WP_CLI::error( $e->getMessage() );
		}
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, self::COLUMNS );
		self::summary( $rows, $dry_run, "Starter {$starter->name}" );
	}

	/**
	 * Deletes every page and menu a starter made, edited or not.
	 *
	 * A front page or posts page setting that points at a deleted page goes
	 * back to showing the latest posts. Pages are deleted permanently, not
	 * moved to the trash.
	 *
	 * ## OPTIONS
	 *
	 * <starter>
	 * : Starter name.
	 *
	 * [--dry-run]
	 * : Show what would be deleted without deleting it.
	 *
	 * [--yes]
	 * : Do not ask for confirmation.
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
	 *     wp pergel starter remove corporate --dry-run
	 *     wp pergel starter remove corporate --yes
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function remove( array $args, array $assoc_args ): void {
		$name    = $args[0] ?? '';
		$dry_run = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		if ( ! $dry_run ) {
			WP_CLI::confirm( "Permanently delete every page and menu the $name starter made?", $assoc_args );
		}
		$rows = Starter::remove( $name, $dry_run );
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, self::COLUMNS );
		self::summary( $rows, $dry_run, "Starter $name" );
	}

	/**
	 * Prints how many items each action touched.
	 *
	 * @param list<array{action: string}> $rows    Report rows.
	 * @param bool                        $dry_run Whether nothing was saved.
	 * @param string                      $label   Subject of the sentence.
	 */
	private static function summary( array $rows, bool $dry_run, string $label ): void {
		$counts = array_count_values( array_column( $rows, 'action' ) );
		ksort( $counts );
		$parts = array();
		foreach ( $counts as $action => $count ) {
			$parts[] = "$count $action";
		}
		$text = $parts ? implode( ', ', $parts ) : 'nothing to do';
		if ( $dry_run ) {
			WP_CLI::log( "Dry run, nothing was saved. $label: $text." );
			return;
		}
		WP_CLI::success( "$label: $text." );
	}
}
