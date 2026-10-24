<?php
/**
 * `wp pergel package` commands.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Cli;

use Pergel\Package\Package;
use WP_CLI;

/**
 * Works with project packages: a folder of YAML, Markdown, images and
 * brand tokens that describes a whole site. See docs/PROJECT-PACKAGE.md.
 */
final class PackageCommand {

	/**
	 * Checks a package against the active theme without changing the site.
	 *
	 * Reads every file of the package, fills each page's patterns and checks
	 * the settings and brand tokens, then lists every problem at once with
	 * its file and place. Problems stop an import. Notes do not: a field that
	 * keeps the pattern's sample text (a child theme's own picture is not
	 * sample text), an image without alt text, a content file no page uses.
	 *
	 * ## OPTIONS
	 *
	 * <dir>
	 * : Package folder.
	 *
	 * [--strict]
	 * : Count notes as problems.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: text
	 * options:
	 *   - text
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp pergel package validate ./ornek-firma
	 *     wp pergel package validate ./ornek-firma --strict
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function validate( array $args, array $assoc_args ): void {
		$package = Package::read( $args[0] ?? '' );
		if ( array() === $package->problems ) {
			$package->check_theme();
		}
		$strict   = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'strict', false );
		$problems = $package->problems;
		$notes    = $package->notes;
		if ( $strict ) {
			$problems = array_merge( $problems, $notes );
			$notes    = array();
		}

		if ( 'json' === ( $assoc_args['format'] ?? 'text' ) ) {
			WP_CLI::line( (string) wp_json_encode( compact( 'problems', 'notes' ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		} else {
			foreach ( $problems as $problem ) {
				WP_CLI::line( "problem: $problem" );
			}
			foreach ( $notes as $note ) {
				WP_CLI::line( "note: $note" );
			}
		}

		$summary = sprintf( '%d problems, %d notes', count( $problems ), count( $notes ) );
		if ( array() !== $problems ) {
			WP_CLI::error( "The package cannot be imported: $summary." );
		}
		WP_CLI::success(
			sprintf(
				'Package %s: %d pages, %d content files, %d images; %s.',
				$package->site['name'],
				count( $package->pages ),
				count( $package->content ),
				count( $package->media ),
				$summary
			)
		);
	}
}
