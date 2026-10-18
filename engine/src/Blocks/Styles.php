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
 * Table styles on the core Table block. "Comparison": a sticky first
 * column, centred values and row lines. "Specifications": label and value
 * rows with lines, the labels muted, as a product's data sheet. A style's
 * CSS loads only on pages with such a table (and always in the editor
 * canvas).
 */
final class Styles {

	public const COMPARISON = 'pergel-comparison';

	public const STYLE = 'pergel-comparison-table';

	public const SPECS = 'pergel-specs';

	public const SPECS_STYLE = 'pergel-specs-table';

	/**
	 * Hooks the styles in.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_styles' ) );
		add_filter( 'render_block_core/table', array( self::class, 'render' ), 10, 2 );
		add_action( 'enqueue_block_assets', array( self::class, 'enqueue_editor' ) );
	}

	/**
	 * Style name => [ style handle, CSS file ].
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	private static function tables(): array {
		return array(
			self::COMPARISON => array( self::STYLE, 'comparison-table.css' ),
			self::SPECS      => array( self::SPECS_STYLE, 'specs-table.css' ),
		);
	}

	/**
	 * Registers the styles and their CSS (core inlines it, it is small).
	 * Hooked on init.
	 */
	public static function register_styles(): void {
		$labels = array(
			self::COMPARISON => __( 'Comparison', 'pergel-engine' ),
			self::SPECS      => __( 'Specifications', 'pergel-engine' ),
		);
		foreach ( self::tables() as $name => $table ) {
			wp_register_style( $table[0], plugins_url( 'assets/' . $table[1], FILE ), array(), VERSION );
			wp_style_add_data( $table[0], 'path', dirname( FILE ) . '/assets/' . $table[1] );
			register_block_style(
				'core/table',
				array(
					'name'  => $name,
					'label' => $labels[ $name ],
				)
			);
		}
	}

	/**
	 * Loads the CSS when a table uses a style. The table scrolls sideways
	 * on narrow screens, so its frame takes keyboard focus to scroll too.
	 *
	 * @param mixed $content Rendered table.
	 * @param mixed $block   Parsed block.
	 * @return mixed
	 */
	public static function render( mixed $content, mixed $block ): mixed {
		$class = is_array( $block ) && is_array( $block['attrs'] ?? null ) ? ( $block['attrs']['className'] ?? '' ) : '';
		if ( ! is_string( $class ) ) {
			return $content;
		}
		foreach ( self::tables() as $name => $table ) {
			if ( in_array( 'is-style-' . $name, explode( ' ', $class ), true ) ) {
				wp_enqueue_style( $table[0] );
				$tags = new \WP_HTML_Tag_Processor( is_string( $content ) ? $content : '' );
				if ( $tags->next_tag( array( 'tag_name' => 'figure' ) ) && null === $tags->get_attribute( 'tabindex' ) ) {
					$tags->set_attribute( 'tabindex', '0' );
					return $tags->get_updated_html();
				}
				break;
			}
		}
		return $content;
	}

	/**
	 * The CSS in the editor canvas. Hooked on enqueue_block_assets.
	 */
	public static function enqueue_editor(): void {
		if ( is_admin() ) {
			foreach ( self::tables() as $table ) {
				wp_enqueue_style( $table[0] );
			}
		}
	}
}
