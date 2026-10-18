<?php
/**
 * Timeline block: an ordered list of milestones.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Block render variables.
 *
 * @var array<string, mixed> $attributes
 * @var string $content
 */
echo \Pergel\Blocks\Render::wrap( 'ol', $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks are rendered by core; the wrapper is escaped.
