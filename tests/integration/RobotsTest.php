<?php
/**
 * Pergel's noindex in the robots tag of each SEO plugin.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Seo\Module as Seo;
use Pergel\Seo\Robots;
use PHPUnit\Framework\TestCase;

/**
 * Calls the filters the way each plugin does; the SEO matrix in CI reads
 * the printed tag with the plugins active.
 */
final class RobotsTest extends TestCase {

	/**
	 * Robots filters of the SEO plugins, with a value as the plugin passes
	 * it and the value after Pergel's noindex.
	 */
	private const HOOKS = array(
		'rank-math' => array( 'rank_math/frontend/robots', array( 'index' => 'index' ), array( 'index' => 'noindex', 'follow' => 'follow' ) ),
		'aioseo'    => array( 'aioseo_robots_meta', array( 'noindex' => '' ), array( 'noindex' => 'noindex' ) ),
		'seopress'  => array( 'seopress_titles_noindex_bypass', '', '1' ),
		'tsf'       => array( 'the_seo_framework_robots_meta_array', array(), array( 'noindex' => 'noindex' ) ),
	);

	/**
	 * Filters as they were before the test.
	 *
	 * @var array<string, \WP_Hook|null>
	 */
	private array $saved = array();

	/**
	 * Saves the filters the test adds to.
	 */
	protected function setUp(): void {
		global $wp_filter;
		foreach ( array_merge( array( 'wp_robots' ), array_column( self::HOOKS, 0 ) ) as $hook ) {
			$this->saved[ $hook ] = isset( $wp_filter[ $hook ] ) ? clone $wp_filter[ $hook ] : null;
		}
	}

	/**
	 * Puts the filters back.
	 */
	protected function tearDown(): void {
		global $wp_filter;
		remove_all_filters( Seo::NOINDEX );
		foreach ( $this->saved as $hook => $saved ) {
			if ( null === $saved ) {
				unset( $wp_filter[ $hook ] );
			} else {
				$wp_filter[ $hook ] = $saved; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Puts back the filter saved in setUp.
			}
		}
	}

	/**
	 * Adds the robots filter for a plugin, as Module::register() does.
	 *
	 * @param string $plugin Plugin id from Detect.
	 */
	private static function hook( string $plugin ): void {
		( new \ReflectionMethod( Seo::class, 'robots' ) )->invoke( null, $plugin );
	}

	/**
	 * Only true keeps a page out.
	 */
	public function test_only_true_counts(): void {
		$this->assertFalse( Seo::noindex() );
		add_filter( Seo::NOINDEX, static fn (): string => 'yes' );
		$this->assertFalse( Seo::noindex() );
		add_filter( Seo::NOINDEX, '__return_true' );
		$this->assertTrue( Seo::noindex() );
	}

	/**
	 * Each plugin's filter, with and without Pergel's noindex.
	 */
	public function test_each_plugin_gets_noindex_follow(): void {
		foreach ( self::HOOKS as $plugin => [ $hook, $value, $kept_out ] ) {
			remove_all_filters( $hook );
			self::hook( $plugin );
			$this->assertSame( $value, apply_filters( $hook, $value, null ), $plugin );
			add_filter( Seo::NOINDEX, '__return_true' );
			$this->assertSame( $kept_out, apply_filters( $hook, $value, null ), $plugin );
			remove_all_filters( Seo::NOINDEX );
		}

		add_filter( Seo::NOINDEX, '__return_true' );
		$this->assertSame( array(), apply_filters( 'the_seo_framework_robots_meta_array', array(), array( 'id' => 1 ) ), 'Not when The SEO Framework asks about another page.' );
	}

	/**
	 * Core's wp_robots, without a plugin and with Yoast SEO.
	 */
	public function test_core_robots(): void {
		remove_all_filters( 'wp_robots' );
		self::hook( 'yoast' );
		$this->assertSame( array( 'max-image-preview' => 'large' ), apply_filters( 'wp_robots', array( 'max-image-preview' => 'large' ) ) );

		add_filter( Seo::NOINDEX, '__return_true' );
		$this->assertSame(
			array(
				'noindex' => true,
				'follow'  => true,
			),
			apply_filters( 'wp_robots', array( 'max-image-preview' => 'large' ) )
		);
		$this->assertArrayNotHasKey( 'follow', Robots::core( array( 'nofollow' => true ) ), 'A nofollow page stays nofollow.' );
		$this->assertSame( 'nofollow', Robots::rank_math( array( 'follow' => 'nofollow' ) )['follow'] );
	}
}
