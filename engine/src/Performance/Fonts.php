<?php
/**
 * Picks the font files worth preloading from theme.json data.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Performance;

/**
 * Finds the faces of the body and heading fonts that cover basic Latin.
 *
 * Pure PHP (no WordPress calls) so the rules can be unit tested. Style
 * variations ship each font in two subsets (latin, latin-ext); only the
 * basic Latin subset is certain to be used on every page, so only that one
 * is preloaded. Text with letters outside it (ş, ğ, İ, ł, ...) still loads
 * the other subset as usual.
 */
final class Fonts {

	/**
	 * Code point every Latin-script page uses ("A").
	 */
	private const LATIN = 0x41;

	/**
	 * Font family slugs set on the page root and on headings.
	 *
	 * @param array<string, mixed> $styles Merged global styles (wp_get_global_styles()).
	 * @return list<string>
	 */
	public static function used_slugs( array $styles ): array {
		$values   = array( $styles['typography']['fontFamily'] ?? null );
		$elements = is_array( $styles['elements'] ?? null ) ? $styles['elements'] : array();
		foreach ( array( 'heading', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $element ) {
			$values[] = $elements[ $element ]['typography']['fontFamily'] ?? null;
		}
		$slugs = array();
		foreach ( $values as $value ) {
			$slug = is_string( $value ) ? self::slug( $value ) : '';
			if ( '' !== $slug && ! in_array( $slug, $slugs, true ) ) {
				$slugs[] = $slug;
			}
		}
		return $slugs;
	}

	/**
	 * Preset slug from a style value: var(--wp--preset--font-family--x) or
	 * var:preset|font-family|x. Empty for a literal font stack.
	 *
	 * @param string $value Style value.
	 */
	public static function slug( string $value ): string {
		if ( preg_match( '/^var\(--wp--preset--font-family--([a-z0-9-]+)\)$/', trim( $value ), $m )
			|| preg_match( '/^var:preset\|font-family\|([a-z0-9-]+)$/', trim( $value ), $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Font files to preload, as written in theme.json ("file:./..." or URL).
	 *
	 * A slug's family is looked up in the origins in the given order (the
	 * first match wins). Its faces are those of any family whose face
	 * names the same font, so a heading preset that only reuses the body
	 * font (no faces of its own) is covered too. Only upright woff2 faces
	 * that cover basic Latin are kept, one file each.
	 *
	 * @param array<string, mixed> $families Font families by origin (settings.typography.fontFamilies).
	 * @param string[]             $slugs    Slugs in use (used_slugs()).
	 * @phpstan-param list<string> $slugs
	 * @return list<string>
	 */
	public static function files( array $families, array $slugs ): array {
		$all = array();
		foreach ( array( 'custom', 'theme', 'default' ) as $origin ) {
			foreach ( is_array( $families[ $origin ] ?? null ) ? $families[ $origin ] : array() as $family ) {
				if ( is_array( $family ) ) {
					$all[] = $family;
				}
			}
		}

		$names = array();
		foreach ( $slugs as $slug ) {
			foreach ( $all as $family ) {
				if ( ( $family['slug'] ?? null ) === $slug ) {
					$name = self::first_name( (string) ( $family['fontFamily'] ?? '' ) );
					if ( '' !== $name && ! in_array( $name, $names, true ) ) {
						$names[] = $name;
					}
					break;
				}
			}
		}

		$files = array();
		foreach ( $names as $name ) {
			foreach ( $all as $family ) {
				foreach ( is_array( $family['fontFace'] ?? null ) ? $family['fontFace'] : array() as $face ) {
					if ( ! is_array( $face ) || self::first_name( (string) ( $face['fontFamily'] ?? '' ) ) !== $name ) {
						continue;
					}
					if ( 'normal' !== ( $face['fontStyle'] ?? 'normal' ) || ! self::covers( (string) ( $face['unicodeRange'] ?? '' ), self::LATIN ) ) {
						continue;
					}
					$file = self::woff2( $face['src'] ?? null );
					if ( '' !== $file && ! in_array( $file, $files, true ) ) {
						$files[] = $file;
					}
				}
			}
		}
		return $files;
	}

	/**
	 * First font name of a CSS font-family list, unquoted.
	 *
	 * @param string $stack CSS font-family value.
	 */
	public static function first_name( string $stack ): string {
		return trim( explode( ',', $stack )[0], " \t\"'" );
	}

	/**
	 * Whether a CSS unicode-range includes a code point. An empty range
	 * covers everything.
	 *
	 * @param string $range     CSS unicode-range, e.g. "U+0000-00FF, U+0131".
	 * @param int    $codepoint Code point.
	 */
	public static function covers( string $range, int $codepoint ): bool {
		if ( '' === trim( $range ) ) {
			return true;
		}
		foreach ( explode( ',', $range ) as $part ) {
			if ( ! preg_match( '/^U\+([0-9A-F?]{1,6})(?:-([0-9A-F]{1,6}))?$/i', trim( $part ), $m ) ) {
				continue;
			}
			$low  = (int) hexdec( str_replace( '?', '0', $m[1] ) );
			$high = isset( $m[2] ) ? (int) hexdec( $m[2] ) : (int) hexdec( str_replace( '?', 'F', $m[1] ) );
			if ( $codepoint >= $low && $codepoint <= $high ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The woff2 file of a face's src (a string or a list).
	 *
	 * @param mixed $src Face src.
	 */
	private static function woff2( mixed $src ): string {
		foreach ( is_array( $src ) ? $src : array( $src ) as $file ) {
			if ( is_string( $file ) && str_ends_with( strtolower( (string) strtok( $file, '?#' ) ), '.woff2' ) ) {
				return $file;
			}
		}
		return '';
	}
}
