<?php
/**
 * PO catalog tests.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\I18n;

use Pergel\I18n\Catalog;
use PHPUnit\Framework\TestCase;

/**
 * Reading, merging and writing the bundled PO files.
 */
final class CatalogTest extends TestCase {

	private const TEMPLATE = <<<'POT'
# Copyright (C) 2026 Ariumlab
msgid ""
msgstr ""
"Project-Id-Version: Pergel 0.1.0\n"
"Last-Translator: FULL NAME <EMAIL@ADDRESS>\n"
"Content-Type: text/plain; charset=UTF-8\n"
"POT-Creation-Date: 2026-10-16T01:23:05+03:00\n"
"X-Domain: pergel\n"

#. Pattern title
msgctxt "Pattern title"
msgid "Hero"
msgstr ""

#, php-format
msgid "Search results for “%s”"
msgstr ""

msgid "Say \"hi\"\n"
msgstr ""

msgid "%d item"
msgid_plural "%d items"
msgstr[0] ""
msgstr[1] ""
POT;

	public function test_parse_reads_context_comments_escapes_and_plurals(): void {
		$entries = Catalog::parse( self::TEMPLATE );

		$this->assertCount( 5, $entries );
		$this->assertSame( '', $entries[0]['id'] );
		$this->assertStringContainsString( "X-Domain: pergel\n", $entries[0]['strings'][0] );
		$this->assertSame( 'Pattern title', $entries[1]['context'] );
		$this->assertSame( array( '#. Pattern title' ), $entries[1]['comments'] );
		$this->assertSame( "Say \"hi\"\n", $entries[3]['id'] );
		$this->assertSame( '%d items', $entries[4]['plural'] );
		$this->assertSame( array( '', '' ), $entries[4]['strings'] );
	}

	public function test_merge_keeps_translations_in_template_order_and_drops_old_strings(): void {
		$existing = Catalog::parse(
			<<<'PO'
msgid "Removed string"
msgstr "Kaldırılan metin"

msgctxt "Pattern title"
msgid "Hero"
msgstr "Giriş"

#, fuzzy
msgid "Search results for “%s”"
msgstr "“%s” için sonuçlar"
PO
		);
		$merged   = Catalog::merge( Catalog::parse( self::TEMPLATE ), $existing, 'tr_TR' );

		$this->assertSame( array( '', 'Hero', 'Search results for “%s”', "Say \"hi\"\n", '%d item' ), array_column( $merged, 'id' ) );
		$this->assertSame( array( 'Giriş' ), $merged[1]['strings'] );
		$this->assertSame( array( '#, fuzzy' ), $merged[2]['comments'] );
		$this->assertSame( array( '' ), $merged[3]['strings'] );
	}

	public function test_header_is_stable_and_names_the_locale(): void {
		$header = Catalog::merge( Catalog::parse( self::TEMPLATE ), array(), 'tr_TR' )[0]['strings'][0];

		$this->assertStringContainsString( "Language: tr_TR\n", $header );
		$this->assertStringContainsString( "Plural-Forms: nplurals=2; plural=n > 1;\n", $header );
		$this->assertStringNotContainsString( 'POT-Creation-Date', $header );
		$this->assertStringNotContainsString( 'FULL NAME', $header );
	}

	public function test_write_then_parse_gives_the_same_entries(): void {
		$entries = Catalog::fill(
			Catalog::merge( Catalog::parse( self::TEMPLATE ), array(), 'tr_TR' ),
			array(
				"Pattern title\4Hero" => 'Giriş',
				"Say \"hi\"\n"        => "\"Merhaba\" de\n",
				'%d item'             => '%d öğe',
			)
		);

		$this->assertSame( $entries, Catalog::parse( Catalog::write( $entries ) ) );
		$this->assertSame( array( '%d öğe', '%d öğe' ), $entries[4]['strings'] );
	}

	public function test_problems_list_missing_fuzzy_and_wrong_placeholders(): void {
		$entries = Catalog::fill(
			Catalog::merge( Catalog::parse( self::TEMPLATE ), array(), 'tr_TR' ),
			array(
				"Pattern title\4Hero"     => 'Giriş',
				'Search results for “%s”' => 'Arama sonuçları',
				'%d item'                 => '%d öğe',
			)
		);

		$this->assertSame(
			array(
				'placeholders differ: "Search results for “%s”"',
				"untranslated: \"Say \"hi\"\n\"",
			),
			Catalog::problems( $entries )
		);
	}

	public function test_numbered_placeholders_may_change_order(): void {
		$entries = Catalog::parse(
			<<<'PO'
msgid "%1$s was not saved. %2$s"
msgstr "%2$s %1$s kaydedilmedi."

msgid "100%% sure"
msgstr "%%100 emin"
PO
		);

		$this->assertSame( array(), Catalog::problems( $entries ) );
	}
}
