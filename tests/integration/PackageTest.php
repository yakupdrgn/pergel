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
				'logo'    => null,
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

	public function test_the_child_theme_patterns_are_sections_and_others_are_not(): void {
		$registry = \WP_Block_Patterns_Registry::get_instance();
		$theme    = get_stylesheet();
		$switched = get_option( 'theme_switched' );
		$child    = get_theme_root() . '/pergel-test-child';
		$markup   = '<!-- wp:group {"tagName":"section","align":"full","layout":{"type":"constrained"}} --><section class="wp-block-group alignfull"><!-- wp:heading --><h2 class="wp-block-heading">Child title</h2><!-- /wp:heading --></section><!-- /wp:group -->';
		mkdir( "$child/patterns", 0777, true );
		file_put_contents( "$child/style.css", "/*\nTheme Name: Pergel test child\nTemplate: " . get_template() . "\n*/\n" );
		file_put_contents( "$child/patterns/band.php", "<?php\n/**\n * Title: Child band\n * Slug: pergel-test-child/band\n */\n?>\n$markup\n" );
		$registry->register(
			'pergel-test-child/band',
			array(
				'title'    => 'Child band',
				'filePath' => "$child/patterns/band.php",
			)
		);
		$registry->register(
			'other-plugin/band',
			array(
				'title'   => 'Plugin band',
				'content' => $markup,
			)
		);
		try {
			// With the parent theme active, the folder is nobody's: not a section.
			$this->assertNull( Package::pattern( 'pergel-test-child/band' ) );

			switch_theme( 'pergel-test-child' );
			$blocks = Package::pattern( 'pergel-test-child/band' );
			$this->assertIsArray( $blocks );
			// The registry has read the file by now and dropped its path; still a section.
			$this->assertSame( $blocks, Package::pattern( 'pergel-test-child/band' ) );
			$this->assertSame( 'pergel-test-child/band', $blocks[0]['attrs']['metadata']['patternName'] );
			$this->assertSame( 'Child title', trim( wp_strip_all_tags( serialize_blocks( $blocks ) ) ) );
			$this->assertArrayHasKey( 'pergel-test-child/band', Package::section_patterns() );
			$this->assertArrayHasKey( 'pergel/hero-split', Package::section_patterns() );

			// A plugin's pattern stays out, child theme or not.
			$this->assertNull( Package::pattern( 'other-plugin/band' ) );
			$this->assertArrayNotHasKey( 'other-plugin/band', Package::section_patterns() );

			// Content files write Pergel's patterns short and the child theme's in full.
			$this->assertSame( 'hero-split', Package::section_name( 'pergel/hero-split' ) );
			$this->assertSame( 'pergel-test-child/band', Package::section_name( 'pergel-test-child/band' ) );
		} finally {
			$registry->unregister( 'pergel-test-child/band' );
			$registry->unregister( 'other-plugin/band' );
			switch_theme( $theme );
			delete_option( 'theme_mods_pergel-test-child' );
			false === $switched ? delete_option( 'theme_switched' ) : update_option( 'theme_switched', $switched );
			self::remove( $child );
		}
	}

	public function test_a_child_theme_picture_left_in_its_pattern_is_not_sample_text(): void {
		$registry = \WP_Block_Patterns_Registry::get_instance();
		$theme    = get_stylesheet();
		$switched = get_option( 'theme_switched' );
		$child    = get_theme_root() . '/pergel-test-child';
		$picture  = static fn( string $src ): string => '<!-- wp:image --><figure class="wp-block-image"><img src="' . $src . '" alt=""/></figure><!-- /wp:image -->';
		mkdir( "$child/patterns", 0777, true );
		file_put_contents( "$child/style.css", "/*\nTheme Name: Pergel test child\nTemplate: " . get_template() . "\n*/\n" );
		file_put_contents(
			"$child/patterns/icons.php",
			"<?php\n/**\n * Title: Child icons\n * Slug: pergel-test-child/icons\n */\n?>\n"
			. '<!-- wp:group {"tagName":"section"} --><section class="wp-block-group"><!-- wp:heading --><h2 class="wp-block-heading">Icons</h2><!-- /wp:heading -->'
			. $picture( '<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/assets/icons/flask.svg' )
			. $picture( '<?php echo esc_url( get_template_directory_uri() ); ?>/assets/images/sample.jpg' )
			. "</section><!-- /wp:group -->\n"
		);
		$registry->register(
			'pergel-test-child/icons',
			array(
				'title'    => 'Child icons',
				'filePath' => "$child/patterns/icons.php",
			)
		);
		try {
			switch_theme( 'pergel-test-child' );
			$package = $this->package(
				array(
					'content/home.md' => "---\nsections:\n  - pattern: pergel-test-child/icons\n    heading: Merhaba\n---\n",
				)
			);
			$this->assertSame( array(), $package->problems );
			// The child theme's icon is as designed; the parent's picture is sample art.
			$this->assertSame(
				array( "content/home.md: sections[1] (pergel-test-child/icons): image_2 keeps the pattern's sample text; give it a value, or null to remove it" ),
				array_values( array_filter( $package->notes, static fn( string $note ): bool => str_contains( $note, 'pergel-test-child/icons' ) ) )
			);
		} finally {
			$registry->unregister( 'pergel-test-child/icons' );
			switch_theme( $theme );
			delete_option( 'theme_mods_pergel-test-child' );
			false === $switched ? delete_option( 'theme_switched' ) : update_option( 'theme_switched', $switched );
			self::remove( $child );
		}
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

		// align: wide replaces the pattern's full width.
		$faq = substr( $html, (int) strpos( $html, '<!-- wp:group {"tagName":"section","className":"pergel-faq"' ) );
		$this->assertSame( 'wide', parse_blocks( $faq )[0]['attrs']['align'] );
		$this->assertStringContainsString( 'class="wp-block-group pergel-faq alignwide', $faq );
		$this->assertStringNotContainsString( 'alignfull', (string) strstr( $faq, '>', true ) );
		$this->assertSame( 'wide', Package::width( parse_blocks( $faq ) ) );

		// none is the content width: no align at all.
		$narrow = Package::widened( (array) Package::pattern( 'pergel/faq-accordion' ), 'none' );
		$this->assertSame( 'none', Package::width( $narrow ) );
		$this->assertStringNotContainsString( 'alignfull', serialize_blocks( $narrow ) );
		$this->assertSame( 'pergel/faq-accordion', $narrow[0]['attrs']['metadata']['patternName'] );

		// Without a markdown section the body comes last.
		$about = $package->page_content( 'content/about.md', Package::pattern( ... ), $media );
		$this->assertStringStartsWith( '<!-- wp:heading -->', $about );
		$this->assertStringContainsString( 'src="http://site.test/media/tarla.png"', $about );
	}

	public function test_names_every_problem_with_its_file_and_place(): void {
		$package = $this->package(
			array(
				'package.yaml'      => "format: 5\nname: Örnek\ntitle: \"\"\nlocale: turkish\ncolor: red\nsettings:\n  brand:\n    email: not-an-email\n    logo: media/logo.png\n  store: {}\n",
				'sitemap.yaml'      => <<<'YAML'
				pages:
				  - slug: ""
				    title: Ana sayfa
				    content: content/home.md
				  - slug: hakkimizda
				    title: Hakkımızda
				    template: wide
				  - slug: hakkimizda
				    title: Yine
				    adopt: "yes"
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
				'content/home.md'   => <<<'MD'
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
				  - pattern: faq-accordion
				    align: center
				---

				# Bir başlık
				MD,
				'media.yaml'        => "media/tarla.png:\n  alt: Tarla\n  credit: x\nhttps://x.test/a.jpg:\n  alt: Uzak\n",
				'brand/tokens.json' => '{ "radius": { "card": { "$value": "{radius.huge}" } } }',
			)
		);

		$expected = array(
			'package.yaml: "color" is not known here; known: format, name, title, tagline, locale, logo, settings',
			'package.yaml: format: this engine reads formats 1 to 4',
			'package.yaml: name: required, in lowercase letters, digits and hyphens (like a-sirketi)',
			'package.yaml: title: required; it is the site title',
			'package.yaml: locale: "turkish" is not a locale like tr_TR or en_US',
			'package.yaml: settings.brand: "logo" is not known here; known: ',
			'package.yaml: settings: "store" is not known here; known: ',
			'package.yaml: settings.brand.email: ',
			'media.yaml: media/tarla.png: "credit" is not known here; known: alt, caption, source, license, author, library, poster',
			'media.yaml: "https://x.test/a.jpg": only images in the package can be used',
			'sitemap.yaml: pages[3]: slug: "hakkimizda" is used twice at this level',
			'sitemap.yaml: pages[3]: adopt: true or false is expected',
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
			'content/home.md: sections[4]: align: wide, full or none',
			'content/home.md: sections[1]: pattern: "hero-splitt" is not a pattern a package can use',
			'content/home.md: sections[2] (hero-split): "subtitle" is not a field here; fields: eyebrow, heading, text, buttons, image',
			'content/home.md: line 15: ',
			'brand/tokens.json: ',
		);
		foreach ( $expected as $start ) {
			$found = array_filter( $package->problems, static fn( string $problem ): bool => str_starts_with( $problem, $start ) );
			$this->assertNotEmpty( $found, "Missing: $start\nProblems:\n" . implode( "\n", $package->problems ) );
		}
		$this->assertCount( count( $expected ), $package->problems, implode( "\n", $package->problems ) );
	}

	public function test_reads_products_by_sku_without_a_sitemap(): void {
		$package = $this->package(
			array(
				'package.yaml'        => str_replace( 'format: 1', 'format: 2', (string) file_get_contents( self::FIXTURE . '/package.yaml' ) ),
				'sitemap.yaml'        => null,
				'products/AB-100.md'  => <<<'MD'
				---
				title: Hassas terazi
				excerpt: Kısa **açıklama**.
				template: single-product-sections
				price: { regular: 4500, sale: null }
				seo:
				  title: Terazi | Örnek
				---

				## Teknik özellikler
				MD,
				'products/AB-200.md'  => "---\nprice: { regular: \"99.90\" }\n---\n",
				'products/bad sku.md' => "---\ntitle: x\n---\n",
				'products/AB-300.md'  => "---\ntitle: \"\"\nexcerpt: [ x ]\ntemplate: wide\nprice: { regular: abc, cost: 3 }\nslug: x\n---\n",
			)
		);

		$expected = array(
			'products/bad sku.md: a product file is named after the product\'s SKU',
			'products/AB-300.md: "slug" is not known here; known: seo, sections, title, excerpt, template, price',
			'products/AB-300.md: title: text is expected',
			'products/AB-300.md: excerpt: text is expected',
			'products/AB-300.md: price: "cost" is not known here',
			'products/AB-300.md: price.regular: a price like 120 or 99.90 is expected',
			'products/AB-300.md: template: "wide" is not a product template; templates: ',
		);
		foreach ( $expected as $start ) {
			$found = array_filter( $package->problems, static fn( string $problem ): bool => str_starts_with( $problem, $start ) );
			$this->assertNotEmpty( $found, "Missing: $start\nProblems:\n" . implode( "\n", $package->problems ) );
		}
		$this->assertCount( count( $expected ), $package->problems, implode( "\n", $package->problems ) );

		$this->assertFalse( $package->sitemap );
		$this->assertSame( array(), $package->pages );
		$this->assertSame( 2, $package->format );
		$this->assertSame(
			array(
				'sku'      => 'AB-100',
				'file'     => 'products/AB-100.md',
				'title'    => 'Hassas terazi',
				'excerpt'  => 'Kısa **açıklama**.',
				'template' => 'single-product-sections',
				'price'    => array(
					'regular' => '4500',
					'sale'    => '',
				),
				'content'  => true,
			),
			$package->products['AB-100']
		);
		$this->assertSame( 'Terazi | Örnek', $package->content['products/AB-100.md']['seo']['title'] );
		// Only what the file writes: no title, no content, the regular price alone.
		$this->assertNull( $package->products['AB-200']['title'] );
		$this->assertFalse( $package->products['AB-200']['content'] );
		$this->assertSame( array( 'regular' => '99.90' ), $package->products['AB-200']['price'] );
	}

	public function test_products_need_format_2_and_pages_need_a_sitemap(): void {
		$package = $this->package( array( 'products/AB-100.md' => "---\ntitle: x\n---\n" ) );
		$this->assertSame( array( 'package.yaml: format: a package with products/ is format 2 or newer' ), $package->problems );
		$this->assertTrue( $package->sitemap );

		$package = $this->package( array( 'sitemap.yaml' => null ) );
		$this->assertStringStartsWith( 'sitemap.yaml: ', implode( "\n", $package->problems ) );
	}

	public function test_media_library_files_need_format_4_and_videos_are_checked(): void {
		$yaml  = (string) file_get_contents( self::FIXTURE . '/package.yaml' );
		$media = (string) file_get_contents( self::FIXTURE . '/media.yaml' );
		$clip  = "media/klip.mp4:\n  alt: Hasat\n  library: true\n  poster: media/tarla.png\n";
		$files = array(
			'media.yaml'     => $media . $clip,
			'media/klip.mp4' => self::mp4(),
		);

		$package = $this->package( $files );
		$this->assertSame( array( 'package.yaml: format: a package whose media.yaml puts files in the media library (library: true) is format 4' ), $package->problems );

		$four = array( 'package.yaml' => str_replace( 'format: 1', 'format: 4', $yaml ) );
		self::remove( $this->copy );
		$package = $this->package( $four + $files );
		$this->assertSame( array(), $package->problems );
		$this->assertSame( array( 'media/klip.mp4' => 'media/tarla.png' ), $package->library );
		$this->assertSame( array( 'alt' => 'Hasat' ), $package->media['media/klip.mp4'] );

		// A webm clip without a poster; an image may be put in the library too.
		self::remove( $this->copy );
		$package = $this->package(
			$four + array(
				'media.yaml'      => str_replace( "  alt: \"\"\n", "  alt: \"\"\n  library: true\n", $media ) . "media/klip.webm:\n  library: true\n",
				'media/klip.webm' => "\x1A\x45\xDF\xA3" . str_repeat( "\0", 4 ) . "\x42\x82\x84webm",
			)
		);
		$this->assertSame( array(), $package->problems );
		$this->assertSame(
			array(
				'media/logo-beyaz.png' => null,
				'media/klip.webm'      => null,
			),
			$package->library
		);

		$broken = array(
			"media/klip.mp4:\n  alt: Hasat\n"                               => 'media.yaml: media/klip.mp4: no field takes a video; write library: true to put it in the media library',
			"media/klip.mp4:\n  library: \"yes\"\n"                         => 'media.yaml: media/klip.mp4: library: true or false is expected',
			"media/klip.mp4:\n  library: true\n  poster: media/yok.png\n"  => 'media.yaml: media/klip.mp4: poster: "media/yok.png": file not found in the package',
			"media/klip.mp4:\n  library: true\n  poster: media/klip.mp4\n" => 'media.yaml: media/klip.mp4: poster: "media/klip.mp4": an image is a jpg, jpeg, png, webp, avif, gif file',
			"media/tarla.png:\n  alt: x\n  poster: media/logo-beyaz.png\n" => 'media.yaml: media/tarla.png: poster: only a video has a poster',
		);
		foreach ( $broken as $entry => $problem ) {
			self::remove( $this->copy );
			$package = $this->package(
				$four + array(
					'media.yaml'     => $entry,
					'media/klip.mp4' => self::mp4(),
				)
			);
			$this->assertSame( array( $problem ), $package->problems, $entry );
		}

		// The content is checked, not only the name; and the size.
		self::remove( $this->copy );
		$package = $this->package( $four + array( 'media/klip.mp4' => 'not a video' ) + $files );
		$this->assertSame( array( 'media.yaml: "media/klip.mp4": the file is not an mp4 video' ), $package->problems );
		self::remove( $this->copy );
		$package = $this->package( $four + array( 'media/klip.mp4' => self::mp4() . str_repeat( "\0", Package::VIDEO_BYTES ) ) + $files );
		$this->assertSame( array( 'media.yaml: "media/klip.mp4": a video may be at most 8 MB; this one is 8.0 MB' ), $package->problems );
	}

	/**
	 * The smallest mp4 file: a file type box and an empty media data box.
	 */
	private static function mp4(): string {
		return pack( 'N', 24 ) . 'ftypisom' . pack( 'N', 512 ) . 'isomiso2' . pack( 'N', 8 ) . 'mdat';
	}

	public function test_a_logo_needs_format_3_and_only_a_logo_may_be_svg(): void {
		$yaml    = (string) file_get_contents( self::FIXTURE . '/package.yaml' );
		$package = $this->package( array( 'package.yaml' => $yaml . "logo: media/tarla.png\n" ) );
		$this->assertSame( array( 'package.yaml: format: a package with a logo is format 3' ), $package->problems );

		$yaml = str_replace( 'format: 1', 'format: 3', $yaml );
		self::remove( $this->copy );
		$package = $this->package( array( 'package.yaml' => $yaml . "logo: media/tarla.png\n" ) );
		$this->assertSame( array(), $package->problems );
		$this->assertSame( 'media/tarla.png', $package->site['logo'] );
		$this->assertSame( array(), $package->svg_images() );

		// An SVG logo needs a site that takes SVG uploads from this user.
		$svg   = array(
			'package.yaml'   => $yaml . "logo: media/logo.svg\n",
			'media/logo.svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>',
		);
		$allow = static fn( array $mimes ): array => $mimes + array( 'svg' => 'image/svg+xml' );
		$user  = get_current_user_id();
		self::remove( $this->copy );
		wp_set_current_user( 0 );
		$package = $this->package( $svg );
		$this->assertSame( array( 'media/logo.svg' ), $package->svg_images() );
		$this->assertSame( array( 'media/logo.svg: WordPress does not take SVG files on this site; activate the Safe SVG plugin, which also cleans them, and run WP-CLI as an administrator (--user=<login>)' ), $package->problems );
		self::remove( $this->copy );
		// Without Safe SVG the filter allows the type; with it, the administrator may upload.
		add_filter( 'upload_mimes', $allow );
		wp_set_current_user(
			(int) get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
					'fields' => 'ID',
				)
			)[0]
		);
		try {
			$this->assertSame( array(), $this->package( $svg )->problems );
		} finally {
			remove_filter( 'upload_mimes', $allow );
			wp_set_current_user( $user );
		}

		// A share image is never SVG.
		self::remove( $this->copy );
		$package = $this->package( array( 'package.yaml' => $yaml . "  seo:\n    og_image: media/logo.svg\n" ) + $svg );
		$this->assertStringContainsString( 'package.yaml: settings.seo.og_image: "media/logo.svg": an image is a jpg', implode( "\n", $package->problems ) );
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

	public function test_links_out_of_the_package_are_not_followed(): void {
		$outside = sys_get_temp_dir() . '/pergel-secret-' . bin2hex( random_bytes( 4 ) );
		file_put_contents( "$outside.md", "# secret\n" );
		copy( self::FIXTURE . '/media/tarla.png', "$outside.png" );
		$this->package( array( 'content/about.md' => null ) );
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Windows may not allow links.
		$linked  = @symlink( "$outside.md", $this->copy . '/content/about.md' ) && @symlink( "$outside.png", $this->copy . '/media/outside.png' );
		$package = Package::read( $this->copy );
		unlink( "$outside.md" );
		unlink( "$outside.png" );
		if ( ! $linked ) {
			$this->markTestSkipped( 'This system does not make symbolic links.' );
		}

		$this->assertStringContainsString( 'content: content/about.md: file not found', implode( "\n", $package->problems ) );
		$this->expectExceptionMessage( 'file not found in the package' );
		$package->image( 'media/outside.png' );
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
