<?php
/**
 * FAQPage from the questions on a page.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Seo;

/**
 * A page with an FAQ pattern (Details blocks inside "pergel-faq") gets a
 * FAQPage node: in Pergel's own graph, or in the graph of the active SEO
 * plugin. SEOPress takes no extra nodes, so there it is a script of its own.
 * A graph that already has a FAQPage (a plugin's FAQ block) is left as it
 * is. Module adds the hooks, so this class loads only on a page with an
 * FAQ section.
 */
final class Faq {

	/**
	 * Node for this request.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $node = null;

	/**
	 * Adds the node to a list or map of graph nodes.
	 *
	 * @param mixed $graph Graph nodes.
	 */
	public static function graph( mixed $graph ): mixed {
		$node = self::node();
		if ( ! is_array( $graph ) || ! $node || Facts::has_type( $graph, 'FAQPage' ) ) {
			return $graph;
		}
		if ( array_is_list( $graph ) ) {
			$graph[] = $node;
		} else {
			$graph['pergel-faq'] = $node;
		}
		return $graph;
	}

	/**
	 * The node as a script of its own.
	 */
	public static function print_script(): void {
		$node = self::node();
		if ( ! $node ) {
			return;
		}
		$json = wp_json_encode( array( '@context' => 'https://schema.org' ) + $node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( $json ) {
			echo '<script type="application/ld+json" class="pergel-faq-schema">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with < > & escaped.
		}
	}

	/**
	 * FAQPage of the current page, or an empty array.
	 *
	 * @return array<string, mixed>
	 */
	public static function node(): array {
		if ( null !== self::$node ) {
			return self::$node;
		}
		self::$node = array();
		$post       = is_singular() ? get_queried_object() : null;
		if ( $post instanceof \WP_Post && ! post_password_required( $post ) && str_contains( $post->post_content, Facts::FAQ ) ) {
			self::$node = Facts::faq_page( Facts::questions( parse_blocks( $post->post_content ) ), (string) wp_get_canonical_url( $post ), (string) get_bloginfo( 'language' ) );
		}
		return self::$node;
	}

	/**
	 * Drops the cached node. For tests.
	 */
	public static function flush(): void {
		self::$node = null;
	}
}
