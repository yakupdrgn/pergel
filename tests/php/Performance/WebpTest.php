<?php
/**
 * WebP copy naming and address tests.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Performance;

use Pergel\Performance\Webp;
use PHPUnit\Framework\TestCase;

/**
 * Covers which files get a copy and when an address is swapped for its
 * copy's. File checks use a list of paths instead of the disk.
 */
final class WebpTest extends TestCase {

	private const BASE = 'https://example.com/wp-content/uploads';
	private const DIR  = '/srv/www/wp-content/uploads';

	/**
	 * swap_url() with the given copies on "disk".
	 *
	 * @param string       $url    Picture address.
	 * @param list<string> $copies Copy paths that exist.
	 */
	private static function swap( string $url, array $copies ): string {
		return Webp::swap_url( $url, self::BASE, self::DIR, static fn ( string $path ): bool => in_array( $path, $copies, true ) );
	}

	public function test_copy_is_named_after_the_whole_file(): void {
		$this->assertSame( '/a/photo.jpg.webp', Webp::sibling( '/a/photo.jpg' ) );
		// A JPEG and a PNG with the same name get different copies.
		$this->assertNotSame( Webp::sibling( '/a/photo.jpg' ), Webp::sibling( '/a/photo.png' ) );
	}

	public function test_picture_names(): void {
		foreach ( array( 'a.jpg', 'a.JPEG', 'a.png', 'b/c.Png' ) as $name ) {
			$this->assertTrue( Webp::is_picture_name( $name ), $name );
		}
		foreach ( array( 'a.gif', 'a.webp', 'a.jpg.webp', 'a.svg', 'a.pdf', 'jpg' ) as $name ) {
			$this->assertFalse( Webp::is_picture_name( $name ), $name );
		}
	}

	public function test_files_of_an_attachment(): void {
		$meta = array(
			'file'           => '2026/10/photo-scaled.jpg',
			'original_image' => 'photo.jpg',
			'sizes'          => array(
				'thumbnail' => array(
					'file'      => 'photo-150x150.jpg',
					'mime-type' => 'image/jpeg',
				),
				'medium'    => array(
					'file'      => 'photo-300x200.jpg',
					'mime-type' => 'image/jpeg',
				),
				'same'      => array(
					'file'      => 'photo-150x150.jpg',
					'mime-type' => 'image/jpeg',
				),
				'converted' => array(
					'file'      => 'photo-600x400.avif',
					'mime-type' => 'image/avif',
				),
				'broken'    => 'photo.jpg',
			),
		);
		$this->assertSame(
			array(
				self::DIR . '/2026/10/photo-scaled.jpg',
				self::DIR . '/2026/10/photo-150x150.jpg',
				self::DIR . '/2026/10/photo-300x200.jpg',
			),
			Webp::files( $meta, self::DIR ),
			'Full size and sub-sizes once each; not the unscaled original or other types.'
		);
		$this->assertSame( array( self::DIR . '/logo.png' ), Webp::files( array( 'file' => 'logo.png' ), self::DIR ), 'An upload without year/month folders.' );
		$this->assertSame( array(), Webp::files( array( 'file' => '2026/10/anim.gif' ), self::DIR ) );
		$this->assertSame( array(), Webp::files( array(), self::DIR ) );
	}

	public function test_swaps_an_upload_with_a_copy(): void {
		$copy = self::DIR . '/2026/10/photo-300x200.jpg.webp';
		$this->assertSame( self::BASE . '/2026/10/photo-300x200.jpg.webp', self::swap( self::BASE . '/2026/10/photo-300x200.jpg', array( $copy ) ) );
		$this->assertSame( self::BASE . '/2026/10/photo-300x200.jpg', self::swap( self::BASE . '/2026/10/photo-300x200.jpg', array() ), 'No copy (it came out bigger): the original stays.' );
	}

	public function test_keeps_query_scheme_and_encoding(): void {
		$copies = array(
			self::DIR . '/2026/10/photo.jpg.webp',
			self::DIR . '/2026/10/my photo.png.webp',
		);
		$this->assertSame( self::BASE . '/2026/10/photo.jpg.webp?ver=2#x', self::swap( self::BASE . '/2026/10/photo.jpg?ver=2#x', $copies ) );
		$this->assertSame( 'http://example.com/wp-content/uploads/2026/10/photo.jpg.webp', self::swap( 'http://example.com/wp-content/uploads/2026/10/photo.jpg', $copies ), 'http and https are the same upload.' );
		$this->assertSame( '//example.com/wp-content/uploads/2026/10/photo.jpg.webp', self::swap( '//example.com/wp-content/uploads/2026/10/photo.jpg', $copies ) );
		$this->assertSame( self::BASE . '/2026/10/my%20photo.png.webp', self::swap( self::BASE . '/2026/10/my%20photo.png', $copies ) );
	}

	public function test_leaves_other_addresses_alone(): void {
		$all = static fn (): bool => true;
		foreach ( array(
			'https://cdn.example.org/wp-content/uploads/2026/10/photo.jpg',
			'https://example.com/wp-content/themes/t/assets/hero.jpg',
			self::BASE . '/2026/10/anim.gif',
			self::BASE . '/2026/10/photo.jpg.webp',
			self::BASE . '/2026/10/../../../secret.png',
			'data:image/png;base64,AAAA',
			'',
		) as $url ) {
			$this->assertSame( $url, Webp::swap_url( $url, self::BASE, self::DIR, $all ), $url );
		}
	}

	public function test_knows_pngs_by_their_bytes(): void {
		$dir   = sys_get_temp_dir();
		$sign  = chr( 0x89 ) . 'PNG' . chr( 13 ) . chr( 10 ) . chr( 26 ) . chr( 10 );
		$head  = static fn ( int $type ): string => $sign . pack( 'N', 13 ) . 'IHDR' . pack( 'NN', 16, 16 ) . chr( 8 ) . chr( $type ) . str_repeat( chr( 0 ), 3 );
		$files = array(
			'palette' => $head( 3 ),
			'rgba'    => $head( 6 ),
			'short'   => substr( $sign, 0, 4 ),
			'jpeg'    => chr( 0xFF ) . chr( 0xD8 ) . chr( 0xFF ) . str_repeat( chr( 0 ), 30 ),
		);
		foreach ( $files as $name => $bytes ) {
			file_put_contents( $dir . '/pergel-' . $name . '.png', $bytes );
		}
		try {
			$this->assertTrue( Webp::is_png( $dir . '/pergel-palette.png' ) );
			$this->assertTrue( Webp::is_png( $dir . '/pergel-rgba.png' ) );
			$this->assertFalse( Webp::is_png( $dir . '/pergel-short.png' ) );
			$this->assertFalse( Webp::is_png( $dir . '/pergel-jpeg.png' ) );
			$this->assertFalse( Webp::is_png( $dir . '/pergel-missing.png' ) );
		} finally {
			foreach ( array_keys( $files ) as $name ) {
				unlink( $dir . '/pergel-' . $name . '.png' );
			}
		}
	}
}
