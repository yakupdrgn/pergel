<?php
/**
 * Per-post SEO fields and their editor panel.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Seo;

use const Pergel\FILE;
use const Pergel\VERSION;

/**
 * Title, description and "hide from search engines" for each viewable post.
 * Registered only while no SEO plugin is active, so the editor never shows
 * two SEO panels.
 */
final class Meta {

	public const TITLE       = '_pergel_seo_title';
	public const DESCRIPTION = '_pergel_seo_description';
	public const NOINDEX     = '_pergel_seo_noindex';

	/**
	 * Registers the fields for REST and the block editor. Hooked on init.
	 */
	public static function register(): void {
		$auth = static fn ( bool $allowed, string $meta_key, int $object_id ): bool => current_user_can( 'edit_post', $object_id );

		foreach ( array(
			self::TITLE       => 70,
			self::DESCRIPTION => 300,
		) as $key => $max ) {
			register_post_meta(
				'',
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'show_in_rest'      => array( 'schema' => array( 'maxLength' => $max ) ),
					'sanitize_callback' => static fn ( $value ): string => mb_substr( sanitize_text_field( is_string( $value ) ? $value : '' ), 0, $max, 'UTF-8' ),
					'auth_callback'     => $auth,
				)
			);
		}
		register_post_meta(
			'',
			self::NOINDEX,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => $auth,
			)
		);

		// The REST API exposes meta only for types with custom-fields support.
		foreach ( self::post_types() as $type ) {
			if ( ! post_type_supports( $type, 'custom-fields' ) ) {
				add_post_type_support( $type, 'custom-fields' );
			}
		}
	}

	/**
	 * Post types with a public URL that the block editor can edit.
	 *
	 * @return list<string>
	 */
	public static function post_types(): array {
		$types = array();
		foreach ( get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $name => $type ) {
			if ( 'attachment' !== $name && is_post_type_viewable( $type ) ) {
				$types[] = (string) $name;
			}
		}
		return $types;
	}

	/**
	 * A text field of a post.
	 *
	 * @param int    $post_id Post id.
	 * @param string $key     Meta key.
	 */
	public static function get( int $post_id, string $key ): string {
		if ( ! $post_id ) {
			return '';
		}
		$value = get_post_meta( $post_id, $key, true );
		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Whether a post is hidden from search engines.
	 *
	 * @param int $post_id Post id.
	 */
	public static function noindex( int $post_id ): bool {
		return $post_id && (bool) get_post_meta( $post_id, self::NOINDEX, true );
	}

	/**
	 * Loads the SEO panel in the post editor. No build step.
	 */
	public static function enqueue_editor(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base || ! in_array( $screen->post_type, self::post_types(), true ) ) {
			return;
		}
		wp_enqueue_script(
			'pergel-seo-panel',
			plugins_url( 'assets/seo-panel.js', FILE ),
			array( 'wp-components', 'wp-core-data', 'wp-data', 'wp-editor', 'wp-element', 'wp-i18n', 'wp-plugins' ),
			VERSION,
			array( 'in_footer' => true )
		);
		wp_set_script_translations( 'pergel-seo-panel', 'pergel-engine', dirname( FILE ) . '/languages' );
	}
}
