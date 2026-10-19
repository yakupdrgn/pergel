<?php
/**
 * Inline HTML to Markdown tests.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Package;

use Pergel\Package\Markdown;
use Pergel\Package\MarkdownWriter;
use PHPUnit\Framework\TestCase;

/**
 * MarkdownWriter::inline() writes what Markdown::inline() reads back.
 */
final class MarkdownWriterTest extends TestCase {

	/**
	 * Inline HTML the editor saves, the Markdown it should become, and the
	 * HTML that Markdown reads back as (null: the same HTML).
	 *
	 * @return array<string, array{string, string, ?string}>
	 */
	public static function spans(): array {
		return array(
			'plain'            => array( 'Bir satır', 'Bir satır', null ),
			'entities'         => array( 'Alt &amp; başlık &lt;b&gt; &quot;tırnak&quot;', 'Alt & başlık <b> "tırnak"', 'Alt &amp; başlık &lt;b&gt; "tırnak"' ),
			'strong and em'    => array( '<strong>Kalın</strong>, <em>eğik</em> ve <b>b</b> <i>i</i>', '**Kalın**, *eğik* ve **b** *i*', '<strong>Kalın</strong>, <em>eğik</em> ve <strong>b</strong> <em>i</em>' ),
			'nested'           => array( '<strong>çok <em>önemli</em></strong>', '__çok *önemli*__', null ),
			'em around strong' => array( '<em>bir <strong>iki</strong></em>', '*bir **iki***', null ),
			'spaces outside'   => array( 'a<strong> kalın </strong>b', 'a **kalın** b', 'a <strong>kalın</strong> b' ),
			'code'             => array( 'yaz <code>&lt;b&gt; *x*</code> diye', 'yaz `<b> *x*` diye', null ),
			'link'             => array( '<a href="/urunler/?a=1&amp;b=2">ürünler</a>', '[ürünler](/urunler/?a=1&b=2)', null ),
			'link with em'     => array( '<a href="/a_b/"><em>bak</em></a>', '[*bak*](/a_b/)', null ),
			'line break'       => array( 'bir<br>iki', "bir\\\niki", null ),
			'markdown in text' => array( '*yıldız* [köşeli] `tik` \ters', '\*yıldız\* \[köşeli\] \`tik\` \\\\ters', null ),
			'underscores'      => array( 'snake_case_word _alt_', 'snake_case_word \_alt\_', null ),
			'other tags'       => array( '<span class="x">metin</span> <mark>işaret</mark>', 'metin işaret', 'metin işaret' ),
			'unclosed tag'     => array( '<strong>yarım', 'yarım', 'yarım' ),
			'empty strong'     => array( 'a<strong> </strong>b', 'a b', 'a b' ),
		);
	}

	/**
	 * @dataProvider spans
	 *
	 * @param string      $html     Inline HTML.
	 * @param string      $markdown Its Markdown.
	 * @param string|null $back     What the Markdown reads back as.
	 */
	public function test_inline( string $html, string $markdown, ?string $back ): void {
		$this->assertSame( $markdown, MarkdownWriter::inline( $html ) );
		$this->assertSame( $back ?? $html, Markdown::inline( $markdown ) );
	}

	public function test_heading_anchor(): void {
		$block = array(
			'blockName'    => 'core/heading',
			'attrs'        => array( 'level' => 3 ),
			'innerBlocks'  => array(),
			'innerContent' => array( "\n<h3 class=\"wp-block-heading\" id=\"warranty\">Warranty</h3>\n" ),
		);
		$this->assertSame( '### Warranty {#warranty}', MarkdownWriter::block( $block, static fn(): ?string => null ) );
	}

	public function test_inner(): void {
		$this->assertSame( 'a <p>b</p> c', MarkdownWriter::inner( '<div><p class="x">a <p>b</p> c</p></div>', 'p' ) );
		$this->assertNull( MarkdownWriter::inner( '<div>x</div>', 'p' ) );
	}
}
