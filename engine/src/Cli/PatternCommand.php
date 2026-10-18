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
use WP_Block_Patterns_Registry;
use WP_CLI;

/**
 * Shows Pergel's patterns and the fields a project package fills in them.
 */
final class PatternCommand {

	/**
	 * Lists Pergel's patterns with their fields.
	 *
	 * ## OPTIONS
	 *
	 * [--category=<category>]
	 * : Only patterns in this category, like pergel-hero.
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
	 *     wp pergel pattern list --category=pergel-hero
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
		foreach ( WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $pattern ) {
			if ( ! str_starts_with( $pattern['name'], 'pergel/' ) ) {
				continue;
			}
			$categories = (array) ( $pattern['categories'] ?? array() );
			if ( null !== $category && ! in_array( $category, $categories, true ) ) {
				continue;
			}
			$rows[] = array(
				'name'       => substr( $pattern['name'], strlen( 'pergel/' ) ),
				'title'      => $pattern['title'],
				'categories' => implode( ', ', $categories ),
				'fields'     => self::summary( Fields::schema( (array) Package::pattern( $pattern['name'] ) ) ),
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
	 * : Pattern name, like hero-split (or pergel/hero-split).
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
			WP_CLI::error( "\"$name\" is not a Pergel pattern; wp pergel pattern list shows them." );
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
		$section = array( 'pattern' => substr( $name, strlen( 'pergel/' ) ) ) + self::example( $schema );
		WP_CLI::line( rtrim( Yaml::dump( array( $section ) ), "\n" ) );
	}

	/**
	 * Field names in one line, items as name[count]{fields}.
	 *
	 * @param array<string, array<string, mixed>> $schema Field schema.
	 */
	private static function summary( array $schema ): string {
		$names = array();
		foreach ( $schema as $name => $field ) {
			$names[] = 'items' === $field['type']
				? sprintf( '%s[%d]{%s}', $name, count( $field['items'] ), self::summary( $field['items'][0] ?? array() ) )
				: $name;
		}
		return implode( ' ', $names );
	}

	/**
	 * Values a package could write: the sample texts, images under media/.
	 *
	 * @param array<string, array<string, mixed>> $schema Field schema.
	 * @return array<string, mixed>
	 */
	private static function example( array $schema ): array {
		$values = array();
		foreach ( $schema as $name => $field ) {
			$values[ $name ] = match ( $field['type'] ) {
				'items' => array_map( array( self::class, 'example' ), $field['items'] ),
				'image' => 'media/' . ( '' !== $field['default'] ? pathinfo( $field['default'], PATHINFO_FILENAME ) : 'image' ) . '.jpg',
				default => $field['default'],
			};
		}
		return $values;
	}
}
