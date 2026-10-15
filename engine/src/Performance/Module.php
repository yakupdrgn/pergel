<?php
/**
 * The performance module.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Performance;

use Pergel\Core;
use Pergel\Settings\Settings;

/**
 * Trims front-end output most sites do not need and preloads the fonts of
 * the active style variation. Each part has its own switch in the
 * performance settings.
 */
final class Module implements Core\Module {

	/**
	 * {@inheritDoc}
	 */
	public static function id(): string {
		return 'performance';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function should_load(): bool {
		return ! is_admin();
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		if ( Settings::get( 'performance.disable_emoji' ) ) {
			self::disable_emoji();
		}
		if ( Settings::get( 'performance.remove_oembed_links' ) ) {
			// Core hooks it twice (priority 4, and 10 for back-compat).
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 4 );
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		}
		if ( Settings::get( 'performance.dequeue_dashicons' ) ) {
			// Block themes render the template before wp_head; the footer pass catches late enqueues.
			add_action( 'wp_enqueue_scripts', array( self::class, 'dequeue_dashicons' ), 100 );
			add_action( 'wp_footer', array( self::class, 'dequeue_dashicons' ), 1 );
		}
		if ( Settings::get( 'performance.preload_fonts' ) ) {
			add_filter( 'wp_preload_resources', array( self::class, 'preload_fonts' ) );
		}
	}

	/**
	 * Removes the emoji detection script, its styles and the DNS prefetch
	 * for the emoji CDN from pages and embeds. Browsers draw emoji
	 * themselves; feeds and e-mails keep core's image fallback.
	 */
	private static function disable_emoji(): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'embed_head', 'print_emoji_detection_script' );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		remove_action( 'enqueue_embed_scripts', 'wp_enqueue_emoji_styles' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		add_filter( 'emoji_svg_url', '__return_false' );
	}

	/**
	 * Takes Dashicons out of the queue for visitors who are not logged in.
	 * A style that depends on Dashicons still brings it in.
	 */
	public static function dequeue_dashicons(): void {
		if ( ! is_user_logged_in() ) {
			wp_dequeue_style( 'dashicons' );
		}
	}

	/**
	 * Adds the body and heading font files to core's preload list.
	 *
	 * @param mixed $resources Resources from other callbacks.
	 * @return mixed
	 */
	public static function preload_fonts( mixed $resources ): mixed {
		if ( ! is_array( $resources ) ) {
			return $resources;
		}
		foreach ( self::font_urls() as $url ) {
			$resources[] = array(
				'href'        => $url,
				'as'          => 'font',
				'type'        => 'font/woff2',
				'crossorigin' => 'anonymous',
			);
		}
		return $resources;
	}

	/**
	 * URLs of the font files to preload.
	 *
	 * @return list<string>
	 */
	public static function font_urls(): array {
		$styles   = wp_get_global_styles();
		$families = wp_get_global_settings( array( 'typography', 'fontFamilies' ) );
		$files    = Fonts::files( is_array( $families ) ? $families : array(), Fonts::used_slugs( is_array( $styles ) ? $styles : array() ) );

		$urls = array();
		foreach ( $files as $file ) {
			// Same conversion as core's WP_Font_Face_Resolver, so the URL matches the @font-face src.
			$urls[] = str_starts_with( $file, 'file:./' ) ? get_theme_file_uri( substr( $file, 7 ) ) : $file;
		}

		/**
		 * Filters the font files Pergel preloads.
		 *
		 * By default these are the basic Latin files of the body and heading
		 * fonts. A site written mostly in a language with letters outside
		 * Latin-1 (Turkish, Polish, ...) can add the latin-ext files here.
		 *
		 * @param list<string> $urls Font file URLs.
		 */
		$filtered = apply_filters( 'pergel/performance/font_preloads', $urls );

		return is_array( $filtered ) ? array_values( array_filter( $filtered, 'is_string' ) ) : $urls;
	}
}
