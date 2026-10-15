<?php
/**
 * The map block.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Blocks;

/**
 * A static image (or a plain placeholder) with a "Show the map" link. The
 * page makes no request to the map provider until the visitor asks: the
 * view script swaps the image for the embedded map on click. Without
 * JavaScript the link opens the map on Google Maps.
 */
final class Map {

	public const ZOOM = 14;

	/**
	 * Block markup; empty without an address.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Rendered inner blocks (an image).
	 */
	public static function render( array $attributes, string $content ): string {
		$query = is_string( $attributes['query'] ?? null ) ? trim( $attributes['query'] ) : '';
		if ( '' === $query ) {
			return '';
		}
		$zoom  = is_int( $attributes['zoom'] ?? null ) ? max( 1, min( 21, $attributes['zoom'] ) ) : self::ZOOM;
		$image = '' === trim( $content )
			? '<div class="wp-block-pergel-map__placeholder"><span>' . esc_html( $query ) . '</span></div>'
			: $content;
		/* translators: %s: address or place shown on the map. */
		$title   = sprintf( __( 'Map: %s', 'pergel-engine' ), $query );
		$wrapper = get_block_wrapper_attributes(
			array(
				'data-src'   => self::embed_url( $query, $zoom ),
				'data-title' => $title,
			)
		);
		return '<div ' . $wrapper . '>'
			. '<div class="wp-block-pergel-map__frame">' . $image
			. '<a class="wp-block-pergel-map__load wp-element-button" href="' . esc_url( self::link_url( $query ) ) . '">' . esc_html__( 'Show the map', 'pergel-engine' ) . '</a>'
			. '</div>'
			. '<p class="wp-block-pergel-map__note">' . esc_html__( 'The map loads from Google Maps when you open it.', 'pergel-engine' ) . '</p>'
			. '</div>';
	}

	/**
	 * The embedded map's address (no API key needed).
	 *
	 * @param string $query Address or place.
	 * @param int    $zoom  Zoom level.
	 */
	public static function embed_url( string $query, int $zoom ): string {
		return 'https://www.google.com/maps?' . http_build_query(
			array(
				'q'      => $query,
				'z'      => $zoom,
				'output' => 'embed',
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		);
	}

	/**
	 * The map on Google Maps, for visitors without JavaScript.
	 *
	 * @param string $query Address or place.
	 */
	public static function link_url( string $query ): string {
		return 'https://www.google.com/maps/search/?' . http_build_query(
			array(
				'api'   => 1,
				'query' => $query,
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		);
	}
}
