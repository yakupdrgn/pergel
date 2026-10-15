<?php
/**
 * The theme's pattern library on a site.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Patterns\Rules;
use PHPUnit\Framework\TestCase;

/**
 * Every pattern file is registered, parses into known blocks only and renders
 * without notices. The inserter shows the theme's library, not core's or the
 * remote directory's. Hero and full-page patterns have a template without a
 * page title, so the hero's H1 stays the only one.
 */
final class PatternsTest extends TestCase {

	/**
	 * Pattern files of the theme.
	 *
	 * @return list<string>
	 */
	private static function files(): array {
		$files = glob( get_theme_file_path( 'patterns/*.php' ) );
		return false === $files ? array() : $files;
	}

	/**
	 * Blocks of a parsed tree that are not registered, or loose HTML between
	 * blocks (a broken block comment).
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return list<string>
	 */
	private static function problems( array $blocks ): array {
		$problems = array();
		$registry = \WP_Block_Type_Registry::get_instance();
		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? null;
			if ( null === $name ) {
				if ( '' !== trim( (string) ( $block['innerHTML'] ?? '' ) ) ) {
					$problems[] = 'loose HTML: ' . substr( trim( (string) $block['innerHTML'] ), 0, 60 );
				}
				continue;
			}
			if ( ! $registry->is_registered( (string) $name ) ) {
				$problems[] = 'unknown block ' . $name;
			}
			$inner    = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array();
			$problems = array_merge( $problems, self::problems( $inner ) );
		}
		return $problems;
	}

	public function test_every_file_is_registered_in_known_categories(): void {
		$registry   = \WP_Block_Patterns_Registry::get_instance();
		$categories = \WP_Block_Pattern_Categories_Registry::get_instance();

		$this->assertNotEmpty( self::files() );
		foreach ( self::files() as $file ) {
			$slug    = 'pergel/' . basename( $file, '.php' );
			$pattern = $registry->get_registered( $slug );
			$this->assertIsArray( $pattern, "{$slug} is not registered." );
			foreach ( $pattern['categories'] ?? array() as $category ) {
				$this->assertTrue( $categories->is_registered( $category ), "{$slug}: category {$category} is not registered." );
			}
		}
	}

	public function test_patterns_parse_into_known_blocks_and_render(): void {
		$registry = \WP_Block_Patterns_Registry::get_instance();

		set_error_handler(
			static function ( int $errno, string $message, string $file, int $line ): never {
				throw new \ErrorException( $message, 0, $errno, $file, $line ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test failure.
			}
		);
		try {
			foreach ( self::files() as $file ) {
				$slug    = 'pergel/' . basename( $file, '.php' );
				$pattern = $registry->get_registered( $slug );
				$content = is_array( $pattern ) ? (string) $pattern['content'] : '';

				$this->assertNotSame( '', trim( $content ), "{$slug} is empty." );
				$this->assertSame( array(), self::problems( parse_blocks( $content ) ), "{$slug} has broken markup." );
				if ( ! str_starts_with( basename( $file ), 'hidden-' ) ) {
					$this->assertNotSame( '', trim( do_blocks( $content ) ), "{$slug} renders nothing." );
				}
			}
		} finally {
			restore_error_handler();
		}
	}

	public function test_the_rules_hold_for_every_file(): void {
		foreach ( self::files() as $file ) {
			$this->assertSame( array(), Rules::check( basename( $file ), (string) file_get_contents( $file ) ), basename( $file ) );
		}
	}

	public function test_core_and_remote_patterns_stay_out(): void {
		$this->assertFalse( current_theme_supports( 'core-block-patterns' ) );
		$this->assertFalse( apply_filters( 'should_load_remote_block_patterns', true ) );
		$this->assertTrue( \WP_Block_Pattern_Categories_Registry::get_instance()->is_registered( 'pergel-pages' ) );
	}

	public function test_pages_can_drop_the_title_for_a_hero(): void {
		$templates = get_block_templates( array( 'slug__in' => array( 'page-landing' ) ), 'wp_template' );

		$this->assertCount( 1, $templates );
		$this->assertStringNotContainsString( 'wp:post-title', $templates[0]->content );
		$this->assertStringContainsString( 'wp:post-content', $templates[0]->content );
		$this->assertArrayHasKey( 'page-landing', wp_get_theme()->get_page_templates( null, 'page' ) );
	}
}
