<?php
/**
 * Search text folding and tokenizing.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Search;

/**
 * Turns text into the tokens the index stores and a query into the terms
 * it looks for. Both sides fold the same way: lower case, then accents
 * and the Turkish letters off, so "Işık", "ışık", "IŞIK" and "isik" are
 * the same token. Folding both I and İ to i makes the result independent
 * of the language a word was typed in.
 *
 * Pure PHP (mbstring, intl when present) so it can be unit tested.
 */
final class Text {

	/**
	 * Longest token kept, in characters. Longer words are cut, so a prefix
	 * of a long word still matches.
	 */
	public const MAX_LENGTH = 32;

	/**
	 * Most terms read from a query.
	 */
	public const MAX_TERMS = 6;

	/**
	 * Letters that remain after lower-casing and stripping combining marks,
	 * plus the accented ones for servers without intl.
	 */
	private const FOLD = array(
		'ı' => 'i',
		'ğ' => 'g',
		'ü' => 'u',
		'ş' => 's',
		'ö' => 'o',
		'ç' => 'c',
		'â' => 'a',
		'î' => 'i',
		'û' => 'u',
		'à' => 'a',
		'á' => 'a',
		'ã' => 'a',
		'ä' => 'a',
		'å' => 'a',
		'ā' => 'a',
		'ă' => 'a',
		'ą' => 'a',
		'æ' => 'ae',
		'ć' => 'c',
		'č' => 'c',
		'ď' => 'd',
		'đ' => 'd',
		'ð' => 'd',
		'è' => 'e',
		'é' => 'e',
		'ê' => 'e',
		'ë' => 'e',
		'ē' => 'e',
		'ė' => 'e',
		'ę' => 'e',
		'ě' => 'e',
		'ģ' => 'g',
		'ì' => 'i',
		'í' => 'i',
		'ï' => 'i',
		'ī' => 'i',
		'į' => 'i',
		'ķ' => 'k',
		'ĺ' => 'l',
		'ļ' => 'l',
		'ľ' => 'l',
		'ł' => 'l',
		'ñ' => 'n',
		'ń' => 'n',
		'ņ' => 'n',
		'ň' => 'n',
		'ò' => 'o',
		'ó' => 'o',
		'ô' => 'o',
		'õ' => 'o',
		'ø' => 'o',
		'ō' => 'o',
		'ő' => 'o',
		'œ' => 'oe',
		'ŕ' => 'r',
		'ř' => 'r',
		'ś' => 's',
		'š' => 's',
		'ș' => 's',
		'ß' => 'ss',
		'ţ' => 't',
		'ť' => 't',
		'ț' => 't',
		'þ' => 'th',
		'ù' => 'u',
		'ú' => 'u',
		'ū' => 'u',
		'ů' => 'u',
		'ű' => 'u',
		'ų' => 'u',
		'ý' => 'y',
		'ÿ' => 'y',
		'ź' => 'z',
		'ż' => 'z',
		'ž' => 'z',
	);

	/**
	 * Lower case without accents; everything but letters and digits becomes
	 * a single space.
	 *
	 * @param string $text Any UTF-8 text.
	 */
	public static function fold( string $text ): string {
		$text = mb_strtolower( $text, 'UTF-8' );
		if ( class_exists( \Normalizer::class ) ) {
			$decomposed = \Normalizer::normalize( $text, \Normalizer::FORM_D );
			if ( is_string( $decomposed ) ) {
				$text = (string) preg_replace( '/\p{Mn}+/u', '', $decomposed );
			}
		}
		$text = strtr( $text, self::FOLD );
		// Without intl, İ lower-cases to i + a combining dot that the map cannot reach as one letter.
		$text = str_replace( "\u{0307}", '', $text );
		return trim( (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text ) );
	}

	/**
	 * Index tokens of a text, in order of appearance, without repeats.
	 * Single letters are dropped; single digits are kept (sizes, models).
	 *
	 * @param string $text  Any UTF-8 text.
	 * @param int    $limit Most tokens returned; 0 for all.
	 * @return list<string>
	 */
	public static function tokens( string $text, int $limit = 0 ): array {
		$tokens = array();
		foreach ( explode( ' ', self::fold( $text ) ) as $word ) {
			if ( '' === $word || ( 1 === mb_strlen( $word, 'UTF-8' ) && ! ctype_digit( $word ) ) ) {
				continue;
			}
			$tokens[ mb_substr( $word, 0, self::MAX_LENGTH, 'UTF-8' ) ] = true;
			if ( $limit > 0 && count( $tokens ) >= $limit ) {
				break;
			}
		}
		return array_map( 'strval', array_keys( $tokens ) );
	}

	/**
	 * A code such as a SKU as one more token, its punctuation removed:
	 * "AB-12/3" also gives "ab123", so it is found typed either way.
	 *
	 * @param string $code Code.
	 */
	public static function compact( string $code ): string {
		return mb_substr( str_replace( ' ', '', self::fold( $code ) ), 0, self::MAX_LENGTH, 'UTF-8' );
	}

	/**
	 * The terms of a query. A result must match each of them, as a word or
	 * the start of one.
	 *
	 * @param string $query Visitor input.
	 * @return list<string>
	 */
	public static function terms( string $query ): array {
		$query = mb_substr( $query, 0, 200, 'UTF-8' );
		$terms = self::tokens( $query );
		// Prefixes of one another add nothing: "mikro mikroskop" is "mikroskop".
		$terms = array_values(
			array_filter(
				$terms,
				static function ( string $term ) use ( $terms ): bool {
					foreach ( $terms as $other ) {
						if ( $other !== $term && str_starts_with( $other, $term ) ) {
							return false;
						}
					}
					return true;
				}
			)
		);
		return array_slice( $terms, 0, self::MAX_TERMS );
	}
}
