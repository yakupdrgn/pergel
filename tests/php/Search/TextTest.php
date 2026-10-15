<?php
/**
 * Search text tests.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Search;

use Pergel\Search\Text;
use PHPUnit\Framework\TestCase;

/**
 * Covers folding (Turkish letters, accents, both cases of I), tokens, codes
 * and query terms.
 */
final class TextTest extends TestCase {

	public function test_turkish_letters_fold_in_either_case(): void {
		foreach ( array( 'Işık', 'IŞIK', 'ışık', 'isik', 'ISIK' ) as $word ) {
			$this->assertSame( 'isik', Text::fold( $word ), $word );
		}
		$this->assertSame( 'istanbul', Text::fold( 'İstanbul' ) );
		$this->assertSame( 'istanbul', Text::fold( 'İSTANBUL' ) );
		$this->assertSame( 'cagri gunes ogle', Text::fold( 'Çağrı Güneş Öğle' ) );
	}

	public function test_accents_and_ligatures_fold(): void {
		$this->assertSame( 'creme brulee', Text::fold( 'Crème Brûlée' ) );
		$this->assertSame( 'strasse', Text::fold( 'Straße' ) );
		$this->assertSame( 'kar', Text::fold( 'Kâr' ) );
	}

	public function test_punctuation_becomes_single_spaces(): void {
		$this->assertSame( 'ab 12 3 masa', Text::fold( '  AB-12/3 — “masa”! ' ) );
		$this->assertSame( '', Text::fold( '…?!' ) );
	}

	public function test_tokens_drop_repeats_and_single_letters(): void {
		$this->assertSame( array( 'masa', 'ahsap', '2', 'kisilik' ), Text::tokens( 'Masa a ahşap MASA 2 kişilik' ) );
		$this->assertSame( array( 'bir', 'iki' ), Text::tokens( 'bir iki üç dört', 2 ) );
	}

	public function test_long_words_are_cut(): void {
		$tokens = Text::tokens( str_repeat( 'ab', 30 ) );
		$this->assertSame( Text::MAX_LENGTH, mb_strlen( $tokens[0] ) );
	}

	public function test_compact_removes_code_punctuation(): void {
		$this->assertSame( 'ab123', Text::compact( 'AB-12/3' ) );
		$this->assertSame( 'cs90', Text::compact( 'ÇS 90' ) );
	}

	public function test_terms_skip_prefixes_of_other_terms(): void {
		$this->assertSame( array( 'mikroskop' ), Text::terms( 'mikro mikroskop' ) );
		$this->assertSame( array( 'isikli', 'masa' ), Text::terms( 'Işıklı masa' ) );
		$this->assertSame( array(), Text::terms( 'a' ) );
	}

	public function test_terms_are_limited(): void {
		$this->assertCount( Text::MAX_TERMS, Text::terms( 'bir iki uc dort bes alti yedi sekiz' ) );
	}
}
