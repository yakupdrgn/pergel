<?php
/**
 * Core block markup back to Markdown.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Package;

use Closure;

/**
 * The reverse of Markdown: writes a block, or a piece of inline HTML, as
 * the Markdown that Markdown::convert() and Markdown::inline() turn back
 * into it. What the subset cannot hold (a class, a colour, an inline tag
 * other than strong, em, code, a and br) is left out here; the exporter
 * converts the Markdown back and compares, so a block that does not come
 * back the same is reported instead of changed. Pure PHP.
 */
final class MarkdownWriter {

	/**
	 * Markdown for one block, or null when the subset has no way to write it.
	 *
	 * @param array<string, mixed> $block A parse_blocks() block.
	 * @param Closure              $image Turns an image's URL, attachment id and alt text into a package path, null when it cannot be in the package.
	 * @phpstan-param Closure(string, int, string): ?string $image
	 */
	public static function block( array $block, Closure $image ): ?string {
		$html = implode( '', array_filter( (array) $block['innerContent'], 'is_string' ) );
		switch ( $block['blockName'] ) {
			case 'core/paragraph':
				$text = self::inner( $html, 'p' );
				return null === $text || '' === trim( $text ) ? null : self::lines( self::inline( $text ) );
			case 'core/heading':
				$level = (int) ( $block['attrs']['level'] ?? 2 );
				$text  = self::inner( $html, 'h' . $level );
				if ( null === $text || $level < 2 ) {
					return null;
				}
				$text = self::inline( $text );
				return '' === $text || str_contains( $text, "\n" ) ? null : str_repeat( '#', $level ) . ' ' . $text;
			case 'core/list':
				return self::list_block( $block );
			case 'core/quote':
				$inner = array();
				foreach ( (array) $block['innerBlocks'] as $child ) {
					$markdown = self::block( $child, $image );
					if ( null === $markdown ) {
						return null;
					}
					$inner[] = $markdown;
				}
				if ( array() === $inner ) {
					return null;
				}
				return implode( "\n", array_map( static fn( string $line ): string => '' === $line ? '>' : "> $line", explode( "\n", implode( "\n\n", $inner ) ) ) );
			case 'core/code':
				$code = self::inner( $html, 'code' );
				if ( null === $code ) {
					return null;
				}
				$code = self::decode( $code );
				return preg_match( '/^\s*```/m', $code ) ? null : "```\n$code\n```";
			case 'core/separator':
				return '---';
			case 'core/image':
				return self::image( $block, $html, $image );
			case 'core/table':
				return self::table( $html );
			default:
				return null;
		}
	}

	/**
	 * Inline Markdown for a piece of inline HTML.
	 *
	 * @param string $html Inline HTML, as inside a paragraph.
	 */
	public static function inline( string $html ): string {
		$parts = preg_split( '/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		// Each open span: [ closing tag name, opening Markdown, closing Markdown, text so far ].
		$stack = array( array( '', '', '', '' ) );
		$code  = null;
		foreach ( false === $parts ? array() : $parts as $part ) {
			if ( null !== $code ) {
				if ( preg_match( '#^</code\s*>$#i', $part ) ) {
					$stack[ count( $stack ) - 1 ][3] .= '' === $code ? '' : '`' . $code . '`';
					$code                             = null;
				} elseif ( ! str_starts_with( $part, '<' ) ) {
					$code .= self::decode( $part );
				}
				continue;
			}
			if ( ! str_starts_with( $part, '<' ) ) {
				$stack[ count( $stack ) - 1 ][3] .= self::escape( self::decode( $part ) );
				continue;
			}
			if ( ! preg_match( '#^<(/?)([a-z0-9]+)\b([^>]*)>$#i', $part, $m ) ) {
				continue;
			}
			$tag = strtolower( $m[2] );
			if ( '' === $m[1] ) {
				if ( 'br' === $tag ) {
					$stack[ count( $stack ) - 1 ][3] .= "\\\n";
				} elseif ( 'code' === $tag ) {
					$code = '';
				} elseif ( in_array( $tag, array( 'strong', 'b' ), true ) ) {
					$stack[] = array( $tag, '**', '**', '' );
				} elseif ( in_array( $tag, array( 'em', 'i' ), true ) ) {
					$stack[] = array( $tag, '*', '*', '' );
				} elseif ( 'a' === $tag ) {
					$href    = preg_match( '/\shref="([^"]*)"/i', $m[3], $h ) ? self::decode( $h[1] ) : '';
					$stack[] = array( 'a', '[', '](' . $href . ')', '' );
				} else {
					// Other tags (span, mark, s) are left out; their text stays.
					$stack[] = array( $tag, '', '', '' );
				}
				continue;
			}
			// A closing tag closes the innermost span it matches.
			for ( $i = count( $stack ) - 1; $i > 0; $i-- ) {
				if ( $stack[ $i ][0] !== $tag ) {
					continue;
				}
				for ( $open = count( $stack ) - $i; $open > 0; $open-- ) {
					$span = array_pop( $stack );
					$text = $span[3];
					$core = trim( $text, ' ' );
					if ( 'a' === $span[0] ) {
						$wrapped = '' === $core ? $text : self::lead( $text ) . '[' . $core . ']' . substr( $span[2], 1 ) . self::tail( $text );
					} else {
						// **a *b*** reads back wrong; __a *b*__ does not.
						$mark    = '**' === $span[1] && ( str_starts_with( $core, '*' ) || str_ends_with( $core, '*' ) ) ? '__' : $span[1];
						$wrapped = '' === $core ? $text : self::lead( $text ) . $mark . $core . $mark . self::tail( $text );
					}
					$stack[ count( $stack ) - 1 ][3] .= $wrapped;
				}
				break;
			}
		}
		if ( null !== $code ) {
			$stack[ count( $stack ) - 1 ][3] .= '`' . $code . '`';
		}
		for ( $open = count( $stack ) - 1; $open > 0; $open-- ) {
			$span                             = array_pop( $stack );
			$stack[ count( $stack ) - 1 ][3] .= $span[3];
		}
		return trim( $stack[0][3], ' ' );
	}

	/**
	 * A list block, with its nested lists indented under their items.
	 *
	 * @param array<string, mixed> $block List block.
	 * @param int                  $indent Spaces before this list's markers.
	 */
	private static function list_block( array $block, int $indent = 0 ): ?string {
		$ordered = ! empty( $block['attrs']['ordered'] );
		$lines   = array();
		foreach ( (array) $block['innerBlocks'] as $n => $item ) {
			if ( 'core/list-item' !== $item['blockName'] ) {
				return null;
			}
			$first = (string) ( $item['innerContent'][0] ?? '' );
			if ( ! preg_match( '/^\s*<li\b[^>]*>(.*?)(?:<\/li>\s*)?$/s', $first, $m ) ) {
				return null;
			}
			$marker = $ordered ? ( $n + 1 ) . '. ' : '- ';
			$pad    = str_repeat( ' ', $indent + strlen( $marker ) );
			$text   = self::inline( $m[1] );
			if ( '' === $text ) {
				return null;
			}
			$text    = str_replace( "\n", "\n$pad", self::lines( $text ) );
			$lines[] = str_repeat( ' ', $indent ) . $marker . $text;
			foreach ( (array) $item['innerBlocks'] as $nested ) {
				if ( 'core/list' !== $nested['blockName'] ) {
					return null;
				}
				$inner = self::list_block( $nested, $indent + strlen( $marker ) );
				if ( null === $inner ) {
					return null;
				}
				$lines[] = $inner;
			}
		}
		return array() === $lines ? null : implode( "\n", $lines );
	}

	/**
	 * An image block as ![alt](path "caption").
	 *
	 * @param array<string, mixed> $block Image block.
	 * @param string               $html  Its markup.
	 * @param Closure              $image Image path resolver.
	 * @phpstan-param Closure(string, int, string): ?string $image
	 */
	private static function image( array $block, string $html, Closure $image ): ?string {
		if ( ! preg_match( '/<img\b[^>]*\ssrc="([^"]*)"/', $html, $src ) ) {
			return null;
		}
		$alt  = preg_match( '/<img\b[^>]*\salt="([^"]*)"/', $html, $a ) ? self::decode( $a[1] ) : '';
		$path = $image( self::decode( $src[1] ), (int) ( $block['attrs']['id'] ?? 0 ), $alt );
		if ( null === $path || str_contains( $alt, ']' ) ) {
			return null;
		}
		$caption = self::inner( $html, 'figcaption' );
		$caption = null === $caption ? '' : self::inline( $caption );
		if ( str_contains( $caption, '"' ) || str_contains( $caption, "\n" ) ) {
			return null;
		}
		return '![' . $alt . '](' . $path . ( '' === $caption ? '' : ' "' . $caption . '"' ) . ')';
	}

	/**
	 * A table with a header row.
	 *
	 * @param string $html Table block markup.
	 */
	private static function table( string $html ): ?string {
		if ( ! preg_match( '/<thead>\s*<tr>(.*?)<\/tr>\s*<\/thead>/s', $html, $head ) ) {
			return null;
		}
		$row   = static fn( string $tr, string $cell ): array => preg_match_all( "/<$cell\\b[^>]*>(.*?)<\\/$cell>/s", $tr, $m ) ? array_map( static fn( string $text ): string => str_replace( '|', '\\|', self::inline( $text ) ), $m[1] ) : array();
		$cells = $row( $head[1], 'th' );
		if ( array() === $cells ) {
			return null;
		}
		$lines = array( '| ' . implode( ' | ', $cells ) . ' |', '|' . str_repeat( '---|', count( $cells ) ) );
		if ( preg_match( '/<tbody>(.*?)<\/tbody>/s', $html, $body ) && preg_match_all( '/<tr>(.*?)<\/tr>/s', $body[1], $rows ) ) {
			foreach ( $rows[1] as $tr ) {
				$lines[] = '| ' . implode( ' | ', $row( $tr, 'td' ) ) . ' |';
			}
		}
		foreach ( $lines as $line ) {
			if ( str_contains( $line, "\n" ) ) {
				return null;
			}
		}
		return implode( "\n", $lines );
	}

	/**
	 * What is between the first <$tag> and the last </$tag>.
	 *
	 * @param string $html Markup.
	 * @param string $tag  Element name.
	 */
	public static function inner( string $html, string $tag ): ?string {
		if ( ! preg_match( '/<' . $tag . '\b[^>]*>/', $html, $m, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}
		$start = $m[0][1] + strlen( $m[0][0] );
		$end   = strrpos( $html, '</' . $tag . '>' );
		return false === $end || $end < $start ? null : substr( $html, $start, $end - $start );
	}

	/**
	 * Escapes the start of each line so it is not read as a heading, list,
	 * quote or table.
	 *
	 * @param string $markdown Inline Markdown, lines joined by \ and a newline.
	 */
	private static function lines( string $markdown ): string {
		return (string) preg_replace( array( '/^([#>|+-])/m', '/^(\d+)([.)])/m' ), array( '\\\\$1', '$1\\\\$2' ), $markdown );
	}

	/**
	 * Escapes what Markdown::inline() would read as Markdown.
	 *
	 * @param string $text Plain text.
	 */
	private static function escape( string $text ): string {
		$text = (string) preg_replace( '/([\\\\`*\[\]])/', '\\\\$1', $text );
		// An underscore inside a word is not emphasis.
		return (string) preg_replace( '/(?<![\p{L}\p{N}])_|_(?![\p{L}\p{N}])/u', '\\\\_', $text );
	}

	/**
	 * Text of HTML text.
	 *
	 * @param string $text Escaped text.
	 */
	private static function decode( string $text ): string {
		return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Leading spaces of a text.
	 *
	 * @param string $text Text.
	 */
	private static function lead( string $text ): string {
		return substr( $text, 0, strlen( $text ) - strlen( ltrim( $text, ' ' ) ) );
	}

	/**
	 * Trailing spaces of a text.
	 *
	 * @param string $text Text.
	 */
	private static function tail( string $text ): string {
		return substr( $text, strlen( rtrim( $text, ' ' ) ) );
	}
}
