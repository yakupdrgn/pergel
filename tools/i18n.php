<?php
/**
 * Merges the .pot files into the bundled translations and compiles them.
 *
 * For each locale in LOCALES and each part (theme, engine) it updates
 * <part>/languages/<domain>-<locale>.po from the committed .pot (run
 * `composer pot` first) and writes the files WordPress loads from it:
 * .mo, .l10n.php and one .json per editor script that has strings.
 * WordPress looks for a theme's own language files without the domain
 * (theme/languages/tr_TR.mo), so the theme's files are named that way.
 * Compiling uses WP-CLI's i18n commands.
 *
 * Usage: php tools/i18n.php [--check] [--export=<file.json>] [--import=<file.json>]
 *   --check   Change nothing; exit 1 when a PO or compiled file is out of
 *             date, or a string is untranslated, fuzzy or has wrong placeholders.
 *   --export  Write untranslated strings as JSON ({locale: {domain: [...]}}).
 *   --import  Fill untranslated strings from JSON of the same shape, where
 *             each item has "key" (Catalog::key) and "translation".
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Dev;

use Pergel\I18n\Catalog;

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require dirname( __DIR__ ) . '/engine/src/I18n/Catalog.php';

const LOCALES = array( 'tr_TR' );
const PARTS   = array(
	'theme'  => array(
		'domain'  => 'pergel',
		'exclude' => 'tokens',
		'prefix'  => '',
	),
	'engine' => array(
		'domain'  => 'pergel-engine',
		'exclude' => 'build',
		'prefix'  => 'pergel-engine-',
	),
);

$pergel_root    = dirname( __DIR__ );
$pergel_options = getopt( '', array( 'check', 'export:', 'import:' ) );
$pergel_check   = isset( $pergel_options['check'] );
$pergel_import  = is_string( $pergel_options['import'] ?? null ) ? json_decode( (string) file_get_contents( $pergel_options['import'] ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
$pergel_export  = array();
$pergel_stale   = array();
$pergel_failed  = array();
$pergel_temp    = sys_get_temp_dir() . '/pergel-i18n-' . getmypid();

/**
 * Runs a WP-CLI command and stops the script when it fails.
 *
 * @param string $args Arguments after `wp`, already escaped.
 */
function wp( string $args ): void {
	exec( 'wp ' . $args . ' 2>&1', $output, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Dev tool.
	if ( 0 !== $code ) {
		fwrite( STDERR, 'wp ' . $args . " failed:\n" . implode( "\n", $output ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
		exit( 1 );
	}
}

/**
 * Compiled translation files of a domain and locale in a directory.
 *
 * @param string $dir    Directory.
 * @param string $prefix "<domain>-<locale>".
 * @return array<string, string> Contents keyed by file name.
 */
function compiled( string $dir, string $prefix ): array {
	$files = array();
	foreach ( array_merge( array( "{$dir}/{$prefix}.mo", "{$dir}/{$prefix}.l10n.php" ), array_filter( (array) glob( "{$dir}/{$prefix}-*.json" ) ) ) as $file ) {
		if ( is_file( $file ) ) {
			$files[ basename( $file ) ] = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		}
	}
	ksort( $files );
	return $files;
}

/**
 * Writes a file, creating its directory.
 *
 * @param string $file     Path.
 * @param string $contents Contents.
 */
function put( string $file, string $contents ): void {
	if ( ! is_dir( dirname( $file ) ) ) {
		mkdir( dirname( $file ), 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Dev tool.
	}
	file_put_contents( $file, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Dev tool.
}

foreach ( LOCALES as $pergel_locale ) {
	foreach ( PARTS as $pergel_part => $pergel_config ) {
		$pergel_domain = $pergel_config['domain'];
		$pergel_prefix = $pergel_config['prefix'] . $pergel_locale;
		$pergel_dir    = "{$pergel_root}/{$pergel_part}/languages";
		$pergel_po     = "{$pergel_dir}/{$pergel_prefix}.po";
		$pergel_work   = "{$pergel_temp}/{$pergel_part}-{$pergel_locale}";
		$pergel_before = is_file( $pergel_po ) ? (string) file_get_contents( $pergel_po ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.

		$pergel_entries = Catalog::merge(
			Catalog::parse( (string) file_get_contents( "{$pergel_dir}/{$pergel_domain}.pot" ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
			Catalog::parse( $pergel_before ),
			$pergel_locale
		);
		$pergel_fill    = array();
		foreach ( $pergel_import[ $pergel_locale ][ $pergel_domain ] ?? array() as $pergel_item ) {
			$pergel_fill[ (string) $pergel_item['key'] ] = (string) $pergel_item['translation'];
		}
		$pergel_entries = Catalog::fill( $pergel_entries, $pergel_fill );
		$pergel_after   = Catalog::write( $pergel_entries );
		if ( $pergel_after !== $pergel_before ) {
			$pergel_entries = Catalog::stamp( $pergel_entries, gmdate( 'Y-m-d H:i+0000' ) );
			$pergel_after   = Catalog::write( $pergel_entries );
		}

		foreach ( $pergel_entries as $pergel_entry ) {
			if ( '' !== $pergel_entry['id'] && in_array( '', $pergel_entry['strings'], true ) ) {
				$pergel_export[ $pergel_locale ][ $pergel_domain ][] = array(
					'key'      => Catalog::key( $pergel_entry ),
					'context'  => $pergel_entry['context'],
					'text'     => $pergel_entry['id'],
					'plural'   => $pergel_entry['plural'],
					'comments' => array_values( array_filter( $pergel_entry['comments'], static fn ( string $line ): bool => str_starts_with( $line, '#.' ) ) ),
				);
			}
		}
		foreach ( Catalog::problems( $pergel_entries ) as $pergel_problem ) {
			$pergel_failed[] = "{$pergel_part}/languages/{$pergel_prefix}.po: {$pergel_problem}";
		}

		// Compile into a work directory, then compare with or copy to languages/.
		put( "{$pergel_work}/{$pergel_prefix}.po", $pergel_after );
		$pergel_source = escapeshellarg( "{$pergel_work}/{$pergel_prefix}.po" );
		wp( 'i18n make-mo ' . $pergel_source . ' ' . escapeshellarg( "{$pergel_work}/out" ) );
		wp( 'i18n make-php ' . $pergel_source . ' ' . escapeshellarg( "{$pergel_work}/out" ) );

		// make-json needs the source references the committed files leave out.
		wp( 'i18n make-pot ' . escapeshellarg( "{$pergel_root}/{$pergel_part}" ) . ' ' . escapeshellarg( "{$pergel_work}/located.pot" ) . ' --slug=' . escapeshellarg( $pergel_domain ) . ' --domain=' . escapeshellarg( $pergel_domain ) . ' --exclude=' . escapeshellarg( $pergel_config['exclude'] ) . ' --skip-audit' );
		$pergel_located = Catalog::merge( Catalog::parse( (string) file_get_contents( "{$pergel_work}/located.pot" ) ), $pergel_entries, $pergel_locale ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		put( "{$pergel_work}/json/{$pergel_prefix}.po", Catalog::write( $pergel_located ) );
		wp( 'i18n make-json ' . escapeshellarg( "{$pergel_work}/json/{$pergel_prefix}.po" ) . ' ' . escapeshellarg( "{$pergel_work}/out" ) . ' --no-purge' );

		// The generator name carries the WP-CLI version, which differs between machines.
		foreach ( array_filter( (array) glob( "{$pergel_work}/out/{$pergel_prefix}-*.json" ) ) as $pergel_json ) {
			$pergel_data = json_decode( (string) file_get_contents( $pergel_json ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
			unset( $pergel_data['generator'] );
			put( $pergel_json, (string) json_encode( $pergel_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- runs without WordPress loaded.
		}
		// make-php ends the opening tag with PHP_EOL; the repository keeps LF.
		$pergel_php = "{$pergel_work}/out/{$pergel_prefix}.l10n.php";
		put( $pergel_php, str_replace( "\r\n", "\n", (string) file_get_contents( $pergel_php ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.

		$pergel_built   = compiled( "{$pergel_work}/out", $pergel_prefix );
		$pergel_current = compiled( $pergel_dir, $pergel_prefix );
		$pergel_changed = $pergel_before !== $pergel_after || $pergel_built !== $pergel_current;
		if ( $pergel_changed && $pergel_check ) {
			$pergel_stale[] = "{$pergel_part}/languages/{$pergel_prefix}.*";
		} elseif ( $pergel_changed ) {
			put( $pergel_po, $pergel_after );
			foreach ( array_diff_key( $pergel_current, $pergel_built ) as $pergel_name => $pergel_unused ) {
				unlink( "{$pergel_dir}/{$pergel_name}" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Dev tool.
			}
			foreach ( $pergel_built as $pergel_name => $pergel_contents ) {
				put( "{$pergel_dir}/{$pergel_name}", $pergel_contents );
			}
		}
		$pergel_total = count( $pergel_entries ) - 1;
		$pergel_open  = count( $pergel_export[ $pergel_locale ][ $pergel_domain ] ?? array() );
		echo "{$pergel_part} {$pergel_locale}: " . ( $pergel_total - $pergel_open ) . "/{$pergel_total} translated, " . count( $pergel_built ) . ' compiled files' . ( $pergel_changed ? ( $pergel_check ? ', OUT OF DATE.' : ', written.' ) : ', up to date.' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	}
}

// Remove the work directory.
if ( is_dir( $pergel_temp ) ) {
	$pergel_items = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $pergel_temp, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $pergel_items as $pergel_item ) {
		$pergel_path = $pergel_item instanceof \SplFileInfo ? $pergel_item->getPathname() : '';
		is_dir( $pergel_path ) ? rmdir( $pergel_path ) : unlink( $pergel_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.AlternativeFunctions.unlink_unlink -- Dev tool.
	}
	rmdir( $pergel_temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Dev tool.
}

if ( is_string( $pergel_options['export'] ?? null ) ) {
	put( $pergel_options['export'], (string) json_encode( $pergel_export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- runs without WordPress loaded.
}

// Without --check, missing translations are normal work in progress: count them only.
foreach ( $pergel_check ? $pergel_failed : array() as $pergel_problem ) {
	fwrite( STDERR, $pergel_problem . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
}
if ( ! $pergel_check && array() !== $pergel_failed ) {
	echo count( $pergel_failed ) . " problem(s); php tools/i18n.php --check lists them.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
}
if ( array() !== $pergel_stale ) {
	fwrite( STDERR, implode( ', ', $pergel_stale ) . " out of date. Run composer pot and php tools/i18n.php, then commit the result.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
}
if ( $pergel_check && ( array() !== $pergel_stale || array() !== $pergel_failed ) ) {
	exit( 1 );
}
