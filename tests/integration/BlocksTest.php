<?php
/**
 * The blocks module on a site.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Blocks\Module;
use Pergel\Core\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * Every block in the manifest is registered, server-rendered and in the
 * Pergel inserter category.
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
}
