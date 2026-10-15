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

		foreach ( array( 'disable_emoji', 'remove_oembed_links', 'dequeue_dashicons', 'preload_fonts' ) as $key ) {
			$this->assertStringContainsString( "name=\"pergel_settings[performance][$key]\"", $html );
		}
	}
}
