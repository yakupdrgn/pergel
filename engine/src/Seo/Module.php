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
 *
 * Pages Pergel keeps out of search results (an ended flash sale, a shop
 * listing with a visitor's per_page) answer the pergel/seo/noindex filter.
 * The answer goes into the robots tag of whoever prints it: core's
 * wp_robots without an SEO plugin and with Yoast SEO (which merges
 * wp_robots into its own values); the plugin's own filter with Rank Math
 * and All in One SEO (they drop core's robots filters), The SEO Framework
 * and SEOPress (they print a tag of their own). The page keeps one robots
 * tag, and "noindex, follow" in it.
 */
final class Module implements Core\Module {

	/**
	 * Filter: whether Pergel keeps this page out of search results.
	 */
	public const NOINDEX = 'pergel/seo/noindex';

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
	 * Robots filters of the SEO plugins that print the tag without core's
	 * wp_robots, with the Robots method and the number of arguments.
	 */
	private const ROBOTS = array(
		'rank-math' => array( 'rank_math/frontend/robots', 'rank_math', 1 ),
		'aioseo'    => array( 'aioseo_robots_meta', 'aioseo', 1 ),
		'seopress'  => array( 'seopress_titles_noindex_bypass', 'seopress', 1 ),
		'tsf'       => array( 'the_seo_framework_robots_meta_array', 'tsf', 2 ),
	);

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		$plugin = Detect::active();
		self::faq( $plugin );
		self::robots( $plugin );
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
	 * Adds the robots filter of the plugin that prints the tag (core's
	 * wp_robots for none, Yoast SEO and plugins Pergel does not know). Other
	 * pages never load Robots.
	 *
	 * @param string $plugin Plugin id from Detect, '' for none.
	 */
	private static function robots( string $plugin ): void {
		[ $hook, $method, $args ] = self::ROBOTS[ $plugin ] ?? array( 'wp_robots', 'core', 1 );
		add_filter( $hook, static fn( mixed ...$values ): mixed => self::noindex() ? array( Robots::class, $method )( ...$values ) : $values[0], 20, $args );
	}

	/**
	 * Whether Pergel keeps the page shown out of search results.
	 */
	public static function noindex(): bool {
		/**
		 * Filters whether Pergel keeps the page shown out of search results
		 * ("noindex, follow"). Only true counts.
		 *
		 * @param bool $noindex False, or true from an earlier callback.
		 */
		return ! is_admin() && true === apply_filters( 'pergel/seo/noindex', false );
	}

	/**
	 * Whether the page shown has an FAQ section, by a look at its content.
	 */
	public static function has_faq(): bool {
		$post = is_singular() ? get_queried_object() : null;
		return $post instanceof \WP_Post && str_contains( $post->post_content, 'pergel-faq' );
	}
}
