<?php
/**
 * Version checks, version bumps and the release zips.
 *
 * Usage:
 *   php tools/release.php check [--tag=vX.Y.Z]
 *       Exit 1 unless the theme and the engine carry one version everywhere
 *       (headers, VERSION, Stable tag, translation templates), both readmes
 *       have a changelog entry for it, the WordPress and PHP requirements
 *       agree and the theme has a 1200x900 screenshot.png. With --tag, the
 *       tag must name that version (release workflow).
 *   php tools/release.php bump X.Y.Z
 *       Writes the version into the headers, VERSION and Stable tags. The
 *       changelog entries are written by hand; then `composer pot`,
 *       `composer i18n` and `check`.
 *   php tools/release.php package <dir>
 *       Writes <dir>/pergel-X.Y.Z.zip and <dir>/pergel-engine-X.Y.Z.zip from
 *       `git archive HEAD`, so what .gitattributes leaves out (tests,
 *       .gitkeep) and uncommitted changes are not in them. Each zip holds
 *       one folder named after the slug, as WordPress installs it.
 *   php tools/release.php notes
 *       Prints the changelog entries of the current version (release text).
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Dev;

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

const ROOT = __DIR__ . '/..';

/**
 * Where each version string is: file and a pattern whose first group is the version.
 */
const VERSIONS = array(
	array( 'theme/style.css', '/^Version:\s*(\S+)$/m' ),
	array( 'theme/readme.txt', '/^Stable tag:\s*(\S+)$/m' ),
	array( 'engine/pergel-engine.php', '/^ \* Version:\s*(\S+)$/m' ),
	array( 'engine/pergel-engine.php', "/^const VERSION = '([^']+)';$/m" ),
	array( 'engine/readme.txt', '/^Stable tag:\s*(\S+)$/m' ),
	array( 'theme/languages/pergel.pot', '/^"Project-Id-Version: Pergel (\S+)\\\\n"$/m' ),
	array( 'engine/languages/pergel-engine.pot', '/^"Project-Id-Version: Pergel Engine (\S+)\\\\n"$/m' ),
);

/**
 * Files the requirement headers are read from.
 */
const HEADERS = array( 'theme/style.css', 'theme/readme.txt', 'engine/pergel-engine.php', 'engine/readme.txt' );

/**
 * Requirement headers that must agree (engine header has no "Tested up to").
 */
const REQUIREMENTS = array( 'Requires at least', 'Tested up to', 'Requires PHP' );

/**
 * Zip name prefix => folder in the repository.
 */
const PARTS = array(
	'pergel'        => 'theme',
	'pergel-engine' => 'engine',
);

/**
 * Prints a line to STDERR and stops.
 *
 * @param string $message Message.
 */
function fail( string $message ): never {
	fwrite( STDERR, $message . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	exit( 1 );
}

/**
 * Contents of a repository file.
 *
 * @param string $file Path from the repository root.
 */
function read( string $file ): string {
	$contents = file_get_contents( ROOT . '/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
	if ( false === $contents ) {
		fail( "Cannot read {$file}." );
	}
	return $contents;
}

/**
 * Every version string, in the order of VERSIONS.
 *
 * @return list<string|null> Null where the pattern found nothing.
 */
function versions(): array {
	$found = array();
	foreach ( VERSIONS as list( $file, $pattern ) ) {
		$found[] = 1 === preg_match( $pattern, read( $file ), $m ) ? $m[1] : null;
	}
	return $found;
}

/**
 * The one version of the release; stops when the files disagree.
 */
function version(): string {
	$found = versions();
	$all   = array_unique( $found );
	if ( 1 !== count( $all ) || null === reset( $all ) ) {
		$lines = array();
		foreach ( $found as $i => $value ) {
			$lines[] = '  ' . VERSIONS[ $i ][0] . ': ' . ( $value ?? '(not found)' );
		}
		fail( "The version differs between files:\n" . implode( "\n", $lines ) );
	}
	return (string) reset( $all );
}

/**
 * The changelog entry of a version in a readme ('' when there is none).
 *
 * @param string $readme  Path from the repository root.
 * @param string $version Version.
 */
function changelog( string $readme, string $version ): string {
	$quoted = preg_quote( $version, '/' );
	return 1 === preg_match( "/^= {$quoted} =\n(.*?)(?=^=|\z)/ms", read( $readme ), $m ) ? trim( $m[1] ) : '';
}

$pergel_command = $argv[1] ?? '';

if ( 'check' === $pergel_command ) {
	$pergel_version  = version();
	$pergel_problems = array();

	$pergel_tag = null;
	foreach ( array_slice( $argv, 2 ) as $pergel_arg ) {
		if ( str_starts_with( $pergel_arg, '--tag=' ) ) {
			$pergel_tag = substr( $pergel_arg, 6 );
		}
	}
	if ( null !== $pergel_tag && 'v' . $pergel_version !== $pergel_tag ) {
		$pergel_problems[] = "The tag {$pergel_tag} does not name version {$pergel_version}.";
	}

	foreach ( array( 'theme/readme.txt', 'engine/readme.txt' ) as $pergel_readme ) {
		if ( '' === changelog( $pergel_readme, $pergel_version ) ) {
			$pergel_problems[] = "{$pergel_readme} has no changelog entry \"= {$pergel_version} =\".";
		}
	}

	foreach ( REQUIREMENTS as $pergel_header ) {
		$pergel_values = array();
		foreach ( HEADERS as $pergel_file ) {
			if ( 1 === preg_match( '/^(?: \* )?' . preg_quote( $pergel_header, '/' ) . ':\s*(\S+)$/m', read( $pergel_file ), $pergel_m ) ) {
				$pergel_values[ $pergel_file ] = $pergel_m[1];
			}
		}
		if ( count( $pergel_values ) < 3 || 1 !== count( array_unique( $pergel_values ) ) ) {
			$pergel_problems[] = "\"{$pergel_header}\" differs or is missing: " . json_encode( $pergel_values ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- CLI output.
		}
	}

	$pergel_shot = getimagesize( ROOT . '/theme/screenshot.png' );
	if ( false === $pergel_shot || array( 1200, 900, IMAGETYPE_PNG ) !== array( $pergel_shot[0], $pergel_shot[1], $pergel_shot[2] ) ) {
		$pergel_problems[] = 'theme/screenshot.png must be a 1200x900 PNG.';
	}

	if ( array() !== $pergel_problems ) {
		fail( implode( "\n", $pergel_problems ) );
	}
	echo "Version {$pergel_version}: consistent.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	exit( 0 );
}

if ( 'bump' === $pergel_command ) {
	$pergel_new = $argv[2] ?? '';
	if ( 1 !== preg_match( '/^\d+\.\d+\.\d+$/', $pergel_new ) ) {
		fail( 'Usage: php tools/release.php bump X.Y.Z' );
	}
	foreach ( VERSIONS as list( $pergel_file, $pergel_pattern ) ) {
		if ( str_ends_with( $pergel_file, '.pot' ) ) {
			continue; // Written by `composer pot` from the headers.
		}
		$pergel_contents = preg_replace_callback(
			$pergel_pattern,
			static fn ( array $m ): string => str_replace( $m[1], $pergel_new, $m[0] ),
			read( $pergel_file ),
			1,
			$pergel_count
		);
		if ( 1 !== $pergel_count || null === $pergel_contents ) {
			fail( "No version found in {$pergel_file}." );
		}
		file_put_contents( ROOT . '/' . $pergel_file, $pergel_contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Dev tool.
	}
	echo "Version {$pergel_new} written. Next: changelog entries in both readmes, composer pot, composer i18n, php tools/release.php check.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	exit( 0 );
}

if ( 'package' === $pergel_command ) {
	$pergel_dir = $argv[2] ?? '';
	if ( '' === $pergel_dir ) {
		fail( 'Usage: php tools/release.php package <dir>' );
	}
	if ( ! is_dir( $pergel_dir ) && ! mkdir( $pergel_dir, 0777, true ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Dev tool.
		fail( "Cannot create {$pergel_dir}." );
	}
	$pergel_version = version();
	foreach ( PARTS as $pergel_slug => $pergel_folder ) {
		$pergel_tar    = $pergel_dir . "/{$pergel_slug}.tar";
		$pergel_output = array();
		exec( 'git -C ' . escapeshellarg( ROOT ) . ' archive --format=tar -o ' . escapeshellarg( $pergel_tar ) . ' HEAD ' . escapeshellarg( $pergel_folder ) . ' 2>&1', $pergel_output, $pergel_code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Dev tool.
		if ( 0 !== $pergel_code ) {
			fail( "git archive failed:\n" . implode( "\n", $pergel_output ) );
		}
		$pergel_zip_file = $pergel_dir . "/{$pergel_slug}-{$pergel_version}.zip";
		$pergel_zip      = new \ZipArchive();
		if ( true !== $pergel_zip->open( $pergel_zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			fail( "Cannot write {$pergel_zip_file}." );
		}
		$pergel_count = 0;
		foreach ( new \RecursiveIteratorIterator( new \PharData( $pergel_tar ) ) as $pergel_entry ) {
			// Phar paths look like phar://<dir>/<slug>.tar/theme/style.css.
			$pergel_path  = str_replace( '\\', '/', (string) $pergel_entry->getPathname() );
			$pergel_mark  = "/{$pergel_slug}.tar/{$pergel_folder}/";
			$pergel_inner = substr( $pergel_path, (int) strpos( $pergel_path, $pergel_mark ) + strlen( $pergel_mark ) );
			$pergel_zip->addFromString( $pergel_slug . '/' . $pergel_inner, (string) $pergel_entry->getContent() );
			++$pergel_count;
		}
		$pergel_zip->close();
		unlink( $pergel_tar ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Dev tool.
		echo "{$pergel_zip_file}: {$pergel_count} files\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	}
	exit( 0 );
}

if ( 'notes' === $pergel_command ) {
	$pergel_version = version();
	echo "## Pergel (theme) {$pergel_version}\n\n" . changelog( 'theme/readme.txt', $pergel_version ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	echo "## Pergel Engine {$pergel_version}\n\n" . changelog( 'engine/readme.txt', $pergel_version ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	exit( 0 );
}

fail( 'Usage: php tools/release.php check [--tag=vX.Y.Z] | bump X.Y.Z | package <dir> | notes' );
