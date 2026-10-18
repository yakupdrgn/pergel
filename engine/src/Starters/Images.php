<?php
/**
 * Placeholder images for starters.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Starters;

use InvalidArgumentException;

/**
 * Draws a plain placeholder with GD: one flat shape on a light ground, in
 * the active style's colours, so a starter brings no image files. Saved as
 * WebP, or PNG where GD has no WebP. The shape follows the item's key, so
 * the same item gets the same picture.
 */
final class Images {

	private const SHAPES = array( 'circle', 'square', 'arch', 'diamond', 'ring', 'pill' );

	/**
	 * The active style's colours by slug, with fallbacks for missing ones.
	 *
	 * @return array{base: string, surface: string, contrast: string, muted: string, line: string, accent: string}
	 */
	public static function palette(): array {
		$colors = array(
			'base'     => '#ffffff',
			'surface'  => '#f4f5f7',
			'contrast' => '#16181d',
			'muted'    => '#5b6270',
			'line'     => '#dde0e6',
			'accent'   => '#1f4fd1',
		);
		$theme  = function_exists( 'wp_get_global_settings' ) ? wp_get_global_settings( array( 'color', 'palette', 'theme' ) ) : array();
		foreach ( is_array( $theme ) ? $theme : array() as $preset ) {
			$slug = is_array( $preset ) ? ( $preset['slug'] ?? '' ) : '';
			if ( isset( $colors[ $slug ] ) && null !== self::rgb( (string) ( $preset['color'] ?? '' ) ) ) {
				$colors[ $slug ] = (string) $preset['color'];
			}
		}
		return $colors;
	}

	/**
	 * Colours for items without one of their own: the accent and the text
	 * colours, plus the accent mixed towards each.
	 *
	 * @param array<string, string> $palette Output of palette().
	 * @return list<string>
	 */
	public static function tones( array $palette ): array {
		return array(
			$palette['accent'],
			$palette['contrast'],
			self::mix( $palette['accent'], $palette['base'], 0.45 ),
			$palette['muted'],
			self::mix( $palette['accent'], $palette['contrast'], 0.5 ),
		);
	}

	/**
	 * Writes a placeholder image.
	 *
	 * @param string $file   Path without extension.
	 * @param string $seed   What picks the shape, e.g. the item key.
	 * @param string $color  Shape colour, #rrggbb.
	 * @param string $ground Background colour, #rrggbb.
	 * @param int    $width  Width in pixels.
	 * @param int    $height Height in pixels.
	 * @return string The written file, with its extension.
	 * @throws InvalidArgumentException When GD is missing or the file cannot be written.
	 */
	public static function draw( string $file, string $seed, string $color, string $ground, int $width = 800, int $height = 800 ): string {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			throw new InvalidArgumentException( 'The shop starter draws its images with GD; the PHP gd extension is missing.' );
		}
		// Drawn at twice the size and scaled down, which smooths the edges.
		$final  = array( max( 1, $width ), max( 1, $height ) );
		$width  = $final[0] * 2;
		$height = $final[1] * 2;
		$image  = imagecreatetruecolor( $width, $height );
		imagealphablending( $image, true );
		$fill = static function ( string $hex ) use ( $image ): int {
			[ $r, $g, $b ] = self::rgb( $hex ) ?? array( 128, 128, 128 );
			return (int) imagecolorallocate( $image, $r, $g, $b );
		};
		imagefilledrectangle( $image, 0, 0, $width, $height, $fill( $ground ) );

		$cx   = intdiv( $width, 2 );
		$cy   = intdiv( $height, 2 );
		$size = (int) round( min( $width, $height ) * 0.46 );
		$ink  = $fill( $color );

		// A soft floor shadow under the shape.
		imagefilledellipse( $image, $cx, $cy + (int) round( $size * 0.62 ), (int) round( $size * 0.95 ), (int) round( $size * 0.12 ), $fill( self::mix( $ground, '#000000', 0.08 ) ) );

		$half = intdiv( $size, 2 );
		switch ( self::SHAPES[ crc32( $seed ) % count( self::SHAPES ) ] ) {
			case 'circle':
				imagefilledellipse( $image, $cx, $cy, $size, $size, $ink );
				break;
			case 'square':
				self::rounded( $image, $cx - $half, $cy - $half, $cx + $half, $cy + $half, (int) round( $size * 0.12 ), $ink );
				break;
			case 'arch':
				imagefilledrectangle( $image, $cx - $half, $cy, $cx + $half, $cy + $half, $ink );
				imagefilledellipse( $image, $cx, $cy, $size, $size, $ink );
				break;
			case 'diamond':
				imagefilledpolygon( $image, array( $cx, $cy - $half, $cx + $half, $cy, $cx, $cy + $half, $cx - $half, $cy ), $ink );
				break;
			case 'ring':
				imagefilledellipse( $image, $cx, $cy, $size, $size, $ink );
				imagefilledellipse( $image, $cx, $cy, (int) round( $size * 0.5 ), (int) round( $size * 0.5 ), $fill( $ground ) );
				break;
			default:
				self::rounded( $image, $cx - $half, $cy - (int) round( $half * 0.5 ), $cx + $half, $cy + (int) round( $half * 0.5 ), (int) round( $half * 0.5 ), $ink );
		}

		$small = imagecreatetruecolor( $final[0], $final[1] );
		imagecopyresampled( $small, $image, 0, 0, 0, 0, $final[0], $final[1], $width, $height );
		$image = $small;

		$webp = function_exists( 'imagewebp' );
		$path = $file . ( $webp ? '.webp' : '.png' );
		$done = $webp ? imagewebp( $image, $path, 82 ) : imagepng( $image, $path, 9 );
		if ( ! $done ) {
			throw new InvalidArgumentException( esc_html( "Could not write $path." ) );
		}
		return $path;
	}

	/**
	 * A colour between two others.
	 *
	 * @param string $from   #rrggbb.
	 * @param string $to     #rrggbb.
	 * @param float  $amount 0 = $from, 1 = $to.
	 */
	public static function mix( string $from, string $to, float $amount ): string {
		$a = self::rgb( $from ) ?? array( 0, 0, 0 );
		$b = self::rgb( $to ) ?? array( 0, 0, 0 );
		$c = array();
		foreach ( array( 0, 1, 2 ) as $i ) {
			$c[] = (int) round( $a[ $i ] + ( $b[ $i ] - $a[ $i ] ) * $amount );
		}
		return vsprintf( '#%02x%02x%02x', $c );
	}

	/**
	 * Red, green and blue of a #rgb or #rrggbb colour; null for anything else.
	 *
	 * @param string $hex Colour.
	 * @return array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}|null
	 */
	public static function rgb( string $hex ): ?array {
		if ( ! preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', trim( $hex ), $m ) ) {
			return null;
		}
		$h       = 3 === strlen( $m[1] ) ? $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2] : $m[1];
		$channel = static fn( int $at ): int => max( 0, min( 255, (int) hexdec( substr( $h, $at, 2 ) ) ) );
		return array( $channel( 0 ), $channel( 2 ), $channel( 4 ) );
	}

	/**
	 * A filled rectangle with round corners.
	 *
	 * @param \GdImage $image  Image.
	 * @param int      $x1     Left.
	 * @param int      $y1     Top.
	 * @param int      $x2     Right.
	 * @param int      $y2     Bottom.
	 * @param int      $radius Corner radius.
	 * @param int      $color  Allocated colour.
	 */
	private static function rounded( \GdImage $image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color ): void {
		$radius = min( $radius, intdiv( $x2 - $x1, 2 ), intdiv( $y2 - $y1, 2 ) );
		imagefilledrectangle( $image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color );
		imagefilledrectangle( $image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color );
		foreach ( array( array( $x1 + $radius, $y1 + $radius ), array( $x2 - $radius, $y1 + $radius ), array( $x1 + $radius, $y2 - $radius ), array( $x2 - $radius, $y2 - $radius ) ) as [ $x, $y ] ) {
			imagefilledellipse( $image, $x, $y, $radius * 2, $radius * 2, $color );
		}
	}
}
