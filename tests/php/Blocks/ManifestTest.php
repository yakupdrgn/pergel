<?php
/**
 * Block manifest tests.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Blocks;

use Pergel\Blocks\Manifest;
use PHPUnit\Framework\TestCase;

/**
 * Rules for engine/blocks and the generated manifest.
 */
final class ManifestTest extends TestCase {

	private const FIXTURES = __DIR__ . '/fixtures';

	public function test_builds_blocks_in_folder_order_without_schema_key(): void {
		$blocks = Manifest::build( self::FIXTURES . '/good' );

		$this->assertSame( array( 'card', 'plain-box' ), array_keys( $blocks ) );
		$this->assertSame( 'pergel/card', $blocks['card']['name'] );
		$this->assertArrayNotHasKey( '$schema', $blocks['card'] );
	}

	public function test_lists_every_problem(): void {
		try {
			Manifest::build( self::FIXTURES . '/bad' );
			$this->fail( 'Bad blocks were accepted.' );
		} catch ( \RuntimeException $e ) {
			$message = $e->getMessage();
		}

		$this->assertStringContainsString( 'Bad_Name: folder name must be lowercase', $message );
		$this->assertStringContainsString( 'empty: block.json is missing or not a JSON object', $message );
		foreach ( array( 'name must be "pergel/wrong"', 'apiVersion must be 3', 'textdomain must be', 'title is missing', 'category is missing', 'render must be', 'a block without editorScript needs supports.autoRegister', 'style file file:./missing.css does not exist' ) as $problem ) {
			$this->assertStringContainsString( "wrong: {$problem}", $message );
		}
	}

	public function test_export_returns_the_blocks(): void {
		$blocks = Manifest::build( self::FIXTURES . '/good' );
		$file   = tempnam( sys_get_temp_dir(), 'pergel' );
		file_put_contents( $file, Manifest::export( $blocks ) );

		$this->assertSame( $blocks, require $file );
		unlink( $file );
	}

	public function test_shipped_manifest_is_up_to_date(): void {
		$this->assertFalse( Manifest::sync( dirname( __DIR__, 3 ) . '/engine/blocks', true ), 'Run php tools/blocks.php.' );
	}
}
