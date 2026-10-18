<?php
/**
 * Term image.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Block render variables.
 *
 * @var array<string, mixed> $attributes
 * @var WP_Block $block
 */
echo \Pergel\Blocks\TermImage::render( $attributes, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in TermImage.
