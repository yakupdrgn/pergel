<?php
/**
 * Converts design token values to CSS strings and checks colour contrast.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tokens;

/**
 * Pure functions, no WordPress calls: used by WP-CLI and by tools/tokens.php in CI.
 */
final class Css {

	/**
	 * Root font size used to convert rem to px for fluid calculations.
	 */
	private const ROOT_PX = 16.0;

	/**
	 * Units accepted in dimension tokens. DTCG allows px and rem; em and %
	 * are accepted for radius and layout values.
	 */
	private const UNITS = array( 'px', 'rem', 'em', '%' );

	/**
	 * CSS generic families and keywords that must not be quoted.
	 */
	private const GENERIC_FAMILIES = array(
		'serif',
		'sans-serif',
		'monospace',
		'cursive',
		'fantasy',
		'system-ui',
		'ui-serif',
		'ui-sans-serif',
		'ui-monospace',
		'ui-rounded',
		'math',
		'emoji',
		'fangsong',
		'-apple-system',
		'blinkmacsystemfont',
		'inherit',
		'initial',
	);

	/**
	 * Parses a colour token value: "#rgb", "#rrggbb", "#rrggbbaa" or a DTCG
	 * 2025.10 colour object in the srgb colour space.
	 *
	 * @param mixed $value Token value.
	 * @return array{0: float, 1: float, 2: float, 3: float} r, g, b in 0..1 and alpha.
	 * @throws \InvalidArgumentException When the value is not a supported colour.
	 */
	public static function parse_color( mixed $value ): array {
		if ( is_string( $value ) && preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value, $m ) ) {
			$hex = strtolower( $m[1] );
			if ( 3 === strlen( $hex ) ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}
			$alpha = 8 === strlen( $hex ) ? hexdec( substr( $hex, 6, 2 ) ) / 255 : 1.0;
			return array(
				hexdec( substr( $hex, 0, 2 ) ) / 255,
				hexdec( substr( $hex, 2, 2 ) ) / 255,
				hexdec( substr( $hex, 4, 2 ) ) / 255,
				(float) $alpha,
			);
		}
		if ( is_array( $value ) && 'srgb' === ( $value['colorSpace'] ?? null ) && isset( $value['components'] ) && is_array( $value['components'] ) && 3 === count( $value['components'] ) ) {
			$c = array_values( $value['components'] );
			foreach ( $c as $component ) {
				if ( ( ! is_int( $component ) && ! is_float( $component ) ) || $component < 0 || $component > 1 ) {
					throw new \InvalidArgumentException( 'srgb components must be numbers between 0 and 1' );
				}
			}
			$alpha = $value['alpha'] ?? 1;
			if ( ( ! is_int( $alpha ) && ! is_float( $alpha ) ) || $alpha < 0 || $alpha > 1 ) {
				throw new \InvalidArgumentException( 'alpha must be a number between 0 and 1' );
			}
			return array( (float) $c[0], (float) $c[1], (float) $c[2], (float) $alpha );
		}
		throw new \InvalidArgumentException( 'expected "#rrggbb", "#rrggbbaa" or {"colorSpace":"srgb","components":[r,g,b]}' );
	}

	/**
	 * Colour as CSS: #rrggbb, or #rrggbbaa when translucent.
	 *
	 * @param mixed $value Token value.
	 */
	public static function color( mixed $value ): string {
		$rgba = self::parse_color( $value );
		$hex  = '#';
		foreach ( array( $rgba[0], $rgba[1], $rgba[2] ) as $channel ) {
			$hex .= sprintf( '%02x', (int) round( $channel * 255 ) );
		}
		return $rgba[3] < 1 ? $hex . sprintf( '%02x', (int) round( $rgba[3] * 255 ) ) : $hex;
	}

	/**
	 * WCAG 2.x contrast ratio between two opaque colours.
	 *
	 * @param mixed $fg Foreground token value.
	 * @param mixed $bg Background token value.
	 * @throws \InvalidArgumentException When a colour is translucent.
	 */
	public static function contrast( mixed $fg, mixed $bg ): float {
		$a = self::parse_color( $fg );
		$b = self::parse_color( $bg );
		if ( $a[3] < 1 || $b[3] < 1 ) {
			throw new \InvalidArgumentException( 'contrast needs opaque colours' );
		}
		$la = self::luminance( $a );
		$lb = self::luminance( $b );
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	/**
	 * Relative luminance (WCAG 2.x).
	 *
	 * @param array{0: float, 1: float, 2: float, 3: float} $rgba Colour.
	 */
	private static function luminance( array $rgba ): float {
		$linear = array_map(
			static fn ( float $c ): float => $c <= 0.04045 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4,
			array( $rgba[0], $rgba[1], $rgba[2] )
		);
		return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
	}

	/**
	 * Parses a dimension: DTCG {"value": 1.5, "unit": "rem"} or the "1.5rem" shorthand.
	 *
	 * @param mixed $value Token value.
	 * @return array{0: float, 1: string} Number and unit.
	 * @throws \InvalidArgumentException When the value is not a supported dimension.
	 */
	public static function parse_dimension( mixed $value ): array {
		if ( is_array( $value ) && isset( $value['value'], $value['unit'] ) && ( is_int( $value['value'] ) || is_float( $value['value'] ) ) && in_array( $value['unit'], self::UNITS, true ) ) {
			return array( (float) $value['value'], (string) $value['unit'] );
		}
		if ( is_string( $value ) && preg_match( '/^(-?\d*\.?\d+)(px|rem|em|%)$/', $value, $m ) ) {
			return array( (float) $m[1], $m[2] );
		}
		if ( 0 === $value || '0' === $value ) {
			return array( 0.0, 'px' );
		}
		throw new \InvalidArgumentException( 'expected {"value": <number>, "unit": "px|rem|em|%"} or a string such as "1.5rem"' );
	}

	/**
	 * Dimension as CSS, e.g. "1.5rem".
	 *
	 * @param mixed $value Token value.
	 */
	public static function dimension( mixed $value ): string {
		[ $number, $unit ] = self::parse_dimension( $value );
		return 0.0 === $number ? '0' : self::number( $number ) . $unit;
	}

	/**
	 * A clamp() that grows linearly from $min at $vw_min to $max at $vw_max.
	 *
	 * @param mixed $min    Dimension token value at the small viewport.
	 * @param mixed $max    Dimension token value at the large viewport.
	 * @param float $vw_min Small viewport width in px.
	 * @param float $vw_max Large viewport width in px.
	 * @throws \InvalidArgumentException When units are not px/rem or min > max.
	 */
	public static function fluid( mixed $min, mixed $max, float $vw_min, float $vw_max ): string {
		$min_px = self::to_px( $min );
		$max_px = self::to_px( $max );
		if ( $min_px > $max_px ) {
			throw new \InvalidArgumentException( 'fluid min is larger than the value' );
		}
		$slope     = ( $max_px - $min_px ) / ( $vw_max - $vw_min );
		$intercept = ( $min_px - $slope * $vw_min ) / self::ROOT_PX;
		$preferred = self::number( $intercept ) . 'rem + ' . self::number( $slope * 100 ) . 'vw';
		return 'clamp(' . self::dimension( $min ) . ', ' . $preferred . ', ' . self::dimension( $max ) . ')';
	}

	/**
	 * Converts a px or rem dimension to px.
	 *
	 * @param mixed $value Token value.
	 * @throws \InvalidArgumentException For other units.
	 */
	public static function to_px( mixed $value ): float {
		[ $number, $unit ] = self::parse_dimension( $value );
		return match ( $unit ) {
			'px'    => $number,
			'rem'   => $number * self::ROOT_PX,
			default => throw new \InvalidArgumentException( 'fluid values must use px or rem' ),
		};
	}

	/**
	 * Font family stack as CSS. Names with spaces or digits are quoted.
	 * A name is letters, digits, spaces, dots, hyphens and underscores, so
	 * it cannot close the quotes or the rule it is written into.
	 *
	 * @param mixed $value String or list of family names.
	 * @throws \InvalidArgumentException When the value is not a string or list of strings.
	 */
	public static function font_family( mixed $value ): string {
		$names = is_string( $value ) ? array( $value ) : $value;
		if ( ! is_array( $names ) || array() === $names ) {
			throw new \InvalidArgumentException( 'expected a font name or a list of names' );
		}
		$out = array();
		foreach ( $names as $name ) {
			if ( ! is_string( $name ) || '' === trim( $name ) ) {
				throw new \InvalidArgumentException( 'font names must be non-empty strings' );
			}
			$name = trim( $name, " \t\"'" );
			if ( 1 !== preg_match( '/^[\p{L}\p{N} ._-]+$/u', $name ) ) {
				throw new \InvalidArgumentException( 'font names may hold letters, digits, spaces, dots, hyphens and underscores' );
			}
			$out[] = in_array( strtolower( $name ), self::GENERIC_FAMILIES, true ) || preg_match( '/^[a-z-]+$/i', $name ) ? $name : '"' . $name . '"';
		}
		return implode( ', ', $out );
	}

	/**
	 * Shadow (one layer or a list of layers) as CSS.
	 *
	 * @param mixed $value DTCG shadow object or list of them.
	 * @throws \InvalidArgumentException When a layer is malformed.
	 */
	public static function shadow( mixed $value ): string {
		if ( ! is_array( $value ) || array() === $value ) {
			throw new \InvalidArgumentException( 'expected a shadow object or a list of them' );
		}
		$layers = array_is_list( $value ) ? $value : array( $value );
		$out    = array();
		foreach ( $layers as $layer ) {
			if ( ! is_array( $layer ) || ! isset( $layer['color'], $layer['offsetX'], $layer['offsetY'] ) ) {
				throw new \InvalidArgumentException( 'each shadow needs color, offsetX and offsetY' );
			}
			$parts = array(
				self::dimension( $layer['offsetX'] ),
				self::dimension( $layer['offsetY'] ),
				self::dimension( $layer['blur'] ?? 0 ),
				self::dimension( $layer['spread'] ?? 0 ),
				self::color( $layer['color'] ),
			);
			$out[] = ( true === ( $layer['inset'] ?? false ) ? 'inset ' : '' ) . implode( ' ', $parts );
		}
		return implode( ', ', $out );
	}

	/**
	 * Number without trailing zeros, at most 4 decimals.
	 *
	 * @param float $number Number.
	 */
	public static function number( float $number ): string {
		$out = rtrim( rtrim( sprintf( '%.4F', $number ), '0' ), '.' );
		return '-0' === $out ? '0' : $out;
	}
}
