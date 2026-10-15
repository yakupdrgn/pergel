<?php
/**
 * The brand module: the pergel/brand Block Bindings source.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Brand;

use Pergel\Core;

use const Pergel\FILE;
use const Pergel\VERSION;

/**
 * Lets paragraph, heading, button, image and other core blocks show the
 * brand details entered once in the settings.
 */
final class Module implements Core\Module {

	public const SOURCE = 'pergel/brand';

	/**
	 * Block attributes filled with rich text; their values are escaped here.
	 * Other attributes are escaped by core when it writes them.
	 */
	private const RICH_TEXT = array( 'content', 'text', 'caption' );

	/**
	 * {@inheritDoc}
	 */
	public static function id(): string {
		return 'brand';
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
		add_action( 'init', array( self::class, 'register_source' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor' ) );
	}

	/**
	 * Registers the binding source. Hooked on init.
	 */
	public static function register_source(): void {
		register_block_bindings_source(
			self::SOURCE,
			array(
				'label'              => __( 'Pergel Brand', 'pergel-engine' ),
				'get_value_callback' => array( self::class, 'bound_value' ),
			)
		);
	}

	/**
	 * Value of a bound attribute. Null keeps the block's own content.
	 *
	 * @param array<string, mixed> $args      Binding arguments: "key", and "network" for social.
	 * @param \WP_Block            $block     The block being rendered.
	 * @param string               $attribute Attribute name.
	 */
	public static function bound_value( array $args, \WP_Block $block, string $attribute ): string|int|null {
		unset( $block );
		$value = Brand::value( is_string( $args['key'] ?? null ) ? $args['key'] : '', $args, $attribute );
		if ( is_string( $value ) && in_array( $attribute, self::RICH_TEXT, true ) ) {
			return esc_html( $value );
		}
		return $value;
	}

	/**
	 * Shows the bound values in the block and site editors. No build step.
	 */
	public static function enqueue_editor(): void {
		wp_enqueue_script(
			'pergel-brand-bindings',
			plugins_url( 'assets/brand-bindings.js', FILE ),
			array( 'wp-blocks' ),
			VERSION,
			array( 'in_footer' => true )
		);
		wp_add_inline_script(
			'pergel-brand-bindings',
			'window.pergelBrand = ' . wp_json_encode( Brand::editor_data(), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ';',
			'before'
		);
	}
}
