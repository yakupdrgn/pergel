<?php
/**
 * Markdown to blocks tests.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Package;

use InvalidArgumentException;
use Pergel\Package\Markdown;
use PHPUnit\Framework\TestCase;

/**
 * Block markup as the editor saves it.
 */
final class MarkdownTest extends TestCase {

	/**
	 * Converts with an image resolver that knows media/known.jpg.
	 *
	 * @param string $markdown Markdown.
	 */
	private static function convert( string $markdown ): string {
		return Markdown::convert(
			$markdown,
			static fn( string $src, string $alt ): array => 'media/known.jpg' === $src
				? array(
					'id'  => 12,
					'url' => 'http://site/wp-content/uploads/known.jpg',
				)
				: array(
					'id'  => 0,
					'url' => $src,
				)
		);
	}

	public function test_paragraphs_and_headings(): void {
		$this->assertSame(
			"<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Neden biz?</h2>\n<!-- /wp:heading -->\n\n"
			. "<!-- wp:paragraph -->\n<p>Bir satır ve devamı.</p>\n<!-- /wp:paragraph -->\n\n"
			. "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">Alt &amp; başlık</h3>\n<!-- /wp:heading -->",
			self::convert( "## Neden biz?\n\nBir satır\nve devamı.\n\n### Alt & başlık ##\n" )
		);
	}

	public function test_inline_spans_escape_html(): void {
		$this->assertSame(
			'<strong>Kalın</strong>, <em>eğik</em>, <em>alt</em>, <code>&lt;b&gt;</code>, <a href="/urunler/?a=1&amp;b=2">ürünler</a>, &lt;script&gt; "tırnak" snake_case_word *yıldız*<br>yeni satır',
			Markdown::inline( "**Kalın**, *eğik*, _alt_, `<b>`, [ürünler](/urunler/?a=1&b=2), <script> \"tırnak\" snake_case_word \\*yıldız\\*  \nyeni satır" )
		);
		$this->assertSame( '<a href="/a_b_c/">x</a>', Markdown::inline( '[x](/a_b_c/)' ) );
	}

	public function test_refuses_script_links_and_h1(): void {
		try {
			Markdown::inline( '[x](javascript:alert(1))' );
			$this->fail( 'Expected an error.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertStringContainsString( 'javascript:', $e->getMessage() );
		}
		foreach ( array( "\x01javascript:alert(1)", "java\tscript:alert(1)", 'a b' ) as $url ) {
			try {
				Markdown::url( $url );
				$this->fail( 'Expected an error.' );
			} catch ( InvalidArgumentException $e ) {
				$this->assertStringContainsString( 'spaces or control characters', $e->getMessage() );
			}
		}
		$this->assertSame( '/a%20b/', Markdown::url( '/a%20b/' ) );
		try {
			self::convert( "Metin\n\n# Başlık" );
			$this->fail( 'Expected an error.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertStringStartsWith( 'line 3: a # heading is the page title', $e->getMessage() );
		}
	}

	public function test_lists_nest(): void {
		$this->assertSame(
			"<!-- wp:list -->\n<ul class=\"wp-block-list\"><!-- wp:list-item -->\n<li>Bir<!-- wp:list {\"ordered\":true} -->\n<ol class=\"wp-block-list\"><!-- wp:list-item -->\n<li>İç</li>\n<!-- /wp:list-item --></ol>\n<!-- /wp:list --></li>\n<!-- /wp:list-item -->\n\n<!-- wp:list-item -->\n<li>İki devam</li>\n<!-- /wp:list-item --></ul>\n<!-- /wp:list -->",
			self::convert( "- Bir\n  1. İç\n- İki\n  devam" )
		);
	}

	public function test_quote_code_separator(): void {
		$this->assertSame(
			"<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>Söz <em>bu</em>.</p>\n<!-- /wp:paragraph --></blockquote>\n<!-- /wp:quote -->\n\n"
			. "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->\n\n"
			. "<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>a &lt; b\n  **c**</code></pre>\n<!-- /wp:code -->",
			self::convert( "> Söz *bu*.\n\n---\n\n```php\na < b\n  **c**\n```" )
		);
	}

	public function test_images(): void {
		$this->assertSame(
			"<!-- wp:image {\"id\":12,\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"http://site/wp-content/uploads/known.jpg\" alt=\"Tarla &quot;güneşte&quot;\" class=\"wp-image-12\"/><figcaption class=\"wp-element-caption\">Ekim <strong>2026</strong></figcaption></figure>\n<!-- /wp:image -->",
			self::convert( '![Tarla "güneşte"](media/known.jpg "Ekim **2026**")' )
		);
		$this->assertStringStartsWith(
			"<!-- wp:image {\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"https://x.test/a.jpg\" alt=\"\"/>",
			self::convert( '![](https://x.test/a.jpg)' )
		);
	}

	public function test_tables(): void {
		$this->assertSame(
			"<!-- wp:table -->\n<figure class=\"wp-block-table\"><table class=\"has-fixed-layout\"><thead><tr><th>Ürün</th><th>Fiyat</th></tr></thead><tbody><tr><td>Bal</td><td><strong>120</strong> ₺</td></tr><tr><td>a | b</td><td></td></tr></tbody></table></figure>\n<!-- /wp:table -->",
			self::convert( "| Ürün | Fiyat |\n|---|---:|\n| Bal | **120** ₺ |\n| a \\| b |\n" )
		);
	}

	public function test_quotes_and_lists_nest_a_limited_depth(): void {
		$this->assertStringContainsString( '<blockquote class="wp-block-quote"><!-- wp:quote -->', self::convert( '> > a' ) );
		$list = '';
		for ( $level = 0; $level < 20; $level++ ) {
			$list .= str_repeat( '  ', $level ) . "- item\n";
		}
		foreach ( array( str_repeat( '>', 5000 ) . ' a', $list ) as $markdown ) {
			try {
				self::convert( $markdown );
				$this->fail( 'Expected an error.' );
			} catch ( InvalidArgumentException $e ) {
				$this->assertStringContainsString( 'nest at most 16 deep', $e->getMessage() );
			}
		}
	}

	public function test_json_matches_serialize_block_attributes(): void {
		$this->assertSame( '{"a":"\\u003cb\\u003e \\u002d\\u002d \\u0026 \\u0022q\\u0022 ç/"}', Markdown::json( array( 'a' => '<b> -- & "q" ç/' ) ) );
	}
}
