<?php
/**
 * Markup of the engine blocks that wrap their inner blocks.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Blocks;

/**
 * The counter, timeline and pricing blocks keep their content in core
 * blocks; the server only puts the block's element around it.
 */
final class Render {

	/**
	 * Default count duration of the counter, in milliseconds.
	 */
	public const COUNTER_DURATION = 1500;

	/**
	 * The inner blocks in the block's element.
	 *
	 * @param string               $tag     Element name.
	 * @param string               $content Rendered inner blocks.
	 * @param array<string, mixed> $extra   Extra wrapper attributes.
	 */
	public static function wrap( string $tag, string $content, array $extra = array() ): string {
		return '<' . $tag . ' ' . get_block_wrapper_attributes( $extra ) . '>' . $content . '</' . $tag . '>';
	}

	/**
	 * Counter: the duration goes to the view script.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Rendered inner blocks.
	 */
	public static function counter( array $attributes, string $content ): string {
		$duration = is_int( $attributes['duration'] ?? null ) ? $attributes['duration'] : self::COUNTER_DURATION;
		return self::wrap( 'div', $content, array( 'data-duration' => (string) max( 0, min( 10000, $duration ) ) ) );
	}

	/**
	 * Pricing plan: a highlighted plan gets is-featured.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Rendered inner blocks.
	 */
	public static function pricing_plan( array $attributes, string $content ): string {
		$featured = true === ( $attributes['featured'] ?? false );
		return self::wrap( 'div', $content, $featured ? array( 'class' => 'is-featured' ) : array() );
	}
}
