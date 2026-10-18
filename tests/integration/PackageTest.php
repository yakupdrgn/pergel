<?php
/**
 * Project package reader and checks.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Package\Package;
use PHPUnit\Framework\TestCase;

/**
 * tests/fixtures/package is a package without problems; the tests break
 * copies of it and expect every problem to be named with its file and place.
 */
final class PackageTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/package';

	/**
	 * Copy of the fixture, removed after each test.
	 *
	 * @var string
	 */
	private string $copy = '';

	protected function tearDown(): void {
		if ( '' !== $this->copy ) {
			self::remove( $this->copy );
		}
	}

	/**
	 * Copies the fixture and changes files in the copy.
	 *
	 * @param array<string, string|null> $files Package path => new contents; null deletes.
	 */
	private function package( array $files ): Package {
		$this->copy = sys_get_temp_dir() . '/pergel-package-' . bin2hex( random_bytes( 4 ) );
		self::copy( self::FIXTURE, $this->copy );
		foreach ( $files as $path => $text ) {
			if ( null === $text ) {
				unlink( "$this->copy/$path" );
				continue;
			}
			if ( ! is_dir( dirname( "$this->copy/$path" ) ) ) {
				mkdir( dirname( "$this->copy/$path" ), 0777, true );
			}
			file_put_contents( "$this->copy/$path", $text );
		}
		$package = Package::read( $this->copy );
		$package->check_theme();
		return $package;
	}

	/**
	 * Copies a directory.
	 *
	 * @param string $from Source.
	 * @param string $to   Target.
	 */
	private static function copy( string $from, string $to ): void {
		mkdir( $to, 0777, true );
		foreach ( scandir( $from ) as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			is_dir( "$from/$name" ) ? self::copy( "$from/$name", "$to/$name" ) : copy( "$from/$name", "$to/$name" );
		}
	}

	/**
	 * Removes a directory.
	 *
	 * @param string $dir Directory.
	 */
	private static function remove( string $dir ): void {
		foreach ( scandir( $dir ) as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			is_dir( "$dir/$name" ) ? self::remove( "$dir/$name" ) : unlink( "$dir/$name" );
		}
		rmdir( $dir );
	}

	public function test_reads_a_package_without_problems(): void {
		$package = Package::read( self::FIXTURE );
		$package->check_theme();

		$this->assertSame( array(), $package->problems );
		$this->assertSame( array(), $package->notes );
		$this->assertSame(
			array(
				'name'    => 'ornek-firma',
				'title'   => 'Örnek Firma',
				'tagline' => 'Topraktan sofraya',
				'locale'  => 'tr_TR',
			),
			$package->site
		);
		$this->assertSame( array( '', 'hakkimizda', 'hakkimizda/ekip', 'blog', 'sepet' ), array_column( $package->pages, 'path' ) );
		$this->assertSame( array( 'page', 'page', 'page', 'posts', 'cart' ), array_column( $package->pages, 'type' ) );
		$this->assertSame( 'page-landing', $package->pages[2]['template'] );
		$this->assertSame( 'hakkimizda', $package->pages[2]['parent'] );

		// Without a menu in the sitemap: the page tree without the front page and the cart.
		$this->assertSame(
			array(
				array(
					'label'    => 'Hakkımızda',
					'page'     => 'hakkimizda',
					'url'      => null,
					'children' => array(
						array(
							'label'    => 'Ekibimiz',
							'page'     => 'hakkimizda/ekip',
							'url'      => null,
							'children' => array(),
						),
					),
				),
				array(
					'label'    => 'Blog',
					'page'     => 'blog',
					'url'      => null,
					'children' => array(),
				),
			),
			$package->menu
		);

		$this->assertSame( 'Örnek Firma | Organik ürünler', $package->content['content/home.md']['seo']['title'] );
		$this->assertSame( 'Sabah ışığında buğday tarlası', $package->media['media/tarla.png']['alt'] );
		$this->assertNotNull( $package->tokens );

		$settings = $package->settings(
			static fn( string $path ): array => array(
				'id'  => 'media/logo-beyaz.png' === $path ? 42 : 0,
				'url' => $path,
			)
		);
		$this->assertSame( array(), $settings['problems'] );
		$this->assertSame( 42, $settings['changes']['brand']['logo_inverse'] );
		$this->assertSame( 'info@ornek.test', $settings['changes']['brand']['email'] );
	}

	public function test_page_content_puts_the_body_where_the_markdown_section_is(): void {
		$package = Package::read( self::FIXTURE );
		$media   = static fn( string $path ): array => array(
			'id'  => 9,
			'url' => "http://site.test/$path",
		);
		$html    = $package->page_content( 'content/home.md', Package::pattern( ... ), $media );

		$this->assertSame( array(), $package->problems );
		$hero = strpos( $html, '<h1 class="wp-block-heading">Topraktan <strong>sofraya</strong></h1>' );
		$body = strpos( $html, '<h2 class="wp-block-heading">Neden biz?</h2>' );
		$faq  = strpos( $html, '<summary>Kargo ne kadar sürer?</summary>' );
		$this->assertIsInt( $hero );
		$this->assertIsInt( $body );
		$this->assertIsInt( $faq );
		$this->assertTrue( $hero < $body && $body < $faq );
		$this->assertSame( 2, substr_count( $html, '<!-- wp:details' ) );
		$this->assertStringContainsString( 'alt="Sabah ışığında buğday tarlası"', $html );
		$this->assertStringContainsString( 'class="wp-image-9"', $html );
		$this->assertSame( serialize_blocks( parse_blocks( $html ) ), $html );

		// Without a markdown section the body comes last.
		$about = $package->page_content( 'content/about.md', Package::pattern( ... ), $media );
		$this->assertStringStartsWith( '<!-- wp:heading -->', $about );
		$this->assertStringContainsString( 'src="http://site.test/media/tarla.png"', $about );
	}

	public function test_names_every_problem_with_its_file_and_place(): void {
		$package = $this->package(
			array(
				'package.yaml'     => "format: 2\nname: Örnek\ntitle: \"\"\nlocale: turkish\ncolor: red\nsettings:\n  brand:\n    email: not-an-email\n    logo: media/logo.png\n  shop: {}\n",
				'sitemap.yaml'     => <<<'YAML'
				pages:
				  - slug: ""
				    title: Ana sayfa
				    content: content/home.md
				  - slug: hakkimizda
				    title: Hakkımızda
				    template: wide
				  - slug: hakkimizda
				    title: Yine
				  - slug: Ürünler
				    title: Ürünler
				    type: store
				  - slug: sepet
				    title: Sepet
				    type: cart
				  - slug: kasa
				    type: cart
				    content: content/yok.md
				menu:
				  - hakkimizda
				  - iletisim
				  - { label: Instagram }
				  - { label: Kötü, url: "javascript:alert(1)" }
				YAML,
				'content/home.md'  => <<<'MD'
				---
				title: Ana sayfa
				seo:
				  noindex: "yes"
				sections:
				  - pattern: hero-splitt
				  - pattern: hero-split
				    subtitle: x
				  - markdown: true
				    heading: x
				---

				# Bir başlık
				MD,
				'media.yaml'       => "media/tarla.png:\n  alt: Tarla\n  credit: x\nhttps://x.test/a.jpg:\n  alt: Uzak\n",
				'brand/tokens.json' => '{ "radius": { "card": { "$value": "{radius.huge}" } } }',
			)
		);

		$expected = array(
			'package.yaml: "color" is not known here; known: format, name, title, tagline, locale, settings',
			'package.yaml: format: this engine reads format 1',
			'package.yaml: name: required, in lowercase letters, digits and hyphens (like a-sirketi)',
			'package.yaml: title: required; it is the site title',
			'package.yaml: locale: "turkish" is not a locale like tr_TR or en_US',
			'package.yaml: settings.brand: "logo" is not known here; known: ',
			'package.yaml: settings: "shop" is not known here; known: ',
			'package.yaml: settings.brand.email: ',
			'media.yaml: media/tarla.png: "credit" is not known here; known: alt, caption, source, license, author',
			'media.yaml: "https://x.test/a.jpg": only images in the package can be used',
			'sitemap.yaml: pages[3]: slug: "hakkimizda" is used twice at this level',
			'sitemap.yaml: pages[4]: slug: "Ürünler" is not a slug; use lowercase letters, digits and hyphens',
			'sitemap.yaml: pages[4]: type: one of page, posts, shop, cart, checkout, account is expected',
			'sitemap.yaml: pages[6]: title: required',
			'sitemap.yaml: pages[6]: content: content/yok.md: file not found',
			'sitemap.yaml: pages[6]: type: only one page can be the cart page; pages[5] is',
			'sitemap.yaml: pages[2]: template: "wide" is not a page template; templates: page-landing',
			'sitemap.yaml: menu[2]: page: "iletisim" is not a page path in pages',
			'sitemap.yaml: menu[3]: either page or url is expected',
			'sitemap.yaml: menu[4]: url: the link "javascript:alert(1)" uses javascript:, which is not allowed',
			'content/home.md: "title" is set in sitemap.yaml, not in the content file',
			'content/home.md: seo.noindex: true or false is expected',
			'content/home.md: sections[3]: a { markdown: true } section, which places the Markdown body, has nothing else',
			'content/home.md: sections[1]: pattern: "hero-splitt" is not a Pergel pattern',
			'content/home.md: sections[2] (hero-split): "subtitle" is not a field here; fields: eyebrow, heading, text, buttons, image',
			'content/home.md: line 13: ',
			'brand/tokens.json: ',
		);
		foreach ( $expected as $start ) {
			$found = array_filter( $package->problems, static fn( string $problem ): bool => str_starts_with( $problem, $start ) );
			$this->assertNotEmpty( $found, "Missing: $start\nProblems:\n" . implode( "\n", $package->problems ) );
		}
		$this->assertCount( count( $expected ), $package->problems, implode( "\n", $package->problems ) );
	}

	public function test_notes_do_not_stop_a_package(): void {
		$package = $this->package(
			array(
				'content/home.md'   => "---\nsections:\n  - pattern: hero-split\n    heading: Merhaba\n    image: media/yeni.png\n---\n",
				'media/yeni.png'    => (string) file_get_contents( self::FIXTURE . '/media/tarla.png' ),
				'content/eski.md'   => "Eski metin.\n",
				'content/about.md'  => "## Biz\n\n![](media/yok.png)\n",
				'brand/tokens.json' => null,
			)
		);

		$this->assertSame(
			array(
				'content/about.md: line 3: "media/yok.png": file not found in the package',
			),
			$package->problems
		);
		$this->assertSame(
			array(
				'content/eski.md: no page in sitemap.yaml uses this file',
				'content/home.md: sections[1]: media/yeni.png has no alt text; add it in media.yaml (alt: "" marks a decorative image)',
				"content/home.md: sections[1] (hero-split): eyebrow keeps the pattern's sample text; give it a value, or null to remove it",
				"content/home.md: sections[1] (hero-split): text keeps the pattern's sample text; give it a value, or null to remove it",
				"content/home.md: sections[1] (hero-split): buttons keeps the pattern's sample text; give it a value, or null to remove it",
			),
			$package->notes
		);
		$this->assertNull( $package->tokens );
	}

	public function test_brand_fonts_come_from_the_parent_theme(): void {
		$font = static fn( string $file ): string => (string) wp_json_encode(
			array(
				'font-family' => array(
					'heading' => array(
						'$value'      => array( 'Lexend Exa', 'sans-serif' ),
						'$extensions' => array(
							'com.ariumlab.pergel' => array(
								'fontFace' => array(
									array(
										'src'        => "file:./assets/fonts/lexend-exa/$file",
										'fontWeight' => '300 900',
									),
								),
							),
						),
					),
				),
			)
		);

		$package = $this->package( array( 'brand/tokens.json' => $font( 'lexend-exa-latin-wght-normal.woff2' ) ) );
		$this->assertSame( array(), $package->problems );

		self::remove( $this->copy );
		$package = $this->package( array( 'brand/tokens.json' => $font( 'yok.woff2' ) ) );
		$this->assertSame(
			array( 'brand/tokens.json: font-family.heading: file:./assets/fonts/lexend-exa/yok.woff2 does not exist in the theme' ),
			$package->problems
		);
	}

	public function test_a_missing_package(): void {
		$package = Package::read( self::FIXTURE . '/yok' );
		$this->assertCount( 1, $package->problems );
		$this->assertStringEndsWith( 'yok: not a directory', $package->problems[0] );

		$package = $this->package(
			array(
				'package.yaml' => null,
				'sitemap.yaml' => "pages: [\n",
			)
		);
		$this->assertSame(
			array(
				'package.yaml: file not found',
				'sitemap.yaml: line 1: a { } or [ ] list is not closed',
			),
			array_slice( $package->problems, 0, 2 )
		);
	}
}
