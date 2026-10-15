<?php
/**
 * Breadcrumb block: the trail of the current page.
 *
 * @package Pergel
 */

declare( strict_types=1 );

echo \Pergel\Blocks\Breadcrumb::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in markup().
