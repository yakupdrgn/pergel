<?php
/**
 * Scroll animation on blocks.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Blocks;

use const Pergel\FILE;
use const Pergel\VERSION;

/**
 * Adds a "Scroll animation" option to section and content blocks: the block
 * fades, rises, zooms or slides in as it scrolls into view. It is CSS only
 * (a scroll-driven animation on a view() timeline), so it needs no script
 * and costs nothing while scrolling. Browsers without scroll-driven
 * animations, and visitors who ask for reduced motion, see the block as it
 * is. A block is fully shown once it is fully in view, so the last blocks
 * of a page never stay faded. The CSS loads only on pages that use it.
 */
final class Animation {

	public const ATTRIBUTE = 'pergelAnimation';

	public const EFFECTS = array( 'fade', 'rise', 'zoom', 'slide' );

	public const STYLE = 'pergel-animation';

	public const SCRIPT = 'pergel-animation-controls';

	/**
	 * Blocks that take the option. Inner parts that live in a scroller of
	 * their own (carousel slides) or in a menu are left out.
	 */
	public const BLOCKS = array(
		'core/buttons',
		'core/column',
		'core/columns',
		'core/cover',
		'core/details',
		'core/gallery',
		'core/group',
		'core/heading',
		'core/image',
		'core/list',
		'core/media-text',
		'core/paragraph',
		'core/pullquote',
		'core/quote',
		'core/table',
		'core/video',
		'pergel/carousel',
		'pergel/counter',
		'pergel/form',
		'pergel/logo-marquee',
		'pergel/map',
		'pergel/pricing-plan',
		'pergel/pricing-table',
		'pergel/timeline',
		'pergel/timeline-item',
	);

	/**
	 * Hooks the option in.
	 */
	public static function register(): void {
		add_filter( 'register_block_type_args', array( self::class, 'attributes' ), 10, 2 );
		add_filter( 'render_block', array( self::class, 'render' ), 10, 2 );
		add_action( 'init', array( self::class, 'register_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor' ) );
	}

	/**
	 * Blocks that take the option.
	 *
	 * @return list<string>
	 */
	public static function blocks(): array {
		/**
		 * Filters the blocks that offer a scroll animation.
		 *
		 * @param string[] $blocks Block names.
		 */
		$blocks = apply_filters( 'pergel/animation/blocks', self::BLOCKS );
		return is_array( $blocks ) ? array_values( array_filter( $blocks, 'is_string' ) ) : self::BLOCKS;
	}

	/**
	 * Adds the attribute to the blocks that take it.
	 *
	 * @param mixed $args Block type arguments.
	 * @param mixed $name Block name.
	 * @return mixed
	 */
	public static function attributes( mixed $args, mixed $name ): mixed {
		if ( ! is_array( $args ) || ! is_string( $name ) || ! in_array( $name, self::blocks(), true ) ) {
			return $args;
		}
		$attributes                    = is_array( $args['attributes'] ?? null ) ? $args['attributes'] : array();
		$attributes[ self::ATTRIBUTE ] = array(
			'type'    => 'string',
			'enum'    => array_merge( array( '' ), self::EFFECTS ),
			'default' => '',
		);
		$args['attributes']            = $attributes;
		return $args;
	}

	/**
	 * Wrapper classes for a block's attributes.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return list<string>
	 */
	public static function classes( array $attrs ): array {
		$effect = $attrs[ self::ATTRIBUTE ] ?? '';
		if ( ! in_array( $effect, self::EFFECTS, true ) ) {
			return array();
		}
		return array( 'pergel-animate', 'pergel-animate--' . $effect );
	}

	/**
	 * Adds the animation classes to the block's wrapper and loads the CSS.
	 *
	 * @param mixed $content Rendered block.
	 * @param mixed $block   Parsed block.
	 * @return mixed
	 */
	public static function render( mixed $content, mixed $block ): mixed {
		if ( ! is_string( $content ) || '' === $content || ! is_array( $block ) || ! is_array( $block['attrs'] ?? null ) || empty( $block['attrs'][ self::ATTRIBUTE ] ) ) {
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
		return $tags->get_updated_html();
	}

	/**
	 * Registers the CSS (core inlines it, it is small) and the editor
	 * script. The editor helper of Pergel's blocks depends on the script, so
	 * its filter is in place before those blocks register. Hooked on init.
	 */
	public static function register_assets(): void {
		wp_register_style( self::STYLE, plugins_url( 'assets/animation.css', FILE ), array(), VERSION );
		wp_style_add_data( self::STYLE, 'path', dirname( FILE ) . '/assets/animation.css' );
		$src = plugins_url( 'assets/animation-controls.js', FILE );
		if ( '' === $src ) {
			return;
		}
		wp_register_script(
			self::SCRIPT,
			$src,
			array( 'wp-block-editor', 'wp-components', 'wp-compose', 'wp-element', 'wp-hooks', 'wp-i18n' ),
			VERSION,
			array( 'in_footer' => true )
		);
	}

	/**
	 * The option in the editors. No build step.
	 */
	public static function enqueue_editor(): void {
		wp_add_inline_script( self::SCRIPT, 'window.pergelAnimation = ' . wp_json_encode( array( 'blocks' => self::blocks() ) ) . ';', 'before' );
		wp_set_script_translations( self::SCRIPT, 'pergel-engine', dirname( FILE ) . '/languages' );
		wp_enqueue_script( self::SCRIPT );
	}
}
