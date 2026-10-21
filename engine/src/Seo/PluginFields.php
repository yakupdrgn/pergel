<?php
/**
 * The title and description fields of the active SEO plugin.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Seo;

/**
 * Where each supported SEO plugin keeps a post's own title and meta
 * description, so a project package can write its SEO fields there too:
 * with an SEO plugin active, Pergel steps back and the plugin's fields are
 * the ones a page shows. Rank Math, Yoast, SEOPress and The SEO Framework
 * keep them in post meta; All in One SEO keeps them in its own table,
 * written through its post model when that is loaded.
 *
 * Only the title and the description: a plugin's robots settings combine
 * with site-wide defaults, so the package's noindex stays Pergel's own.
 */
final class PluginFields {

	/**
	 * Post meta keys by plugin id and field.
	 */
	public const KEYS = array(
		'rank-math' => array(
			'title'       => 'rank_math_title',
			'description' => 'rank_math_description',
		),
		'yoast'     => array(
			'title'       => '_yoast_wpseo_title',
			'description' => '_yoast_wpseo_metadesc',
		),
		'seopress'  => array(
			'title'       => '_seopress_titles_title',
			'description' => '_seopress_titles_desc',
		),
		'tsf'       => array(
			'title'       => '_genesis_title',
			'description' => '_genesis_description',
		),
	);

	/**
	 * All in One SEO's post model.
	 */
	private const AIOSEO_POST = '\\AIOSEO\\Plugin\\Common\\Models\\Post';

	/**
	 * Post meta to write for the given fields, for the active plugin; empty
	 * values are left out, so a package without a description keeps the one
	 * the plugin has.
	 *
	 * @param array<string, string> $fields Field (title, description) => value.
	 * @param string|null           $plugin Plugin id; the active one when null.
	 * @return array<string, string> Meta key => value.
	 */
	public static function meta( array $fields, ?string $plugin = null ): array {
		$keys = self::KEYS[ $plugin ?? Detect::active() ] ?? array();
		$meta = array();
		foreach ( $keys as $field => $key ) {
			$value = (string) ( $fields[ $field ] ?? '' );
			if ( '' !== $value ) {
				$meta[ $key ] = $value;
			}
		}
		return $meta;
	}

	/**
	 * Writes the fields to All in One SEO's table when it is the active
	 * plugin (the other plugins get theirs as post meta, see meta()).
	 *
	 * @param int                   $post_id Post ID.
	 * @param array<string, string> $fields  Field (title, description) => value.
	 */
	public static function save_table( int $post_id, array $fields ): void {
		$post = self::aioseo_post( $post_id );
		if ( null === $post ) {
			return;
		}
		$changed = false;
		$current = get_object_vars( $post );
		// AIOSEO's model holds its table's columns as properties.
		foreach ( array( 'title', 'description' ) as $field ) {
			$value = (string) ( $fields[ $field ] ?? '' );
			if ( '' !== $value && ( $current[ $field ] ?? '' ) !== $value ) {
				self::set( $post, $field, $value );
				$changed = true;
			}
		}
		if ( $changed && method_exists( $post, 'save' ) ) {
			$post->save();
		}
	}

	/**
	 * Sets a property of a plugin's model.
	 *
	 * @param object $model Model.
	 * @param string $name  Property.
	 * @param string $value Value.
	 */
	private static function set( object $model, string $name, string $value ): void {
		$model->$name = $value;
	}

	/**
	 * All in One SEO's record of a post, when it is the active plugin.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function aioseo_post( int $post_id ): ?object {
		if ( 'aioseo' !== Detect::active() ) {
			return null;
		}
		/**
		 * The post model's loader; filterable so a test or another
		 * version of the plugin can hand its own.
		 *
		 * @param mixed $callback Callable that takes a post ID.
		 */
		$callback = apply_filters( 'pergel/seo/aioseo_post', array( self::AIOSEO_POST, 'getPost' ) );
		if ( ! is_callable( $callback ) ) {
			return null;
		}
		$post = $callback( $post_id );
		return is_object( $post ) ? $post : null;
	}
}
