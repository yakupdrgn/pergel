<?php
/**
 * Carousel block: slides in a scrollable row.
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
echo \Pergel\Blocks\Carousel::render( $attributes, $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks are rendered by core; the wrapper is escaped.
