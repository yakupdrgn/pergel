<?php
/**
 * Project package import against a real WordPress.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Package\Exporter;
use Pergel\Package\Importer;
use Pergel\Package\Package;
use Pergel\Seo\Meta;
use Pergel\Settings\Settings;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Imports a copy of tests/fixtures/package under its own name and
 * addresses, so pages on the site are left alone, and puts the site back
 * afterwards: posts, options, settings and the active theme.
 */
final class ImporterTest extends TestCase {

	private const NAME    = 'pergel-test-paket';
	private const OPTIONS = array( 'blogname', 'blogdescription', 'WPLANG', 'show_on_front', 'page_on_front', 'page_for_posts', 'theme_switched', Settings::OPTION );

	private const SITEMAP = <<<'YAML'
	pages:
	  - slug: ""
	    title: Ana sayfa
	    content: content/home.md
	  - slug: pergel-test-hakkimizda
	    title: Hakkımızda
	    content: content/about.md
	    children:
	      - slug: ekip
	        title: Ekibimiz
	        template: page-landing
	  - slug: pergel-test-blog
	    title: Blog
	    type: posts
	menu:
	  - pergel-test-hakkimizda
	  - { page: pergel-test-hakkimizda/ekip, label: Ekip }
	  - { label: Instagram, url: "https://instagram.com/ornek" }
	YAML;

	/**
	 * Package copy.
	 *
	 * @var string
	 */
	private string $dir = '';

	/**
	 * Options as they were before the test.
	 *
	 * @var array<string, mixed>
	 */
	private array $saved = array();

	/**
	 * Active theme before the test.
	 *
	 * @var string
	 */
	private string $theme = '';

	protected function setUp(): void {
		foreach ( self::OPTIONS as $option ) {
			$this->saved[ $option ] = get_option( $option );
		}
		$this->theme = get_stylesheet();
		$this->dir   = sys_get_temp_dir() . '/pergel-import-' . bin2hex( random_bytes( 4 ) );
		self::copy( __DIR__ . '/../fixtures/package', $this->dir );
		$this->write( 'package.yaml', str_replace( 'name: ornek-firma', 'name: ' . self::NAME, (string) file_get_contents( "$this->dir/package.yaml" ) ) );
		$this->write( 'sitemap.yaml', self::SITEMAP );
		unlink( "$this->dir/brand/tokens.json" );
	}

	protected function tearDown(): void {
		$posts = get_posts(
			array(
				'post_type'      => array( 'page', 'attachment', 'wp_navigation' ),
				'post_status'    => array_keys( get_post_stati() ),
				'posts_per_page' => -1,
				'meta_key'       => Importer::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => self::NAME, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		foreach ( $posts as $post ) {
			'attachment' === $post->post_type ? wp_delete_attachment( $post->ID, true ) : wp_delete_post( $post->ID, true );
		}
		if ( get_stylesheet() !== $this->theme ) {
			switch_theme( $this->theme );
		}
		delete_option( 'theme_mods_' . self::NAME );
		// Restored after the switch: switch_theme(), here or in the importer,
		// leaves after_switch_theme to the next request through
		// theme_switched, and WooCommerce resets its catalog settings there.
		// The site was not really switched, so nothing should run.
		foreach ( $this->saved as $option => $value ) {
			false === $value ? delete_option( $option ) : update_option( $option, $value );
		}
		Settings::flush();
		$child = get_theme_root() . '/' . self::NAME;
		if ( is_dir( $child ) ) {
			self::remove( $child );
		}
		self::remove( $this->dir );
	}

	public function test_imports_once_and_keeps_everything_the_second_time(): void {
		$rows = $this->import();
		$made = $this->made();

		$this->assertSame( array( '/', '/pergel-test-blog', '/pergel-test-hakkimizda', '/pergel-test-hakkimizda/ekip', 'media/logo-beyaz.png', 'media/tarla.png', 'menu' ), array_keys( $made ) );
		$this->assertSame( array( 'create' ), array_values( array_unique( array_column( array_filter( $rows, static fn( array $row ): bool => in_array( $row['type'], array( 'page', 'image', 'menu' ), true ) ), 'action' ) ) ) );

		$home = get_post( $made['/'] );
		$this->assertInstanceOf( WP_Post::class, $home );
		$this->assertSame( 'Ana sayfa', $home->post_title );
		$this->assertStringContainsString( 'Topraktan <strong>sofraya</strong>', $home->post_content );
		$this->assertStringContainsString( 'wp-image-' . $made['media/tarla.png'], $home->post_content );
		$this->assertSame( 'Örnek Firma | Organik ürünler', get_post_meta( $home->ID, Meta::TITLE, true ) );

		$team = get_post( $made['/pergel-test-hakkimizda/ekip'] );
		$this->assertInstanceOf( WP_Post::class, $team );
		$this->assertSame( $made['/pergel-test-hakkimizda'], $team->post_parent );
		$this->assertSame( 'page-landing', get_page_template_slug( $team ) );

		$this->assertSame( 'Sabah ışığında buğday tarlası', get_post_meta( $made['media/tarla.png'], '_wp_attachment_image_alt', true ) );
		$this->assertSame( 'Firmaya ait', get_post_meta( $made['media/tarla.png'], '_pergel_media_license', true ) );
		$this->assertSame( $made['media/logo-beyaz.png'], Settings::get( 'brand.logo_inverse' ) );
		$this->assertSame( 'info@ornek.test', Settings::get( 'brand.email' ) );

		$this->assertSame( 'Örnek Firma', get_option( 'blogname' ) );
		$this->assertSame( 'page', get_option( 'show_on_front' ) );
		$this->assertSame( $made['/'], (int) get_option( 'page_on_front' ) );
		$this->assertSame( $made['/pergel-test-blog'], (int) get_option( 'page_for_posts' ) );

		$menu = (string) get_post_field( 'post_content', $made['menu'] );
		$this->assertStringContainsString( '"label":"Hakkımızda","type":"page","id":' . $made['/pergel-test-hakkimizda'], $menu );
		$this->assertStringContainsString( '"label":"Ekip"', $menu );
		$this->assertStringContainsString( '"url":"https://instagram.com/ornek","kind":"custom"', $menu );

		$again = $this->import();
		$this->assertSame( array( 'keep' ), array_values( array_unique( array_column( $again, 'action' ) ) ), (string) wp_json_encode( $again ) );
		$this->assertSame( $made, $this->made() );
	}

	public function test_dry_run_saves_nothing(): void {
		$blogname = get_option( 'blogname' );
		$rows     = $this->import( dry_run: true );

		$this->assertSame( array(), $this->made() );
		$this->assertSame( $blogname, get_option( 'blogname' ) );
		$this->assertContains( 'create', array_column( $rows, 'action' ) );
	}

	public function test_a_page_edited_in_the_panel_is_skipped_unless_forced(): void {
		$this->import();
		$id = $this->made()['/pergel-test-hakkimizda'];
		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => 'Biz kimiz',
			)
		);

		$importer = $this->importer();
		$importer->run();
		$this->assertSame( 'skip', self::row( $importer->rows, '/pergel-test-hakkimizda' )['action'] );
		$this->assertSame( 'Biz kimiz', get_the_title( $id ) );
		$this->assertStringContainsString( 'was edited in the panel', implode( "\n", $importer->notes ) );

		$importer = $this->importer( force: true );
		$importer->run();
		$this->assertSame( 'update', self::row( $importer->rows, '/pergel-test-hakkimizda' )['action'] );
		$this->assertSame( 'Hakkımızda', get_the_title( $id ) );
	}

	public function test_a_changed_package_updates_and_prunes(): void {
		$this->import();
		$team = $this->made()['/pergel-test-hakkimizda/ekip'];
		$this->write( 'sitemap.yaml', str_replace( "    children:\n      - slug: ekip\n        title: Ekibimiz\n        template: page-landing\n", '', self::SITEMAP ) );
		$this->write( 'content/about.md', "## Hikâyemiz\n\nYeni metin.\n" );
		$this->write( 'sitemap.yaml', str_replace( "  - { page: pergel-test-hakkimizda/ekip, label: Ekip }\n", '', (string) file_get_contents( "$this->dir/sitemap.yaml" ) ) );

		$rows = $this->import();
		$this->assertSame( 'update', self::row( $rows, '/pergel-test-hakkimizda' )['action'] );
		$this->assertSame( 'stale', self::row( $rows, '/pergel-test-hakkimizda/ekip' )['action'] );
		$this->assertSame( 'publish', get_post_status( $team ) );

		$rows = $this->import( prune: true );
		$this->assertSame( 'remove', self::row( $rows, '/pergel-test-hakkimizda/ekip' )['action'] );
		$this->assertSame( 'trash', get_post_status( $team ) );
		$this->assertStringContainsString( 'Yeni metin.', (string) get_post_field( 'post_content', $this->made()['/pergel-test-hakkimizda'] ) );
	}

	public function test_a_page_the_package_did_not_make_stops_the_import(): void {
		$id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Elle yapılmış',
				'post_name'   => 'pergel-test-hakkimizda',
			)
		);
		try {
			$problems = $this->importer()->preflight();
		} finally {
			wp_delete_post( (int) $id, true );
		}
		$this->assertSame( array( "sitemap.yaml: pages[2]: /pergel-test-hakkimizda/ is page $id, which this package did not make; move or delete it first" ), $problems );
	}

	public function test_a_language_that_is_not_installed_is_a_note_before_anything_is_saved(): void {
		$yaml = (string) file_get_contents( "$this->dir/package.yaml" );
		if ( in_array( 'zu_ZA', get_available_languages(), true ) ) {
			$this->markTestSkipped( 'zu_ZA is installed on this site.' );
		}
		$this->write( 'package.yaml', str_replace( 'locale: tr_TR', 'locale: zu_ZA', $yaml ) );
		$note = 'package.yaml: locale: zu_ZA is not installed, the site language stays as it is; wp language core install zu_ZA installs it';
		$this->assertSame( array( $note ), $this->importer()->site_notes() );

		$this->write( 'package.yaml', str_replace( 'locale: tr_TR', 'locale: en_US', $yaml ) );
		$this->assertSame( array(), $this->importer()->site_notes() );
	}

	public function test_brand_tokens_make_a_child_theme(): void {
		copy( __DIR__ . '/../fixtures/package/brand/tokens.json', "$this->dir/brand/tokens.json" );
		$rows  = $this->import();
		$child = get_theme_root() . '/' . self::NAME;

		$this->assertSame( self::NAME, get_stylesheet() );
		$this->assertSame( get_template(), wp_get_theme( self::NAME )->get( 'Template' ) );
		$this->assertFileExists( "$child/theme.json" );
		$this->assertStringContainsString( 'Pergel package: ' . self::NAME, (string) file_get_contents( "$child/style.css" ) );
		$this->assertContains( 'create', array_column( array_filter( $rows, static fn( array $row ): bool => 'theme' === $row['type'] ), 'action' ) );

		$again = $this->import();
		$this->assertSame( 'keep', self::row( $again, self::NAME )['action'] );

		// Without tokens the site goes back to the Pergel theme.
		unlink( "$this->dir/brand/tokens.json" );
		$this->import();
		$this->assertSame( get_template(), get_stylesheet() );
	}

	public function test_an_export_imports_back_unchanged(): void {
		$this->import();
		$out = sys_get_temp_dir() . '/pergel-export-' . bin2hex( random_bytes( 4 ) );
		try {
			$exporter = new Exporter( $out, self::NAME );
			$this->assertSame( array(), $exporter->preflight() );
			$exporter->run();
			$this->assertSame( array(), $exporter->notes );
			$this->assertSame( array( 'create' ), array_values( array_unique( array_column( $exporter->rows, 'action' ) ) ) );
			foreach ( array( 'package.yaml', 'sitemap.yaml', 'media.yaml', 'content/home.md', 'content/pergel-test-hakkimizda.md', 'media/tarla.png', 'media/logo-beyaz.png' ) as $file ) {
				$this->assertFileExists( "$out/$file" );
			}
			$this->assertFileEquals( "$this->dir/media/tarla.png", "$out/media/tarla.png" );
			$home = (string) file_get_contents( "$out/content/home.md" );
			$this->assertStringContainsString( "  - pattern: hero-split\n", $home );
			$this->assertStringContainsString( 'heading: Topraktan **sofraya**', $home );
			$this->assertStringContainsString( "## Neden biz?\n\nÜreticiden **doğrudan** gelir.\n", $home );
			$this->assertStringContainsString( '- pergel-test-hakkimizda', (string) file_get_contents( "$out/sitemap.yaml" ) );

			// The exported package makes the same site, so everything is kept.
			$package = Package::read( $out );
			$package->check_theme();
			$this->assertSame( array(), $package->problems );
			$importer = new Importer( $package, false, false, false );
			$this->assertSame( array(), $importer->preflight() );
			$importer->run();
			$rows = array_filter( $importer->rows, static fn( array $row ): bool => in_array( $row['type'], array( 'page', 'image', 'menu', 'setting' ), true ) );
			$this->assertSame( array( 'keep' ), array_values( array_unique( array_column( $rows, 'action' ) ) ), (string) wp_json_encode( $rows ) );

			// Exporting again changes no file.
			$again = new Exporter( $out, self::NAME );
			$again->run();
			$this->assertSame( array( 'keep' ), array_values( array_unique( array_column( $again->rows, 'action' ) ) ) );
		} finally {
			if ( is_dir( $out ) ) {
				self::remove( $out );
			}
		}
	}

	public function test_an_export_into_a_folder_with_other_files_is_refused(): void {
		$this->assertStringContainsString( 'holds no package.yaml', implode( "\n", ( new Exporter( __DIR__, self::NAME ) )->preflight() ) );
		$this->assertStringContainsString( 'holds the package ornek-firma', implode( "\n", ( new Exporter( __DIR__ . '/../fixtures/package', self::NAME ) )->preflight() ) );
	}

	/**
	 * Reads, checks and imports the package copy.
	 *
	 * @param bool $dry_run Dry run.
	 * @param bool $prune   Prune.
	 * @return list<array{action: string, type: string, key: string, id: string, title: string}>
	 */
	private function import( bool $dry_run = false, bool $prune = false ): array {
		$importer = $this->importer( $dry_run, false, $prune );
		$this->assertSame( array(), $importer->preflight() );
		$importer->run();
		return $importer->rows;
	}

	/**
	 * An importer for the package copy, which must have no problems.
	 *
	 * @param bool $dry_run Dry run.
	 * @param bool $force   Force.
	 * @param bool $prune   Prune.
	 */
	private function importer( bool $dry_run = false, bool $force = false, bool $prune = false ): Importer {
		$package = Package::read( $this->dir );
		$package->check_theme();
		$this->assertSame( array(), $package->problems );
		return new Importer( $package, $dry_run, $force, $prune );
	}

	/**
	 * Posts the package made, published or not (not trashed), by key.
	 *
	 * @return array<string, int>
	 */
	private function made(): array {
		$made = array();
		foreach ( get_posts(
			array(
				'post_type'      => array( 'page', 'attachment', 'wp_navigation' ),
				'post_status'    => array( 'publish', 'inherit', 'draft' ),
				'posts_per_page' => -1,
				'meta_key'       => Importer::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => self::NAME, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		) as $post ) {
			$made[ (string) get_post_meta( $post->ID, Importer::KEY, true ) ] = $post->ID;
		}
		ksort( $made );
		return $made;
	}

	/**
	 * The report row of a key.
	 *
	 * @param list<array{action: string, type: string, key: string, id: string, title: string}> $rows Rows.
	 * @param string                                                                            $key  Key.
	 * @return array{action: string, type: string, key: string, id: string, title: string}
	 */
	private static function row( array $rows, string $key ): array {
		foreach ( $rows as $row ) {
			if ( $key === $row['key'] ) {
				return $row;
			}
		}
		self::fail( "No row for $key" );
	}

	/**
	 * Writes a file of the package copy.
	 *
	 * @param string $path Package path.
	 * @param string $text Contents.
	 */
	private function write( string $path, string $text ): void {
		file_put_contents( "$this->dir/$path", $text );
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
}
