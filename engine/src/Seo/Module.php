<?php
/**
 * The SEO module (plan K8).
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Seo;

use Pergel\Core;

/**
 * Hands over to an active SEO plugin and adds the brand details to its
 * structured data. Without one, Pergel prints the meta tags and JSON-LD
 * itself, adds an SEO panel to the post editor and tunes the core sitemap.
 */
final class Module implements Core\Module {

	/**
	 * {@inheritDoc}
	 */
	public static function id(): string {
		return 'seo';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function should_load(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		$plugin = Detect::active();
		Faq::register( $plugin );
		if ( '' !== $plugin ) {
			Integrations::register( $plugin );
			Trail::register( $plugin );
			return;
		}

		// Late, so post types registered on init get the fields too.
		add_action( 'init', array( Meta::class, 'register' ), 99 );
		add_action( 'enqueue_block_editor_assets', array( Meta::class, 'enqueue_editor' ) );
		Sitemap::register();
		if ( ! is_admin() ) {
			Head::register();
		}
	}
}
