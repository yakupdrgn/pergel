<?php
/**
 * Live preview of unsaved settings.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Settings;

/**
 * The settings screen sends its unsaved values to POST /pergel/v1/preview;
 * they are cleaned like a save and kept for the user for a while. A page
 * opened with ?pergel_preview=<nonce> by that user then reads them instead
 * of the saved settings. Nothing is saved, and preview pages are not
 * cached and not indexed.
 *
 * Settings\Module loads this class only on REST requests and on pages
 * carrying the argument.
 */
final class Preview {

	/**
	 * Query argument of a preview page.
	 */
	public const ARG = 'pergel_preview';

	/**
	 * Nonce action of a preview page.
	 */
	private const NONCE = 'pergel_preview';

	/**
	 * How long the values are kept.
	 */
	private const TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * The values this request reads, once known; false when it does not
	 * preview.
	 *
	 * @var array<string, mixed>|false|null
	 */
	private static array|false|null $values = null;

	/**
	 * Adds the preview hooks on a page carrying the argument.
	 */
	public static function register(): void {
		add_filter( 'pre_option_' . Settings::OPTION, array( self::class, 'option' ) );
		// Settings read while plugins load, before the user is known, are
		// read again.
		add_action( 'init', array( Settings::class, 'flush' ), 0 );
		add_action( 'send_headers', array( self::class, 'headers' ) );
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		add_filter( 'show_admin_bar', '__return_false' );
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Page cache plugins' own switch.
		}
	}

	/**
	 * Not cached and not indexed. The header holds even where an SEO plugin
	 * prints its own robots tag instead of wp_robots.
	 */
	public static function headers(): void {
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow' );
		}
	}

	/**
	 * The previewed values instead of the saved ones, for the user who sent
	 * them. Hooked on pre_option_pergel_settings.
	 *
	 * @param mixed $pre Value so far (false: read the option).
	 */
	public static function option( mixed $pre ): mixed {
		if ( null === self::$values ) {
			// The user is known once the pluggable functions are loaded.
			if ( ! function_exists( 'wp_verify_nonce' ) || ! did_action( 'plugins_loaded' ) ) {
				return $pre;
			}
			self::$values = false;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is the nonce check.
			$nonce = isset( $_GET[ self::ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::ARG ] ) ) : '';
			if ( current_user_can( 'manage_options' ) && false !== wp_verify_nonce( $nonce, self::NONCE ) ) {
				$stored       = get_transient( self::key() );
				self::$values = is_array( $stored ) ? $stored : false;
			}
		}
		return false === self::$values ? $pre : self::$values;
	}

	/**
	 * Keeps the sent values and answers with the preview pages.
	 *
	 * @param \WP_REST_Request $request Request with "settings": the form's values by group.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 */
	public static function save( \WP_REST_Request $request ): \WP_REST_Response {
		$input = $request->get_param( 'settings' );
		$value = Schema::sanitize( is_array( $input ) ? $input : array(), Settings::all() )['value'];
		set_transient( self::key(), $value, self::TTL );
		$nonce = wp_create_nonce( self::NONCE );
		return new \WP_REST_Response(
			array(
				'shop'    => add_query_arg( self::ARG, $nonce, self::shop() ),
				'product' => add_query_arg( self::ARG, $nonce, self::product() ),
			)
		);
	}

	/**
	 * The shop page, else the home page.
	 */
	private static function shop(): string {
		$url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';
		return '' !== $url ? $url : home_url( '/' );
	}

	/**
	 * The newest product, else the shop page.
	 */
	private static function product(): string {
		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$url = $ids ? get_permalink( (int) $ids[0] ) : false;
		return is_string( $url ) ? $url : self::shop();
	}

	/**
	 * Transient of the current user's values.
	 */
	private static function key(): string {
		return 'pergel_preview_' . get_current_user_id();
	}
}
