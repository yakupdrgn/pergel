<?php
/**
 * Header options on the header template part.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Blocks;

use const Pergel\FILE;
use const Pergel\VERSION;

/**
 * Adds two options to the Template Part block that shows the header: a
 * sticky mode ("always", or "smart": hides while scrolling down) and an
 * overlay mode (the header sits over the page's first section, with no
 * background until the page scrolls). The options are block attributes, so
 * each template can set its own; the front-page template is the usual home
 * of the overlay. CSS and a small script load only where an option is on.
 */
final class Header {

	public const STICKY = array( 'always', 'smart' );

	public const STYLE = 'pergel-header';

	public const SCRIPT = 'pergel/header';

	/**
	 * Hooks the options in.
	 */
	public static function register(): void {
		add_filter( 'register_block_type_args', array( self::class, 'attributes' ), 10, 2 );
		add_filter( 'render_block_core/template-part', array( self::class, 'render' ), 10, 2 );
		add_action( 'init', array( self::class, 'register_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor' ) );
	}

	/**
	 * Adds the option attributes to the Template Part block.
	 *
	 * @param mixed $args Block type arguments.
	 * @param mixed $name Block name.
	 * @return mixed
	 */
	public static function attributes( mixed $args, mixed $name ): mixed {
		if ( 'core/template-part' !== $name || ! is_array( $args ) ) {
			return $args;
		}
		$attributes                  = is_array( $args['attributes'] ?? null ) ? $args['attributes'] : array();
		$attributes['pergelSticky']  = array(
			'type'    => 'string',
			'enum'    => array_merge( array( '' ), self::STICKY ),
			'default' => '',
		);
		$attributes['pergelOverlay'] = array(
			'type'    => 'boolean',
			'default' => false,
		);
		$args['attributes']          = $attributes;
		return $args;
	}

	/**
	 * Wrapper classes for a block's attributes.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return list<string>
	 */
	public static function classes( array $attrs ): array {
		$classes = array();
		$sticky  = $attrs['pergelSticky'] ?? '';
		if ( in_array( $sticky, self::STICKY, true ) ) {
			$classes[] = 'always' === $sticky ? 'is-sticky' : 'is-smart';
		}
		if ( true === ( $attrs['pergelOverlay'] ?? false ) ) {
			$classes[] = 'is-overlay';
		}
		if ( array() !== $classes ) {
			array_unshift( $classes, 'pergel-header' );
		}
		return $classes;
	}

	/**
	 * Adds the option classes to the part's wrapper and loads the assets.
	 *
	 * @param mixed $content Rendered part.
	 * @param mixed $block   Parsed block.
	 * @return mixed
	 */
	public static function render( mixed $content, mixed $block ): mixed {
		if ( ! is_string( $content ) || ! is_array( $block ) || ! is_array( $block['attrs'] ?? null ) ) {
			return $content;
		}
		$classes = self::classes( $block['attrs'] );
		if ( array() === $classes ) {
			return $content;
		}
		$tags = new \WP_HTML_Tag_Processor( $content );
		if ( ! $tags->next_tag() ) {
			return $content;
		}
		foreach ( $classes as $class ) {
			$tags->add_class( $class );
		}
		wp_enqueue_style( self::STYLE );
		wp_enqueue_script_module( self::SCRIPT );
		return $tags->get_updated_html();
	}

	/**
	 * Registers the front-end CSS (core inlines it, it is small) and the
	 * script module. Hooked on init.
	 */
	public static function register_assets(): void {
		wp_register_style( self::STYLE, plugins_url( 'assets/header.css', FILE ), array(), VERSION );
		wp_style_add_data( self::STYLE, 'path', dirname( FILE ) . '/assets/header.css' );
		wp_register_script_module( self::SCRIPT, plugins_url( 'assets/header.js', FILE ), array(), VERSION, array( 'fetchpriority' => 'low' ) );
	}

	/**
	 * The options panel in the editors. No build step.
	 */
	public static function enqueue_editor(): void {
		wp_enqueue_script(
			'pergel-header-controls',
			plugins_url( 'assets/header-controls.js', FILE ),
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-compose', 'wp-core-data', 'wp-data', 'wp-element', 'wp-hooks', 'wp-i18n' ),
			VERSION,
			array( 'in_footer' => true )
		);
		wp_set_script_translations( 'pergel-header-controls', 'pergel-engine', dirname( FILE ) . '/languages' );
	}
}
