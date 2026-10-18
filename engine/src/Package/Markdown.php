<?php
/**
 * Markdown to core block markup.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Package;

use Closure;
use InvalidArgumentException;

/**
 * Turns the Markdown body of a content file into core blocks, written the
 * way the block editor saves them so the editor opens them without a
 * "this block contains unexpected content" warning. Pure PHP for unit tests.
 *
 * Blocks: paragraphs, ## to ###### headings (the page title is the only
 * H1, so # is refused), - * + and 1. lists (nested by indentation),
 * > quotes, ``` code, --- separators, a line holding only ![alt](src
 * "caption") as an image and | tables with a |---| row.
 * Inline: **strong**, *em* or _em_, `code`, [text](url) and a line ending in
 * two spaces or \ as a line break. HTML is shown as text, never run: the
 * package format has no Custom HTML block.
 */
final class Markdown {

	/**
	 * Link schemes allowed besides relative addresses and #anchors.
	 */
	private const SCHEMES = array( 'http', 'https', 'mailto', 'tel' );

	/**
	 * Deepest nesting of quotes and lists, so a crafted file cannot exhaust
	 * the stack.
	 */
	private const MAX_DEPTH = 16;

	/**
	 * Quotes and lists open around the block being read.
	 *
	 * @var int
	 */
	private int $depth = 0;

	/**
	 * Use convert().
	 *
	 * @param Closure $image Turns an image path and its alt text into an attachment id (0 = none) and URL.
	 * @phpstan-param Closure(string, string): array{id: int, url: string} $image
	 */
	private function __construct( private readonly Closure $image ) {
	}

	/**
	 * Converts Markdown to block markup.
	 *
	 * @param string  $markdown Markdown text.
	 * @param Closure $image Image resolver, see the constructor.
	 * @phpstan-param Closure(string, string): array{id: int, url: string} $image
	 * @throws InvalidArgumentException With "line N: problem" for what the format refuses.
	 */
	public static function convert( string $markdown, Closure $image ): string {
		$lines = explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", $markdown ) );
		return implode( "\n\n", ( new self( $image ) )->blocks( $lines, 0 ) );
	}

	/**
	 * Converts inline Markdown in one piece of text (a field value) to HTML.
	 *
	 * @param string $text Text with inline Markdown.
	 * @throws InvalidArgumentException When a link uses a scheme that is not allowed.
	 */
	public static function inline( string $text ): string {
		$text  = str_replace( array( "\r\n", "\r" ), "\n", trim( $text ) );
		$parts = preg_split( '/(?:  |\\\\)\n/', $text );
		$html  = array();
		foreach ( false === $parts ? array( $text ) : $parts as $part ) {
			$html[] = self::spans( str_replace( "\n", ' ', $part ) );
		}
		return implode( '<br>', $html );
	}

	/**
	 * Converts lines to a list of block markup strings.
	 *
	 * @param array<int, string> $lines  Lines.
	 * @param int                $offset Line number of the first line, for messages.
	 * @return list<string>
	 * @throws InvalidArgumentException With the line number, for Markdown outside the subset.
	 */
	private function blocks( array $lines, int $offset ): array {
		$blocks = array();
		$count  = count( $lines );
		$i      = 0;
		while ( $i < $count ) {
			$line = $lines[ $i ];
			$trim = trim( $line );
			if ( '' === $trim ) {
				++$i;
				continue;
			}
			$start = $i;
			try {
				if ( preg_match( '/^(#{1,6})\s+(.*?)\s*#*\s*$/', $trim, $m ) ) {
					$blocks[] = self::heading( strlen( $m[1] ), $m[2] );
					++$i;
				} elseif ( preg_match( '/^(?:-\s*){3,}$|^(?:\*\s*){3,}$|^(?:_\s*){3,}$/', $trim ) ) {
					$blocks[] = "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->";
					++$i;
				} elseif ( str_starts_with( $trim, '```' ) ) {
					$blocks[] = $this->code( $lines, $i );
				} elseif ( str_starts_with( $trim, '>' ) ) {
					$blocks[] = $this->quote( $lines, $i, $offset );
				} elseif ( self::list_marker( $line ) ) {
					$blocks[] = $this->list_block( $lines, $i, self::indent( $line ) );
				} elseif ( str_starts_with( $trim, '|' ) && isset( $lines[ $i + 1 ] ) && preg_match( '/^\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)*\|?$/', trim( $lines[ $i + 1 ] ) ) ) {
					$blocks[] = $this->table( $lines, $i );
				} else {
					$blocks[] = $this->paragraph( $lines, $i );
				}
			} catch ( InvalidArgumentException $e ) {
				if ( preg_match( '/^line \d+:/', $e->getMessage() ) ) {
					throw $e;
				}
				throw new InvalidArgumentException( sprintf( 'line %d: %s', $offset + $start + 1, $e->getMessage() ) );
			}
		}
		return $blocks;
	}

	/**
	 * A heading block.
	 *
	 * @param int    $level 1–6.
	 * @param string $text  Inline Markdown.
	 * @throws InvalidArgumentException For a level 1 heading.
	 */
	private static function heading( int $level, string $text ): string {
		if ( 1 === $level ) {
			throw new InvalidArgumentException( 'a # heading is the page title, which the page already has; start body headings at ##' );
		}
		$attrs = 2 === $level ? '' : ' {"level":' . $level . '}';
		return "<!-- wp:heading$attrs -->\n<h$level class=\"wp-block-heading\">" . self::inline( $text ) . "</h$level>\n<!-- /wp:heading -->";
	}

	/**
	 * A paragraph, or an image when the paragraph is only an image.
	 *
	 * @param array<int, string> $lines Lines.
	 * @param int                $i     First line; moved past the paragraph.
	 */
	private function paragraph( array $lines, int &$i ): string {
		$text  = array();
		$count = count( $lines );
		while ( $i < $count ) {
			$line = $lines[ $i ];
			$trim = trim( $line );
			if ( '' === $trim || ( array() !== $text && ( preg_match( '/^#{1,6}\s/', $trim ) || str_starts_with( $trim, '>' ) || str_starts_with( $trim, '```' ) || self::list_marker( $line ) ) ) ) {
				break;
			}
			$text[] = rtrim( ltrim( $line ), "\t" );
			++$i;
		}
		$joined = implode( "\n", $text );
		if ( preg_match( '/^!\[([^\]]*)\]\(\s*(\S+?)(?:\s+"([^"]*)")?\s*\)$/', trim( $joined ), $m ) ) {
			return $this->image( $m[2], $m[1], $m[3] ?? '' );
		}
		return "<!-- wp:paragraph -->\n<p>" . self::inline( $joined ) . "</p>\n<!-- /wp:paragraph -->";
	}

	/**
	 * An image block.
	 *
	 * @param string $src     Path or URL.
	 * @param string $alt     Alt text.
	 * @param string $caption Caption (inline Markdown).
	 */
	private function image( string $src, string $alt, string $caption ): string {
		$found = ( $this->image )( $src, $alt );
		$attrs = $found['id'] > 0 ? array(
			'id'              => $found['id'],
			'sizeSlug'        => 'large',
			'linkDestination' => 'none',
		) : array(
			'sizeSlug'        => 'large',
			'linkDestination' => 'none',
		);
		$class = $found['id'] > 0 ? ' class="wp-image-' . $found['id'] . '"' : '';
		$html  = '<figure class="wp-block-image size-large"><img src="' . self::attr( $found['url'] ) . '" alt="' . self::attr( $alt ) . '"' . $class . '/>';
		if ( '' !== $caption ) {
			$html .= '<figcaption class="wp-element-caption">' . self::inline( $caption ) . '</figcaption>';
		}
		return '<!-- wp:image ' . self::json( $attrs ) . " -->\n$html</figure>\n<!-- /wp:image -->";
	}

	/**
	 * A fenced code block.
	 *
	 * @param array<int, string> $lines Lines.
	 * @param int                $i     Opening fence line; moved past the closing fence.
	 * @throws InvalidArgumentException When the fence is not closed.
	 */
	private function code( array $lines, int &$i ): string {
		$fence = $lines[ $i ];
		$body  = array();
		$count = count( $lines );
		for ( $j = $i + 1; $j < $count; $j++ ) {
			if ( str_starts_with( trim( $lines[ $j ] ), '```' ) ) {
				$i = $j + 1;
				return "<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>" . self::text( implode( "\n", $body ) ) . "</code></pre>\n<!-- /wp:code -->";
			}
			$body[] = $lines[ $j ];
		}
		unset( $fence );
		throw new InvalidArgumentException( 'a ``` code block is not closed' );
	}

	/**
	 * A quote holding the blocks written after its > marks.
	 *
	 * @param array<int, string> $lines  Lines.
	 * @param int                $i      First line; moved past the quote.
	 * @param int                $offset Line number of $lines[0].
	 */
	private function quote( array $lines, int &$i, int $offset ): string {
		$start = $i;
		$inner = array();
		$count = count( $lines );
		while ( $i < $count && str_starts_with( trim( $lines[ $i ] ), '>' ) ) {
			$inner[] = (string) preg_replace( '/^\s*> ?/', '', $lines[ $i ] );
			++$i;
		}
		$this->enter();
		try {
			$blocks = $this->blocks( $inner, $offset + $start );
		} finally {
			--$this->depth;
		}
		return "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">" . implode( "\n\n", $blocks ) . "</blockquote>\n<!-- /wp:quote -->";
	}

	/**
	 * Opens one more level of quote or list.
	 *
	 * @throws InvalidArgumentException When it is one level too many.
	 */
	private function enter(): void {
		if ( ++$this->depth > self::MAX_DEPTH ) {
			throw new InvalidArgumentException( 'quotes and lists nest at most ' . self::MAX_DEPTH . ' deep' );
		}
	}

	/**
	 * A list block, with nested lists for deeper items.
	 *
	 * @param array<int, string> $lines  Lines.
	 * @param int                $i      First item line; moved past the list.
	 * @param int                $indent Indentation of this list's markers.
	 */
	private function list_block( array $lines, int &$i, int $indent ): string {
		$ordered = (bool) preg_match( '/^\s*\d+[.)]\s/', $lines[ $i ] );
		$texts   = array();
		$nested  = array();
		$count   = count( $lines );
		while ( $i < $count ) {
			$line = $lines[ $i ];
			if ( '' === trim( $line ) ) {
				// A blank line ends the list unless another item follows.
				$next = $i + 1;
				if ( $next < $count && self::list_marker( $lines[ $next ] ) && self::indent( $lines[ $next ] ) >= $indent ) {
					++$i;
					continue;
				}
				break;
			}
			$own = self::indent( $line );
			if ( ! self::list_marker( $line ) || $own < $indent ) {
				break;
			}
			if ( $own > $indent ) {
				// A deeper list belongs to the item before it.
				if ( array() === $texts ) {
					break;
				}
				$this->enter();
				try {
					$nested[ count( $texts ) - 1 ][] = $this->list_block( $lines, $i, $own );
				} finally {
					--$this->depth;
				}
				continue;
			}
			if ( (bool) preg_match( '/^\s*\d+[.)]\s/', $line ) !== $ordered ) {
				break;
			}
			$text = (string) preg_replace( '/^\s*(?:[-*+]|\d+[.)])\s+/', '', $line );
			++$i;
			// Lines indented under the item continue its text.
			while ( $i < $count && '' !== trim( $lines[ $i ] ) && ! self::list_marker( $lines[ $i ] ) && self::indent( $lines[ $i ] ) > $indent ) {
				$text .= "\n" . trim( $lines[ $i ] );
				++$i;
			}
			$texts[]  = $text;
			$nested[] = array();
		}
		$tag  = $ordered ? 'ol' : 'ul';
		$html = array();
		foreach ( $texts as $n => $text ) {
			$html[] = "<!-- wp:list-item -->\n<li>" . self::inline( $text ) . implode( '', $nested[ $n ] ) . "</li>\n<!-- /wp:list-item -->";
		}
		$open = $ordered ? '<!-- wp:list {"ordered":true} -->' : '<!-- wp:list -->';
		return "$open\n<$tag class=\"wp-block-list\">" . implode( "\n\n", $html ) . "</$tag>\n<!-- /wp:list -->";
	}

	/**
	 * A table with a header row.
	 *
	 * @param array<int, string> $lines Lines.
	 * @param int                $i     Header line; moved past the table.
	 */
	private function table( array $lines, int &$i ): string {
		$head  = self::cells( $lines[ $i ] );
		$i    += 2;
		$rows  = array();
		$count = count( $lines );
		while ( $i < $count && str_starts_with( trim( $lines[ $i ] ), '|' ) ) {
			$rows[] = self::cells( $lines[ $i ] );
			++$i;
		}
		$html = '<thead><tr>';
		foreach ( $head as $cell ) {
			$html .= '<th>' . self::inline( $cell ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$html .= '<tr>';
			foreach ( array_pad( array_slice( $row, 0, count( $head ) ), count( $head ), '' ) as $cell ) {
				$html .= '<td>' . self::inline( $cell ) . '</td>';
			}
			$html .= '</tr>';
		}
		$html .= '</tbody>';
		return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table class=\"has-fixed-layout\">$html</table></figure>\n<!-- /wp:table -->";
	}

	/**
	 * Cells of a | table row.
	 *
	 * @param string $line Row.
	 * @return list<string>
	 */
	private static function cells( string $line ): array {
		$line  = trim( trim( $line ), '|' );
		$cells = preg_split( '/(?<!\\\\)\|/', $line );
		return array_map( static fn( string $cell ): string => trim( str_replace( '\\|', '|', $cell ) ), false === $cells ? array( $line ) : $cells );
	}

	/**
	 * Inline spans in one line of text.
	 *
	 * @param string $text Text.
	 * @throws InvalidArgumentException When a link uses a scheme that is not allowed.
	 */
	private static function spans( string $text ): string {
		// Code spans first: nothing inside them is Markdown. \` is not one.
		$held = array();
		$text = (string) preg_replace_callback(
			'/(?<!\\\\)`([^`]+)`/',
			static function ( array $m ) use ( &$held ): string {
				$held[] = '<code>' . self::text( $m[1] ) . '</code>';
				return "\x01" . ( count( $held ) - 1 ) . "\x02";
			},
			$text
		);
		// Backslash escapes.
		$text = (string) preg_replace_callback(
			'/\\\\([\\\\`*_\[\]()#+\-.!|>])/',
			static function ( array $m ) use ( &$held ): string {
				$held[] = self::text( $m[1] );
				return "\x01" . ( count( $held ) - 1 ) . "\x02";
			},
			$text
		);
		// Links: the address is held so * and _ in it stay as they are.
		$text = (string) preg_replace_callback(
			'/\[([^\]]+)\]\(\s*([^)\s]+)\s*\)/',
			static function ( array $m ) use ( &$held ): string {
				$held[] = '<a href="' . self::attr( self::url( $m[2] ) ) . '">';
				$held[] = '</a>';
				return "\x01" . ( count( $held ) - 2 ) . "\x02" . $m[1] . "\x01" . ( count( $held ) - 1 ) . "\x02";
			},
			$text
		);
		$text = self::text( $text );
		$text = (string) preg_replace( '/\*\*(?=\S)(.+?)(?<=\S)\*\*/', '<strong>$1</strong>', $text );
		$text = (string) preg_replace( '/__(?=\S)(.+?)(?<=\S)__/', '<strong>$1</strong>', $text );
		$text = (string) preg_replace( '/\*(?=\S)(.+?)(?<=\S)\*/', '<em>$1</em>', $text );
		$text = (string) preg_replace( '/(?<![\p{L}\p{N}])_(?=\S)(.+?)(?<=\S)_(?![\p{L}\p{N}])/u', '<em>$1</em>', $text );
		return (string) preg_replace_callback( "/\x01(\d+)\x02/", static fn( array $m ): string => $held[ (int) $m[1] ], $text );
	}

	/**
	 * Checks a link address. Browsers skip spaces and control characters in
	 * front of a scheme and inside it ("\x01javascript:"), so an address
	 * with any of them is refused before the scheme is read.
	 *
	 * @param string $url Address as written.
	 * @throws InvalidArgumentException For a scheme that is not allowed.
	 */
	public static function url( string $url ): string {
		if ( preg_match( '/[\x00-\x20\x7F]/', $url ) ) {
			throw new InvalidArgumentException( 'the link "' . addcslashes( $url, "\0..\37\177" ) . '" has spaces or control characters; write a space as %20' );
		}
		if ( preg_match( '/^([a-zA-Z][a-zA-Z0-9+.-]*):/', $url, $m ) && ! in_array( strtolower( $m[1] ), self::SCHEMES, true ) ) {
			throw new InvalidArgumentException( "the link \"$url\" uses $m[1]:, which is not allowed" );
		}
		return $url;
	}

	/**
	 * Whether a line starts a list item.
	 *
	 * @param string $line Line.
	 */
	private static function list_marker( string $line ): bool {
		return (bool) preg_match( '/^\s*(?:[-*+]|\d+[.)])\s+\S/', $line ) && ! preg_match( '/^\s*(?:[-*_]\s*){3,}$/', $line );
	}

	/**
	 * Number of leading spaces (a tab counts as four).
	 *
	 * @param string $line Line.
	 */
	private static function indent( string $line ): int {
		$lead = strlen( $line ) - strlen( ltrim( $line, " \t" ) );
		return strlen( str_replace( "\t", '    ', substr( $line, 0, $lead ) ) );
	}

	/**
	 * Text with &, < and > escaped, as the editor saves it.
	 *
	 * @param string $text Text.
	 */
	public static function text( string $text ): string {
		return htmlspecialchars( $text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	/**
	 * An attribute value.
	 *
	 * @param string $value Value.
	 */
	public static function attr( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	/**
	 * Block comment attributes, encoded the way serialize_block() does.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 */
	public static function json( array $attrs ): string {
		$json = (string) json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- the flags serialize_block_attributes() uses.
		// serialize_block_attributes() escapes these so the comment stays a comment.
		return str_replace( array( '--', '<', '>', '&', '\\"' ), array( '\\u002d\\u002d', '\\u003c', '\\u003e', '\\u0026', '\\u0022' ), $json );
	}
}
