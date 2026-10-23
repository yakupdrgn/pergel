<?php
/**
 * Pergel's noindex in the robots tag of each SEO plugin.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Seo\Robots;
use PHPUnit\Framework\TestCase;

/**
 * Calls the filters the way each plugin does; the SEO matrix in CI reads
 * the printed tag with the plugins active.
 */
final class RobotsTest extends TestCase {

	protected function tearDown(): void {
		remove_all_filters( Robots::FILTER );
	}

	public function test_nothing_changes_unless_a_page_is_kept_out(): void {
		$robots = array( 'max-image-preview' => 'large' );
		$this->assertSame( $robots, Robots::core( $robots ) );
		$this->assertSame( array( 'index' => 'index' ), Robots::rank_math( array( 'index' => 'index' ) ) );
		$this->assertSame( array( 'noindex' => '' ), Robots::aioseo( array( 'noindex' => '' ) ) );
		$this->assertSame( '', Robots::seopress( '' ) );
		$this->assertSame( array(), Robots::tsf( array(), null ) );

		add_filter( Robots::FILTER, static fn (): string => 'yes' );
		$this->assertFalse( Robots::noindex(), 'Only true counts.' );
	}

	public function test_each_plugin_gets_noindex_follow(): void {
		add_filter( Robots::FILTER, '__return_true' );

		$this->assertSame(
			array(
				'noindex' => true,
				'follow'  => true,
			),
			Robots::core( array( 'max-image-preview' => 'large' ) )
		);
		$this->assertArrayNotHasKey( 'follow', Robots::core( array( 'nofollow' => true ) ), 'A nofollow page stays nofollow.' );
		$this->assertSame(
			array(
				'index'  => 'noindex',
				'follow' => 'follow',
			),
			Robots::rank_math( array( 'index' => 'index' ) )
		);
		$this->assertSame( 'nofollow', Robots::rank_math( array( 'follow' => 'nofollow' ) )['follow'] );
		$this->assertSame( 'noindex', Robots::aioseo( array( 'noindex' => '' ) )['noindex'] );
		$this->assertSame( '1', Robots::seopress( '' ) );
		$this->assertSame( array( 'noindex' => 'noindex' ), Robots::tsf( array(), null ) );
		$this->assertSame( array(), Robots::tsf( array(), array( 'id' => 1 ) ), 'Not when The SEO Framework asks about another page.' );
	}

	public function test_the_filter_of_the_plugin_that_prints_the_tag(): void {
		$hooks = array(
			'rank-math' => array( 'rank_math/frontend/robots', 'rank_math' ),
			'aioseo'    => array( 'aioseo_robots_meta', 'aioseo' ),
			'seopress'  => array( 'seopress_titles_noindex_bypass', 'seopress' ),
			'tsf'       => array( 'the_seo_framework_robots_meta_array', 'tsf' ),
			'yoast'     => array( 'wp_robots', 'core' ),
		);
		foreach ( $hooks as $plugin => [ $hook, $method ] ) {
			$callback = array( Robots::class, $method );
			$had      = has_filter( $hook, $callback );
			Robots::register( $plugin );
			$this->assertNotFalse( has_filter( $hook, $callback ), $plugin );
			if ( false === $had ) {
				remove_filter( $hook, $callback, 'wp_robots' === $hook ? 10 : 20 );
			}
		}
	}
}
