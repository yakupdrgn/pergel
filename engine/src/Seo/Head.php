<?php
/**
 * Meta tags and structured data when no SEO plugin is active.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Seo;

use Pergel\Brand\Brand;
use Pergel\Settings\Settings;

/**
 * Adds what core leaves out: meta description, canonical links on lists,
 * Open Graph and Twitter tags, and one JSON-LD graph. Core keeps printing
 * the title, the robots tag and the canonical link of single pages; Pergel
 * changes them through their filters.
 *
 * @phpstan-import-type Page from Graph
 * @phpstan-import-type Image from Graph
 */
final class Head {

	/**
	 * Page data for this request.
	 *
	 * @var array<string, mixed>|null
	 * @phpstan-var Page|null
	 */
	private static ?array $page = null;

	/**
	 * Adds the front-end hooks.
	 */
	public static function register(): void {
		add_filter( 'pre_get_document_title', array( self::class, 'document_title' ), 20 );
		add_filter( 'wp_robots', array( self::class, 'robots' ) );
		add_action( 'wp_head', array( self::class, 'print_meta' ), 2 );
		add_action( 'wp_head', array( self::class, 'print_canonical' ), 10 );
		add_action( 'wp_head', array( self::class, 'print_graph' ), 11 );
	}

	/**
	 * The title typed in the SEO panel replaces the whole document title.
	 *
	 * @param mixed $title Title from earlier filters ('' lets core build it).
	 */
	public static function document_title( mixed $title ): mixed {
		if ( is_singular() ) {
			$custom = Meta::get( (int) get_queried_object_id(), Meta::TITLE );
			if ( '' !== $custom ) {
				return $custom;
			}
		}
		return $title;
	}

	/**
	 * Adds noindex for pages hidden in the SEO panel.
	 *
	 * @param array<string, mixed> $robots Directives.
	 * @return array<string, mixed>
	 */
	public static function robots( array $robots ): array {
		if ( is_singular() && Meta::noindex( (int) get_queried_object_id() ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}

	/**
	 * Meta description, Open Graph and Twitter tags.
	 */
	public static function print_meta(): void {
		if ( is_404() ) {
			return;
		}
		$page  = self::page();
		$tags  = array();
		$image = $page['image'];

		if ( '' !== $page['description'] ) {
			$tags[] = array( 'name', 'description', $page['description'] );
		}
		$tags[] = array( 'property', 'og:locale', get_locale() );
		$tags[] = array( 'property', 'og:type', '' !== ( $page['article']['headline'] ?? '' ) ? 'article' : 'website' );
		$tags[] = array( 'property', 'og:title', $page['name'] );
		if ( '' !== $page['description'] ) {
			$tags[] = array( 'property', 'og:description', $page['description'] );
		}
		$tags[] = array( 'property', 'og:url', $page['url'] );
		$tags[] = array( 'property', 'og:site_name', self::site_name() );
		if ( '' !== ( $image['url'] ?? '' ) ) {
			$tags[] = array( 'property', 'og:image', $image['url'] );
			if ( isset( $image['width'], $image['height'] ) ) {
				$tags[] = array( 'property', 'og:image:width', (string) $image['width'] );
				$tags[] = array( 'property', 'og:image:height', (string) $image['height'] );
			}
			if ( '' !== ( $image['alt'] ?? '' ) ) {
				$tags[] = array( 'property', 'og:image:alt', $image['alt'] );
			}
		}
		if ( '' !== ( $page['article']['published'] ?? '' ) ) {
			$tags[] = array( 'property', 'article:published_time', $page['article']['published'] );
			$tags[] = array( 'property', 'article:modified_time', $page['article']['modified'] ?? $page['article']['published'] );
		}
		$tags[] = array( 'name', 'twitter:card', '' !== ( $image['url'] ?? '' ) ? 'summary_large_image' : 'summary' );
		$handle = self::twitter_handle();
		if ( '' !== $handle ) {
			$tags[] = array( 'name', 'twitter:site', $handle );
		}

		foreach ( $tags as [ $attribute, $key, $value ] ) {
			printf( '<meta %1$s="%2$s" content="%3$s" />' . "\n", $attribute, esc_attr( $key ), esc_attr( $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attribute is "name" or "property".
		}
	}

	/**
	 * Canonical link for lists (home, archives); core prints it for
	 * single pages.
	 */
	public static function print_canonical(): void {
		if ( is_singular() || is_search() || is_404() ) {
			return;
		}
		$url = self::page()['url'];
		if ( '' !== $url ) {
			echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
		}
	}

	/**
	 * The JSON-LD graph.
	 */
	public static function print_graph(): void {
		if ( is_404() ) {
			return;
		}
		$site  = array(
			'url'         => home_url( '/' ),
			'name'        => self::site_name(),
			'description' => html_entity_decode( (string) get_bloginfo( 'description' ), ENT_QUOTES, 'UTF-8' ),
			'language'    => (string) get_bloginfo( 'language' ),
			'search'      => add_query_arg( 's', '{search_term_string}', home_url( '/' ) ),
		);
		$graph = Graph::build( $site, self::page(), Brand::organization() );

		/**
		 * Filters the JSON-LD graph Pergel prints when no SEO plugin is active.
		 *
		 * @param list<array<string, mixed>> $graph Graph nodes.
		 */
		$graph = apply_filters( 'pergel/seo/graph', $graph );
		if ( ! is_array( $graph ) || ! $graph ) {
			return;
		}
		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => array_values( $graph ),
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
		);
		if ( $json ) {
			echo '<script type="application/ld+json" class="pergel-schema">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with < > & escaped.
		}
	}

	/**
	 * Data about the current page, collected once per request.
	 *
	 * @phpstan-return Page
	 * @return array<string, mixed>
	 */
	public static function page(): array {
		if ( null !== self::$page ) {
			return self::$page;
		}

		$page = array(
			'type'        => 'WebPage',
			'url'         => '',
			'name'        => '',
			'description' => '',
			'image'       => array(),
			'crumbs'      => array(),
			'article'     => array(),
			'front'       => is_front_page(),
		);
		$home = array(
			'name' => __( 'Home', 'pergel-engine' ),
			'url'  => home_url( '/' ),
		);

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post ) {
				$page = self::singular( $post, $page, $home );
			}
		} elseif ( is_home() ) {
			$posts_page          = (int) get_option( 'page_for_posts' );
			$page['type']        = 'CollectionPage';
			$page['url']         = self::paged( $posts_page ? (string) get_permalink( $posts_page ) : home_url( '/' ) );
			$page['name']        = $posts_page ? self::post_title( $posts_page ) : self::site_name();
			$page['description'] = $posts_page ? self::description( $posts_page ) : self::tagline();
			$page['crumbs']      = $posts_page ? array( $home, self::crumb( $page['name'], $page['url'] ) ) : array();
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$link                = get_term_link( $term );
				$page['type']        = 'CollectionPage';
				$page['url']         = self::paged( is_string( $link ) ? $link : '' );
				$page['name']        = $term->name;
				$page['description'] = Graph::clip( wp_strip_all_tags( term_description( $term->term_id ) ) );
				$page['crumbs']      = array_merge( array( $home ), self::term_trail( $term ) );
			}
		} elseif ( is_post_type_archive() ) {
			$type           = (string) get_query_var( 'post_type' );
			$link           = get_post_type_archive_link( $type );
			$page['type']   = 'CollectionPage';
			$page['url']    = self::paged( is_string( $link ) ? $link : '' );
			$page['name']   = (string) post_type_archive_title( '', false );
			$page['crumbs'] = array( $home, self::crumb( $page['name'], $page['url'] ) );
		} elseif ( is_author() ) {
			$author              = get_queried_object();
			$page['type']        = 'ProfilePage';
			$page['url']         = $author instanceof \WP_User ? self::paged( get_author_posts_url( $author->ID ) ) : '';
			$page['name']        = $author instanceof \WP_User ? $author->display_name : '';
			$page['description'] = $author instanceof \WP_User ? Graph::clip( (string) get_the_author_meta( 'description', $author->ID ) ) : '';
			$page['crumbs']      = array( $home, self::crumb( $page['name'], $page['url'] ) );
		} elseif ( is_date() ) {
			$page['type']   = 'CollectionPage';
			$page['url']    = self::paged( self::date_link() );
			$page['name']   = wp_strip_all_tags( get_the_archive_title() );
			$page['crumbs'] = array( $home, self::crumb( $page['name'], $page['url'] ) );
		} elseif ( is_search() ) {
			$page['type'] = 'SearchResultsPage';
			$page['url']  = get_search_link();
			/* translators: %s: search terms. */
			$page['name'] = sprintf( __( 'Search results for “%s”', 'pergel-engine' ), get_search_query( false ) );
		}

		if ( ! $page['image'] ) {
			$page['image'] = self::attachment( (int) Settings::get( 'seo.og_image' ) );
		}

		self::$page = $page;
		return $page;
	}

	/**
	 * Drops the cached page data. For tests.
	 */
	public static function flush(): void {
		self::$page = null;
	}

	/**
	 * Page data of a single post, page or other post type.
	 *
	 * @param \WP_Post                         $post Queried post.
	 * @param array<string, mixed>             $page Defaults.
	 * @param array{name: string, url: string} $home Home crumb.
	 * @phpstan-param Page $page
	 * @phpstan-return Page
	 * @return array<string, mixed>
	 */
	private static function singular( \WP_Post $post, array $page, array $home ): array {
		$title = Meta::get( $post->ID, Meta::TITLE );

		$page['url']         = (string) wp_get_canonical_url( $post );
		$page['name']        = '' !== $title ? $title : self::post_title( $post->ID );
		$page['description'] = self::description( $post->ID );
		if ( $page['front'] && '' === Meta::get( $post->ID, Meta::DESCRIPTION ) && '' !== self::tagline() ) {
			$page['description'] = self::tagline();
		}
		$page['image'] = self::attachment( (int) get_post_thumbnail_id( $post ) );

		if ( 'post' === $post->post_type ) {
			$author          = get_userdata( (int) $post->post_author );
			$page['article'] = array(
				'type'      => 'BlogPosting',
				'headline'  => self::post_title( $post->ID ),
				'published' => (string) get_post_time( DATE_W3C, true, $post ),
				'modified'  => (string) get_post_modified_time( DATE_W3C, true, $post ),
				'author'    => $author instanceof \WP_User ? $author->display_name : '',
			);
		}

		if ( $page['front'] ) {
			return $page;
		}
		$crumbs = array( $home );
		if ( 'post' === $post->post_type ) {
			$posts_page = (int) get_option( 'page_for_posts' );
			if ( $posts_page ) {
				$crumbs[] = self::crumb( self::post_title( $posts_page ), (string) get_permalink( $posts_page ) );
			}
		} elseif ( 'page' !== $post->post_type ) {
			$link = get_post_type_archive_link( $post->post_type );
			$type = get_post_type_object( $post->post_type );
			if ( is_string( $link ) && $type ) {
				$crumbs[] = self::crumb( $type->labels->name, $link );
			}
		}
		foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor ) {
			$crumbs[] = self::crumb( self::post_title( (int) $ancestor ), (string) get_permalink( (int) $ancestor ) );
		}
		$crumbs[]       = self::crumb( $page['name'], $page['url'] );
		$page['crumbs'] = $crumbs;
		return $page;
	}

	/**
	 * Description of a post: SEO panel, then the excerpt, then the start
	 * of the content.
	 *
	 * @param int $post_id Post id.
	 */
	private static function description( int $post_id ): string {
		$custom = Meta::get( $post_id, Meta::DESCRIPTION );
		if ( '' !== $custom ) {
			return $custom;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || post_password_required( $post ) ) {
			return '';
		}
		$text = '' !== $post->post_excerpt ? $post->post_excerpt : excerpt_remove_blocks( strip_shortcodes( $post->post_content ) );
		// Keep a space between blocks: "Heading</h2><p>Text" must not become "HeadingText".
		$text = (string) preg_replace( '#(</(?:p|h[1-6]|li|dt|dd|div|blockquote|figcaption|td|th|pre)>|<br\s*/?>)#i', '$1 ', $text );
		return Graph::clip( html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Term ancestors and the term itself as crumbs.
	 *
	 * @param \WP_Term $term Queried term.
	 * @return list<array{name: string, url: string}>
	 */
	private static function term_trail( \WP_Term $term ): array {
		$crumbs = array();
		$ids    = array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );
		$ids[]  = $term->term_id;
		foreach ( $ids as $id ) {
			$item = get_term( (int) $id, $term->taxonomy );
			$link = $item instanceof \WP_Term ? get_term_link( $item ) : '';
			if ( $item instanceof \WP_Term && is_string( $link ) ) {
				$crumbs[] = self::crumb( $item->name, $link );
			}
		}
		return $crumbs;
	}

	/**
	 * A breadcrumb step.
	 *
	 * @param string $name Step name.
	 * @param string $url  Step URL.
	 * @return array{name: string, url: string}
	 */
	private static function crumb( string $name, string $url ): array {
		return array(
			'name' => $name,
			'url'  => $url,
		);
	}

	/**
	 * Adds the current page number to a list URL.
	 *
	 * @param string $url First page of the list.
	 */
	private static function paged( string $url ): string {
		$paged = (int) get_query_var( 'paged' );
		if ( '' === $url || $paged < 2 ) {
			return $url;
		}
		global $wp_rewrite;
		if ( $wp_rewrite instanceof \WP_Rewrite && $wp_rewrite->using_permalinks() ) {
			return trailingslashit( $url ) . user_trailingslashit( $wp_rewrite->pagination_base . '/' . $paged, 'paged' );
		}
		return add_query_arg( 'paged', $paged, $url );
	}

	/**
	 * Link of the queried date archive.
	 */
	private static function date_link(): string {
		$year = (int) get_query_var( 'year' );
		if ( is_day() ) {
			return (string) get_day_link( $year, (int) get_query_var( 'monthnum' ), (int) get_query_var( 'day' ) );
		}
		if ( is_month() ) {
			return (string) get_month_link( $year, (int) get_query_var( 'monthnum' ) );
		}
		return (string) get_year_link( $year );
	}

	/**
	 * Image data of an attachment, or an empty array.
	 *
	 * @param int $id Attachment id.
	 * @phpstan-return Image
	 * @return array<string, mixed>
	 */
	private static function attachment( int $id ): array {
		$src = $id ? wp_get_attachment_image_src( $id, 'full' ) : false;
		if ( ! $src ) {
			return array();
		}
		$image = array( 'url' => $src[0] );
		if ( $src[1] && $src[2] ) {
			$image['width']  = (int) $src[1];
			$image['height'] = (int) $src[2];
		}
		$alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		if ( '' !== $alt ) {
			$image['alt'] = $alt;
		}
		return $image;
	}

	/**
	 * Post title as plain text.
	 *
	 * @param int $post_id Post id.
	 */
	private static function post_title( int $post_id ): string {
		return html_entity_decode( wp_strip_all_tags( get_the_title( $post_id ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Site title as plain text.
	 */
	private static function site_name(): string {
		return html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Tagline as plain text.
	 */
	private static function tagline(): string {
		return html_entity_decode( (string) get_bloginfo( 'description' ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * The X username from the settings, with its @.
	 */
	private static function twitter_handle(): string {
		$handle = ltrim( trim( (string) Settings::get( 'seo.twitter_site' ) ), '@' );
		return preg_match( '/^\w{1,15}$/', $handle ) ? '@' . $handle : '';
	}
}
