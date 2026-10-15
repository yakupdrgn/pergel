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
}
