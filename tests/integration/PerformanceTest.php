<?php
/**
 * The performance module on a front-end request.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Core\Plugin;
use Pergel\Performance\Module;
use Pergel\Performance\Webp;
use Pergel\Settings\Screen;
use Pergel\Settings\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Checks the hooks the module removes and adds, the printed preload links
 * and the settings tab. Filters added by a test are removed again.
 */
final class PerformanceTest extends TestCase {

	/**
	 * Theme data filter that applies a style variation, if a test added one.
	 *
	 * @var (callable(\WP_Theme_JSON_Data): \WP_Theme_JSON_Data)|null
	 */
	private $variation = null;

	protected function tearDown(): void {
		remove_all_filters( 'pergel/performance/font_preloads' );
		remove_filter( 'wp_get_attachment_image_src', array( Webp::class, 'image_src' ), 20 );
		remove_filter( 'wp_calculate_image_srcset', array( Webp::class, 'srcset' ), 20 );
		remove_filter( 'wp_content_img_tag', array( Webp::class, 'content_img' ), 20 );
		if ( null !== $this->variation ) {
			remove_filter( 'wp_theme_json_data_theme', $this->variation );
		}
		\WP_Theme_JSON_Resolver::clean_cached_data();
		wp_clean_theme_json_cache();
		wp_dequeue_style( 'dashicons' );
		wp_set_current_user( 0 );
	}

	/**
	 * Head output of core's preload printer.
	 */
	private static function preloads(): string {
		ob_start();
		wp_preload_resources();
		return (string) ob_get_clean();
	}

	public function test_core_head_output_is_removed(): void {
		$this->assertContains( 'performance', Plugin::loaded() );
		$this->assertFalse( has_action( 'wp_head', 'print_emoji_detection_script' ) );
		$this->assertFalse( has_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' ) );
		$this->assertFalse( has_action( 'wp_print_styles', 'print_emoji_styles' ) );
		$this->assertFalse( has_action( 'embed_head', 'print_emoji_detection_script' ) );
		$this->assertFalse( has_action( 'wp_head', 'wp_oembed_add_discovery_links' ) );
		// Feeds and e-mails keep core's emoji images.
		$this->assertNotFalse( has_filter( 'the_content_feed', 'wp_staticize_emoji' ) );

		ob_start();
		wp_resource_hints();
		$this->assertStringNotContainsString( 's.w.org', (string) ob_get_clean() );
	}

	public function test_dashicons_stay_for_logged_in_users_and_dependents(): void {
		wp_enqueue_style( 'dashicons' );
		Module::dequeue_dashicons();
		$this->assertFalse( wp_style_is( 'dashicons', 'enqueued' ) );

		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		wp_set_current_user( (int) $admins[0] );
		wp_enqueue_style( 'dashicons' );
		Module::dequeue_dashicons();
		$this->assertTrue( wp_style_is( 'dashicons', 'enqueued' ) );
		wp_set_current_user( 0 );

		// A style that depends on Dashicons still brings it in.
		wp_register_style( 'pergel-test-icons', false, array( 'dashicons' ), '1' );
		wp_enqueue_style( 'pergel-test-icons' );
		Module::dequeue_dashicons();
		$wp_styles = wp_styles();
		$wp_styles->all_deps( $wp_styles->queue );
		$this->assertContains( 'dashicons', $wp_styles->to_do );
		wp_dequeue_style( 'pergel-test-icons' );
		wp_deregister_style( 'pergel-test-icons' );
		$wp_styles->to_do = array();
	}

	public function test_preloads_the_latin_files_of_the_active_variation(): void {
		$this->assertSame( array(), Module::font_urls(), 'The base theme uses system fonts.' );

		$variation = wp_json_file_decode( get_theme_file_path( 'styles/lab-precision.json' ), array( 'associative' => true ) );
		$this->assertIsArray( $variation );
		$this->variation = static fn ( \WP_Theme_JSON_Data $data ): \WP_Theme_JSON_Data => $data->update_with( $variation );
		add_filter( 'wp_theme_json_data_theme', $this->variation );
		\WP_Theme_JSON_Resolver::clean_cached_data();
		wp_clean_theme_json_cache();

		$urls = Module::font_urls();
		$this->assertSame(
			array(
				get_theme_file_uri( 'assets/fonts/figtree/figtree-latin-wght-normal.woff2' ),
				get_theme_file_uri( 'assets/fonts/lexend-exa/lexend-exa-latin-wght-normal.woff2' ),
			),
			$urls
		);
		// The same URLs as core's @font-face rules, or the browser downloads twice.
		ob_start();
		wp_print_font_faces();
		$faces = (string) ob_get_clean();
		foreach ( $urls as $url ) {
			$this->assertStringContainsString( "src:url('$url')", $faces );
		}

		$html = self::preloads();
		$this->assertSame( 2, substr_count( $html, "as='font' type='font/woff2' crossorigin='anonymous'" ) );

		add_filter( 'pergel/performance/font_preloads', static fn ( array $urls ): array => array_slice( $urls, 0, 1 ) );
		$this->assertSame( 1, substr_count( self::preloads(), "as='font'" ) );
	}

	public function test_defaults_and_pass_through(): void {
		$this->assertTrue( Settings::get( 'performance.preload_fonts' ) );
		$this->assertTrue( Settings::get( 'performance.disable_emoji' ) );
		$this->assertNotFalse( has_filter( 'wp_preload_resources', array( Module::class, 'preload_fonts' ) ) );
		$this->assertSame( 'kept', Module::preload_fonts( 'kept' ), 'A non-array from another callback is passed on.' );
	}

	public function test_settings_tab(): void {
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		wp_set_current_user( (int) $admins[0] );
		$GLOBALS['title'] = 'Pergel settings';
		$_GET['tab']      = 'performance';
		ob_start();
		Screen::render();
		$html = (string) ob_get_clean();
		unset( $_GET['tab'] );

		foreach ( array( 'disable_emoji', 'remove_oembed_links', 'dequeue_dashicons', 'preload_fonts', 'webp' ) as $key ) {
			$this->assertStringContainsString( "name=\"pergel_settings[performance][$key]\"", $html );
		}
	}

	public function test_webp_copies_follow_the_upload(): void {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$this->assertTrue( Settings::get( 'performance.webp' ) );
		$this->assertNotFalse( has_filter( 'wp_update_attachment_metadata', array( Webp::class, 'on_metadata' ) ) );
		if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			$this->markTestSkipped( 'This image editor cannot write WebP.' );
		}

		// A smooth photo-like JPEG, large enough for several sub-sizes. (Fine
		// noise can make a small WebP bigger than its JPEG; that copy is dropped.)
		$image = imagecreatetruecolor( 1200, 800 );
		for ( $y = 0; $y < 800; $y += 8 ) {
			for ( $x = 0; $x < 1200; $x += 8 ) {
				imagefilledrectangle( $image, $x, $y, $x + 7, $y + 7, (int) imagecolorallocate( $image, intdiv( $x * 255, 1200 ), intdiv( $y * 255, 800 ), 128 + intdiv( $x + $y, 16 ) % 64 ) );
			}
		}
		$uploads = wp_upload_dir();
		$file    = $uploads['path'] . '/pergel-webp-test.jpg';
		imagejpeg( $image, $file, 90 );
		$id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Pergel WebP test',
				'post_status'    => 'inherit',
			),
			$file
		);
		$this->assertIsInt( $id );
		try {
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
			$files = Webp::attachment_files( $id );
			$this->assertGreaterThan( 2, count( $files ) );
			foreach ( $files as $path ) {
				$this->assertFileExists( Webp::sibling( $path ) );
				$this->assertLessThan( filesize( $path ), filesize( Webp::sibling( $path ) ) );
			}
			$this->assertSame( array( 'kept' => count( $files ) ), Webp::attachment( $id ), 'A second run keeps the copies.' );

			// Pages show the copies; the attachment and the head keep the originals.
			$before = wp_get_attachment_image( $id, 'medium_large' );
			$large  = (string) wp_get_attachment_image_url( $id, 'large' );
			$this->assertStringNotContainsString( '.jpg.webp', $before );
			$this->assertSame( $large, Webp::url( $large ), 'Before the page starts, addresses stay.' );
			Module::serve_webp();
			$this->assertSame( $large . '.webp', Webp::url( $large ) );
			$after = wp_get_attachment_image( $id, 'medium_large' );
			$this->assertMatchesRegularExpression( '/src="[^"]+\.jpg\.webp"/', $after );
			$this->assertSame( 0, preg_match( '/\.jpg(?!\.webp)[ "]/', $after ), 'Every srcset candidate is a copy.' );
			$this->assertStringEndsWith( '.jpg', (string) wp_get_attachment_url( $id ) );

			$content = wp_filter_content_tags( '<img src="' . esc_url( $large ) . '" class="wp-image-' . $id . '" alt="">' );
			$this->assertStringContainsString( '.jpg.webp', $content );
			$this->assertStringContainsString( 'srcset="', $content );
			$this->assertSame( 0, preg_match( '/\.jpg(?!\.webp)[ "]/', $content ) );
		} finally {
			wp_delete_attachment( $id, true );
		}
		foreach ( $files as $path ) {
			$this->assertFileDoesNotExist( Webp::sibling( $path ), 'Deleting the attachment deletes its copies.' );
		}
	}

	public function test_webp_copies_of_palette_and_grey_pngs(): void {
		if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			$this->markTestSkipped( 'This image editor cannot write WebP.' );
		}
		// GD loads both as palette images, which it cannot write as WebP as is.
		$palette = imagecreate( 400, 300 );
		imagecolorallocate( $palette, 240, 240, 240 );
		$clear = (int) imagecolorallocate( $palette, 0, 0, 0 );
		imagecolortransparent( $palette, $clear );
		imagefilledrectangle( $palette, 0, 0, 99, 99, $clear );
		for ( $i = 0; $i < 40; $i++ ) {
			imagefilledellipse( $palette, 100 + $i * 7, 150, 30, 30, (int) imagecolorallocate( $palette, $i * 6, 100, 200 - $i * 4 ) );
		}
		$dir   = wp_upload_dir()['path'];
		$files = array( $dir . '/pergel-webp-palette.png', $dir . '/pergel-webp-grey.png' );
		imagepng( $palette, $files[0], 9 );

		// GD only writes palette or colour PNGs, so the grey one (colour type 0) is put together by hand.
		$rows = '';
		for ( $y = 0; $y < 300; $y++ ) {
			$rows .= chr( 0 );
			for ( $x = 0; $x < 400; $x++ ) {
				$rows .= chr( intdiv( ( $x + $y ) * 255, 700 ) );
			}
		}
		$chunk = static fn ( string $type, string $data ): string => pack( 'N', strlen( $data ) ) . $type . $data . pack( 'N', crc32( $type . $data ) );
		file_put_contents( $files[1], chr( 0x89 ) . "PNG\r\n" . chr( 26 ) . "\n" . $chunk( 'IHDR', pack( 'NN', 400, 300 ) . chr( 8 ) . chr( 0 ) . chr( 0 ) . chr( 0 ) . chr( 0 ) ) . $chunk( 'IDAT', (string) gzcompress( $rows, 9 ) ) . $chunk( 'IEND', '' ) );
		$this->assertSame( array( 400, 300 ), array_slice( (array) wp_getimagesize( $files[1] ), 0, 2 ) );
		try {
			foreach ( $files as $file ) {
				$this->assertTrue( Webp::is_png( $file ) );
				$result = Webp::make( $file, true );
				$this->assertContains( $result, array( 'made', 'bigger' ), $file );
				if ( 'made' === $result ) {
					$size = wp_getimagesize( Webp::sibling( $file ) );
					$this->assertIsArray( $size );
					$this->assertSame( array( 400, 300 ), array( $size[0], $size[1] ) );
				} else {
					$this->assertFileDoesNotExist( Webp::sibling( $file ) );
				}
			}
		} finally {
			foreach ( $files as $file ) {
				wp_delete_file( Webp::sibling( $file ) );
				wp_delete_file( $file );
			}
		}
	}
}
