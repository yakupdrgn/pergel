<?php
/**
 * `wp pergel settings` commands.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Cli;

use Pergel\Core\Svg;
use Pergel\Settings\Labels;
use Pergel\Settings\Schema;
use Pergel\Settings\Settings;
use WP_CLI;

/**
 * Reads and writes the Pergel settings.
 *
 * Every value goes through the settings schema, so the command line accepts
 * exactly what the settings screen accepts.
 */
final class SettingsCommand {

	/**
	 * Prints every setting, a group or one field.
	 *
	 * ## OPTIONS
	 *
	 * [<path>]
	 * : "group" or "group.field", e.g. brand or brand.email. Empty: everything.
	 *
	 * [--format=<format>]
	 * : Output format. "plain" prints text fields as they are; lists and
	 * groups are always JSON.
	 * ---
	 * default: plain
	 * options:
	 *   - plain
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp pergel settings get
	 *     wp pergel settings get brand
	 *     wp pergel settings get brand.phone
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function get( array $args, array $assoc_args ): void {
		$path  = $args[0] ?? '';
		$value = '' === $path ? Settings::all() : self::read( $path );
		if ( 'json' !== ( $assoc_args['format'] ?? 'plain' ) && ( is_string( $value ) || is_int( $value ) ) ) {
			WP_CLI::line( (string) $value );
			return;
		}
		WP_CLI::line( self::json( $value ) );
	}

	/**
	 * Changes one field.
	 *
	 * The value is checked like a form entry: on/off fields take
	 * true/false (also 1/0, yes/no), lists take one URL per line or a
	 * JSON array with --format=json. An invalid value changes nothing.
	 *
	 * ## OPTIONS
	 *
	 * <path>
	 * : "group.field", e.g. brand.email.
	 *
	 * <value>
	 * : New value. An empty string clears a text field.
	 *
	 * [--format=<format>]
	 * : How to read <value>.
	 * ---
	 * default: plain
	 * options:
	 *   - plain
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp pergel settings set brand.phone "+90 212 000 00 00"
	 *     wp pergel settings set seo.sitemap_users true
	 *     wp pergel settings set brand.social '["https://www.linkedin.com/company/example"]' --format=json
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function set( array $args, array $assoc_args ): void {
		[ $path, $raw ]  = $args + array( '', '' );
		[ $group, $key ] = array_pad( explode( '.', $path, 2 ), 2, '' );
		if ( ! isset( Schema::groups()[ $group ][ $key ] ) ) {
			WP_CLI::error( "Unknown setting: $path. See `wp pergel settings get` for the fields." );
		}
		$field = Schema::groups()[ $group ][ $key ];
		$value = 'json' === ( $assoc_args['format'] ?? 'plain' ) ? self::decode( $raw ) : $raw;
		$id    = Schema::field( $field, $value );
		if ( 'image' === $field['type'] && is_array( $id ) && $id[0] > 0 ) {
			if ( ! wp_attachment_is_image( $id[0] ) ) {
				WP_CLI::error( "Attachment {$id[0]} is not an image on this site. Nothing was saved." );
			}
			if ( false === ( $field['svg'] ?? true ) && Svg::is( $id[0] ) ) {
				WP_CLI::error( "$path: " . Labels::problem( 'svg' ) . ' Nothing was saved.' );
			}
		}
		self::save( array( $group => array( $key => $value ) ) );
		WP_CLI::success( "$path = " . self::json( Settings::get( $path ), false ) );
	}

	/**
	 * Writes every setting as JSON, for `wp pergel settings import`.
	 *
	 * Image fields hold attachment IDs, which only mean the same image on
	 * the site they come from.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : File to write. Empty: standard output.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pergel settings export > pergel-settings.json
	 *     wp pergel settings export pergel-settings.json
	 *
	 * @param string[] $args Positional arguments.
	 */
	public function export( array $args ): void {
		$json = self::json( Settings::all() ) . "\n";
		$file = $args[0] ?? '';
		if ( '' === $file ) {
			WP_CLI::line( rtrim( $json ) );
			return;
		}
		if ( false === file_put_contents( $file, $json ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI only.
			WP_CLI::error( "Cannot write $file." );
		}
		WP_CLI::success( "Settings written to $file." );
	}

	/**
	 * Reads settings from JSON, e.g. a `wp pergel settings export` file.
	 *
	 * Groups and fields missing from the file keep their current value, so
	 * a file can hold only what changes. Nothing is saved when any field is
	 * invalid or unknown. Image fields that point to no image on this site,
	 * or to an SVG where SVG cannot be used, are cleared, with a warning.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : JSON file, or "-" for standard input.
	 *
	 * [--dry-run]
	 * : Check the file and show what would change, without saving.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pergel settings import pergel-settings.json
	 *     cat brand.json | wp pergel settings import - --dry-run
	 *
	 * @param string[]                   $args       Positional arguments.
	 * @param array<string, string|bool> $assoc_args Associative arguments.
	 */
	public function import( array $args, array $assoc_args ): void {
		$file = $args[0] ?? '';
		$text = '-' === $file ? file_get_contents( 'php://stdin' ) : ( is_file( $file ) ? file_get_contents( $file ) : false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file or stdin.
		if ( false === $text ) {
			WP_CLI::error( "Cannot read $file." );
		}
		$input = self::decode( $text );
		if ( ! is_array( $input ) || ( $input && array_is_list( $input ) ) ) {
			WP_CLI::error( 'The file must hold a JSON object of setting groups.' );
		}

		$unknown = array();
		foreach ( $input as $group => $fields ) {
			if ( ! isset( Schema::groups()[ $group ] ) ) {
				$unknown[] = (string) $group;
				continue;
			}
			foreach ( is_array( $fields ) ? array_keys( $fields ) : array() as $key ) {
				if ( ! isset( Schema::groups()[ $group ][ $key ] ) ) {
					$unknown[] = "$group.$key";
				}
			}
		}
		if ( $unknown ) {
			WP_CLI::error( 'Unknown settings: ' . implode( ', ', $unknown ) . '. Nothing was saved.' );
		}

		$input = self::drop_missing_images( $input );
		$dry   = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$diff  = self::diff( Settings::all(), Schema::sanitize( $input, Settings::all() )['value'] );
		if ( $dry ) {
			self::check( $input );
			foreach ( $diff as $line ) {
				WP_CLI::log( $line );
			}
			WP_CLI::success( sprintf( 'Dry run: %d field(s) would change.', count( $diff ) ) );
			return;
		}
		self::save( $input );
		foreach ( $diff as $line ) {
			WP_CLI::log( $line );
		}
		WP_CLI::success( sprintf( '%d field(s) changed.', count( $diff ) ) );
	}

	/**
	 * A group or field by path, or an error.
	 *
	 * @param string $path "group" or "group.field".
	 */
	private static function read( string $path ): mixed {
		[ $group, $key ] = array_pad( explode( '.', $path, 2 ), 2, null );
		$groups          = Schema::groups();
		if ( ! isset( $groups[ $group ] ) || ( null !== $key && ! isset( $groups[ $group ][ $key ] ) ) ) {
			WP_CLI::error( "Unknown setting: $path. Groups: " . implode( ', ', array_keys( $groups ) ) . '.' );
		}
		return Settings::get( $path );
	}

	/**
	 * Stops with every problem when the input does not pass the schema.
	 *
	 * @param array<string, mixed> $input Partial settings.
	 */
	private static function check( array $input ): void {
		$problems = Schema::sanitize( $input, Settings::all() )['problems'];
		foreach ( $problems as $problem ) {
			WP_CLI::warning( ( '' === $problem['field'] ? 'settings' : $problem['field'] ) . ': ' . Labels::problem( $problem['code'] ) );
		}
		if ( $problems ) {
			WP_CLI::error( sprintf( '%d invalid field(s). Nothing was saved.', count( $problems ) ) );
		}
	}

	/**
	 * Checks, then saves partial settings.
	 *
	 * @param array<string, mixed> $input Partial settings.
	 */
	private static function save( array $input ): void {
		self::check( $input );
		Settings::update( $input );
	}

	/**
	 * Clears image fields whose attachment is not an image on this site, or
	 * is an SVG where the field takes none.
	 *
	 * @param array<string, mixed> $input Partial settings.
	 * @return array<string, mixed>
	 */
	private static function drop_missing_images( array $input ): array {
		foreach ( Schema::groups() as $group => $fields ) {
			foreach ( $fields as $key => $field ) {
				$id = $input[ $group ][ $key ] ?? null;
				if ( 'image' === $field['type'] && is_int( $id ) && $id > 0 && ! wp_attachment_is_image( $id ) ) {
					WP_CLI::warning( "$group.$key: attachment $id is not an image on this site; cleared." );
					$input[ $group ][ $key ] = 0;
				} elseif ( 'image' === $field['type'] && is_int( $id ) && false === ( $field['svg'] ?? true ) && Svg::is( $id ) ) {
					WP_CLI::warning( "$group.$key: attachment $id is an SVG. " . Labels::problem( 'svg' ) . ' Cleared.' );
					$input[ $group ][ $key ] = 0;
				}
			}
		}
		return $input;
	}

	/**
	 * "group.field: old → new" lines for every changed field.
	 *
	 * @param array<string, array<string, mixed>> $before Current settings.
	 * @param array<string, array<string, mixed>> $after  New settings.
	 * @return list<string>
	 */
	private static function diff( array $before, array $after ): array {
		$lines = array();
		foreach ( $after as $group => $fields ) {
			foreach ( $fields as $key => $value ) {
				$old = $before[ $group ][ $key ] ?? null;
				if ( $old !== $value ) {
					$lines[] = "$group.$key: " . self::json( $old, false ) . ' → ' . self::json( $value, false );
				}
			}
		}
		return $lines;
	}

	/**
	 * JSON text of a value.
	 *
	 * @param mixed $value  Value.
	 * @param bool  $pretty Indent.
	 */
	private static function json( mixed $value, bool $pretty = true ): string {
		$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | ( $pretty ? JSON_PRETTY_PRINT : 0 );
		return (string) wp_json_encode( $value, $flags );
	}

	/**
	 * Decoded JSON, or an error.
	 *
	 * @param string $text JSON text.
	 */
	private static function decode( string $text ): mixed {
		$value = json_decode( $text, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			WP_CLI::error( 'Invalid JSON: ' . json_last_error_msg() . '.' );
		}
		return $value;
	}
}
