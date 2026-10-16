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
 * patterns are built from these blocks. A page view registers the blocks
 * without their editor script and style, so it reads none of their
 * index.asset.php files.
 */
final class Module implements Core\Module {

	public const CATEGORY = 'pergel';

	/**
	 * Blocks that need WooCommerce; without it they are not registered.
	 */
	public const SHOP_BLOCKS = array( 'pergel/product-actions', 'pergel/product-cta', 'pergel/shop-links', 'pergel/shop-list' );

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
		Animation::register();
		Header::register();
		MegaMenu::register();
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
			array( 'wp-blocks', 'wp-block-editor', 'wp-element', Animation::SCRIPT ),
			VERSION,
			array( 'in_footer' => true )
		);
	}

	/**
	 * Registers every block listed in the manifest. Hooked on init.
	 */
	public static function register_blocks(): void {
		$dir  = self::dir();
		$lean = ! self::editor_request();
		if ( $lean ) {
			add_filter( 'block_type_metadata', array( self::class, 'without_editor_assets' ) );
		}
		wp_register_block_types_from_metadata_collection( $dir, $dir . '/' . Manifest::FILE );
		if ( $lean ) {
			remove_filter( 'block_type_metadata', array( self::class, 'without_editor_assets' ) );
		}
		if ( ! \Pergel\Woo\Module::active() ) {
			foreach ( self::SHOP_BLOCKS as $name ) {
				unregister_block_type( $name );
			}
		}
	}

	/**
	 * Drops the editor script and style of a Pergel block.
	 *
	 * @param mixed $metadata Block metadata.
	 * @return mixed
	 */
	public static function without_editor_assets( mixed $metadata ): mixed {
		if ( is_array( $metadata ) && str_starts_with( (string) ( $metadata['name'] ?? '' ), 'pergel/' ) ) {
			unset( $metadata['editorScript'], $metadata['editorStyle'] );
		}
		return $metadata;
	}

	/**
	 * Whether this request may show a block editor or describe the blocks to
	 * one: wp-admin (with admin-ajax and admin-post), the REST API, WP-CLI.
	 */
	public static function editor_request(): bool {
		if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return true;
		}
		if ( isset( $_GET['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only routes the request.
			return true;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		return str_contains( $path . '/', '/' . rest_get_url_prefix() . '/' );
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
