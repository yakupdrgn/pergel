<?php
/**
 * `wp pergel export`.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Cli;

use InvalidArgumentException;
use Pergel\Package\Exporter;
use Pergel\Package\Package;
use WP_CLI;

/**
 * Writes the site out as a project package. See docs/PROJECT-PACKAGE.md.
 */
final class ExportCommand {

	/**
	 * Writes the site out as a project package.
	 *
	 * The reverse of `wp pergel import`: the site title, tagline and
	 * language, the Pergel settings that are not their defaults, the pages
	 * with their SEO fields, the menu, the products the package filled in
	 * (products/<SKU>.md, with the fields it wrote), the images in use and
	 * an active child theme's design tokens. A section a pattern made is written as the
	 * pattern's fields, other blocks as Markdown. Each is read back as an
	 * import would and compared with the page: what would not come back the
	 * same is a note.
	 *
	 * By default the pages and products a package made or filled in are
	 * exported, under that package's name. --all exports every published
	 * page.
	 *
	 * The folder must be empty, new or hold the same package; exporting again
	 * rewrites what changed and removes the content files and images the
	 * site no longer has. The package is checked as `wp pergel package
	 * validate` does once it is written.
	 *
	 * ## OPTIONS
	 *
	 * <dir>
	 * : Package folder.
	 *
	 * [--name=<name>]
	 * : Package name. Defaults to the package that made the pages or filled in the products, or with --all to the site title.
	 *
	 * [--all]
	 * : Export every published page, not only the package's.
	 *
	 * [--dry-run]
	 * : Show what would be written without writing anything.
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
	 *     wp pergel export ./ornek-firma --dry-run
	 *     wp pergel export ./ornek-firma
	 *     wp pergel export ./site --all --name=site
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$dir     = rtrim( str_replace( '\\', '/', $args[0] ?? '' ), '/' );
		$all     = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );
		$dry_run = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$strict  = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'strict', false );
		if ( '' === $dir ) {
			WP_CLI::error( 'Give the package folder.' );
		}

		$name = (string) ( $assoc_args['name'] ?? '' );
		if ( '' === $name ) {
			$names = Exporter::names();
			if ( count( $names ) > 1 ) {
				WP_CLI::error( sprintf( 'Pages or products come from %d packages (%s); choose one with --name.', count( $names ), implode( ', ', $names ) ) );
			}
			$name = $names[0] ?? '';
			if ( '' === $name ) {
				if ( ! $all ) {
					WP_CLI::error( 'No page or product comes from a package; --all exports every page.' );
				}
				$name = sanitize_title( remove_accents( (string) get_option( 'blogname' ) ) );
				$name = (bool) preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $name ) ? $name : 'site';
			}
		}

		$exporter = new Exporter( $dir, $name, $all, $dry_run );
		$problems = $exporter->preflight();
		if ( array() !== $problems ) {
			foreach ( $problems as $problem ) {
				WP_CLI::line( "problem: $problem" );
			}
			WP_CLI::error( 'Nothing was exported.' );
		}
		try {
			$exporter->run();
		} catch ( InvalidArgumentException $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $exporter->rows, array( 'action', 'type', 'key', 'id', 'title' ) );
		$notes = $exporter->notes;
		if ( ! $dry_run ) {
			$package = Package::read( $dir );
			if ( array() === $package->problems ) {
				$package->check_theme();
			}
			foreach ( $package->problems as $problem ) {
				WP_CLI::line( "problem: $problem" );
			}
			if ( array() !== $package->problems ) {
				WP_CLI::error( sprintf( 'The package was written but does not pass validation: %d problems.', count( $package->problems ) ) );
			}
			$notes = array_values( array_unique( array_merge( $notes, $package->notes ) ) );
		}
		foreach ( $notes as $note ) {
			WP_CLI::line( "note: $note" );
		}

		$counts = array_count_values( array_column( $exporter->rows, 'action' ) );
		ksort( $counts );
		$parts = array();
		foreach ( $counts as $action => $count ) {
			$parts[] = "$count $action";
		}
		$text = sprintf( 'Package %s: %s; %d notes.', $name, $parts ? implode( ', ', $parts ) : 'nothing to write', count( $notes ) );
		if ( $strict && array() !== $notes ) {
			WP_CLI::error( "$text --strict stops on notes." );
		}
		if ( $dry_run ) {
			WP_CLI::log( "Dry run, nothing was written. $text" );
			return;
		}
		WP_CLI::success( $text );
	}
}
