<?php
/**
 * Logo marquee block: a moving row of logos.
 *
 * @package Pergel
 */

declare( strict_types=1 );

/**
 * Block render variables.
 *
 * @var array<string, mixed> $attributes
 * @var string $content
 */
echo \Pergel\Blocks\Marquee::render( $attributes, $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks are rendered by core; the wrapper is escaped.
