<?php
/**
 * The blocks module on a site.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Blocks\Header;
use Pergel\Blocks\Module;
use Pergel\Core\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * Every block in the manifest is registered, server-rendered and in the
 * Pergel inserter category. The header part takes the header options.
 */
final class BlocksTest extends TestCase {

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
			$this->assertNotNull( $type, "pergel/{$slug} is not registered." );
			$this->assertIsCallable( $type->render_callback, "pergel/{$slug} has no render callback." );
			$this->assertSame( $metadata['category'], $type->category );
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
}
