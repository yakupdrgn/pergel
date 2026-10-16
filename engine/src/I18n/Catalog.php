<?php
/**
 * Reads, merges and writes gettext PO files.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\I18n;

/**
 * The bundled translations live next to the .pot files as PO files
 * (theme/languages/tr_TR.po). tools/i18n.php merges each .pot into
 * its PO file with this class: entries follow the template's order, keep
 * their translations, and strings the template dropped disappear. The PO
 * file keeps no source references, like the template, so moving code does
 * not change it. Needs no WordPress: CI runs it.
 *
 * An entry is an array with:
 * - comments: raw comment lines ("#. ...", "#: ...", "#, ...").
 * - context:  msgctxt, or null.
 * - id:       msgid.
 * - plural:   msgid_plural, or null.
 * - strings:  msgstr values (one, or one per plural form).
 *
 * @phpstan-type Entry array{comments: list<string>, context: ?string, id: string, plural: ?string, strings: list<string>}
 */
final class Catalog {

	/**
	 * Plural rule of each bundled locale, as WordPress language packs write it.
	 */
	public const PLURAL_FORMS = array(
		'tr_TR' => 'nplurals=2; plural=n > 1;',
	);

	/**
	 * Order of the PO header rows; other rows follow.
	 */
	private const HEADER_ORDER = array( 'Project-Id-Version', 'Report-Msgid-Bugs-To', 'POT-Creation-Date', 'PO-Revision-Date', 'Language', 'MIME-Version', 'Content-Type', 'Content-Transfer-Encoding', 'Plural-Forms', 'X-Domain' );

	/**
	 * Entries of a PO or POT file, header first.
	 *
	 * @param string $source File contents.
	 * @return list<Entry>
	 * @throws \RuntimeException When a line cannot be read.
	 */
	public static function parse( string $source ): array {
		$entries = array();
		$entry   = self::blank();
		$field   = null;
		$started = false;
		foreach ( explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", $source ) ) as $number => $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( str_starts_with( $line, '#' ) ) {
				if ( $started ) {
					$entries[] = $entry;
					$entry     = self::blank();
					$started   = false;
					$field     = null;
				}
				$entry['comments'][] = $line;
				continue;
			}
			if ( ! preg_match( '/^(msgctxt|msgid_plural|msgid|msgstr(?:\[(\d+)\])?)\s+"(.*)"$/', $line, $match ) ) {
				if ( null !== $field && preg_match( '/^"(.*)"$/', $line, $continued ) ) {
					self::append( $entry, $field, self::unescape( $continued[1] ) );
					continue;
				}
				throw new \RuntimeException( sprintf( 'Line %d cannot be read: %s', $number + 1, $line ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI message, never HTML.
			}
			$keyword = $match[1];
			$value   = self::unescape( $match[3] );
			if ( $started && ( 'msgctxt' === $keyword || ( 'msgid' === $keyword && 'context' !== $field ) ) ) {
				$entries[] = $entry;
				$entry     = self::blank();
			}
			$started = true;
			$field   = match ( true ) {
				'msgctxt' === $keyword      => 'context',
				'msgid' === $keyword        => 'id',
				'msgid_plural' === $keyword => 'plural',
				default                     => 'strings:' . ( '' === $match[2] ? '0' : $match[2] ),
			};
			self::append( $entry, $field, $value, true );
		}
		if ( $started ) {
			$entries[] = $entry;
		}
		return $entries;
	}

	/**
	 * The template's entries with the translations of the PO file.
	 *
	 * The header comes from the template, minus its dates and placeholders,
	 * plus the locale, its plural rule and the revision date of the PO file,
	 * so the result does not change between runs (see self::stamp()).
	 * Fuzzy translations are kept and stay fuzzy.
	 *
	 * @param array<int, array<string, mixed>> $template Entries of the .pot file.
	 * @param array<int, array<string, mixed>> $existing Entries of the PO file (empty for a new locale).
	 * @param string                           $locale   Locale such as tr_TR.
	 * @phpstan-param list<Entry> $template
	 * @phpstan-param list<Entry> $existing
	 * @return list<Entry>
	 */
	public static function merge( array $template, array $existing, string $locale ): array {
		$known = array();
		foreach ( $existing as $entry ) {
			$known[ self::key( $entry ) ] = $entry;
		}
		preg_match( '/^PO-Revision-Date: (.+)$/m', $known['']['strings'][0] ?? '', $date );
		$merged = array();
		foreach ( $template as $entry ) {
			if ( '' === $entry['id'] && null === $entry['context'] ) {
				$merged[] = self::header( $entry, $locale, $date[1] ?? null );
				continue;
			}
			$entry['comments'] = array_values( array_filter( $entry['comments'], static fn ( string $line ): bool => ! str_starts_with( $line, '#,' ) ) );
			$entry['strings']  = array_fill( 0, null === $entry['plural'] ? 1 : self::plural_count( $locale ), '' );
			$old               = $known[ self::key( $entry ) ] ?? null;
			if ( null !== $old ) {
				foreach ( array_keys( $entry['strings'] ) as $index ) {
					$entry['strings'][ $index ] = $old['strings'][ $index ] ?? '';
				}
				foreach ( $old['comments'] as $line ) {
					if ( str_starts_with( $line, '#,' ) && str_contains( $line, 'fuzzy' ) ) {
						$entry['comments'][] = '#, fuzzy';
					}
				}
			}
			$merged[] = $entry;
		}
		return $merged;
	}

	/**
	 * Copies translations into entries that have none, by context and msgid.
	 *
	 * @param array<int, array<string, mixed>> $entries      Entries to fill.
	 * @param array<string, string>            $translations Translations keyed by self::key() (singular only).
	 * @phpstan-param list<Entry> $entries
	 * @return list<Entry>
	 */
	public static function fill( array $entries, array $translations ): array {
		foreach ( $entries as $index => $entry ) {
			$key = self::key( $entry );
			if ( '' !== $entry['id'] && '' === $entry['strings'][0] && isset( $translations[ $key ] ) ) {
				$entries[ $index ]['strings'] = array_fill( 0, count( $entry['strings'] ), $translations[ $key ] );
			}
		}
		return $entries;
	}

	/**
	 * PO file contents.
	 *
	 * @param array<int, array<string, mixed>> $entries Entries, header first.
	 * @phpstan-param list<Entry> $entries
	 * @return string
	 */
	public static function write( array $entries ): string {
		$blocks = array();
		foreach ( $entries as $entry ) {
			$lines = $entry['comments'];
			if ( null !== $entry['context'] ) {
				$lines[] = 'msgctxt ' . self::quote( $entry['context'] );
			}
			$lines[] = 'msgid ' . self::quote( $entry['id'] );
			if ( null !== $entry['plural'] ) {
				$lines[] = 'msgid_plural ' . self::quote( $entry['plural'] );
				foreach ( $entry['strings'] as $index => $string ) {
					$lines[] = 'msgstr[' . $index . '] ' . self::quote( $string );
				}
			} elseif ( '' === $entry['id'] ) {
				$lines[] = 'msgstr ""';
				foreach ( explode( "\n", rtrim( $entry['strings'][0] ?? '', "\n" ) ) as $header ) {
					$lines[] = self::quote( $header . "\n" );
				}
			} else {
				$lines[] = 'msgstr ' . self::quote( $entry['strings'][0] ?? '' );
			}
			$blocks[] = implode( "\n", $lines );
		}
		return implode( "\n\n", $blocks ) . "\n";
	}

	/**
	 * Problems that block a release: untranslated or fuzzy entries, and
	 * translations whose printf placeholders differ from the original.
	 *
	 * @param array<int, array<string, mixed>> $entries Entries.
	 * @phpstan-param list<Entry> $entries
	 * @return list<string>
	 */
	public static function problems( array $entries ): array {
		$problems = array();
		foreach ( $entries as $entry ) {
			if ( '' === $entry['id'] ) {
				continue;
			}
			$label = '"' . $entry['id'] . '"' . ( null === $entry['context'] ? '' : ' (' . $entry['context'] . ')' );
			if ( in_array( '', $entry['strings'], true ) ) {
				$problems[] = 'untranslated: ' . $label;
				continue;
			}
			if ( in_array( '#, fuzzy', $entry['comments'], true ) ) {
				$problems[] = 'fuzzy: ' . $label;
			}
			$expected = self::placeholders( $entry['id'] );
			foreach ( $entry['strings'] as $string ) {
				if ( self::placeholders( $string ) !== $expected ) {
					$problems[] = 'placeholders differ: ' . $label;
					break;
				}
			}
		}
		return $problems;
	}

	/**
	 * Lookup key of an entry: context and msgid joined like in a .mo file.
	 *
	 * @param array<string, mixed> $entry Entry.
	 * @phpstan-param Entry $entry
	 * @return string
	 */
	public static function key( array $entry ): string {
		return null === $entry['context'] ? $entry['id'] : $entry['context'] . "\4" . $entry['id'];
	}

	/**
	 * Sorted printf placeholders of a string ("%s", "%1$s", "%d").
	 *
	 * Numbered placeholders may change order in a translation, so the list
	 * is sorted; a literal "%%" is not a placeholder.
	 *
	 * @param string $text Text.
	 * @return list<string>
	 */
	private static function placeholders( string $text ): array {
		preg_match_all( '/%(?:\d+\$)?[-+ 0\'#]*\d*(?:\.\d+)?[bcdeEfFgGosuxX]/', str_replace( '%%', '', $text ), $found );
		$list = $found[0];
		sort( $list );
		return $list;
	}

	/**
	 * Entries with a new revision date in the header.
	 *
	 * WP-CLI writes the current time into compiled files whose PO header has
	 * no dates, so the header always carries one: the time the translations
	 * last changed. It is also used as the creation date.
	 *
	 * @param array<int, array<string, mixed>> $entries Entries, header first.
	 * @param string                           $date    Date such as "2026-10-16 09:00+0000".
	 * @phpstan-param list<Entry> $entries
	 * @return list<Entry>
	 */
	public static function stamp( array $entries, string $date ): array {
		if ( isset( $entries[0] ) && '' === $entries[0]['id'] ) {
			$rows                      = self::rows( $entries[0]['strings'][0] ?? '' );
			$rows['POT-Creation-Date'] = $date;
			$rows['PO-Revision-Date']  = $date;
			$entries[0]['strings']     = array( self::join_rows( $rows ) );
		}
		return $entries;
	}

	/**
	 * Header entry for a locale.
	 *
	 * @param array<string, mixed> $template Header of the .pot file.
	 * @param string               $locale   Locale.
	 * @param ?string              $date     Revision date of the existing PO file.
	 * @phpstan-param Entry $template
	 * @return Entry
	 */
	private static function header( array $template, string $locale, ?string $date ): array {
		$keep                 = array( 'Project-Id-Version', 'Report-Msgid-Bugs-To', 'MIME-Version', 'Content-Type', 'Content-Transfer-Encoding', 'X-Domain' );
		$rows                 = array_intersect_key( self::rows( $template['strings'][0] ?? '' ), array_flip( $keep ) );
		$rows['Language']     = $locale;
		$rows['Plural-Forms'] = self::PLURAL_FORMS[ $locale ] ?? 'nplurals=2; plural=n != 1;';
		if ( null !== $date ) {
			$rows['POT-Creation-Date'] = $date;
			$rows['PO-Revision-Date']  = $date;
		}
		return array(
			'comments' => array_values( array_filter( $template['comments'], static fn ( string $line ): bool => ! str_starts_with( $line, '#,' ) ) ),
			'context'  => null,
			'id'       => '',
			'plural'   => null,
			'strings'  => array( self::join_rows( $rows ) ),
		);
	}

	/**
	 * Header rows keyed by name.
	 *
	 * @param string $header Header text ("Name: value" lines).
	 * @return array<string, string>
	 */
	private static function rows( string $header ): array {
		$rows = array();
		foreach ( explode( "\n", $header ) as $row ) {
			$parts = explode( ':', $row, 2 );
			if ( 2 === count( $parts ) ) {
				$rows[ trim( $parts[0] ) ] = trim( $parts[1] );
			}
		}
		return $rows;
	}

	/**
	 * Header text in a fixed row order.
	 *
	 * @param array<string, string> $rows Rows keyed by name.
	 * @return string
	 */
	private static function join_rows( array $rows ): string {
		$text = '';
		foreach ( array_replace( array_intersect_key( array_flip( self::HEADER_ORDER ), $rows ), $rows ) as $name => $value ) {
			$text .= "{$name}: {$value}\n";
		}
		return $text;
	}

	/**
	 * Number of plural forms of a locale.
	 *
	 * @param string $locale Locale.
	 * @return int
	 */
	private static function plural_count( string $locale ): int {
		preg_match( '/nplurals=(\d+)/', self::PLURAL_FORMS[ $locale ] ?? 'nplurals=2', $match );
		return max( 1, (int) ( $match[1] ?? 2 ) );
	}

	/**
	 * An empty entry.
	 *
	 * @return Entry
	 */
	private static function blank(): array {
		return array(
			'comments' => array(),
			'context'  => null,
			'id'       => '',
			'plural'   => null,
			'strings'  => array(),
		);
	}

	/**
	 * Adds text to a field of an entry.
	 *
	 * @param array<string, mixed> $entry   Entry, changed in place.
	 * @param string               $field   context, id, plural or strings:<index>.
	 * @param string               $value   Text.
	 * @param bool                 $replace Whether the field starts with this text.
	 * @phpstan-param Entry $entry
	 * @phpstan-param-out Entry $entry
	 */
	private static function append( array &$entry, string $field, string $value, bool $replace = false ): void {
		if ( str_starts_with( $field, 'strings:' ) ) {
			$index             = (int) substr( $field, 8 );
			$strings           = $entry['strings'];
			$strings[ $index ] = ( $replace ? '' : ( $strings[ $index ] ?? '' ) ) . $value;
			ksort( $strings );
			$entry['strings'] = array_values( $strings );
			return;
		}
		if ( 'context' === $field ) {
			$entry['context'] = ( $replace ? '' : (string) $entry['context'] ) . $value;
		} elseif ( 'plural' === $field ) {
			$entry['plural'] = ( $replace ? '' : (string) $entry['plural'] ) . $value;
		} else {
			$entry['id'] = ( $replace ? '' : $entry['id'] ) . $value;
		}
	}

	/**
	 * A string as a quoted PO literal.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function quote( string $text ): string {
		return '"' . strtr(
			$text,
			array(
				'\\' => '\\\\',
				'"'  => '\\"',
				"\n" => '\\n',
				"\t" => '\\t',
			)
		) . '"';
	}

	/**
	 * Text of a quoted PO literal (without the quotes).
	 *
	 * @param string $text Literal.
	 * @return string
	 */
	private static function unescape( string $text ): string {
		return (string) preg_replace_callback(
			'/\\\\(.)/',
			static fn ( array $found ): string => match ( $found[1] ) {
				'n'     => "\n",
				't'     => "\t",
				default => $found[1],
			},
			$text
		);
	}
}
