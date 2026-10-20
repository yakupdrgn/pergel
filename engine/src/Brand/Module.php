<?php
/**
 * The brand module: the pergel/brand Block Bindings source.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Brand;

use Pergel\Core;
use Pergel\Settings\Settings;

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
		add_filter( 'render_block_data', array( self::class, 'logo_without_title' ) );
	}

	/**
	 * Leaves out the site title that stands next to the Site Logo, as in the
	 * header patterns, when the logo already shows the name. Hooked on
	 * render_block_data; a site title elsewhere (the footer) stays.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return array<string, mixed>
	 */
	public static function logo_without_title( array $block ): array {
		if ( empty( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
			return $block;
		}
		$names = array_column( $block['innerBlocks'], 'blockName' );
		$title = array_search( 'core/site-title', $names, true );
		if ( false === $title || ! in_array( 'core/site-logo', $names, true ) || true !== Settings::get( 'brand.logo_has_name' ) || ! has_custom_logo() ) {
			return $block;
		}
		// innerContent holds a null where each inner block goes, in the same order.
		$seen = -1;
		foreach ( (array) ( $block['innerContent'] ?? array() ) as $i => $chunk ) {
			if ( null === $chunk && ++$seen === $title ) {
				unset( $block['innerContent'][ $i ] );
				$block['innerContent'] = array_values( $block['innerContent'] );
				break;
			}
		}
		array_splice( $block['innerBlocks'], (int) $title, 1 );
		return $block;
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
