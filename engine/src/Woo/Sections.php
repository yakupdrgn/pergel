<?php
/**
 * Products with sections open in the block editor.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * WooCommerce edits products in the classic editor, which shows a
 * description made of blocks as markup and can break it. A product on the
 * "Product with sections" template, or whose description already holds
 * blocks, opens in the block editor instead; the product data stays below
 * it as a meta box. Other products keep the classic editor.
 */
final class Sections {

	public const TEMPLATE = 'single-product-sections';

	/**
	 * Hooks the editor choice in.
	 */
	public static function register(): void {
		add_filter( 'use_block_editor_for_post', array( self::class, 'block_editor' ), 10, 2 );
	}

	/**
	 * Filter for use_block_editor_for_post.
	 *
	 * @param mixed $enabled Whether the block editor is used.
	 * @param mixed $post    The post being edited.
	 * @return mixed
	 */
	public static function block_editor( mixed $enabled, mixed $post ): mixed {
		if ( ! $post instanceof \WP_Post || 'product' !== $post->post_type ) {
			return $enabled;
		}
		return self::TEMPLATE === get_page_template_slug( $post ) || has_blocks( $post->post_content ) ? true : $enabled;
	}
}
