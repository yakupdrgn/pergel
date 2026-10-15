<?php
/**
 * The blocks module on a site.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Blocks\Breadcrumb;
use Pergel\Blocks\Header;
use Pergel\Blocks\Module;
use Pergel\Core\Plugin;
use Pergel\Seo\Detect;
use Pergel\Seo\Head;
use PHPUnit\Framework\TestCase;

/**
 * Every block in the manifest is registered, server-rendered and in the
 * Pergel inserter category. The header part takes the header options, and
 * the breadcrumb prints the page's trail.
 */
final class BlocksTest extends TestCase {

	/**
	 * Pages created by the test.
	 *
	 * @var list<int>
	 */
	private array $pages = array();

	public function test_module_always_loads(): void {
		$this->assertContains( 'blocks', Plugin::loaded() );
	}

	public function test_inserter_has_the_pergel_category_once(): void {
		$slugs = array_column( get_block_categories( new \WP_Block_Editor_Context() ), 'slug' );

		$this->assertSame( 1, count( array_keys( $slugs, Module::CATEGORY, true ) ) );
	}

	public function test_every_manifest_block_is_registered(): void {
		$blocks   = require Module::dir() . '/manifest.php';
		$registry = \WP_Block_Type_Registry::get_instance();

		$this->assertIsArray( $blocks );
		foreach ( $blocks as $slug => $metadata ) {
			$type = $registry->get_registered( "pergel/{$slug}" );
			if ( in_array( "pergel/{$slug}", Module::SHOP_BLOCKS, true ) && ! \Pergel\Woo\Module::active() ) {
				$this->assertNull( $type, "pergel/{$slug} needs WooCommerce." );
				continue;
			}
			$this->assertNotNull( $type, "pergel/{$slug} is not registered." );
			$this->assertIsCallable( $type->render_callback, "pergel/{$slug} has no render callback." );
			$this->assertSame( $metadata['category'], $type->category );
		}
	}

	public function test_a_page_view_registers_the_blocks_without_editor_assets(): void {
		$carousel = \WP_Block_Type_Registry::get_instance()->get_registered( 'pergel/carousel' );

		$this->assertFalse( Module::editor_request() );
		$this->assertNotNull( $carousel );
		$this->assertSame( array(), $carousel->editor_script_handles );
		$this->assertNotSame( array(), $carousel->view_script_module_ids );
		$this->assertNotSame( array(), $carousel->style_handles );
	}

	public function test_editor_requests_keep_the_editor_assets(): void {
		$block = array(
			'name'         => 'pergel/box',
			'editorScript' => 'file:./index.js',
			'editorStyle'  => 'file:./editor.css',
			'style'        => 'file:./style.css',
		);
		$core  = array(
			'name'         => 'core/paragraph',
			'editorScript' => 'file:./index.js',
		);
		$this->assertSame(
			array(
				'name'  => 'pergel/box',
				'style' => 'file:./style.css',
			),
			Module::without_editor_assets( $block )
		);
		$this->assertSame( $core, Module::without_editor_assets( $core ) );

		$uri = $_SERVER['REQUEST_URI'] ?? null;
		try {
			$_SERVER['REQUEST_URI'] = '/blog/wp-json/wp/v2/block-types';
			$this->assertTrue( Module::editor_request() );
			$_SERVER['REQUEST_URI'] = '/blog/wp-jsonish/';
			$this->assertFalse( Module::editor_request() );
			$_GET['rest_route'] = '/wp/v2/block-types';
			$this->assertTrue( Module::editor_request() );
		} finally {
			unset( $_GET['rest_route'] );
			if ( null === $uri ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $uri;
			}
		}
	}

	public function test_template_part_block_has_the_header_attributes(): void {
		$type = \WP_Block_Type_Registry::get_instance()->get_registered( 'core/template-part' );

		$this->assertNotNull( $type );
		$this->assertArrayHasKey( 'pergelSticky', $type->attributes );
		$this->assertArrayHasKey( 'pergelOverlay', $type->attributes );
	}

	public function test_header_options_add_classes_and_load_the_css(): void {
		wp_dequeue_style( Header::STYLE );
		$plain = do_blocks( '<!-- wp:template-part {"slug":"header","tagName":"div"} /-->' );

		$this->assertStringNotContainsString( 'pergel-header', $plain );
		$this->assertFalse( wp_style_is( Header::STYLE ) );

		$html = do_blocks( '<!-- wp:template-part {"slug":"header","tagName":"div","pergelSticky":"smart","pergelOverlay":true} /-->' );
		$tags = new \WP_HTML_Tag_Processor( $html );

		$this->assertTrue( $tags->next_tag() );
		foreach ( array( 'wp-block-template-part', 'pergel-header', 'is-smart', 'is-overlay' ) as $class ) {
			$this->assertTrue( $tags->has_class( $class ), "Missing class {$class}." );
		}
		$this->assertTrue( wp_style_is( Header::STYLE ) );
		$this->assertNotEmpty( wp_styles()->get_data( Header::STYLE, 'path' ) );
		wp_dequeue_style( Header::STYLE );
	}

	public function test_breadcrumb_follows_the_trail_of_the_page(): void {
		$this->skip_with_seo_plugin();
		$parent = $this->page( 'Pergel breadcrumb parent' );
		$child  = $this->page( 'Pergel breadcrumb & child', $parent );
		self::go( array( 'page_id' => $child ) );

		$html = do_blocks( '<!-- wp:pergel/breadcrumb /-->' );

		$this->assertStringStartsWith( '<nav ', $html );
		$this->assertStringContainsString( 'aria-label="Breadcrumb"', $html );
		$this->assertSame( 3, substr_count( $html, '<li>' ) );
		$this->assertStringContainsString( '<a href="' . esc_url( home_url( '/' ) ) . '">', $html );
		$this->assertStringContainsString( '<a href="' . esc_url( (string) get_permalink( $parent ) ) . '">Pergel breadcrumb parent</a>', $html );
		$this->assertStringContainsString( '<span aria-current="page">Pergel breadcrumb &amp; child</span>', $html );
		$this->assertTrue( wp_style_is( 'pergel-breadcrumb-style' ) );
	}

	public function test_breadcrumb_prints_nothing_without_a_trail(): void {
		$this->skip_with_seo_plugin();
		self::go( array( 's' => 'pergel' ) );

		$this->assertSame( '', do_blocks( '<!-- wp:pergel/breadcrumb /-->' ) );
	}

	public function test_breadcrumb_adds_the_list_when_the_plugin_has_none(): void {
		$this->skip_with_seo_plugin();
		$id = $this->page( 'Pergel breadcrumb schema' );
		self::go( array( 'page_id' => $id ) );
		add_filter( 'pergel/seo/plugin', static fn (): string => 'seopress' );

		do_blocks( '<!-- wp:pergel/breadcrumb /-->' );
		do_blocks( '<!-- wp:pergel/breadcrumb /-->' );
		$out = self::printed();

		$this->assertSame( 1, preg_match_all( '#<script type="application/ld\+json" class="pergel-breadcrumb-schema">(.+?)</script>#', $out, $match ) );
		$data = json_decode( $match[1][0], true );
		$this->assertIsArray( $data );
		$this->assertSame( 'BreadcrumbList', $data['@type'] );
		$this->assertSame( (string) get_permalink( $id ) . '#breadcrumb', $data['@id'] );
		$this->assertSame( array( 'Home', 'Pergel breadcrumb schema' ), array_column( $data['itemListElement'], 'name' ) );
	}

	public function test_breadcrumb_adds_no_list_next_to_pergels_graph(): void {
		$this->skip_with_seo_plugin();
		$id = $this->page( 'Pergel breadcrumb graph' );
		self::go( array( 'page_id' => $id ) );

		do_blocks( '<!-- wp:pergel/breadcrumb /-->' );

		$this->assertStringNotContainsString( 'pergel-breadcrumb-schema', self::printed() );
	}

	protected function tearDown(): void {
		foreach ( $this->pages as $id ) {
			wp_delete_post( $id, true );
		}
		remove_all_filters( 'pergel/seo/plugin' );
		$GLOBALS['wp_query']     = new \WP_Query();
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
		Head::flush();
		Breadcrumb::flush();
	}

	/**
	 * Skips a test that needs Pergel's own trail.
	 */
	private function skip_with_seo_plugin(): void {
		if ( '' !== Detect::active() ) {
			$this->markTestSkipped( 'An SEO plugin is active on this site.' );
		}
	}

	/**
	 * A published page.
	 *
	 * @param string $title  Page title.
	 * @param int    $parent Parent page.
	 */
	private function page( string $title, int $parent = 0 ): int {
		$id            = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_parent' => $parent,
				'post_author' => 1,
			)
		);
		$this->pages[] = $id;
		return $id;
	}

	/**
	 * Runs a main query, as WordPress does for a front-end request.
	 *
	 * @param array<string, mixed> $vars Query vars.
	 */
	private static function go( array $vars ): void {
		$GLOBALS['wp_query']     = new \WP_Query( $vars );
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
		Head::flush();
	}

	/**
	 * The BreadcrumbList the block queued, if it is hooked to print.
	 */
	private static function printed(): string {
		$hook = did_action( 'wp_head' ) ? 'wp_footer' : 'wp_head';
		if ( false === has_action( $hook, array( Breadcrumb::class, 'print_schema' ) ) ) {
			return '';
		}
		ob_start();
		Breadcrumb::print_schema();
		return (string) ob_get_clean();
	}
}
