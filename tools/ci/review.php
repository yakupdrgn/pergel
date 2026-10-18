<?php
/**
 * Reads Theme Check's JSON and fails on REQUIRED and WARNING lines, apart
 * from the accepted ones below (CI review job, tools/ci/review.sh).
 *
 * Usage: php tools/ci/review.php theme-check.json
 *
 * @package Pergel
 */

declare( strict_types=1 );

// Each line names why the finding is accepted.
const PERGEL_ACCEPTED = array(
	// WordPress's compiled translations (.l10n.php) separate a context from
	// its string with \x04; the file is written by wp i18n make-php.
	'/^Non-printable characters were found in the languages\/[a-z]{2}_[A-Z]{2}\.l10n\.php file\./',
);

$pergel_lines = json_decode( (string) file_get_contents( $argv[1] ?? '' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
if ( ! is_array( $pergel_lines ) ) {
	fwrite( STDERR, "::error::Theme Check gave no JSON.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output.
	exit( 1 );
}

$pergel_failed = false;
foreach ( $pergel_lines as $pergel_line ) {
	$pergel_type  = (string) ( $pergel_line['type'] ?? '' );
	$pergel_value = (string) ( $pergel_line['value'] ?? '' );
	$pergel_short = strlen( $pergel_value ) > 300 ? substr( $pergel_value, 0, 300 ) . '…' : $pergel_value;
	$pergel_known = false;
	foreach ( PERGEL_ACCEPTED as $pergel_pattern ) {
		$pergel_known = $pergel_known || 1 === preg_match( $pergel_pattern, $pergel_value );
	}
	if ( in_array( $pergel_type, array( 'REQUIRED', 'WARNING' ), true ) && ! $pergel_known ) {
		echo "::error::Theme Check $pergel_type: $pergel_short\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
		$pergel_failed = true;
	} else {
		echo $pergel_type . ( $pergel_known ? ' (accepted)' : '' ) . ": $pergel_short\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	}
}
exit( $pergel_failed ? 1 : 0 );
