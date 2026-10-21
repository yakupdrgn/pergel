<?php
/**
 * `wp pergel pattern` commands.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Cli;

use InvalidArgumentException;
use Pergel\Package\Fields;
use Pergel\Package\Package;
use Pergel\Package\Yaml;
use WP_CLI;

/**
 * Shows the patterns a project package can use (Pergel's and the child
 * theme's) and the fields a package fills in them.
 */
final class PatternCommand {

	/**
	 * Lists the patterns a package can use, with their fields: Pergel's own
	 * (named without pergel/) and the child theme's (with their full name).
	 *
	 * ## OPTIONS
	 *
	 * [--category=<category>]
	 * : Only patterns in this category, like banner or pergel-faq.
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
	 *     wp pergel pattern list
	 *     wp pergel pattern list --category=banner
	 *
	 * @subcommand list
	 *
	 * @param string[]              $args       Positional arguments (unused).
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function list_( array $args, array $assoc_args ): void {
		unset( $args );
		$category = $assoc_args['category'] ?? null;
		$rows     = array();
		foreach ( Package::section_patterns() as $pattern ) {
			$categories = (array) ( $pattern['categories'] ?? array() );
			if ( null !== $category && ! in_array( $category, $categories, true ) ) {
				continue;
			}
			$rows[] = array(
				'name'       => Package::section_name( $pattern['name'] ),
				'title'      => $pattern['title'],
				'categories' => implode( ', ', $categories ),
				'fields'     => Fields::summary( Fields::schema( (array) Package::pattern( $pattern['name'] ) ) ),
			);
		}
		usort( $rows, static fn( array $a, array $b ): int => strcmp( $a['name'], $b['name'] ) );
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'name', 'title', 'categories', 'fields' ) );
	}

	/**
	 * Prints a pattern's fields as a section to copy into a content file.
	 *
	 * The values are the pattern's own sample texts; images point at a .jpg
	 * under media/ named after the pattern's sample image. A field left out
	 * keeps the sample text, and null removes the block. Items can be added
	 * or removed; separators between them follow.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Pattern name, like hero-split (or pergel/hero-split), or a child theme's, like ariumlab/hero.
	 *
	 * [--format=<format>]
	 * : Output format: a YAML section, or the field schema as JSON.
	 * ---
	 * default: yaml
	 * options:
	 *   - yaml
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp pergel pattern fields hero-split
	 *     wp pergel pattern fields faq-accordion --format=json
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function fields( array $args, array $assoc_args ): void {
		$name   = $args[0] ?? '';
		$name   = str_contains( $name, '/' ) ? $name : "pergel/$name";
		$blocks = Package::pattern( $name );
		if ( null === $blocks ) {
			WP_CLI::error( "\"$name\" is not a pattern a package can use (Pergel's own or the child theme's); wp pergel pattern list shows them." );
		}
		try {
			$schema = Fields::schema( $blocks );
		} catch ( InvalidArgumentException $e ) {
			WP_CLI::error( $e->getMessage() );
		}
		if ( 'json' === ( $assoc_args['format'] ?? 'yaml' ) ) {
			WP_CLI::line( (string) wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			return;
		}
		$section = array( 'pattern' => Package::section_name( $name ) ) + Fields::example( $schema );
		WP_CLI::line( rtrim( Yaml::dump( array( $section ) ), "\n" ) );
	}
}
