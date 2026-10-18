<?php
/**
 * `wp pergel import`.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Cli;

use InvalidArgumentException;
use Pergel\Package\Importer;
use Pergel\Package\Package;
use WP_CLI;

/**
 * Builds the site from a project package. See docs/PROJECT-PACKAGE.md.
 */
final class ImportCommand {

	/**
	 * Builds the site from a project package.
	 *
	 * Checks the package first, as `wp pergel package validate` does, and
	 * then the site: a page at one of the package's addresses that the
	 * package did not make stops the import before anything is saved. Then
	 * sets the site title, tagline and language, imports the images in use,
	 * saves the Pergel settings, makes the pages, the reading settings and
	 * the menu, and for brand/tokens.json writes and activates a child theme.
	 *
	 * Importing again finds what the package made before. What did not change
	 * is kept; a page or menu edited in the panel since is skipped with a
	 * note, unless --force. What the package no longer has is listed as
	 * stale; --prune moves those pages to the trash and deletes those images.
	 *
	 * ## OPTIONS
	 *
	 * <dir>
	 * : Package folder.
	 *
	 * [--dry-run]
	 * : Show what would happen without saving anything.
	 *
	 * [--force]
	 * : Overwrite pages and the menu even when they were edited in the panel.
	 *
	 * [--prune]
	 * : Remove what an earlier import made and the package no longer has.
	 *
	 * [--strict]
	 * : Stop on notes as well as problems.
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
	 *     wp pergel import ./ornek-firma --dry-run
	 *     wp pergel import ./ornek-firma
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$dry_run = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$package = Package::read( $args[0] ?? '' );
		if ( array() === $package->problems ) {
			$package->check_theme();
		}
		$problems = $package->problems;
		if ( (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'strict', false ) ) {
			$problems = array_merge( $problems, $package->notes );
		}
		$importer = new Importer(
			$package,
			$dry_run,
			(bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false ),
			(bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'prune', false )
		);
		if ( array() === $problems ) {
			$problems = $importer->preflight();
		}
		if ( array() !== $problems ) {
			foreach ( $problems as $problem ) {
				WP_CLI::line( "problem: $problem" );
			}
			WP_CLI::error( sprintf( 'Nothing was imported: %d problems. wp pergel package validate checks the package.', count( $problems ) ) );
		}

		try {
			$importer->run();
		} catch ( InvalidArgumentException $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $importer->rows, array( 'action', 'type', 'key', 'id', 'title' ) );
		foreach ( $importer->notes as $note ) {
			WP_CLI::line( "note: $note" );
		}
		$counts = array_count_values( array_column( $importer->rows, 'action' ) );
		ksort( $counts );
		$parts = array();
		foreach ( $counts as $action => $count ) {
			$parts[] = "$count $action";
		}
		$text = sprintf( 'Package %s: %s; %d notes.', $package->site['name'], $parts ? implode( ', ', $parts ) : 'nothing to do', count( $importer->notes ) );
		if ( $dry_run ) {
			WP_CLI::log( "Dry run, nothing was saved. $text" );
			return;
		}
		WP_CLI::success( $text );
	}
}
