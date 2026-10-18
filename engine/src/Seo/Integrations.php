<?php
/**
 * Brand details in the structured data of SEO plugins.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Seo;

use Pergel\Brand\Brand;

/**
 * While an SEO plugin owns the head, Pergel prints nothing and only fills
 * the empty properties of the plugin's Organization node (address, phone,
 * tax number, social profiles...). The plugin's own settings always win.
 */
final class Integrations {

	/**
	 * Organization node for this request.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $organization = null;

	/**
	 * Adds the filters of one plugin.
	 *
	 * @param string $plugin Plugin id from Detect.
	 */
	public static function register( string $plugin ): void {
		switch ( $plugin ) {
			case 'rank-math':
				// After Rank Math has linked its entities (priority 99).
				add_filter( 'rank_math/json_ld', array( self::class, 'graph' ), 100 );
				break;
			case 'yoast':
				add_filter( 'wpseo_schema_organization', array( self::class, 'node' ) );
				break;
			case 'aioseo':
				add_filter( 'aioseo_schema_output', array( self::class, 'graph' ) );
				break;
			case 'seopress':
				add_filter( 'seopress_schemas_organization_html', array( self::class, 'json' ) );
				break;
			case 'tsf':
				add_filter( 'the_seo_framework_schema_graph_data', array( self::class, 'graph' ) );
				break;
		}
	}

	/**
	 * Fills a list or map of graph nodes.
	 *
	 * @param mixed $graph Nodes from the plugin.
	 */
	public static function graph( mixed $graph ): mixed {
		return Graph::enrich_all( $graph, self::organization() );
	}

	/**
	 * Fills one node.
	 *
	 * @param mixed $node Node from the plugin.
	 */
	public static function node( mixed $node ): mixed {
		return Graph::enrich( $node, self::organization() );
	}

	/**
	 * Fills a node that arrives as JSON (SEOPress).
	 *
	 * @param mixed $json JSON text of one node.
	 */
	public static function json( mixed $json ): mixed {
		if ( ! is_string( $json ) ) {
			return $json;
		}
		$node = json_decode( $json, true );
		if ( ! is_array( $node ) || ! Graph::is_organization( $node ) ) {
			return $json;
		}
		$encoded = wp_json_encode( Graph::enrich( $node, self::organization() ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		return is_string( $encoded ) ? $encoded : $json;
	}

	/**
	 * Organization from the brand settings, built once per request.
	 *
	 * @return array<string, mixed>
	 */
	private static function organization(): array {
		if ( null === self::$organization ) {
			self::$organization = Brand::organization();
		}
		return self::$organization;
	}

	/**
	 * Drops the cached node. For tests.
	 */
	public static function flush(): void {
		self::$organization = null;
	}
}
