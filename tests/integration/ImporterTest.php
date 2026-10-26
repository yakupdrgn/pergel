<?php
/**
 * Project package import against a real WordPress.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Closure;
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
	private const OPTIONS = array( 'blogname', 'blogdescription', 'WPLANG', 'show_on_front', 'page_on_front', 'page_for_posts', 'theme_switched', 'site_logo', Settings::OPTION );

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
	  - { label: Ürünler, url: "/urunler/#liste" }
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
	 * Products made for a test, deleted after it.
	 *
	 * @var list<int>
	 */
	private array $products = array();

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
		foreach ( $this->products as $id ) {
			wc_get_product( $id )?->delete( true );
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

	public function test_writes_the_seo_plugin_fields_and_keeps_an_edit_made_there(): void {
		$plugin = static fn(): string => 'rank-math';
		add_filter( 'pergel/seo/plugin', $plugin );
		try {
			$this->import();
			$made = $this->made();
			$home = $made['/'];
			$this->assertSame( 'Örnek Firma | Organik ürünler', get_post_meta( $home, 'rank_math_title', true ) );
			$this->assertSame( 'Sertifikalı organik ürünler, üreticiden kapınıza.', get_post_meta( $home, 'rank_math_description', true ) );
			// Pergel's own fields are written as before.
			$this->assertSame( 'Sertifikalı organik ürünler, üreticiden kapınıza.', get_post_meta( $home, Meta::DESCRIPTION, true ) );

			// A page without SEO fields leaves the plugin's own alone.
			$about = $made['/pergel-test-hakkimizda'];
			update_post_meta( $about, 'rank_math_description', 'The plugin has this one' );

			// Importing again changes nothing.
			$rows = $this->import();
			$this->assertSame( array( 'keep' ), array_values( array_unique( array_column( array_filter( $rows, static fn( array $row ): bool => 'page' === $row['type'] && '/' === $row['key'] ), 'action' ) ) ) );
			$this->assertSame( 'The plugin has this one', get_post_meta( $about, 'rank_math_description', true ) );

			// An edit in the plugin's field is an edit in the panel: kept.
			update_post_meta( $home, 'rank_math_description', 'Written in Rank Math' );
			$this->write( 'content/home.md', str_replace( 'üreticiden kapınıza.', 'doğrudan kapınıza.', (string) file_get_contents( "$this->dir/content/home.md" ) ) );
			$rows = $this->import();
			$this->assertSame( array( 'skip' ), array_values( array_unique( array_column( array_filter( $rows, static fn( array $row ): bool => 'page' === $row['type'] && '/' === $row['key'] ), 'action' ) ) ) );
			$this->assertSame( 'Written in Rank Math', get_post_meta( $home, 'rank_math_description', true ) );
		} finally {
			remove_filter( 'pergel/seo/plugin', $plugin );
		}
	}

	public function test_writes_all_in_one_seo_fields_through_its_model(): void {
		$model  = new class() {
			/**
			 * Title column.
			 *
			 * @var string
			 */
			public $title = '';
			/**
			 * Description column.
			 *
			 * @var string
			 */
			public $description = '';
			/**
			 * Times saved.
			 *
			 * @var int
			 */
			public $saves = 0;

			public function save(): void {
				++$this->saves;
			}
		};
		$plugin = static fn(): string => 'aioseo';
		$loader = static fn(): Closure => static fn(): object => $model;
		add_filter( 'pergel/seo/plugin', $plugin );
		add_filter( 'pergel/seo/aioseo_post', $loader );
		try {
			$this->import();
			$this->assertSame( 'Örnek Firma | Organik ürünler', $model->title );
			$this->assertSame( 'Sertifikalı organik ürünler, üreticiden kapınıza.', $model->description );
			$this->assertGreaterThan( 0, $model->saves );
			// No post meta of another plugin.
			$this->assertSame( '', get_post_meta( $this->made()['/'], 'rank_math_title', true ) );
		} finally {
			remove_filter( 'pergel/seo/plugin', $plugin );
			remove_filter( 'pergel/seo/aioseo_post', $loader );
		}
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
		$this->assertStringContainsString( '"url":"' . home_url( '/urunler/#liste' ) . '","kind":"custom"', $menu );

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
		$this->assertSame( array( "sitemap.yaml: pages[2]: /pergel-test-hakkimizda/ is page $id, which this package did not make; move or delete it first, or set adopt: true to take it over" ), $problems );
	}

	public function test_adopt_takes_over_the_page_at_the_address_and_prune_only_releases_it(): void {
		$about = $this->hand_made( 'pergel-test-hakkimizda', 'Elle yazılmış metin' );
		$refs  = $this->hand_made( 'pergel-test-referanslar', 'Eski referans metni' );
		update_post_meta( $refs, Meta::TITLE, 'Eski SEO başlığı' );
		$about_page = "  - slug: pergel-test-hakkimizda\n    title: Hakkımızda\n    content: content/about.md\n";
		$refs_page  = "  - slug: pergel-test-referanslar\n    title: Referanslar\n    adopt: true\n";
		$sitemap    = str_replace( $about_page, $about_page . "    adopt: true\n", self::SITEMAP );
		$this->write( 'sitemap.yaml', str_replace( "  - slug: pergel-test-blog\n", $refs_page . "  - slug: pergel-test-blog\n", $sitemap ) );

		try {
			$rows = $this->import();
			$made = $this->made();
			$this->assertSame( 'adopt', self::row( $rows, '/pergel-test-hakkimizda' )['action'] );
			$this->assertSame( 'adopt', self::row( $rows, '/pergel-test-referanslar' )['action'] );
			$this->assertSame( $about, $made['/pergel-test-hakkimizda'] );
			$this->assertSame( $refs, $made['/pergel-test-referanslar'] );
			$this->assertSame( $about, get_post( $made['/pergel-test-hakkimizda/ekip'] )?->post_parent );

			// With a content file the package's content replaces the page's.
			$this->assertStringNotContainsString( 'Elle yazılmış metin', (string) get_post_field( 'post_content', $about ) );
			// Without one the page keeps its content and SEO fields.
			$this->assertSame( 'Eski referans metni', get_post_field( 'post_content', $refs ) );
			$this->assertSame( 'Referanslar', get_the_title( $refs ) );
			$this->assertSame( 'Eski SEO başlığı', get_post_meta( $refs, Meta::TITLE, true ) );

			$again = $this->import();
			$this->assertSame( array( 'keep' ), array_values( array_unique( array_column( $again, 'action' ) ) ), (string) wp_json_encode( $again ) );

			$this->write( 'sitemap.yaml', $sitemap );
			$rows = $this->import( prune: true );
			$this->assertSame( 'release', self::row( $rows, '/pergel-test-referanslar' )['action'] );
			$this->assertSame( 'publish', get_post_status( $refs ) );
			$this->assertSame( '', get_post_meta( $refs, Importer::META, true ) );
			$this->assertSame( '', get_post_meta( $refs, Importer::ADOPTED, true ) );
		} finally {
			wp_delete_post( $refs, true );
		}
	}

	public function test_a_page_another_package_owns_is_not_adopted(): void {
		$id = $this->hand_made( 'pergel-test-hakkimizda', 'Başka paketin metni' );
		update_post_meta( $id, Importer::META, 'baska-paket' );
		$about_page = "  - slug: pergel-test-hakkimizda\n    title: Hakkımızda\n    content: content/about.md\n";
		$this->write( 'sitemap.yaml', str_replace( $about_page, $about_page . "    adopt: true\n", self::SITEMAP ) );
		try {
			$problems = $this->importer()->preflight();
		} finally {
			wp_delete_post( $id, true );
		}
		$this->assertSame( array( "sitemap.yaml: pages[2]: adopt: page $id belongs to the package baska-paket" ), $problems );
	}

	public function test_an_attachment_at_the_address_of_a_page_stops_the_import(): void {
		$id         = wp_insert_attachment(
			array(
				'post_title'     => 'Ek',
				'post_name'      => 'pergel-test-hakkimizda',
				'post_mime_type' => 'image/png',
			)
		);
		$about_page = '  - slug: pergel-test-hakkimizda
    title: Hakkımızda
    content: content/about.md
';
		$this->write(
			'sitemap.yaml',
			str_replace(
				$about_page,
				$about_page . '    adopt: true
',
				self::SITEMAP
			)
		);
		try {
			$problems = $this->importer()->preflight();
		} finally {
			wp_delete_attachment( $id, true );
		}
		$this->assertSame( array( "sitemap.yaml: pages[2]: /pergel-test-hakkimizda/ is the address of the attachment $id; give the attachment another slug first" ), $problems );
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

	public function test_brand_style_rules_reach_the_child_theme_and_leave_with_it(): void {
		$this->write( 'brand/tokens.json', '{"$extensions":{"com.ariumlab.pergel":{"styles":{"elements":{"button":{"typography":{"textTransform":"uppercase"}}}}}}}' );
		$this->import();
		$theme = get_theme_root() . '/' . self::NAME . '/theme.json';
		$this->assertStringContainsString( 'uppercase', (string) file_get_contents( $theme ) );
		$this->assertSame( 'keep', self::row( $this->import(), self::NAME )['action'] );

		$this->write( 'brand/tokens.json', '{ "radius": { "card": { "$value": "{radius.large}" } } }' );
		$this->assertSame( 'update', self::row( $this->import(), self::NAME )['action'] );
		$this->assertStringNotContainsString( 'uppercase', (string) file_get_contents( $theme ) );
	}

	public function test_the_logo_becomes_the_site_logo_and_exports_back(): void {
		$yaml = (string) file_get_contents( "$this->dir/package.yaml" );
		$this->write( 'package.yaml', str_replace( 'format: 1', 'format: 3', $yaml ) . "logo: media/tarla.png\n" );
		$this->assertSame( '(new image)', self::row( $this->import( true ), 'site_logo' )['title'] );

		$this->import();
		$this->assertSame( $this->made()['media/tarla.png'], (int) get_option( 'site_logo' ) );
		$this->assertSame( 'keep', self::row( $this->import(), 'site_logo' )['action'] );

		$out = sys_get_temp_dir() . '/pergel-export-' . bin2hex( random_bytes( 4 ) );
		try {
			( new Exporter( $out, self::NAME ) )->run();
			$exported = (string) file_get_contents( "$out/package.yaml" );
			$this->assertStringContainsString( "format: 3\n", $exported );
			$this->assertStringContainsString( "logo: media/tarla.png\n", $exported );
		} finally {
			if ( is_dir( $out ) ) {
				self::remove( $out );
			}
		}

		// A logo the package did not make stays out, as hand-made pages do.
		$foreign = (int) wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => 'Elle yüklenmiş logo',
				'post_status'    => 'inherit',
			)
		);
		update_option( 'site_logo', $foreign );
		try {
			( new Exporter( $out, self::NAME ) )->run();
			$exported = (string) file_get_contents( "$out/package.yaml" );
			$this->assertStringContainsString( "format: 1\n", $exported );
			$this->assertStringNotContainsString( "\nlogo:", $exported );
		} finally {
			wp_delete_attachment( $foreign, true );
			if ( is_dir( $out ) ) {
				self::remove( $out );
			}
		}
	}

	public function test_a_media_library_video_comes_with_its_poster_and_exports_back(): void {
		$yaml = (string) file_get_contents( "$this->dir/package.yaml" );
		$this->write( 'package.yaml', str_replace( 'format: 1', 'format: 4', $yaml ) );
		$this->write( 'media/klip.mp4', pack( 'N', 24 ) . 'ftypisom' . pack( 'N', 512 ) . 'isomiso2' . pack( 'N', 8 ) . 'mdat' );
		$this->write( 'media.yaml', (string) file_get_contents( "$this->dir/media.yaml" ) . "media/klip.mp4:\n  alt: Hasat\n  library: true\n  poster: media/tarla.png\n" );

		$rows = $this->import();
		$this->assertSame( 'video', self::row( $rows, 'media/klip.mp4' )['type'] );
		$made = $this->made();
		$clip = $made['media/klip.mp4'];
		$this->assertSame( 'video/mp4', get_post_mime_type( $clip ) );
		$this->assertSame( $made['media/tarla.png'], (int) get_post_thumbnail_id( $clip ) );
		$this->assertSame( 'Hasat', get_post_meta( $clip, '_wp_attachment_image_alt', true ) );
		$this->assertSame( '1', get_post_meta( $clip, Importer::LIBRARY, true ) );
		$this->assertSame( 'keep', self::row( $this->import(), 'media/klip.mp4' )['action'] );

		// A poster changed in the panel is put back.
		delete_post_thumbnail( $clip );
		$poster = array_values( array_filter( $this->import( true ), static fn( array $row ): bool => 'poster' === $row['type'] ) );
		$this->assertSame( array( 'update', 'media/klip.mp4' ), array( $poster[0]['action'] ?? '', $poster[0]['key'] ?? '' ) );
		$this->assertSame( 0, (int) get_post_thumbnail_id( $clip ) );
		$this->import();
		$this->assertSame( $made['media/tarla.png'], (int) get_post_thumbnail_id( $clip ) );

		$out = sys_get_temp_dir() . '/pergel-export-' . bin2hex( random_bytes( 4 ) );
		try {
			( new Exporter( $out, self::NAME ) )->run();
			$this->assertStringContainsString( "format: 4\n", (string) file_get_contents( "$out/package.yaml" ) );
			$this->assertFileEquals( "$this->dir/media/klip.mp4", "$out/media/klip.mp4" );
			$exported = Package::read( $out );
			$this->assertSame( array( 'media/klip.mp4' => 'media/tarla.png' ), $exported->library );
			$this->assertSame( 'Hasat', $exported->media['media/klip.mp4']['alt'] );
		} finally {
			if ( is_dir( $out ) ) {
				self::remove( $out );
			}
		}

		// Out of the library: the clip is no longer the package's to keep there.
		$this->write( 'media.yaml', str_replace( "media/klip.mp4:\n  alt: Hasat\n  library: true\n  poster: media/tarla.png\n", '', (string) file_get_contents( "$this->dir/media.yaml" ) ) );
		$this->assertSame( 'stale', self::row( $this->import(), 'media/klip.mp4' )['action'] );
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

	public function test_products_are_filled_in_by_sku_and_only_released(): void {
		$id = $this->shop_product( 'PERGEL-TEST-1' );
		$this->products_only(
			<<<'MD'
			---
			title: Hassas terazi
			excerpt: Kısa **açıklama**.
			template: single-product-sections
			seo:
			  title: Terazi | Örnek
			---

			## Teknik özellikler

			Gövde metni.
			MD
		);
		$front = get_option( 'page_on_front' );

		$rows = $this->import();
		$this->assertSame( 'adopt', self::row( $rows, 'products/PERGEL-TEST-1.md' )['action'] );
		$this->assertSame( array(), array_filter( $rows, static fn( array $row ): bool => in_array( $row['type'], array( 'page', 'menu' ), true ) ) );
		$this->assertSame( $front, get_option( 'page_on_front' ) );

		$product = wc_get_product( $id );
		$this->assertSame( 'Hassas terazi', $product->get_name() );
		$this->assertSame( 'Kısa <strong>açıklama</strong>.', $product->get_short_description() );
		$this->assertStringContainsString( 'Gövde metni.', $product->get_description() );
		$this->assertSame( 'single-product-sections', get_page_template_slug( $id ) );
		$this->assertSame( 'Terazi | Örnek', get_post_meta( $id, Meta::TITLE, true ) );
		// No price in the file: the shop's price stays.
		$this->assertSame( '100', $product->get_regular_price() );
		$this->assertSame( 7, $product->get_stock_quantity() );

		// The shop's own fields change without the product counting as edited.
		$product->set_stock_quantity( 3 );
		$product->save();
		$this->assertSame( array( 'keep' ), array_values( array_unique( array_column( $this->import(), 'action' ) ) ) );

		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => 'Panelde',
			)
		);
		$importer = $this->importer();
		$importer->run();
		$this->assertSame( 'skip', self::row( $importer->rows, 'products/PERGEL-TEST-1.md' )['action'] );
		$this->assertSame( 'Panelde', get_the_title( $id ) );
		$importer = $this->importer( force: true );
		$importer->run();
		$this->assertSame( 'update', self::row( $importer->rows, 'products/PERGEL-TEST-1.md' )['action'] );
		$this->assertSame( 'Hassas terazi', get_the_title( $id ) );

		$this->write( 'products/PERGEL-TEST-1.md', "---\ntitle: Hassas terazi\nprice: { regular: 120, sale: 99.5 }\n---\n" );
		$this->assertSame( 'update', self::row( $this->import(), 'products/PERGEL-TEST-1.md' )['action'] );
		$product = wc_get_product( $id );
		$this->assertSame( '120', $product->get_regular_price() );
		$this->assertSame( '99.5', $product->get_price() );
		$this->assertSame( 'Kısa <strong>açıklama</strong>.', $product->get_short_description() );
		$this->assertSame( array( 'keep' ), array_values( array_unique( array_column( $this->import(), 'action' ) ) ) );

		unlink( "$this->dir/products/PERGEL-TEST-1.md" );
		$this->write( 'products/PERGEL-TEST-2.md', "---\ntitle: İkinci\n---\n" );
		$this->shop_product( 'PERGEL-TEST-2' );
		$this->assertSame( 'stale', self::row( $this->import(), 'products/PERGEL-TEST-1.md' )['action'] );
		$this->assertSame( 'release', self::row( $this->import( prune: true ), 'products/PERGEL-TEST-1.md' )['action'] );
		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertSame( '', get_post_meta( $id, Importer::META, true ) );
		$this->assertSame( 'Hassas terazi', get_the_title( $id ) );
	}

	public function test_a_product_the_shop_does_not_have_or_a_price_it_cannot_take_stops_the_import(): void {
		$this->shop_product( 'PERGEL-TEST-V', 'variable' );
		$this->products_only( "---\ntitle: Yok\n---\n" );
		$this->write( 'products/PERGEL-TEST-V.md', "---\nprice: { regular: 10 }\n---\n" );

		$problems = implode( "\n", $this->importer()->preflight() );
		$this->assertStringContainsString( 'products/PERGEL-TEST-1.md: the shop has no product with the SKU PERGEL-TEST-1', $problems );
		$this->assertStringContainsString( 'products/PERGEL-TEST-V.md: price: product ', $problems );
	}

	public function test_an_export_writes_the_products_back(): void {
		$this->shop_product( 'PERGEL-TEST-1' );
		$this->products_only( "---\ntitle: Hassas terazi\nexcerpt: Kısa **açıklama**.\nprice: { regular: 120 }\n---\n\nGövde metni.\n" );
		$this->import();
		$out = sys_get_temp_dir() . '/pergel-export-' . bin2hex( random_bytes( 4 ) );
		try {
			$exporter = new Exporter( $out, self::NAME );
			$this->assertSame( array(), $exporter->preflight() );
			$exporter->run();
			$this->assertSame( array(), $exporter->notes );
			$this->assertFileDoesNotExist( "$out/sitemap.yaml" );
			$this->assertStringContainsString( "format: 2\n", (string) file_get_contents( "$out/package.yaml" ) );
			$product = (string) file_get_contents( "$out/products/PERGEL-TEST-1.md" );
			$this->assertStringContainsString( 'Hassas terazi', $product );
			$this->assertStringContainsString( 'Kısa **açıklama**.', $product );
			$this->assertStringContainsString( "price:\n  regular: \"120\"\n", $product );
			$this->assertStringEndsWith( "---\n\nGövde metni.\n", $product );

			$package = Package::read( $out );
			$package->check_theme();
			$this->assertSame( array(), $package->problems );
			$importer = new Importer( $package, false, false, false );
			$this->assertSame( array(), $importer->preflight() );
			$importer->run();
			$this->assertSame( 'keep', self::row( $importer->rows, 'products/PERGEL-TEST-1.md' )['action'] );
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
	 * A shop product with a SKU, a price of 100 and 7 in stock, deleted
	 * after the test.
	 *
	 * @param string $sku  SKU.
	 * @param string $type simple or variable.
	 */
	private function shop_product( string $sku, string $type = 'simple' ): int {
		if ( ! class_exists( \WC_Product_Simple::class ) ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
		$product = 'variable' === $type ? new \WC_Product_Variable() : new \WC_Product_Simple();
		$product->set_name( "Ürün $sku" );
		$product->set_sku( $sku );
		$product->set_regular_price( '100' );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 7 );
		$product->set_status( 'publish' );
		$id               = $product->save();
		$this->products[] = $id;
		return $id;
	}

	/**
	 * Makes the package copy one of products alone: format 2, no sitemap
	 * and no page content, with products/PERGEL-TEST-1.md.
	 *
	 * @param string $product Contents of products/PERGEL-TEST-1.md.
	 */
	private function products_only( string $product ): void {
		$this->write( 'package.yaml', str_replace( 'format: 1', 'format: 2', (string) file_get_contents( "$this->dir/package.yaml" ) ) );
		unlink( "$this->dir/sitemap.yaml" );
		self::remove( "$this->dir/content" );
		mkdir( "$this->dir/products" );
		$this->write( 'products/PERGEL-TEST-1.md', $product );
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
	 * A published page made in the panel, not by the package.
	 *
	 * @param string $slug    Slug.
	 * @param string $content Content.
	 * @return int Page ID.
	 */
	private function hand_made( string $slug, string $content ): int {
		return (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Elle yapılmış',
				'post_name'    => $slug,
				'post_content' => $content,
			)
		);
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
