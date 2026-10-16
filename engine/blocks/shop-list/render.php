<?php
/**
 * The wishlist or the products to compare.
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
echo \Pergel\Woo\ListView::page( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in ListView.
