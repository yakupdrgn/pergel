<?php
/**
 * Form block: a contact or quote request form.
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
echo \Pergel\Forms\Form::render( $attributes, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Form::render().
