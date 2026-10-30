<?php
/**
 * WebP copies of uploaded pictures.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Performance;

use WP_HTML_Tag_Processor;

/**
 * Every JPEG or PNG file of an image attachment (the full size and each
 * sub-size) gets a WebP copy next to it, named after the whole file:
 * "photo-300x300.jpg" → "photo-300x300.jpg.webp". The attachment, its
 * metadata and the post content keep the original names; pages show the
 * copy's address instead, so switching the setting off brings the
 * originals back and old addresses never break. A copy that comes out
 * no smaller than its original is not kept.
 *
 * The copies are made when WordPress saves an attachment's metadata
 * (upload, regenerate, image editor) and by `wp pergel webp make` for
 * older uploads. Nothing is made while a page is shown.
 */
final class Webp {

	/**
	 * What the copy's name adds to the original's.
	 */
	public const SUFFIX = '.webp';

	/**
	 * Picture types that get a copy.
	 */
	public const TYPES = array( 'image/jpeg', 'image/png' );

	/**
	 * Files tried in this request, so a file whose copy came out bigger is
	 * not encoded again on each metadata save of the same upload.
	 *
	 * @var array<string, true>
	 */
	private static array $tried = array();

	/**
	 * Whether a copy exists, by file path.
	 *
	 * @var array<string, bool>
	 */
	private static array $exists = array();

	/**
	 * Upload folder URL and path, read on the first swap; empty when the
	 * folder has an error.
	 *
	 * @var array{url: string, dir: string}|array{}|null
	 */
	private static ?array $uploads = null;

	/**
	 * The copy's path for a file.
	 *
	 * @param string $file Original file path.
	 */
	public static function sibling( string $file ): string {
		return $file . self::SUFFIX;
	}

	/**
	 * The files of an attachment that get a copy: the full size and each
	 * sub-size, without the unscaled original WordPress keeps aside.
	 *
	 * @param array<string, mixed> $meta Attachment metadata.
	 * @param string               $dir  Upload folder path, without the slash.
	 * @return list<string> File paths.
	 */
	public static function files( array $meta, string $dir ): array {
		$file = isset( $meta['file'] ) && is_string( $meta['file'] ) ? $meta['file'] : '';
		if ( '' === $file || ! self::is_picture_name( $file ) ) {
			return array();
		}
		$folder = $dir . '/' . ( '.' === dirname( $file ) ? '' : dirname( $file ) . '/' );
		$files  = array( $dir . '/' . $file );
		foreach ( is_array( $meta['sizes'] ?? null ) ? $meta['sizes'] : array() as $size ) {
			if ( ! is_array( $size ) || ! isset( $size['file'] ) || ! is_string( $size['file'] ) ) {
				continue;
			}
			$type = $size['mime-type'] ?? '';
			if ( is_string( $type ) && '' !== $type ? in_array( $type, self::TYPES, true ) : self::is_picture_name( $size['file'] ) ) {
				$files[] = $folder . $size['file'];
			}
		}
		return array_values( array_unique( $files ) );
	}

	/**
	 * Whether a file name ends in a picture type that gets a copy.
	 *
	 * @param string $name File name or path, without a query.
	 */
	public static function is_picture_name( string $name ): bool {
		return 1 === preg_match( '/\.(?:jpe?g|png)$/i', $name );
	}

	/**
	 * The address of a file's copy, if the copy exists.
	 *
	 * @param string                 $url    Picture address.
	 * @param string                 $base   Upload folder URL.
	 * @param string                 $dir    Upload folder path.
	 * @param callable(string): bool $exists Whether a file path exists.
	 * @return string The copy's address, or $url unchanged.
	 */
	public static function swap_url( string $url, string $base, string $dir, callable $exists ): string {
		// http and https addresses of the same upload are the same file.
		$strip = static fn ( string $value ): string => (string) preg_replace( '#^(?:https?:)?//#i', '//', $value );
		$base  = rtrim( $strip( $base ), '/' ) . '/';
		$plain = $strip( $url );
		if ( ! str_starts_with( $plain, $base ) ) {
			return $url;
		}
		$cut  = strcspn( $url, '?#' );
		$path = substr( $url, 0, $cut );
		$tail = rawurldecode( substr( $strip( $path ), strlen( $base ) ) );
		if ( ! self::is_picture_name( $tail ) || str_contains( $tail, '..' ) ) {
			return $url;
		}
		if ( ! $exists( self::sibling( rtrim( $dir, '/\\' ) . '/' . $tail ) ) ) {
			return $url;
		}
		return $path . self::SUFFIX . substr( $url, $cut );
	}

	/**
	 * Makes the copy of one file.
	 *
	 * @param string $file  Original file path.
	 * @param bool   $force Make it again even if a newer copy exists.
	 * @return string 'made', 'kept' (a newer copy exists), 'bigger' (the
	 *                copy was not smaller and was dropped), 'missing' or
	 *                'failed'.
	 */
	public static function make( string $file, bool $force = false ): string {
		$copy = self::sibling( $file );
		if ( ! is_file( $file ) ) {
			return 'missing';
		}
		if ( ! $force && is_file( $copy ) && (int) filemtime( $copy ) >= (int) filemtime( $file ) ) {
			return 'kept';
		}
		if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			return 'failed';
		}
		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return 'failed';
		}
		/**
		 * Filters the quality of the WebP copies, 1–100.
		 *
		 * @param int $quality Quality; 82 by default.
		 */
		$quality = max( 1, min( 100, (int) apply_filters( 'pergel/performance/webp_quality', 82 ) ) );
		if ( $editor instanceof \WP_Image_Editor_GD && self::is_png( $file ) ) {
			// GD loads palette and grey PNGs as palette images, which it
			// cannot write as WebP: it warns and leaves a broken file.
			$written = self::gd_truecolor( $file, $copy, $quality );
		} else {
			$editor->set_quality( $quality );
			$written = ! is_wp_error( $editor->save( $copy, 'image/webp' ) );
		}
		clearstatcache( true, $copy );
		if ( ! $written || ! self::is_image( $copy ) ) {
			if ( is_file( $copy ) ) {
				wp_delete_file( $copy );
			}
			return 'failed';
		}
		if ( (int) filesize( $copy ) >= (int) filesize( $file ) ) {
			wp_delete_file( $copy );
			return 'bigger';
		}
		return 'made';
	}

	/**
	 * Makes the copies of an attachment's files.
	 *
	 * @param int  $id    Attachment ID.
	 * @param bool $force Make them again even if newer copies exist.
	 * @return array<string, int> Count per make() result.
	 */
	public static function attachment( int $id, bool $force = false ): array {
		$counts = array();
		foreach ( self::attachment_files( $id ) as $file ) {
			$result            = self::make( $file, $force );
			$counts[ $result ] = ( $counts[ $result ] ?? 0 ) + 1;
		}
		return $counts;
	}

	/**
	 * The files of an attachment that get a copy.
	 *
	 * @param int                       $id   Attachment ID.
	 * @param array<string, mixed>|null $meta Metadata, if the caller has it.
	 * @return list<string>
	 */
	public static function attachment_files( int $id, ?array $meta = null ): array {
		if ( ! in_array( get_post_mime_type( $id ), self::TYPES, true ) ) {
			return array();
		}
		$meta    = $meta ?? wp_get_attachment_metadata( $id );
		$uploads = wp_get_upload_dir();
		if ( ! is_array( $meta ) || ! empty( $uploads['error'] ) ) {
			return array();
		}
		return self::files( $meta, rtrim( (string) $uploads['basedir'], '/\\' ) );
	}

	/**
	 * Makes the copies when WordPress saves an attachment's metadata. During
	 * an upload this runs after each new sub-size, so only files not tried
	 * yet in this request are encoded.
	 *
	 * @param mixed $data Attachment metadata.
	 * @param mixed $id   Attachment ID.
	 * @return mixed The metadata, unchanged.
	 */
	public static function on_metadata( mixed $data, mixed $id ): mixed {
		if ( ! is_array( $data ) || ! is_numeric( $id ) ) {
			return $data;
		}
		foreach ( self::attachment_files( (int) $id, $data ) as $file ) {
			if ( isset( self::$tried[ $file ] ) ) {
				continue;
			}
			self::$tried[ $file ] = true;
			self::make( $file );
		}
		return $data;
	}

	/**
	 * Deletes a file's copy along with it.
	 *
	 * @param mixed $file Path WordPress is about to delete.
	 * @return mixed The path, unchanged.
	 */
	public static function on_delete( mixed $file ): mixed {
		if ( is_string( $file ) && self::is_picture_name( $file ) && is_file( self::sibling( $file ) ) ) {
			wp_delete_file( self::sibling( $file ) );
		}
		return $file;
	}

	/**
	 * Swaps an attachment picture's address for its copy.
	 *
	 * @param mixed $image Array of URL, width, height and whether it is a sub-size, or false.
	 * @return mixed
	 */
	public static function image_src( mixed $image ): mixed {
		if ( is_array( $image ) && isset( $image[0] ) && is_string( $image[0] ) ) {
			$image[0] = self::swap( $image[0] );
		}
		return $image;
	}

	/**
	 * Swaps the addresses of a srcset for their copies.
	 *
	 * @param mixed $sources Sources by width, each with a 'url'.
	 * @return mixed
	 */
	public static function srcset( mixed $sources ): mixed {
		if ( ! is_array( $sources ) ) {
			return $sources;
		}
		foreach ( $sources as $width => $source ) {
			if ( is_array( $source ) && isset( $source['url'] ) && is_string( $source['url'] ) ) {
				$sources[ $width ]['url'] = self::swap( $source['url'] );
			}
		}
		return $sources;
	}

	/**
	 * Swaps the src and srcset addresses of an image in content. Content
	 * also covers templates and template parts in a block theme.
	 *
	 * @param mixed $html An img tag.
	 * @return mixed
	 */
	public static function content_img( mixed $html ): mixed {
		if ( ! is_string( $html ) || ! str_contains( $html, '<img' ) ) {
			return $html;
		}
		$tag = new WP_HTML_Tag_Processor( $html );
		if ( ! $tag->next_tag( array( 'tag_name' => 'img' ) ) ) {
			return $html;
		}
		$src = $tag->get_attribute( 'src' );
		if ( is_string( $src ) ) {
			$tag->set_attribute( 'src', self::swap( $src ) );
		}
		$srcset = $tag->get_attribute( 'srcset' );
		if ( is_string( $srcset ) && '' !== $srcset ) {
			$parts = array();
			foreach ( explode( ',', $srcset ) as $candidate ) {
				$candidate = trim( $candidate );
				$space     = strcspn( $candidate, " \t\n" );
				$parts[]   = self::swap( substr( $candidate, 0, $space ) ) . substr( $candidate, $space );
			}
			$tag->set_attribute( 'srcset', implode( ', ', $parts ) );
		}
		return $tag->get_updated_html();
	}

	/**
	 * The copy's address for an uploaded picture, for themes and plugins
	 * that print addresses the image filters never see (block attributes,
	 * JSON for a script). Outside the front end, or with the setting off,
	 * the address comes back as it is.
	 *
	 * @param string $url Picture address.
	 */
	public static function url( string $url ): string {
		return self::swap( $url );
	}

	/**
	 * The copy's address for a picture on this page, if the copy exists.
	 * Addresses printed in the head (sharing images, structured data)
	 * stay as they are: not every network shows WebP.
	 *
	 * @param string $url Picture address.
	 */
	private static function swap( string $url ): string {
		// Module::serve_webp() adds the filters on the front end only.
		if ( doing_action( 'wp_head' ) || false === has_filter( 'wp_content_img_tag', array( self::class, 'content_img' ) ) ) {
			return $url;
		}
		if ( null === self::$uploads ) {
			$uploads       = wp_get_upload_dir();
			self::$uploads = empty( $uploads['error'] ) ? array(
				'url' => (string) $uploads['baseurl'],
				'dir' => (string) $uploads['basedir'],
			) : array();
		}
		if ( array() === self::$uploads ) {
			return $url;
		}
		return self::swap_url( $url, self::$uploads['url'], self::$uploads['dir'], array( self::class, 'exists' ) );
	}

	/**
	 * Whether a file starts like a PNG, whatever its name says.
	 *
	 * @param string $file File path.
	 */
	public static function is_png( string $file ): bool {
		$head = is_file( $file ) ? (string) file_get_contents( $file, false, null, 0, 8 ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file header.
		return "\x89PNG\r\n\x1a\n" === $head;
	}

	/**
	 * Writes a PNG as WebP through GD, made true colour first.
	 *
	 * @param string $file    PNG path.
	 * @param string $copy    WebP path.
	 * @param int    $quality Quality, 1–100.
	 */
	private static function gd_truecolor( string $file, string $copy, int $quality ): bool {
		if ( ! function_exists( 'imagecreatefrompng' ) || ! function_exists( 'imagewebp' ) ) {
			return false;
		}
		$image = imagecreatefrompng( $file );
		if ( false === $image || ! imagepalettetotruecolor( $image ) ) {
			return false;
		}
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		return imagewebp( $image, $copy, $quality );
	}

	/**
	 * Whether a written file reads back as a picture with a size.
	 *
	 * @param string $file File path.
	 */
	private static function is_image( string $file ): bool {
		if ( ! is_file( $file ) || (int) filesize( $file ) < 32 ) {
			return false;
		}
		$size = wp_getimagesize( $file );
		return is_array( $size ) && $size[0] > 0 && $size[1] > 0;
	}

	/**
	 * Whether a copy exists, remembered for the request.
	 *
	 * @param string $path File path.
	 */
	public static function exists( string $path ): bool {
		if ( ! isset( self::$exists[ $path ] ) ) {
			self::$exists[ $path ] = is_file( $path );
		}
		return self::$exists[ $path ];
	}
}
