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
	 * Graph filters of the SEO plugins that take extra nodes, with their
	 * priority; '' is Pergel's own graph. SEOPress takes none.
	 */
	private const GRAPHS = array(
		''          => array( 'pergel/seo/graph', 10 ),
		'rank-math' => array( 'rank_math/json_ld', 100 ),
		'yoast'     => array( 'wpseo_schema_graph', 10 ),
		'aioseo'    => array( 'aioseo_schema_output', 10 ),
		'tsf'       => array( 'the_seo_framework_schema_graph_data', 10 ),
	);

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		$plugin = Detect::active();
		self::faq( $plugin );
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

	/**
	 * Hands the graph, or with SEOPress the page head, to Faq on a page
	 * with an FAQ section; other pages never load Faq and Facts.
	 *
	 * @param string $plugin Plugin id from Detect, '' for none.
	 */
	private static function faq( string $plugin ): void {
		if ( isset( self::GRAPHS[ $plugin ] ) ) {
			add_filter( self::GRAPHS[ $plugin ][0], static fn( mixed $graph ): mixed => self::has_faq() ? Faq::graph( $graph ) : $graph, self::GRAPHS[ $plugin ][1] );
			return;
		}
		add_action(
			'wp_head',
			static function (): void {
				if ( self::has_faq() ) {
					Faq::print_script();
				}
			},
			99
		);
	}

	/**
	 * Whether the page shown has an FAQ section, by a look at its content.
	 */
	public static function has_faq(): bool {
		$post = is_singular() ? get_queried_object() : null;
		return $post instanceof \WP_Post && str_contains( $post->post_content, 'pergel-faq' );
	}
}
