<?php
/**
 * Counter block: a number that counts up, with its label.
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
echo \Pergel\Blocks\Render::counter( $attributes, $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks are rendered by core; the wrapper is escaped.
