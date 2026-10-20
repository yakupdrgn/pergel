<?php
/**
 * SVG attachments.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Core;

/**
 * WordPress blocks SVG uploads, because an SVG file can carry script. A
 * plugin such as Safe SVG allows them and cleans every file on upload;
 * Pergel cleans nothing itself, it only knows where SVG cannot be used.
 */
final class Svg {

	public const MIME = 'image/svg+xml';

	/**
	 * Image types every social network shows as a share image.
	 */
	public const SHAREABLE = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );

	/**
	 * Whether an attachment is an SVG image.
	 *
	 * @param int $id Attachment id.
	 */
	public static function is( int $id ): bool {
		return $id > 0 && self::MIME === get_post_mime_type( $id );
	}

	/**
	 * Whether the current user can upload SVG files here.
	 */
	public static function uploads(): bool {
		return in_array( self::MIME, get_allowed_mime_types(), true );
	}

	/**
	 * Whether WordPress takes this SVG file through media_handle_sideload()
	 * for the current user. A copy goes through the same checks: the
	 * sideload filters (Safe SVG checks the user there, allows the type and
	 * cleans the copy) and the file type check. uploads() is not enough:
	 * Safe SVG allows the type only during an upload or on its media screens.
	 *
	 * @param string $file SVG file.
	 */
	public static function sideloads( string $file ): bool {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$name = 'pergel-check.svg';
		$tmp  = wp_tempnam( $name );
		if ( ! copy( $file, $tmp ) ) {
			wp_delete_file( $tmp );
			return false;
		}
		$fields = array(
			'name'     => $name,
			'type'     => self::MIME,
			'tmp_name' => $tmp,
			'error'    => 0,
			'size'     => (int) filesize( $tmp ),
		);
		$upload = apply_filters( 'wp_handle_sideload_prefilter', $fields ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's filter, run as a sideload runs it.
		$type   = wp_check_filetype_and_ext( $tmp, $name )['type'];
		wp_delete_file( $tmp );
		return is_array( $upload ) && empty( $upload['error'] ) && self::MIME === $type;
	}
}
