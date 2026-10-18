<?php
/**
 * Mega menu block: a navigation item that opens a wide panel.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Block render variables.
 *
 * @var array<string, mixed> $attributes
 */
echo \Pergel\Blocks\MegaMenu::render( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template part is rendered by core; the rest is escaped.
