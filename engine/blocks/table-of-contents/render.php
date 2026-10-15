<?php
/**
 * Table of contents block: links to the headings of the content.
 *
 * @package Pergel
 */

declare( strict_types=1 );

/**
 * Block render variables.
 *
 * @var array<string, mixed> $attributes
 * @var WP_Block $block
 */
echo \Pergel\Blocks\Toc::render( $attributes, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in render().
