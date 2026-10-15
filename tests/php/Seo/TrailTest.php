<?php
/**
 * Breadcrumb trail tests.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Seo;

use Pergel\Seo\Trail;
use PHPUnit\Framework\TestCase;

/**
 * The trail keeps the steps the SEO plugins put in their BreadcrumbList.
 */
final class TrailTest extends TestCase {

	public function test_keeps_named_linked_visible_steps(): void {
		$trail = Trail::clean(
			array(
				array(
					'name' => 'Home',
					'url'  => 'https://example.com/',
				),
				array(
					'name' => 'Products',
					'url'  => '',
				),
				array(
					'name'   => 'Page 2',
					'url'    => 'https://example.com/blog/page/2/',
					'hidden' => true,
				),
				array(
					'name' => '',
					'url'  => 'https://example.com/empty/',
				),
				'not a step',
				array(
					'name' => 'Blog',
					'url'  => 'https://example.com/blog/',
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'name' => 'Home',
					'url'  => 'https://example.com/',
				),
				array(
					'name' => 'Blog',
					'url'  => 'https://example.com/blog/',
				),
			),
			$trail
		);
	}

	public function test_names_lose_entities_and_tags(): void {
		$trail = Trail::clean(
			array(
				array(
					'name' => 'Home',
					'url'  => 'https://example.com/',
				),
				array(
					'name' => ' <b>Tips &amp; tricks</b> &ldquo;now&rdquo; ',
					'url'  => 'https://example.com/tips/',
				),
			)
		);

		$this->assertSame( 'Tips & tricks “now”', $trail[1]['name'] );
	}

	public function test_a_single_step_is_no_trail(): void {
		$this->assertSame(
			array(),
			Trail::clean(
				array(
					array(
						'name' => 'Home',
						'url'  => 'https://example.com/',
					),
				)
			)
		);
		$this->assertSame( array(), Trail::clean( array() ) );
	}

	public function test_rank_math_current_page_gets_its_address(): void {
		$crumbs = array(
			array(
				'Home',
				'https://example.com/',
				'hide_in_schema' => false,
			),
			array(
				'Blog',
				'',
				'hide_in_schema' => false,
			),
		);

		$this->assertSame( 'https://example.com/blog/', Trail::link_last( $crumbs, 'https://example.com/blog/' )[1][1] );
	}

	public function test_rank_math_linked_hidden_or_unknown_steps_stay(): void {
		$linked = array( array( 'Home', 'https://example.com/' ), array( 'Post', 'https://example.com/post/' ) );
		$hidden = array(
			array( 'Home', 'https://example.com/' ),
			array(
				'Page 2',
				'',
				'hide_in_schema' => true,
			),
		);
		$blank  = array( array( 'Home', 'https://example.com/' ), array( '', '' ) );

		$this->assertSame( $linked, Trail::link_last( $linked, 'https://example.com/other/' ) );
		$this->assertSame( $hidden, Trail::link_last( $hidden, 'https://example.com/blog/' ) );
		$this->assertSame( $blank, Trail::link_last( $blank, 'https://example.com/blog/' ) );
		$this->assertSame( array(), Trail::link_last( array(), 'https://example.com/' ) );
		$this->assertSame( $blank, Trail::link_last( $blank, '' ) );
	}
}
