<?php
/**
 * The content blocks on a site.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Blocks\Map;
use Pergel\Blocks\Styles;
use Pergel\Blocks\Toc;
use PHPUnit\Framework\TestCase;

/**
 * The counter, timeline, logo marquee, pricing, comparison table, table of
 * contents and map blocks print their markup from the saved inner blocks
 * and load their CSS only when they are on the page.
 */
final class ContentBlocksTest extends TestCase {

	/**
	 * Pages created by the test.
	 *
	 * @var list<int>
	 */
	private array $pages = array();

	public function test_counter_passes_a_clamped_duration(): void {
		$inner = '<!-- wp:paragraph --><p>120</p><!-- /wp:paragraph -->';

		$this->assertSame( '1500', self::first( '<!-- wp:pergel/counter -->' . $inner . '<!-- /wp:pergel/counter -->' )->get_attribute( 'data-duration' ) );
		$this->assertSame( '10000', self::first( '<!-- wp:pergel/counter {"duration":99999} -->' . $inner . '<!-- /wp:pergel/counter -->' )->get_attribute( 'data-duration' ) );
		$this->assertSame( '0', self::first( '<!-- wp:pergel/counter {"duration":-5} -->' . $inner . '<!-- /wp:pergel/counter -->' )->get_attribute( 'data-duration' ) );
		$this->assertStringContainsString( '>120</p></div>', do_blocks( '<!-- wp:pergel/counter -->' . $inner . '<!-- /wp:pergel/counter -->' ) );
	}

	public function test_timeline_is_an_ordered_list(): void {
		wp_dequeue_style( 'pergel-timeline-style' );
		$item = '<!-- wp:pergel/timeline-item --><!-- wp:paragraph --><p>2020</p><!-- /wp:paragraph --><!-- /wp:pergel/timeline-item -->';
		$html = do_blocks( '<!-- wp:pergel/timeline -->' . $item . $item . '<!-- /wp:pergel/timeline -->' );

		$this->assertStringStartsWith( '<ol class="wp-block-pergel-timeline">', $html );
		$this->assertSame( 2, substr_count( $html, '<li class="wp-block-pergel-timeline-item">' ) );
		$this->assertStringEndsWith( '</li></ol>', $html );
		$this->assertTrue( wp_style_is( 'pergel-timeline-style' ) );
	}

	public function test_pricing_plan_can_be_featured(): void {
		$inner = '<!-- wp:paragraph --><p>Basic</p><!-- /wp:paragraph -->';

		$this->assertFalse( self::first( '<!-- wp:pergel/pricing-plan -->' . $inner . '<!-- /wp:pergel/pricing-plan -->' )->has_class( 'is-featured' ) );
		$this->assertTrue( self::first( '<!-- wp:pergel/pricing-plan {"featured":true} -->' . $inner . '<!-- /wp:pergel/pricing-plan -->' )->has_class( 'is-featured' ) );
		$this->assertStringStartsWith( '<div class="wp-block-pergel-pricing-table">', do_blocks( '<!-- wp:pergel/pricing-table -->' . $inner . '<!-- /wp:pergel/pricing-table -->' ) );
	}

	public function test_marquee_repeats_the_logos_for_screen_readers_once(): void {
		$logo = '<!-- wp:image {"anchor":"acme"} --><figure class="wp-block-image" id="acme"><img src="https://example.com/acme.png" alt="Acme"/></figure><!-- /wp:image -->';
		$html = do_blocks( '<!-- wp:pergel/logo-marquee {"duration":999} -->' . $logo . '<!-- /wp:pergel/logo-marquee -->' );

		$this->assertSame( 2, substr_count( $html, 'alt="Acme"' ) );
		$this->assertSame( 1, substr_count( $html, 'id="acme"' ) );
		$this->assertSame( 1, substr_count( $html, 'aria-hidden="true" inert' ) );
		$this->assertStringContainsString( '--pergel-marquee-duration:120s', $html );
		$this->assertStringContainsString( 'aria-pressed="false"', $html );
		$this->assertSame( '', do_blocks( '<!-- wp:pergel/logo-marquee --><!-- /wp:pergel/logo-marquee -->' ) );
	}

	public function test_map_loads_nothing_from_google_until_opened(): void {
		$query = 'Kızılay "Ankara" <b>';
		$html  = do_blocks( '<!-- wp:pergel/map ' . wp_json_encode( array( 'query' => $query, 'zoom' => 50 ) ) . ' /-->' );
		$tags  = new \WP_HTML_Tag_Processor( $html );

		$this->assertTrue( $tags->next_tag() );
		$this->assertSame( Map::embed_url( $query, 21 ), $tags->get_attribute( 'data-src' ) );
		$this->assertSame( 'Map: ' . $query, $tags->get_attribute( 'data-title' ) );
		$this->assertStringStartsWith( 'https://www.google.com/maps?q=K%C4%B1z%C4%B1lay%20%22Ankara%22%20%3Cb%3E&z=21&output=embed', Map::embed_url( $query, 21 ) );
		$this->assertTrue( $tags->next_tag( array( 'tag_name' => 'a' ) ) );
		$this->assertSame( Map::link_url( $query ), $tags->get_attribute( 'href' ) );
		$this->assertStringNotContainsString( '<iframe', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringContainsString( 'wp-block-pergel-map__placeholder', $html );
		$this->assertSame( '', do_blocks( '<!-- wp:pergel/map {"query":"  "} /-->' ) );
	}

	public function test_comparison_style_loads_its_css_only_when_used(): void {
		$table = '<figure class="wp-block-table"><table><tbody><tr><td>A</td></tr></tbody></table></figure>';
		wp_dequeue_style( Styles::STYLE );
		do_blocks( '<!-- wp:table -->' . $table . '<!-- /wp:table -->' );

		$this->assertFalse( wp_style_is( Styles::STYLE ) );
		$this->assertArrayHasKey( Styles::COMPARISON, \WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block( 'core/table' ) );

		$this->assertStringNotContainsString( 'tabindex', do_blocks( '<!-- wp:table -->' . $table . '<!-- /wp:table -->' ) );
		$html = do_blocks( '<!-- wp:table {"className":"is-style-' . Styles::COMPARISON . '"} --><figure class="wp-block-table is-style-' . Styles::COMPARISON . '"><table><tbody><tr><td>A</td></tr></tbody></table></figure><!-- /wp:table -->' );

		$this->assertStringStartsWith( '<figure tabindex="0" class="wp-block-table', $html );
		$this->assertTrue( wp_style_is( Styles::STYLE ) );
		$this->assertNotEmpty( wp_styles()->get_data( Styles::STYLE, 'path' ) );
		wp_dequeue_style( Styles::STYLE );
	}

	public function test_toc_lists_the_headings_and_they_get_matching_ids(): void {
		$id = $this->page(
			self::heading( 2, 'Intro' )
			. self::heading( 3, 'Details' )
			. self::heading( 3, 'Details' )
			. '<!-- wp:heading {"anchor":"own"} --><h2 class="wp-block-heading" id="own">Plans</h2><!-- /wp:heading -->'
			. '<!-- wp:group --><div class="wp-block-group">' . self::heading( 4, 'Deep &amp; down' ) . '</div><!-- /wp:group -->'
			. self::heading( 2, 'Intro' )
		);
		self::go( $id );

		$toc = do_blocks( '<!-- wp:pergel/table-of-contents /-->' );

		$this->assertStringStartsWith( '<nav class="wp-block-pergel-table-of-contents" aria-labelledby="pergel-toc-', $toc );
		$this->assertStringContainsString(
			'<ol><li><a href="#intro">Intro</a><ol><li><a href="#details">Details</a></li><li><a href="#details-2">Details</a></li></ol></li><li><a href="#own">Plans</a></li><li><a href="#intro-2">Intro</a></li></ol>',
			$toc
		);
		$this->assertStringContainsString( '<a href="#deep-down">Deep &amp; down</a>', do_blocks( '<!-- wp:pergel/table-of-contents {"maxLevel":4} /-->' ) );

		// A first pass, like the excerpt for the meta description, must not use the ids up.
		do_blocks( (string) get_post_field( 'post_content', $id ) );
		$content = (string) apply_filters( 'the_content', get_post_field( 'post_content', $id ) );
		foreach ( array( 'intro', 'details', 'details-2', 'own', 'deep-down', 'intro-2' ) as $anchor ) {
			$this->assertSame( 1, substr_count( $content, 'id="' . $anchor . '"' ), "Heading id {$anchor}." );
		}
	}

	public function test_toc_prints_nothing_for_fewer_than_two_headings(): void {
		self::go( $this->page( self::heading( 2, 'Only one' ) ) );

		$this->assertSame( '', do_blocks( '<!-- wp:pergel/table-of-contents /-->' ) );
	}

	public function test_headings_outside_the_page_get_no_id(): void {
		$id = $this->page( self::heading( 2, 'Intro' ) . self::heading( 2, 'Next' ) );
		self::go( array( 's' => 'pergel' ) );
		$GLOBALS['post'] = get_post( $id );

		$this->assertStringNotContainsString( 'id=', do_blocks( self::heading( 2, 'Intro' ) ) );
	}

	public function test_nest_closes_every_list(): void {
		$items = array(
			array(
				'level' => 3,
				'text'  => 'A',
				'id'    => 'a',
			),
			array(
				'level' => 2,
				'text'  => 'B',
				'id'    => 'b',
			),
			array(
				'level' => 4,
				'text'  => 'C <x>',
				'id'    => 'c',
			),
		);

		$this->assertSame( '<ol><li><a href="#a">A</a></li><li><a href="#b">B</a><ol><li><a href="#c">C &lt;x&gt;</a></li></ol></li></ol>', Toc::nest( $items ) );
	}

	protected function tearDown(): void {
		foreach ( $this->pages as $id ) {
			wp_delete_post( $id, true );
		}
		$GLOBALS['wp_query']     = new \WP_Query();
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
		unset( $GLOBALS['post'] );
		Toc::flush();
	}

	/**
	 * The first tag of a block's markup.
	 *
	 * @param string $markup Serialized block.
	 */
	private static function first( string $markup ): \WP_HTML_Tag_Processor {
		$tags = new \WP_HTML_Tag_Processor( do_blocks( $markup ) );
		self::assertTrue( $tags->next_tag() );
		return $tags;
	}

	/**
	 * A serialized heading block.
	 *
	 * @param int    $level Heading level.
	 * @param string $text  Heading text (HTML).
	 */
	private static function heading( int $level, string $text ): string {
		$attrs = 2 === $level ? '' : ' {"level":' . $level . '}';
		return "<!-- wp:heading{$attrs} --><h{$level} class=\"wp-block-heading\">{$text}</h{$level}><!-- /wp:heading -->";
	}

	/**
	 * A published page.
	 *
	 * @param string $content Page content.
	 */
	private function page( string $content ): int {
		$id            = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Pergel content blocks',
				'post_content' => wp_slash( $content ),
				'post_author'  => 1,
			)
		);
		$this->pages[] = $id;
		return $id;
	}

	/**
	 * Runs a main query, as WordPress does for a front-end request, and
	 * sets up the post the loop is on.
	 *
	 * @param int|array<string, mixed> $vars Page id or query vars.
	 */
	private static function go( int|array $vars ): void {
		$GLOBALS['wp_query']     = new \WP_Query( is_int( $vars ) ? array( 'page_id' => $vars ) : $vars );
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
		if ( is_int( $vars ) ) {
			$GLOBALS['post'] = get_post( $vars );
		}
	}
}
