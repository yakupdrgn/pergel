<?php
/**
 * Header links to the wishlist and compare pages.
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
echo \Pergel\Woo\ListView::links(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in ListView.
