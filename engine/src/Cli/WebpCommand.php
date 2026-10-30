<?php
/**
 * `wp pergel webp` commands.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Cli;

use Pergel\Performance\Webp;
use Pergel\Settings\Settings;
use WP_CLI;

/**
 * Makes, counts and removes the WebP copies of uploaded pictures.
 * Uploads get their copies on their own; these commands are for pictures
 * uploaded before the setting was on.
 */
final class WebpCommand {

	/**
	 * Attachments read per query.
	 */
	private const BATCH = 100;

	/**
	 * Counts the JPEG and PNG files of image attachments and their copies.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp pergel webp status
	 *
	 * @param string[]              $args       Positional arguments (unused).
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function status( array $args, array $assoc_args ): void {
		unset( $args );
		$attachments = 0;
		$files       = 0;
		$copies      = 0;
		$bytes       = 0;
		$copy_bytes  = 0;
		foreach ( self::batches() as $ids ) {
			foreach ( $ids as $id ) {
				++$attachments;
				foreach ( Webp::attachment_files( $id ) as $file ) {
					if ( ! is_file( $file ) ) {
						continue;
					}
					++$files;
					$copy = Webp::sibling( $file );
					if ( is_file( $copy ) ) {
						++$copies;
						$bytes      += (int) filesize( $file );
						$copy_bytes += (int) filesize( $copy );
					}
				}
			}
		}
		$items = array(
			array(
				'key'   => 'setting',
				'value' => Settings::get( 'performance.webp' ) ? 'on' : 'off',
			),
			array(
				'key'   => 'attachments',
				'value' => (string) $attachments,
			),
			array(
				'key'   => 'files',
				'value' => (string) $files,
			),
			array(
				'key'   => 'copies',
				'value' => (string) $copies,
			),
			array(
				'key'   => 'originals with a copy',
				'value' => size_format( $bytes, 1 ),
			),
			array(
				'key'   => 'their copies',
				'value' => size_format( $copy_bytes, 1 ) . ( $bytes > 0 ? sprintf( ' (%d%%)', (int) round( 100 * $copy_bytes / $bytes ) ) : '' ),
			),
		);
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $items, array( 'key', 'value' ) );
	}

	/**
	 * Makes the missing WebP copies of every JPEG and PNG image attachment.
	 *
	 * A copy that comes out no smaller than its original is not kept, so
	 * the page goes on showing the original. Running it again only makes
	 * the copies that are missing or older than their file.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Make every copy again.
	 *
	 * [--dry-run]
	 * : Count the files that would get a copy, without making any.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pergel webp make --dry-run
	 *     wp pergel webp make
	 *
	 * @param string[]                   $args       Positional arguments (unused).
	 * @param array<string, string|bool> $assoc_args Associative arguments.
	 */
	public function make( array $args, array $assoc_args ): void {
		unset( $args );
		$force = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );
		$dry   = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		if ( ! $dry && ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			WP_CLI::error( 'The image editor on this server (GD or Imagick) cannot write WebP.' );
		}
		$total  = self::count();
		$counts = array();
		$saved  = 0;
		$done   = 0;
		WP_CLI::log( sprintf( '%d JPEG and PNG attachments.', $total ) );
		foreach ( self::batches() as $ids ) {
			foreach ( $ids as $id ) {
				foreach ( Webp::attachment_files( $id ) as $file ) {
					$copy = Webp::sibling( $file );
					if ( $dry ) {
						$result = ! is_file( $file ) ? 'missing' : ( ! $force && is_file( $copy ) && (int) filemtime( $copy ) >= (int) filemtime( $file ) ? 'kept' : 'to make' );
					} else {
						$result = Webp::make( $file, $force );
						if ( 'made' === $result ) {
							$saved += (int) filesize( $file ) - (int) filesize( $copy );
						}
					}
					$counts[ $result ] = ( $counts[ $result ] ?? 0 ) + 1;
				}
				++$done;
			}
			self::forget();
			if ( 0 === $done % 500 || $done === $total ) {
				WP_CLI::log( sprintf( '%d / %d', $done, $total ) );
			}
		}
		ksort( $counts );
		foreach ( $counts as $result => $count ) {
			WP_CLI::log( sprintf( '%-8s %d', $result . ':', $count ) );
		}
		if ( $dry ) {
			WP_CLI::success( 'Dry run, nothing was written.' );
			return;
		}
		if ( ! Settings::get( 'performance.webp' ) ) {
			WP_CLI::warning( 'The WebP setting is off, so pages go on showing the originals.' );
		}
		WP_CLI::success( sprintf( 'Made %d copies, %s smaller than their originals.', $counts['made'] ?? 0, size_format( max( 0, $saved ), 1 ) ) );
	}

	/**
	 * Deletes every WebP copy made for image attachments. The originals stay.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Do not ask for confirmation.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pergel webp remove --yes
	 *
	 * @param string[]                   $args       Positional arguments (unused).
	 * @param array<string, string|bool> $assoc_args Associative arguments.
	 */
	public function remove( array $args, array $assoc_args ): void {
		unset( $args );
		WP_CLI::confirm( 'Delete the WebP copies of all uploaded pictures?', $assoc_args );
		$removed = 0;
		foreach ( self::batches() as $ids ) {
			foreach ( $ids as $id ) {
				foreach ( Webp::attachment_files( $id ) as $file ) {
					$copy = Webp::sibling( $file );
					if ( is_file( $copy ) ) {
						wp_delete_file( $copy );
						++$removed;
					}
				}
			}
			self::forget();
		}
		WP_CLI::success( sprintf( 'Deleted %d copies.', $removed ) );
	}

	/**
	 * Number of JPEG and PNG attachments.
	 */
	private static function count(): int {
		$counts = (array) wp_count_attachments( Webp::TYPES );
		$total  = 0;
		foreach ( Webp::TYPES as $type ) {
			$total += (int) ( $counts[ $type ] ?? 0 );
		}
		return $total;
	}

	/**
	 * JPEG and PNG attachment IDs, a batch at a time. The commands do not
	 * add or delete attachments, so paging by ID is stable.
	 *
	 * @return \Generator<int, list<int>>
	 */
	private static function batches(): \Generator {
		$page = 1;
		do {
			$ids   = array_values(
				array_map(
					'intval',
					get_posts(
						array(
							'post_type'      => 'attachment',
							'post_status'    => 'any',
							'post_mime_type' => implode( ',', Webp::TYPES ),
							'fields'         => 'ids',
							'orderby'        => 'ID',
							'order'          => 'ASC',
							'posts_per_page' => self::BATCH,
							'paged'          => $page++,
							'no_found_rows'  => true,
						)
					)
				)
			);
			$found = count( $ids );
			if ( array() !== $ids ) {
				yield $ids;
			}
		} while ( self::BATCH === $found );
	}

	/**
	 * Frees the object cache between batches on large libraries.
	 */
	private static function forget(): void {
		if ( function_exists( 'wp_cache_flush_runtime' ) ) {
			wp_cache_flush_runtime();
		}
	}
}
