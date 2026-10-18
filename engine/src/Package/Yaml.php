<?php
/**
 * The YAML subset project packages are written in.
 *
 * @package Pergel
 */

declare( strict_types=1 );

// Messages name files, lines and keys; WP-CLI prints them and REST returns them as JSON, never as HTML.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

namespace Pergel\Package;

use InvalidArgumentException;

/**
 * Reads the part of YAML 1.2 that package files need, with the core schema
 * for plain values. Pure PHP, so the plugin needs no YAML extension or
 * library and the reader can be unit tested.
 *
 * Read: block mappings and sequences (a sequence may sit at its key's
 * indent), flow `{ }` and `[ ]` collections (they may go on over several
 * lines), plain, 'single' and "double" quoted values, `|` and `>` blocks
 * with `-` or `+` chomping, `#` comments and one leading `---`.
 * Plain values: null, ~ and nothing are null; true and false are booleans;
 * numbers are int or float; anything else is a string (yes, no, on and off
 * stay strings, as in YAML 1.2).
 *
 * Refused with the line number, so nothing is read differently from what
 * the author meant: tabs in indentation, anchors, aliases, tags, several
 * documents, a plain or quoted value that goes on to the next line,
 * repeated keys and anything indented where it does not belong.
 */
final class Yaml {

	/**
	 * Lines without their line breaks.
	 *
	 * @var array<int, string>
	 */
	private array $lines;

	/**
	 * Deepest nesting read, so a crafted file cannot exhaust the stack.
	 */
	private const MAX_DEPTH = 32;

	/**
	 * Number of lines.
	 *
	 * @var int
	 */
	private int $total;

	/**
	 * Line being read, 0-based.
	 *
	 * @var int
	 */
	private int $at = 0;

	/**
	 * Use parse().
	 *
	 * @param string $text YAML text.
	 */
	private function __construct( string $text ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		if ( str_starts_with( $text, "\u{FEFF}" ) ) {
			$text = substr( $text, 3 );
		}
		$this->lines = explode( "\n", $text );
		$this->total = count( $this->lines );
	}

	/**
	 * Reads a YAML document.
	 *
	 * @param string $text YAML text.
	 * @return mixed Arrays for collections, scalars as typed; null when empty.
	 * @throws InvalidArgumentException With "line N: problem" when the text is outside the subset.
	 */
	public static function parse( string $text ): mixed {
		$reader = new self( $text );
		$reader->skip_blank();
		if ( $reader->at < count( $reader->lines ) && preg_match( '/^---\s*(#.*)?$/', $reader->lines[ $reader->at ] ) ) {
			++$reader->at;
			$reader->skip_blank();
		}
		if ( $reader->at >= count( $reader->lines ) ) {
			return null;
		}
		$indent = self::indent( $reader->lines[ $reader->at ] );
		if ( 0 !== $indent ) {
			$reader->fail( 'the document must start at the left edge' );
		}
		$value = $reader->node( 0 );
		$reader->skip_blank();
		if ( $reader->at < count( $reader->lines ) ) {
			$line = $reader->lines[ $reader->at ];
			$reader->fail( preg_match( '/^(---|\.\.\.)/', $line ) ? 'only one document is read' : 'unexpected indentation or text' );
		}
		return $value;
	}

	/**
	 * Reads the node that starts on the current line at $indent.
	 *
	 * @param int $indent Column the node starts at.
	 */
	private function node( int $indent ): mixed {
		if ( $indent > 2 * self::MAX_DEPTH ) {
			$this->fail( 'lists and maps nest at most ' . self::MAX_DEPTH . ' deep' );
		}
		$line = $this->lines[ $this->at ];
		$rest = substr( $line, $indent );
		if ( '-' === $rest || str_starts_with( $rest, '- ' ) ) {
			return $this->sequence( $indent );
		}
		if ( null !== $this->key( $rest ) ) {
			return $this->mapping( $indent );
		}
		$value = $this->inline( $rest );
		++$this->at;
		return $value;
	}

	/**
	 * Reads a block sequence whose dashes are at $indent.
	 *
	 * @param int $indent Column of the dashes.
	 * @return list<mixed>
	 */
	private function sequence( int $indent ): array {
		$items = array();
		while ( $this->at < $this->total ) {
			$line = $this->lines[ $this->at ];
			if ( self::indent( $line ) !== $indent ) {
				break;
			}
			$rest = substr( $line, $indent );
			if ( '-' !== $rest && ! str_starts_with( $rest, '- ' ) ) {
				break;
			}
			$after = ltrim( substr( $rest, 1 ), ' ' );
			if ( '' === $after || str_starts_with( $after, '#' ) ) {
				++$this->at;
				$items[] = $this->nested( $indent, false );
			} elseif ( preg_match( '/^([|>])([-+]?)\s*(#.*)?$/', $after, $block ) ) {
				++$this->at;
				$items[] = $this->block_scalar( $indent, '>' === $block[1], $block[2] );
			} else {
				// "- key: value" or "- - x": the item starts where its text does,
				// as if the dash were a space.
				$column                   = $indent + strlen( $rest ) - strlen( $after );
				$this->lines[ $this->at ] = str_repeat( ' ', $column ) . $after;
				$items[]                  = $this->node( $column );
			}
			$this->skip_blank();
		}
		return $items;
	}

	/**
	 * Reads a block mapping whose keys are at $indent.
	 *
	 * @param int $indent Column of the keys.
	 * @return array<string, mixed>
	 */
	private function mapping( int $indent ): array {
		$map = array();
		while ( $this->at < $this->total ) {
			$line = $this->lines[ $this->at ];
			if ( self::indent( $line ) !== $indent ) {
				break;
			}
			if ( preg_match( '/^(---|\.\.\.)(\s|$)/', $line ) ) {
				break;
			}
			$rest = substr( $line, $indent );
			$pair = $this->key( $rest );
			if ( null === $pair ) {
				if ( '-' === $rest || str_starts_with( $rest, '- ' ) ) {
					$this->fail( 'a list item where a key was expected' );
				}
				$this->fail( 'a key followed by ":" was expected' );
			}
			[ $key, $after ] = $pair;
			if ( array_key_exists( $key, $map ) ) {
				$this->fail( "the key \"$key\" is repeated" );
			}
			$after = ltrim( $after, ' ' );
			if ( '' === $after || str_starts_with( $after, '#' ) ) {
				++$this->at;
				$map[ $key ] = $this->nested( $indent, true );
			} elseif ( preg_match( '/^([|>])([-+]?)\s*(#.*)?$/', $after, $block ) ) {
				++$this->at;
				$map[ $key ] = $this->block_scalar( $indent, '>' === $block[1], $block[2] );
			} else {
				$map[ $key ] = $this->inline( $after );
				++$this->at;
			}
			$this->skip_blank();
		}
		return $map;
	}

	/**
	 * Reads the value under a key or dash that has nothing after it.
	 *
	 * @param int  $indent     Column of the key or dash.
	 * @param bool $in_mapping Whether a sequence may sit at the key's own indent.
	 */
	private function nested( int $indent, bool $in_mapping ): mixed {
		$this->skip_blank();
		if ( $this->at >= count( $this->lines ) ) {
			return null;
		}
		$line  = $this->lines[ $this->at ];
		$child = self::indent( $line );
		if ( $child > $indent ) {
			return $this->node( $child );
		}
		$rest = substr( $line, $indent );
		if ( $in_mapping && $child === $indent && ( '-' === $rest || str_starts_with( $rest, '- ' ) ) ) {
			return $this->sequence( $indent );
		}
		return null;
	}

	/**
	 * Reads a `|` or `>` block under a key at $indent.
	 *
	 * @param int    $indent Column of the key.
	 * @param bool   $folded Whether lines are joined with spaces (`>`).
	 * @param string $chomp  "", "-" (no final line break) or "+" (keep all).
	 */
	private function block_scalar( int $indent, bool $folded, string $chomp ): string {
		$lines  = array();
		$column = null;
		while ( $this->at < $this->total ) {
			$line = $this->lines[ $this->at ];
			if ( '' === trim( $line ) ) {
				$lines[] = '';
				++$this->at;
				continue;
			}
			$own = self::indent( $line );
			if ( $own <= $indent ) {
				break;
			}
			$column ??= $own;
			if ( $own < $column ) {
				$this->fail( 'a line in the block is indented less than the first one' );
			}
			$lines[] = substr( $line, $column );
			++$this->at;
		}
		// Blank lines after the block belong to the next node.
		$trailing = 0;
		for ( $i = count( $lines ) - 1; $i >= 0 && '' === $lines[ $i ]; $i-- ) {
			++$trailing;
		}
		$this->at -= $trailing;
		$body      = array_slice( $lines, 0, count( $lines ) - $trailing );
		$text      = $folded ? self::fold( $body ) : implode( "\n", $body );
		if ( array() === $body ) {
			return '';
		}
		return match ( $chomp ) {
			'-'     => $text,
			'+'     => $text . str_repeat( "\n", $trailing + 1 ),
			default => $text . "\n",
		};
	}

	/**
	 * Joins the lines of a `>` block: single breaks become spaces, a blank
	 * line becomes a break, more-indented lines keep their breaks.
	 *
	 * @param array<int, string> $lines Block lines, indentation removed.
	 */
	private static function fold( array $lines ): string {
		$text = '';
		foreach ( $lines as $i => $line ) {
			if ( 0 === $i ) {
				$text = $line;
				continue;
			}
			$previous = $lines[ $i - 1 ];
			$literal  = str_starts_with( $line, ' ' ) || str_starts_with( $previous, ' ' );
			if ( '' === $line ) {
				$text .= "\n";
			} elseif ( '' === $previous || $literal ) {
				$text .= ( '' === $previous && ! $literal ? '' : "\n" ) . $line;
			} else {
				$text .= ' ' . $line;
			}
		}
		return $text;
	}

	/**
	 * Splits "key: rest" when $text starts with a mapping key.
	 *
	 * @param string $text Line text from the node's column.
	 * @return array{0: string, 1: string}|null Key and the text after the colon.
	 */
	private function key( string $text ): ?array {
		if ( '' === $text || str_starts_with( $text, '#' ) || str_starts_with( $text, '{' ) || str_starts_with( $text, '[' ) ) {
			return null;
		}
		if ( '"' === $text[0] || "'" === $text[0] ) {
			$position = 0;
			try {
				$key = self::quoted( $text, $position );
			} catch ( InvalidArgumentException ) {
				return null;
			}
			$after = substr( $text, $position );
			if ( ! preg_match( '/^\s*:(\s|$)/', $after ) ) {
				return null;
			}
			return array( $key, (string) substr( ltrim( $after ), 1 ) );
		}
		// The first colon followed by a space or the line end ends the key, so
		// "http://example.com" is a value and "a:b: c" has the key "a:b".
		if ( ! preg_match( '/^(.*?)\s*:(?: |$)/', $text, $match ) ) {
			return null;
		}
		$key = $match[1];
		if ( '' === $key || '-' === $key || str_starts_with( $key, '- ' ) || str_contains( ' ' . $key, ' #' ) ) {
			return null;
		}
		$this->check_plain( $key );
		return array( $key, (string) substr( $text, strlen( $match[0] ) ) );
	}

	/**
	 * Reads a value written on one line (a flow collection may continue on
	 * the following lines).
	 *
	 * @param string $text The value text.
	 */
	private function inline( string $text ): mixed {
		$text = rtrim( $text );
		if ( str_starts_with( $text, '{' ) || str_starts_with( $text, '[' ) ) {
			$start = $this->at;
			while ( ! self::balanced( $text ) ) {
				if ( $this->at + 1 >= $this->total ) {
					$this->at = $start;
					$this->fail( 'a { } or [ ] list is not closed' );
				}
				++$this->at;
				$text .= ' ' . trim( self::strip_comment( $this->lines[ $this->at ] ) );
			}
			$position = 0;
			try {
				$value = self::flow( $text, $position );
				self::end( $text, $position );
			} catch ( InvalidArgumentException $e ) {
				$this->fail( $e->getMessage() );
			}
			return $value;
		}
		if ( '"' === $text[0] || "'" === $text[0] ) {
			$position = 0;
			try {
				$value = self::quoted( $text, $position );
				self::end( $text, $position );
			} catch ( InvalidArgumentException $e ) {
				$this->fail( $e->getMessage() );
			}
			return $value;
		}
		$plain = rtrim( self::strip_comment( $text ) );
		$this->check_plain( $plain );
		$this->check_continuation();
		return self::resolve( $plain );
	}

	/**
	 * Refuses a plain value that goes on on the next, deeper line.
	 */
	private function check_continuation(): void {
		$own  = self::indent( $this->lines[ $this->at ] );
		$next = $this->at + 1;
		while ( $next < $this->total && '' === trim( $this->lines[ $next ] ) ) {
			++$next;
		}
		if ( $next >= count( $this->lines ) ) {
			return;
		}
		$line = $this->lines[ $next ];
		if ( self::indent( $line ) > $own && ! str_starts_with( ltrim( $line ), '#' ) ) {
			$this->at = $next;
			$this->fail( 'a value cannot go on to the next line; use | or > for text over several lines' );
		}
	}

	/**
	 * Refuses YAML features outside the subset.
	 *
	 * @param string $plain Plain scalar text.
	 */
	private function check_plain( string $plain ): void {
		if ( '' !== $plain && str_contains( '&*!%@`|>', $plain[0] ) ) {
			$this->fail( "\"$plain\": anchors, aliases, tags and values starting with {$plain[0]} are not read; put the value in quotes" );
		}
	}

	/**
	 * Reads a flow collection or value at $position.
	 *
	 * @param string $text     Line text.
	 * @param int    $position Read position, moved past the value.
	 * @param int    $depth    Collections around this one.
	 * @throws InvalidArgumentException On a syntax error.
	 */
	private static function flow( string $text, int &$position, int $depth = 0 ): mixed {
		if ( $depth > self::MAX_DEPTH ) {
			throw new InvalidArgumentException( 'lists and maps nest at most ' . self::MAX_DEPTH . ' deep' );
		}
		self::space( $text, $position );
		$char = $text[ $position ] ?? '';
		if ( '{' === $char || '[' === $char ) {
			$close = '{' === $char ? '}' : ']';
			$items = array();
			++$position;
			self::space( $text, $position );
			if ( ( $text[ $position ] ?? '' ) === $close ) {
				++$position;
				return $items;
			}
			while ( true ) {
				if ( '}' === $close ) {
					$key = self::flow_scalar( $text, $position, true );
					if ( ! is_string( $key ) && ! is_int( $key ) ) {
						throw new InvalidArgumentException( 'a key in { } must be text' );
					}
					$key = (string) $key;
					self::space( $text, $position );
					if ( ':' !== ( $text[ $position ] ?? '' ) ) {
						throw new InvalidArgumentException( "\"$key\" in { } needs a \": value\"" );
					}
					++$position;
					if ( array_key_exists( $key, $items ) ) {
						throw new InvalidArgumentException( "the key \"$key\" is repeated" );
					}
					$items[ $key ] = self::flow( $text, $position, $depth + 1 );
				} else {
					$items[] = self::flow( $text, $position, $depth + 1 );
				}
				self::space( $text, $position );
				$next = $text[ $position ] ?? '';
				++$position;
				if ( $next === $close ) {
					return $items;
				}
				if ( ',' !== $next ) {
					throw new InvalidArgumentException( "expected , or $close" );
				}
				self::space( $text, $position );
				if ( ( $text[ $position ] ?? '' ) === $close ) {
					++$position;
					return $items;
				}
			}
		}
		return self::flow_scalar( $text, $position, false );
	}

	/**
	 * Reads a quoted or plain value inside a flow collection.
	 *
	 * @param string $text     Line text.
	 * @param int    $position Read position, moved past the value.
	 * @param bool   $is_key   Whether ":" ends the value.
	 * @throws InvalidArgumentException On a syntax error.
	 */
	private static function flow_scalar( string $text, int &$position, bool $is_key ): mixed {
		self::space( $text, $position );
		$char = $text[ $position ] ?? '';
		if ( '"' === $char || "'" === $char ) {
			return self::quoted( $text, $position );
		}
		$start = $position;
		$len   = strlen( $text );
		while ( $position < $len ) {
			$c = $text[ $position ];
			if ( ',' === $c || '}' === $c || ']' === $c || '{' === $c || '[' === $c ) {
				break;
			}
			if ( ':' === $c && ( $is_key || ' ' === ( $text[ $position + 1 ] ?? ' ' ) ) ) {
				break;
			}
			if ( '#' === $c && $position > $start && ' ' === $text[ $position - 1 ] ) {
				break;
			}
			++$position;
		}
		$plain = trim( substr( $text, $start, $position - $start ) );
		if ( '' !== $plain && str_contains( '&*!%@`|>', $plain[0] ) ) {
			throw new InvalidArgumentException( "\"$plain\": anchors, aliases, tags and values starting with {$plain[0]} are not read; put the value in quotes" );
		}
		return $is_key ? $plain : self::resolve( $plain );
	}

	/**
	 * Reads a single or double quoted string at $position.
	 *
	 * @param string $text     Line text.
	 * @param int    $position Read position at the opening quote, moved past the closing one.
	 * @throws InvalidArgumentException When the string is not closed or has a bad escape.
	 */
	private static function quoted( string $text, int &$position ): string {
		$quote = $text[ $position ];
		$len   = strlen( $text );
		$out   = '';
		++$position;
		while ( $position < $len ) {
			$c = $text[ $position ];
			if ( "'" === $quote && "'" === $c ) {
				if ( "'" === ( $text[ $position + 1 ] ?? '' ) ) {
					$out      .= "'";
					$position += 2;
					continue;
				}
				++$position;
				return $out;
			}
			if ( '"' === $quote && '"' === $c ) {
				++$position;
				return $out;
			}
			if ( '"' === $quote && '\\' === $c ) {
				$e         = $text[ $position + 1 ] ?? '';
				$position += 2;
				$simple    = array(
					'n'  => "\n",
					't'  => "\t",
					'r'  => "\r",
					'0'  => "\0",
					'"'  => '"',
					'\\' => '\\',
					'/'  => '/',
					' '  => ' ',
					'b'  => "\x08",
					'f'  => "\f",
				);
				if ( isset( $simple[ $e ] ) ) {
					$out .= $simple[ $e ];
					continue;
				}
				if ( 'u' === $e && preg_match( '/^[0-9a-fA-F]{4}/', substr( $text, $position, 4 ) ) ) {
					$out      .= mb_chr( (int) hexdec( substr( $text, $position, 4 ) ), 'UTF-8' );
					$position += 4;
					continue;
				}
				throw new InvalidArgumentException( "unknown escape \\$e in a double-quoted value" );
			}
			$out .= $c;
			++$position;
		}
		throw new InvalidArgumentException( 'a quoted value is not closed on its line' );
	}

	/**
	 * Allows only spaces and a comment after a value.
	 *
	 * @param string $text     Line text.
	 * @param int    $position Position after the value.
	 * @throws InvalidArgumentException When something else follows.
	 */
	private static function end( string $text, int $position ): void {
		$rest = trim( substr( $text, $position ) );
		if ( '' !== $rest && ! str_starts_with( $rest, '#' ) ) {
			throw new InvalidArgumentException( "unexpected \"$rest\" after the value" );
		}
	}

	/**
	 * Moves $position past spaces.
	 *
	 * @param string $text     Line text.
	 * @param int    $position Read position.
	 */
	private static function space( string $text, int &$position ): void {
		$len = strlen( $text );
		while ( $position < $len && ( ' ' === $text[ $position ] || "\t" === $text[ $position ] ) ) {
			++$position;
		}
	}

	/**
	 * Whether every { and [ outside quotes is closed.
	 *
	 * @param string $text Text.
	 */
	private static function balanced( string $text ): bool {
		$depth = 0;
		$quote = '';
		$len   = strlen( $text );
		for ( $i = 0; $i < $len; $i++ ) {
			$c = $text[ $i ];
			if ( '' !== $quote ) {
				if ( '\\' === $c && '"' === $quote ) {
					++$i;
				} elseif ( $c === $quote ) {
					$quote = '';
				}
				continue;
			}
			if ( '"' === $c || "'" === $c ) {
				$quote = $c;
			} elseif ( '{' === $c || '[' === $c ) {
				++$depth;
			} elseif ( '}' === $c || ']' === $c ) {
				--$depth;
			} elseif ( '#' === $c && ( 0 === $i || ' ' === $text[ $i - 1 ] ) ) {
				break;
			}
		}
		return $depth <= 0;
	}

	/**
	 * Removes a " #" comment from a plain value.
	 *
	 * @param string $text Text.
	 */
	private static function strip_comment( string $text ): string {
		if ( str_starts_with( $text, '#' ) ) {
			return '';
		}
		$position = strpos( $text, ' #' );
		return false === $position ? $text : substr( $text, 0, $position );
	}

	/**
	 * Types a plain value with the YAML 1.2 core schema.
	 *
	 * @param string $plain Plain scalar text, trimmed.
	 */
	public static function resolve( string $plain ): mixed {
		if ( '' === $plain || '~' === $plain || in_array( $plain, array( 'null', 'Null', 'NULL' ), true ) ) {
			return null;
		}
		if ( in_array( $plain, array( 'true', 'True', 'TRUE' ), true ) ) {
			return true;
		}
		if ( in_array( $plain, array( 'false', 'False', 'FALSE' ), true ) ) {
			return false;
		}
		if ( preg_match( '/^[-+]?[0-9]+$/', $plain ) ) {
			return (int) $plain;
		}
		if ( preg_match( '/^[-+]?(\.[0-9]+|[0-9]+(\.[0-9]*)?)([eE][-+]?[0-9]+)?$/', $plain ) ) {
			return (float) $plain;
		}
		return $plain;
	}

	/**
	 * Writes a value as YAML that parse() reads back to the same value.
	 *
	 * Block style; text is plain when that reads back the same, `|` when it
	 * has line breaks and "double quoted" otherwise. An empty array is [].
	 *
	 * @param mixed $value Arrays, scalars and null.
	 * @return string YAML text ending with a line break.
	 */
	public static function dump( mixed $value ): string {
		if ( is_array( $value ) && array() !== $value ) {
			return implode( "\n", self::block( $value, 0 ) ) . "\n";
		}
		return self::scalar( $value ) . "\n";
	}

	/**
	 * Lines of a non-empty collection whose keys or dashes are at $indent.
	 *
	 * @param array<int|string, mixed> $value  Collection.
	 * @param int                      $indent Column.
	 * @return array<int, string>
	 */
	private static function block( array $value, int $indent ): array {
		$pad   = str_repeat( ' ', $indent );
		$list  = array_is_list( $value );
		$lines = array();
		foreach ( $value as $key => $item ) {
			$head = $pad . ( $list ? '-' : self::text( (string) $key ) . ':' );
			if ( is_array( $item ) && array() !== $item ) {
				$inner = self::block( $item, $indent + 2 );
				if ( $list && ! array_is_list( $item ) ) {
					// "- key: value", with the other keys under the first.
					$inner[0] = $head . ' ' . substr( $inner[0], $indent + 2 );
				} else {
					$lines[] = $head;
				}
				array_push( $lines, ...$inner );
				continue;
			}
			if ( is_string( $item ) && self::literal( $item ) ) {
				$body    = str_ends_with( $item, "\n" ) ? substr( $item, 0, -1 ) : $item;
				$lines[] = $head . ' |' . ( $body === $item ? '-' : '' );
				foreach ( explode( "\n", $body ) as $line ) {
					$lines[] = '' === $line ? '' : $pad . '  ' . $line;
				}
				continue;
			}
			$lines[] = $head . ' ' . self::scalar( $item );
		}
		return $lines;
	}

	/**
	 * Whether text over several lines can be written as a `|` block.
	 *
	 * @param string $text Text.
	 */
	private static function literal( string $text ): bool {
		return str_contains( $text, "\n" )
			&& mb_check_encoding( $text, 'UTF-8' )
			&& ! str_ends_with( $text, "\n\n" )
			&& ! preg_match( '/^[ \n]|[\x00-\x09\x0b-\x1f\x7f]|\n +(\n|$)/', $text );
	}

	/**
	 * A scalar on one line.
	 *
	 * @param mixed $value Scalar or null.
	 * @throws InvalidArgumentException For objects, resources, NAN and INF.
	 */
	private static function scalar( mixed $value ): string {
		if ( null === $value ) {
			return 'null';
		}
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_int( $value ) ) {
			return (string) $value;
		}
		if ( is_float( $value ) && is_finite( $value ) ) {
			return self::json( $value );
		}
		if ( is_string( $value ) ) {
			return self::text( $value );
		}
		if ( array() === $value ) {
			return '[]';
		}
		throw new InvalidArgumentException( sprintf( 'a %s cannot be written as YAML', get_debug_type( $value ) ) );
	}

	/**
	 * Text, plain when that reads back the same, else double quoted.
	 *
	 * @param string $text Text.
	 * @throws InvalidArgumentException For text that is not UTF-8.
	 */
	private static function text( string $text ): string {
		if ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
			throw new InvalidArgumentException( 'text that is not UTF-8 cannot be written as YAML' );
		}
		$plain = '' !== $text
			&& self::resolve( $text ) === $text
			&& ! preg_match( '/^[\s\-?:,\[\]{}#&*!|>\'"%@`]|\s$|: | #|:$|[\x00-\x1f\x7f]/', $text );
		return $plain ? $text : self::json( $text );
	}

	/**
	 * JSON for a float or a quoted string; JSON escapes are YAML escapes.
	 *
	 * @param float|string $value Value, text in UTF-8.
	 */
	private static function json( float|string $value ): string {
		return (string) json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- YAML double-quoted escapes, not data for WordPress.
	}

	/**
	 * Skips blank and comment-only lines.
	 */
	private function skip_blank(): void {
		while ( $this->at < $this->total ) {
			$line = $this->lines[ $this->at ];
			$trim = ltrim( $line, ' ' );
			if ( '' !== $trim && ! str_starts_with( $trim, '#' ) ) {
				if ( str_starts_with( $trim, "\t" ) || ( strlen( $line ) > strlen( $trim ) && str_contains( substr( $line, 0, strlen( $line ) - strlen( ltrim( $line ) ) ), "\t" ) ) ) {
					$this->fail( 'tabs cannot be used for indentation; use spaces' );
				}
				return;
			}
			++$this->at;
		}
	}

	/**
	 * Number of leading spaces.
	 *
	 * @param string $line Line.
	 */
	private static function indent( string $line ): int {
		return strlen( $line ) - strlen( ltrim( $line, ' ' ) );
	}

	/**
	 * Stops with the current line number.
	 *
	 * @param string $problem What is wrong.
	 * @throws InvalidArgumentException Always.
	 * @return never
	 */
	private function fail( string $problem ): never {
		throw new InvalidArgumentException( sprintf( 'line %d: %s', $this->at + 1, $problem ) );
	}
}
