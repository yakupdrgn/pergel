<?php
/**
 * The blocks module.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Blocks;

use Pergel\Core;

use const Pergel\FILE;
use const Pergel\VERSION;

/**
 * Registers the engine's server-rendered blocks from engine/blocks. Each
 * block brings its own CSS, which core prints only on pages that show the
 * block. Blocks that hold core blocks have a small editor script built on
 * the shared pergel-blocks-editor script (no build step). Has no switch:
 * patterns are built from these blocks.
 */
final class Module implements Core\Module {

	public const CATEGORY = 'pergel';

	/**
	 * {@inheritDoc}
	 */
	public static function id(): string {
		return 'blocks';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function should_load(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'init', array( self::class, 'register_editor_script' ), 5 );
		add_action( 'init', array( self::class, 'register_blocks' ) );
		add_filter( 'block_categories_all', array( self::class, 'category' ) );
		Header::register();
		Styles::register();
		Toc::register();
	}

	/**
	 * The blocks directory.
	 */
	public static function dir(): string {
		return dirname( FILE ) . '/blocks';
	}

	/**
	 * Registers the editor helper that the blocks' index.js files depend on.
	 * Hooked on init, before the blocks.
	 */
	public static function register_editor_script(): void {
		$src = plugins_url( 'assets/blocks-editor.js', FILE );
		if ( '' === $src ) {
			return;
		}
		wp_register_script(
			'pergel-blocks-editor',
			$src,
			array( 'wp-blocks', 'wp-block-editor', 'wp-element' ),
			VERSION,
			array( 'in_footer' => true )
		);
	}

	/**
	 * Registers every block listed in the manifest. Hooked on init.
	 */
	public static function register_blocks(): void {
		$dir = self::dir();
		wp_register_block_types_from_metadata_collection( $dir, $dir . '/' . Manifest::FILE );
	}

	/**
	 * Adds the Pergel category to the block inserter, after core's.
	 *
	 * @param mixed $categories Categories from core and other plugins.
	 * @return mixed
	 */
	public static function category( mixed $categories ): mixed {
		if ( ! is_array( $categories ) || in_array( self::CATEGORY, array_column( $categories, 'slug' ), true ) ) {
			return $categories;
		}
		$categories[] = array(
			'slug'  => self::CATEGORY,
			'title' => __( 'Pergel', 'pergel-engine' ),
			'icon'  => null,
		);
		return $categories;
	}
}
