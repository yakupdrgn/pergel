<?php
/**
 * Placeholder image tests.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Starters;

use Pergel\Starters\Images;
use PHPUnit\Framework\TestCase;

/**
 * Colours and the drawn file, without WordPress.
 */
final class ImagesTest extends TestCase {

	public function test_colours_are_read_and_mixed(): void {
		$this->assertSame( array( 255, 0, 51 ), Images::rgb( '#ff0033' ) );
		$this->assertSame( array( 255, 0, 51 ), Images::rgb( '#F03' ) );
		$this->assertNull( Images::rgb( 'red' ) );
		$this->assertNull( Images::rgb( '#12345' ) );
		$this->assertSame( '#808080', Images::mix( '#000000', '#ffffff', 0.5 ) );
		$this->assertSame( '#000000', Images::mix( '#000000', '#ffffff', 0 ) );
	}

	public function test_palette_falls_back_without_wordpress(): void {
		$palette = Images::palette();

		$this->assertSame( array( 'base', 'surface', 'contrast', 'muted', 'line', 'accent' ), array_keys( $palette ) );
		$this->assertCount( 5, Images::tones( $palette ) );
	}

	public function test_draws_a_file_of_the_asked_size(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is missing.' );
		}
		$file = sys_get_temp_dir() . '/pergel-images-test-' . uniqid();
		$path = Images::draw( $file, 'mug', '#1f4fd1', '#f4f5f7', 40, 30 );

		$this->assertSame( $file . ( function_exists( 'imagewebp' ) ? '.webp' : '.png' ), $path );
		$size = getimagesize( $path );
		$this->assertIsArray( $size );
		$this->assertSame( array( 40, 30 ), array( $size[0], $size[1] ) );

		// The corner shows the ground, the middle the shape.
		$image = imagecreatefromstring( (string) file_get_contents( $path ) );
		$this->assertNotFalse( $image );
		$corner = imagecolorsforindex( $image, imagecolorat( $image, 0, 0 ) );
		$this->assertEqualsWithDelta( 244, $corner['red'], 4 );
		unlink( $path );
	}
}
