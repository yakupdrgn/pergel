<?php
/**
 * SEO matrix only: a page asked with ?pergel-noindex=1 is one Pergel keeps
 * out of search results, so check.mjs can read how each SEO plugin prints
 * Pergel's noindex. matrix.sh copies this file into mu-plugins for its run
 * and removes it afterwards. Development and CI only.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Dev;

defined( 'ABSPATH' ) || exit;

add_filter(
	'pergel/seo/noindex',
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checks that the argument is there.
	static fn ( mixed $noindex ): bool => true === $noindex || isset( $_GET['pergel-noindex'] )
);
