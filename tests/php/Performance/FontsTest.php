<?php
/**
 * Font preload selection tests.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Performance;

use Pergel\Performance\Fonts;
use PHPUnit\Framework\TestCase;

/**
 * Covers slug parsing, unicode-range checks and the choice of files, with
 * families shaped like the theme's style variations.
 */
final class FontsTest extends TestCase {

	private const LATIN     = 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+2000-206F';
	private const LATIN_EXT = 'U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+1E00-1E9F, U+20A0-20AB';

	/**
	 * A family with a latin and a latin-ext face.
	 *
	 * @param string $slug Preset slug.
	 * @param string $name Font name.
	 * @param string $dir  Folder under assets/fonts.
	 * @return array<string, mixed>
	 */
	private static function family( string $slug, string $name, string $dir ): array {
		$face = static fn ( string $subset, string $range ): array => array(
			'fontFamily'   => $name,
			'src'          => array( "file:./assets/fonts/$dir/$dir-$subset-wght-normal.woff2" ),
			'fontWeight'   => '300 900',
			'fontStyle'    => 'normal',
			'unicodeRange' => $range,
		);
		return array(
			'slug'       => $slug,
			'fontFamily' => "\"$name\", system-ui, sans-serif",
			'fontFace'   => array( $face( 'latin-ext', self::LATIN_EXT ), $face( 'latin', self::LATIN ) ),
		);
	}

	/**
	 * Theme styles with body on the root and heading on headings.
	 *
	 * @return array<string, mixed>
	 */
	private static function styles(): array {
		return array(
			'typography' => array( 'fontFamily' => 'var(--wp--preset--font-family--body)' ),
			'elements'   => array(
				'heading' => array( 'typography' => array( 'fontFamily' => 'var(--wp--preset--font-family--heading)' ) ),
				'link'    => array( 'typography' => array( 'fontFamily' => 'var(--wp--preset--font-family--mono)' ) ),
			),
		);
	}

	public function test_slug_reads_both_preset_forms(): void {
		$this->assertSame( 'body', Fonts::slug( 'var(--wp--preset--font-family--body)' ) );
		$this->assertSame( 'heading-2', Fonts::slug( 'var:preset|font-family|heading-2' ) );
		$this->assertSame( '', Fonts::slug( 'Georgia, serif' ) );
	}

	public function test_used_slugs_are_root_and_headings_only(): void {
		$styles                                      = self::styles();
		$styles['elements']['h2']['typography']      = array( 'fontFamily' => 'var:preset|font-family|display' );
		$styles['elements']['h3']['typography']      = array( 'fontFamily' => 'var(--wp--preset--font-family--body)' );
		$styles['blocks']['core/code']['typography'] = array( 'fontFamily' => 'var(--wp--preset--font-family--mono)' );

		$this->assertSame( array( 'body', 'heading', 'display' ), Fonts::used_slugs( $styles ) );
		$this->assertSame( array(), Fonts::used_slugs( array() ) );
	}

	public function test_covers(): void {
		$this->assertTrue( Fonts::covers( self::LATIN, 0x41 ) );
		$this->assertFalse( Fonts::covers( self::LATIN_EXT, 0x41 ) );
		$this->assertTrue( Fonts::covers( self::LATIN_EXT, 0x015F ) );
		$this->assertTrue( Fonts::covers( '', 0x41 ) );
		$this->assertTrue( Fonts::covers( 'U+00??', 0x41 ) );
		$this->assertTrue( Fonts::covers( 'u+41', 0x41 ) );
		$this->assertFalse( Fonts::covers( 'garbage', 0x41 ) );
	}

	public function test_latin_files_of_body_and_heading(): void {
		$families = array(
			'theme' => array(
				self::family( 'body', 'Figtree', 'figtree' ),
				self::family( 'heading', 'Lexend Exa', 'lexend-exa' ),
				array(
					'slug'       => 'mono',
					'fontFamily' => 'ui-monospace, monospace',
				),
			),
		);

		$this->assertSame(
			array(
				'file:./assets/fonts/figtree/figtree-latin-wght-normal.woff2',
				'file:./assets/fonts/lexend-exa/lexend-exa-latin-wght-normal.woff2',
			),
			Fonts::files( $families, Fonts::used_slugs( self::styles() ) )
		);
	}

	public function test_heading_that_reuses_the_body_font_adds_nothing(): void {
		$families = array(
			'theme' => array(
				self::family( 'body', 'Manrope', 'manrope' ),
				array(
					'slug'       => 'heading',
					'fontFamily' => 'Manrope, system-ui, sans-serif',
				),
			),
		);

		$this->assertSame(
			array( 'file:./assets/fonts/manrope/manrope-latin-wght-normal.woff2' ),
			Fonts::files( $families, array( 'body', 'heading' ) )
		);
	}

	public function test_system_fonts_preload_nothing(): void {
		$families = array(
			'theme' => array(
				array(
					'slug'       => 'body',
					'fontFamily' => 'system-ui, -apple-system, "Segoe UI", sans-serif',
				),
			),
		);

		$this->assertSame( array(), Fonts::files( $families, array( 'body', 'heading' ) ) );
	}

	public function test_custom_origin_wins_and_italic_or_non_woff2_faces_are_skipped(): void {
		$custom                       = self::family( 'body', 'Inter', 'inter' );
		$custom['fontFace'][1]['src'] = array( 'https://example.com/wp-content/uploads/fonts/inter.woff2?v=2', 'https://example.com/inter.ttf' );
		$italic                       = $custom['fontFace'][1];
		$italic['fontStyle']          = 'italic';
		$italic['src']                = array( 'https://example.com/inter-italic.woff2' );
		$custom['fontFace'][]         = $italic;
		$ttf                          = self::family( 'body', 'Figtree', 'figtree' );
		$ttf['fontFace'][1]['src']    = array( 'file:./assets/fonts/figtree/figtree.ttf' );
		$families                     = array(
			'theme'  => array( $ttf ),
			'custom' => array( $custom ),
		);

		$this->assertSame(
			array( 'https://example.com/wp-content/uploads/fonts/inter.woff2?v=2' ),
			Fonts::files( $families, array( 'body' ) )
		);
		$this->assertSame( array(), Fonts::files( array( 'theme' => array( $ttf ) ), array( 'body' ) ) );
	}

	public function test_bad_data_is_ignored(): void {
		$this->assertSame( array(), Fonts::files( array( 'theme' => 'nope' ), array( 'body' ) ) );
		$this->assertSame(
			array(),
			Fonts::files(
				array(
					'theme' => array(
						'x',
						array(
							'slug'       => 'body',
							'fontFamily' => 'A',
							'fontFace'   => array( 'y', array( 'fontFamily' => 'A' ) ),
						),
					),
				),
				array( 'body' )
			)
		);
		$this->assertSame( array(), Fonts::used_slugs( array( 'elements' => 'nope' ) ) );
	}
}
