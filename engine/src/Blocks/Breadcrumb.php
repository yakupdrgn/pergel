<?php
/**
 * The breadcrumb block.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Blocks;

use Pergel\Seo\Graph;
use Pergel\Seo\Trail;

/**
 * Prints the trail from Pergel\Seo\Trail, the same steps as the page's
 * BreadcrumbList. When nothing else prints a BreadcrumbList for the trail
 * (SEOPress free), the block adds one. In the editor it shows a sample.
 */
final class Breadcrumb {

	/**
	 * Trail queued for the BreadcrumbList of this request.
	 *
	 * @var list<array{name: string, url: string}>
	 */
	private static array $schema = array();

	/**
	 * Block markup for the current page; empty when the page has no trail.
	 */
	public static function render(): string {
		if ( self::is_preview() ) {
			return self::markup( self::sample(), get_block_wrapper_attributes( array( 'aria-label' => __( 'Breadcrumb', 'pergel-engine' ) ) ) );
		}
		$trail = Trail::current();
		if ( array() === $trail ) {
			return '';
		}
		if ( ! Trail::in_schema() ) {
			self::queue_schema( $trail );
		}
		return self::markup( $trail, get_block_wrapper_attributes( array( 'aria-label' => __( 'Breadcrumb', 'pergel-engine' ) ) ) );
	}

	/**
	 * A nav with an ordered list; the last step is the current page.
	 *
	 * @param list<array{name: string, url: string}> $trail   Steps, home first.
	 * @param string                                 $wrapper Wrapper attributes.
	 */
	public static function markup( array $trail, string $wrapper ): string {
		$last  = count( $trail ) - 1;
		$items = '';
		foreach ( $trail as $index => $step ) {
			$items .= $index === $last
				? '<li><span aria-current="page">' . esc_html( $step['name'] ) . '</span></li>'
				: '<li><a href="' . esc_url( $step['url'] ) . '">' . esc_html( $step['name'] ) . '</a></li>';
		}
		return '<nav ' . $wrapper . '><ol>' . $items . '</ol></nav>';
	}

	/**
	 * Prints a BreadcrumbList for the trail once, in the head when the
	 * block renders before it (block themes) and in the footer otherwise.
	 *
	 * @param list<array{name: string, url: string}> $trail Steps, home first.
	 */
	private static function queue_schema( array $trail ): void {
		/**
		 * Filters whether the breadcrumb block adds a BreadcrumbList when
		 * the active SEO plugin prints none (SEOPress free).
		 *
		 * @param bool $add Add the BreadcrumbList.
		 */
		if ( array() !== self::$schema || ! apply_filters( 'pergel/breadcrumb/schema', true ) ) {
			return;
		}
		self::$schema = $trail;
		add_action( did_action( 'wp_head' ) ? 'wp_footer' : 'wp_head', array( self::class, 'print_schema' ), 20 );
	}

	/**
	 * The queued BreadcrumbList.
	 */
	public static function print_schema(): void {
		if ( array() === self::$schema ) {
			return;
		}
		$last = self::$schema[ count( self::$schema ) - 1 ];
		$json = wp_json_encode(
			array( '@context' => 'https://schema.org' ) + Graph::breadcrumbs( self::$schema, $last['url'] . '#breadcrumb' ),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
		);
		if ( $json ) {
			echo '<script type="application/ld+json" class="pergel-breadcrumb-schema">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with < > & escaped.
		}
	}

	/**
	 * Drops the queued BreadcrumbList. For tests.
	 */
	public static function flush(): void {
		self::$schema = array();
		remove_action( 'wp_head', array( self::class, 'print_schema' ), 20 );
		remove_action( 'wp_footer', array( self::class, 'print_schema' ), 20 );
	}

	/**
	 * Whether the block renders for the editor preview (block renderer
	 * endpoint), where there is no page to build a trail for.
	 */
	private static function is_preview(): bool {
		return wp_is_serving_rest_request();
	}

	/**
	 * Sample steps for the editor.
	 *
	 * @return list<array{name: string, url: string}>
	 */
	private static function sample(): array {
		return array(
			array(
				'name' => __( 'Home', 'pergel-engine' ),
				'url'  => home_url( '/' ),
			),
			array(
				'name' => __( 'Parent page', 'pergel-engine' ),
				'url'  => home_url( '/' ),
			),
			array(
				'name' => __( 'Current page', 'pergel-engine' ),
				'url'  => home_url( '/' ),
			),
		);
	}
}
