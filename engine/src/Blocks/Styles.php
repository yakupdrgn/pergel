<?php
/**
 * Engine block styles on core blocks.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Blocks;

use const Pergel\FILE;
use const Pergel\VERSION;

/**
 * The comparison table is the core Table block with the "Comparison" style:
 * a sticky first column, centred values and row lines. Its CSS loads only
 * on pages with such a table (and always in the editor canvas).
 */
final class Styles {

	public const COMPARISON = 'pergel-comparison';

	public const STYLE = 'pergel-comparison-table';

	/**
	 * Hooks the styles in.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_styles' ) );
		add_filter( 'render_block_core/table', array( self::class, 'render' ), 10, 2 );
		add_action( 'enqueue_block_assets', array( self::class, 'enqueue_editor' ) );
	}

	/**
	 * Registers the style and its CSS (core inlines it, it is small).
	 * Hooked on init.
	 */
	public static function register_styles(): void {
		wp_register_style( self::STYLE, plugins_url( 'assets/comparison-table.css', FILE ), array(), VERSION );
		wp_style_add_data( self::STYLE, 'path', dirname( FILE ) . '/assets/comparison-table.css' );
		register_block_style(
			'core/table',
			array(
				'name'  => self::COMPARISON,
				'label' => __( 'Comparison', 'pergel-engine' ),
			)
		);
	}

	/**
	 * Loads the CSS when a table uses the style. The table scrolls sideways
	 * on narrow screens, so its frame takes keyboard focus to scroll too.
	 *
	 * @param mixed $content Rendered table.
	 * @param mixed $block   Parsed block.
	 * @return mixed
	 */
	public static function render( mixed $content, mixed $block ): mixed {
		$class = is_array( $block ) && is_array( $block['attrs'] ?? null ) ? ( $block['attrs']['className'] ?? '' ) : '';
		if ( is_string( $class ) && in_array( 'is-style-' . self::COMPARISON, explode( ' ', $class ), true ) ) {
			wp_enqueue_style( self::STYLE );
			$tags = new \WP_HTML_Tag_Processor( is_string( $content ) ? $content : '' );
			if ( $tags->next_tag( array( 'tag_name' => 'figure' ) ) && null === $tags->get_attribute( 'tabindex' ) ) {
				$tags->set_attribute( 'tabindex', '0' );
				return $tags->get_updated_html();
			}
		}
		return $content;
	}

	/**
	 * The CSS in the editor canvas. Hooked on enqueue_block_assets.
	 */
	public static function enqueue_editor(): void {
		if ( is_admin() ) {
			wp_enqueue_style( self::STYLE );
		}
	}
}
