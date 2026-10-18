<?php
/**
 * Starters against a real WordPress: install, idempotency, reset, remove.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use InvalidArgumentException;
use Pergel\Starters\Starter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Installs a small starter of its own from a temporary folder, so a starter
 * installed on the site by hand is left alone, and removes it afterwards.
 */
final class StarterTest extends TestCase {

	private const NAME    = 'pergel-test-starter';
	private const OPTIONS = array( 'show_on_front', 'page_on_front', 'page_for_posts' );

	/**
	 * Temporary starter folder.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Reading settings as they were before the test.
	 *
	 * @var array<string, mixed>
	 */
	private array $saved = array();

	protected function setUp(): void {
		$this->saved = $this->options();
		$this->dir   = sys_get_temp_dir() . '/pergel-' . uniqid() . '/' . self::NAME;
		mkdir( $this->dir, 0777, true );
		$this->write( self::package() );
	}

	protected function tearDown(): void {
		Starter::remove( self::NAME );
		foreach ( $this->saved as $option => $value ) {
			update_option( $option, $value );
		}
		unlink( $this->dir . '/starter.json' );
		rmdir( $this->dir );
		rmdir( dirname( $this->dir ) );
	}

	public function test_corporate_starter_is_valid(): void {
		$this->assertArrayHasKey( 'corporate', Starter::available() );
		$this->assertSame( 'Corporate', Starter::load( 'corporate' )->title() );
	}

	public function test_dry_run_saves_nothing(): void {
		$rows = Starter::load( $this->dir )->install( true );

		$this->assertSame( array( 'create', 'create', 'create', 'create' ), array_column( array_slice( $rows, 0, 4 ), 'action' ) );
		$this->assertSame( array(), $this->made() );
		$this->assertSame( $this->saved, $this->options() );
	}

	public function test_install_builds_pages_menu_and_reading_settings(): void {
		$rows = Starter::load( $this->dir )->install();
		$made = $this->made();

		$this->assertSame( array( 'blog', 'contact', 'home', 'menu' ), array_keys( $made ) );
		$this->assertCount( 4, array_filter( $rows, static fn( array $row ): bool => 'create' === $row['action'] ) );

		$home = get_post( $made['home'] );
		$this->assertSame( str_starts_with( get_locale(), 'tr' ) ? 'Test ana sayfa' : 'Test home', $home->post_title );
		$this->assertSame( Starter::content( 'pergel/page-home' ), $home->post_content );
		$this->assertSame( 'page-landing', get_page_template_slug( $home ) );
		$this->assertSame( '', get_page_template_slug( $made['blog'] ) );
		$this->assertSame( '', get_post( $made['blog'] )->post_content );

		// Sections carry the same metadata as ones added from the inserter.
		$roots = self::top( $home->post_content );
		$this->assertSame( 'pergel/hero-split', $roots[0]['attrs']['metadata']['patternName'] );
		$this->assertNotEmpty( $roots[0]['attrs']['metadata']['name'] );
		$this->assertStringNotContainsString( 'wp:pattern ', $home->post_content );

		$this->assertSame( 'page', get_option( 'show_on_front' ) );
		$this->assertSame( $made['home'], (int) get_option( 'page_on_front' ) );
		$this->assertSame( $made['blog'], (int) get_option( 'page_for_posts' ) );

		$links = self::top( get_post( $made['menu'] )->post_content );
		$this->assertSame( array( 'core/navigation-link', 'core/navigation-link' ), array_column( $links, 'blockName' ) );
		$this->assertSame( array( $made['contact'], $made['blog'] ), array_column( array_column( $links, 'attrs' ), 'id' ) );
		$this->assertSame( 'Test contact', $links[0]['attrs']['label'] );
		$this->assertSame( get_permalink( $made['contact'] ), $links[0]['attrs']['url'] );
	}

	public function test_second_install_keeps_everything_and_reset_rewrites(): void {
		$starter = Starter::load( $this->dir );
		$starter->install();
		$made = $this->made();
		wp_update_post(
			array(
				'ID'           => $made['contact'],
				'post_content' => '<!-- wp:paragraph --><p>Edited</p><!-- /wp:paragraph -->',
			)
		);

		$again = $starter->install();
		$this->assertSame( array( 'keep' ), array_values( array_unique( array_column( $again, 'action' ) ) ) );
		$this->assertSame( $made, $this->made() );
		$this->assertStringContainsString( 'Edited', get_post( $made['contact'] )->post_content );

		$reset = $starter->install( false, true );
		$this->assertSame( array( 'reset', 'reset', 'reset', 'reset' ), array_column( array_slice( $reset, 0, 4 ), 'action' ) );
		$this->assertSame( $made, $this->made() );
		$this->assertSame( Starter::content( 'pergel/page-contact' ), get_post( $made['contact'] )->post_content );
	}

	public function test_remove_deletes_what_it_made_and_resets_reading(): void {
		Starter::load( $this->dir )->install();
		$made = $this->made();

		$this->assertCount( 4 + 3, Starter::remove( self::NAME, true ) );
		$this->assertSame( $made, $this->made() );

		$rows = Starter::remove( self::NAME );
		$this->assertSame( array( 'delete', 'delete', 'delete', 'delete', 'set', 'set', 'set' ), array_column( $rows, 'action' ) );
		$this->assertSame( array(), $this->made() );
		foreach ( $made as $id ) {
			$this->assertNull( get_post( $id ) );
		}
		$this->assertSame( 'posts', get_option( 'show_on_front' ) );
		$this->assertSame( 0, (int) get_option( 'page_on_front' ) );
		$this->assertSame( 0, (int) get_option( 'page_for_posts' ) );
	}

	public function test_remove_leaves_reading_settings_it_did_not_set(): void {
		Starter::load( $this->dir )->install();
		foreach ( $this->saved as $option => $value ) {
			update_option( $option, $value );
		}

		$rows = Starter::remove( self::NAME );
		$this->assertSame( array( 'page', 'page', 'page', 'menu' ), array_column( $rows, 'type' ) );
		$this->assertSame( $this->saved, $this->options() );
	}

	/**
	 * Broken packages and part of the message each should give.
	 *
	 * @return array<string, array{0: callable, 1: string}>
	 */
	public static function broken(): array {
		return array(
			'format'          => array( static fn( array $p ): array => array( 'format' => 2 ) + $p, 'format must be 1' ),
			'no pages'        => array( static fn( array $p ): array => array( 'pages' => array() ) + $p, 'non-empty list' ),
			'duplicate key'   => array(
				static function ( array $p ): array {
					$p['pages'][1]['key'] = 'home';
					return $p;
				},
				'unique lowercase key',
			),
			'no pattern'      => array(
				static function ( array $p ): array {
					unset( $p['pages'][1]['pattern'] );
					return $p;
				},
				'needs a pattern',
			),
			'unknown pattern' => array(
				static function ( array $p ): array {
					$p['pages'][0]['pattern'] = 'pergel/no-such-page';
					return $p;
				},
				'not registered',
			),
			'two fronts'      => array(
				static function ( array $p ): array {
					$p['pages'][1]['front_page'] = true;
					return $p;
				},
				'only one page can be the front_page',
			),
			'menu key'        => array(
				static function ( array $p ): array {
					$p['menu']['pages'][] = 'missing';
					return $p;
				},
				'unknown page key',
			),
		);
	}

	/**
	 * Each broken package is refused before anything is saved.
	 *
	 * @param callable $breaker Changes the package.
	 * @param string   $message Part of the error.
	 */
	#[DataProvider( 'broken' )]
	public function test_broken_package_is_refused( callable $breaker, string $message ): void {
		$this->write( $breaker( self::package() ) );
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( $message );
		Starter::load( $this->dir );
	}

	public function test_missing_package_is_refused(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'starter.json not found' );
		Starter::load( 'no-such-starter' );
	}

	/**
	 * The test package.
	 *
	 * @return array<string, mixed>
	 */
	private static function package(): array {
		return array(
			'format'      => 1,
			'title'       => 'Test',
			'description' => 'Pages for StarterTest.',
			'pages'       => array(
				array(
					'key'        => 'home',
					'title'      => array(
						'en' => 'Test home',
						'tr' => 'Test ana sayfa',
					),
					'slug'       => 'pergel-test-home',
					'pattern'    => 'pergel/page-home',
					'front_page' => true,
				),
				array(
					'key'     => 'contact',
					'title'   => 'Test contact',
					'slug'    => 'pergel-test-contact',
					'pattern' => 'pergel/page-contact',
				),
				array(
					'key'        => 'blog',
					'title'      => 'Test blog',
					'slug'       => 'pergel-test-blog',
					'posts_page' => true,
				),
			),
			'menu'        => array(
				'title' => 'Test menu',
				'pages' => array( 'contact', 'blog' ),
			),
		);
	}

	/**
	 * Saves a package as the temporary starter.json.
	 *
	 * @param array<string, mixed> $data Package.
	 */
	private function write( array $data ): void {
		file_put_contents( $this->dir . '/starter.json', (string) wp_json_encode( $data ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- temporary test file.
	}

	/**
	 * IDs of what the test starter made, by key.
	 *
	 * @return array<string, int>
	 */
	private function made(): array {
		$made = array();
		$ids  = get_posts(
			array(
				'post_type'      => array( 'page', 'wp_navigation' ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => Starter::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- test.
				'meta_value'     => self::NAME, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- test.
			)
		);
		foreach ( $ids as $id ) {
			$made[ (string) get_post_meta( $id, Starter::META_KEY, true ) ] = $id;
		}
		ksort( $made );
		return $made;
	}

	/**
	 * Reading settings as stored. As text: the option cache keeps the 60 an
	 * earlier update_option() was given, the database "60", and WordPress
	 * skips an update to the same value in the other type.
	 *
	 * @return array<string, string>
	 */
	private function options(): array {
		$options = array();
		foreach ( self::OPTIONS as $option ) {
			$options[ $option ] = (string) get_option( $option );
		}
		return $options;
	}

	/**
	 * Top-level blocks, without the whitespace between them.
	 *
	 * @param string $content Block markup.
	 * @return list<array<string, mixed>>
	 */
	private static function top( string $content ): array {
		return array_values( array_filter( parse_blocks( $content ), static fn( array $block ): bool => null !== $block['blockName'] ) );
	}
}
