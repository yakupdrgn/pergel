<?php
/**
 * YAML subset reader tests.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Package;

use InvalidArgumentException;
use Pergel\Package\Yaml;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What package files use, and what the reader refuses.
 */
final class YamlTest extends TestCase {

	public function test_reads_a_sitemap(): void {
		$yaml = <<<'YAML'
		# Site pages
		pages:
		  - slug: ""              # front page
		    title: Ana sayfa
		    template: front-page
		    content: content/home.md
		  - slug: hakkimizda
		    title: "Hakkımızda"
		    children:
		      - slug: ekip
		        title: 'Ekibimiz'
		  - slug: urunler
		    type: shop
		menu: [hakkimizda, urunler]
		YAML;

		$this->assertSame(
			array(
				'pages' => array(
					array(
						'slug'     => '',
						'title'    => 'Ana sayfa',
						'template' => 'front-page',
						'content'  => 'content/home.md',
					),
					array(
						'slug'     => 'hakkimizda',
						'title'    => 'Hakkımızda',
						'children' => array(
							array(
								'slug'  => 'ekip',
								'title' => 'Ekibimiz',
							),
						),
					),
					array(
						'slug' => 'urunler',
						'type' => 'shop',
					),
				),
				'menu'  => array( 'hakkimizda', 'urunler' ),
			),
			Yaml::parse( $yaml )
		);
	}

	public function test_reads_sections_with_flow_maps(): void {
		$yaml = <<<'YAML'
		sections:
		- pattern: pergel/hero-split
		  heading: Topraktan sofraya
		  cta: { label: Ürünleri gör, url: /urunler/ }
		  buttons:
		    - { label: "Bize yazın", url: "mailto:a@b.c" }
		- pattern: pergel/features-3
		  items: [
		    { title: Sertifikalı, text: "Her parti, test edilir." },
		    { title: Yerel, text: 'O''nun bahçesi' },
		  ]
		YAML;

		$this->assertSame(
			array(
				'sections' => array(
					array(
						'pattern' => 'pergel/hero-split',
						'heading' => 'Topraktan sofraya',
						'cta'     => array(
							'label' => 'Ürünleri gör',
							'url'   => '/urunler/',
						),
						'buttons' => array(
							array(
								'label' => 'Bize yazın',
								'url'   => 'mailto:a@b.c',
							),
						),
					),
					array(
						'pattern' => 'pergel/features-3',
						'items'   => array(
							array(
								'title' => 'Sertifikalı',
								'text'  => 'Her parti, test edilir.',
							),
							array(
								'title' => 'Yerel',
								'text'  => "O'nun bahçesi",
							),
						),
					),
				),
			),
			Yaml::parse( $yaml )
		);
	}

	public function test_types_plain_values_like_yaml_1_2(): void {
		$yaml = <<<'YAML'
		a: null
		b: ~
		c:
		d: true
		e: False
		f: 42
		g: -3.5
		h: yes
		i: off
		j: "true"
		k: 0123
		l: 1e3
		m: http://example.com/a#b
		n: a # comment
		o: 7.1
		YAML;

		$this->assertSame(
			array(
				'a' => null,
				'b' => null,
				'c' => null,
				'd' => true,
				'e' => false,
				'f' => 42,
				'g' => -3.5,
				'h' => 'yes',
				'i' => 'off',
				'j' => 'true',
				'k' => 123,
				'l' => 1000.0,
				'm' => 'http://example.com/a#b',
				'n' => 'a',
				'o' => 7.1,
			),
			Yaml::parse( $yaml )
		);
	}

	public function test_reads_block_scalars(): void {
		$yaml = "literal: |\n  one\n    two\n\n  three\nfolded: >\n  one\n  two\n\n  three\nstrip: |-\n  x\nkeep: |+\n  y\n\nafter: z\n";

		$this->assertSame(
			array(
				'literal' => "one\n  two\n\nthree\n",
				'folded'  => "one two\nthree\n",
				'strip'   => 'x',
				'keep'    => "y\n\n",
				'after'   => 'z',
			),
			Yaml::parse( $yaml )
		);
	}

	public function test_reads_escapes_and_nested_lists(): void {
		$yaml = "a: \"tab\\there \\u00e7 \\\"q\\\"\"\nb:\n  - - 1\n    - 2\n  - []\n  - {}\n'quoted key': 1\n";

		$this->assertSame(
			array(
				'a'          => "tab\there ç \"q\"",
				'b'          => array( array( 1, 2 ), array(), array() ),
				'quoted key' => 1,
			),
			Yaml::parse( $yaml )
		);
	}

	public function test_empty_and_document_marker(): void {
		$this->assertNull( Yaml::parse( '' ) );
		$this->assertNull( Yaml::parse( "# only a comment\n" ) );
		$this->assertSame( array( 'a' => 1 ), Yaml::parse( "\u{FEFF}---\na: 1\r\n" ) );
		$this->assertSame( 'text', Yaml::parse( 'text' ) );
		$this->assertSame( array( 1, 2 ), Yaml::parse( "- 1\n- 2" ) );
	}

	/**
	 * Refused documents and the line the message names.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function refused(): array {
		return array(
			'tab indent'          => array( "a:\n\tb: 1", 'line 2: tabs' ),
			'anchor'              => array( "a: &x 1\nb: *x", 'line 1: "&x 1"' ),
			'tag'                 => array( 'a: !!str 1', 'line 1: "!!str 1"' ),
			'two documents'       => array( "a: 1\n---\nb: 2", 'line 2: only one document' ),
			'repeated key'        => array( "a: 1\na: 2", 'line 2: the key "a" is repeated' ),
			'continued value'     => array( "a: one\n  two", 'line 2: a value cannot go on' ),
			'bad indent'          => array( "a:\n    b: 1\n  c: 2", 'line 3: unexpected indentation' ),
			'unclosed quote'      => array( 'a: "x', 'line 1: a quoted value is not closed' ),
			'unclosed flow'       => array( 'a: [1, 2', 'line 1: a { } or [ ] list is not closed' ),
			'text after quote'    => array( 'a: "x" y', 'line 1: unexpected "y"' ),
			'item in mapping'     => array( "a: 1\n- b", 'line 2: a list item where a key' ),
			'bad escape'          => array( 'a: "\\q"', 'line 1: unknown escape' ),
			'flow map without ":"' => array( 'a: { b }', 'line 1: "b" in { } needs' ),
		);
	}

	#[DataProvider( 'refused' )]
	public function test_refuses_what_it_does_not_read( string $yaml, string $message ): void {
		try {
			Yaml::parse( $yaml );
			$this->fail( 'Expected an error.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertStringStartsWith( $message, $e->getMessage() );
		}
	}

	/**
	 * Values the writer must give back unchanged.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public static function dumped(): array {
		return array(
			'scalars'         => array( array( null, true, false, 0, -7, 1.5, 2.0, 1.0e25, '' ) ),
			'look like other' => array( array( 'null', 'true', '12', '1.0', '~', 'yes', '0x1F', '-', '- a', '? x', 'a: b', 'a #b', 'a:', ' x', 'x ' ) ),
			'yaml syntax'     => array( array( '[x]', '{x}', '"q"', "'s'", '#c', '&a', '*b', '!t', '|', '>', '%', '@', '`', ',' ) ),
			'plain text'      => array( array( 'Topraktan sofraya', 'media/tarla.jpg', '/urunler/?a=1&b=2', 'http://a.test/x', 'a:b', 'Ç ş ğ ü ö ı İ', 'a - b' ) ),
			'escapes'         => array( array( "tab\there", "a\x08b\fc", "nul\0", "cr\r\nlf", "\u{1F600}", 'back\\slash' ) ),
			'blocks'          => array( array( "one\ntwo", "one\ntwo\n", "a\n\n  b\n#c", "x\n\n", "\nx", " x\ny", "x\n  \ny", "x\ny " ) ),
			'empty'           => array( array( 'a' => array(), 'b' => array( array() ) ) ),
			'nested'          => array(
				array(
					'format'   => 1,
					'settings' => array(
						'brand' => array( 'name' => 'Pergel' ),
						'list'  => array( 'a', array( 'b', 'c' ), array( 'd' => 1 ) ),
					),
					'sections' => array(
						array(
							'pattern' => 'hero-split',
							'buttons' => array(
								array(
									'label' => 'Ürünler',
									'url'   => '/urunler/',
								),
							),
							'text'    => "Satır bir\nsatır iki\n",
						),
						array( array( array( 1 ) ) ),
					),
					'7'        => 'int key',
					'a: b'     => 'quoted key',
				),
			),
		);
	}

	/**
	 * Dump then parse gives back the value.
	 *
	 * @param mixed $value Value.
	 */
	#[DataProvider( 'dumped' )]
	public function test_dump_reads_back( mixed $value ): void {
		$this->assertSame( $value, Yaml::parse( Yaml::dump( $value ) ) );
	}

	public function test_dump_is_block_style(): void {
		$yaml = Yaml::dump(
			array(
				'pattern' => 'hero-split',
				'heading' => 'Başlık: alt',
				'buttons' => array(
					array(
						'label' => 'Bak',
						'url'   => '#',
					),
				),
				'list'    => array( 'Bir', 'İki' ),
				'text'    => "a\nb",
				'image'   => null,
			)
		);
		$this->assertSame(
			"pattern: hero-split\nheading: \"Başlık: alt\"\nbuttons:\n  - label: Bak\n    url: \"#\"\nlist:\n  - Bir\n  - İki\ntext: |-\n  a\n  b\nimage: null\n",
			$yaml
		);
	}

	public function test_dump_refuses_objects_and_infinity(): void {
		foreach ( array( new \stdClass(), INF, NAN, "\xff", "a\n\xff" ) as $value ) {
			try {
				Yaml::dump( array( 'a' => $value ) );
				$this->fail( 'Expected an error.' );
			} catch ( InvalidArgumentException $e ) {
				$this->assertStringContainsString( 'cannot be written as YAML', $e->getMessage() );
			}
		}
	}
}
