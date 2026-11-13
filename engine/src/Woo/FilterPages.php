<?php
/**
 * Filter pages: listings of chosen product filters with an address, a title
 * and a place in search results of their own.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Seo\Detect;
use Pergel\Seo\Module as Seo;

use const Pergel\FILE;
use const Pergel\VERSION;

/**
 * A product category may name filter choices that deserve a page of their
 * own in search results, e.g. "SBS-format cooling blocks": the Cooling
 * blocks category filtered by Features: SBS format. Each such filter page
 *
 * - lives at an address under its category, the category's address plus
 *   its own slug (/product-category/cooling-blocks/sbs-format/), answered
 *   from the `request` filter, so no rewrite rules are added or flushed;
 *   a child category of the same slug keeps its address;
 * - has its own document title, heading, meta description and intro, in
 *   Pergel's own head tags and in those of Rank Math, Yoast SEO, All in One
 *   SEO, SEOPress and The SEO Framework, and a step in the breadcrumb;
 * - is the canonical address of the same choice made with the filters
 *   (?filter_features=automation-sbs on the category), which also shows the
 *   page's heading and intro;
 * - is listed in the sitemap (core's, Rank Math's, Yoast SEO's, All in One
 *   SEO's);
 * - shows its choice ticked in WooCommerce's Product Filters block, whose
 *   reset and untick lead back to the category.
 *
 * Every other filtered listing gets "noindex, follow" (pergel/seo/noindex):
 * countless filter combinations stay out of the index and only the chosen
 * pages go in.
 *
 * The pages are kept in the category's term meta and edited in its form
 * (FilterPagesFields). A theme that draws its own listing head puts
 * attributes() on it and marker() in it: the head is then redrawn with the
 * products when a visitor ticks a filter, and the address bar shows the
 * page's own address.
 *
 * @phpstan-type FilterPage array{slug: string, filters: array<string, string>, title: string, h1: string, description: string, intro: string}
 * @phpstan-type Current array{page: FilterPage, term: \WP_Term, url: string}
 */
final class FilterPages {

	/**
	 * Term meta of a product category: its filter pages.
	 */
	public const META = 'pergel_filter_pages';

	/**
	 * Script module and Interactivity API store of the listing head.
	 */
	public const SCRIPT = 'pergel/filter-pages';

	/**
	 * Router region of the listing head.
	 */
	public const REGION = 'pergel-filter-page';

	/**
	 * Name of the core sitemap provider: wp-sitemap-filterpages-1.xml.
	 */
	public const SITEMAP = 'filterpages';

	/**
	 * Most product ids products() gives: a page lists far fewer, and its
	 * numbers stay quick.
	 */
	public const MOST = 500;

	/**
	 * Address parameters of WooCommerce's product filters.
	 */
	private const PARAM = '/^(?:filter_[a-z0-9_-]+|query_type_[a-z0-9_-]+|min_price|max_price|rating_filter)$/';

	/**
	 * The filter page reached by its own address, found on `request`, and
	 * whether the address had filters of its own too.
	 *
	 * @var array{page: FilterPage, term: \WP_Term, more: bool}|null
	 */
	private static ?array $routed = null;

	/**
	 * The filter page shown, once known.
	 *
	 * @var array<string, mixed>|null
	 * @phpstan-var Current|null
	 */
	private static ?array $current = null;

	/**
	 * Whether current() has looked.
	 *
	 * @var bool
	 */
	private static bool $known = false;

	/**
	 * The request address while the Product Filters block draws, to put back.
	 *
	 * @var string|null
	 */
	private static ?string $uri = null;

	/**
	 * Product ids of filter pages, by address.
	 *
	 * @var array<string, list<int>>
	 */
	private static array $products = array();

	/**
	 * Adds the hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'meta' ) );
		if ( is_admin() ) {
			FilterPagesFields::register();
			return;
		}
		add_filter( 'request', array( self::class, 'request' ) );
		add_filter( 'redirect_canonical', array( self::class, 'redirect' ) );
		add_filter( Seo::NOINDEX, array( self::class, 'noindex' ) );
		add_filter( 'pre_render_block', array( self::class, 'before_filters' ), 10, 2 );
		add_filter( 'render_block_woocommerce/product-filters', array( self::class, 'after_filters' ), 1 );
		add_filter( 'get_the_archive_title', array( self::class, 'heading' ) );
		add_filter( 'term_description', array( self::class, 'intro' ), 10, 2 );
		add_action( 'init', array( self::class, 'register_script' ) );
		self::seo( Detect::active() );
	}

	/**
	 * Registers the category meta with its sanitize callback.
	 */
	public static function meta(): void {
		register_term_meta(
			'product_cat',
			self::META,
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( self::class, 'clean' ),
				'auth_callback'     => static fn (): bool => current_user_can( 'manage_product_terms' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Registers the script module of the listing head.
	 */
	public static function register_script(): void {
		wp_register_script_module(
			self::SCRIPT,
			plugins_url( 'assets/filter-pages.js', FILE ),
			array(
				array(
					'id'     => '@wordpress/interactivity',
					'import' => 'static',
				),
			),
			VERSION
		);
	}

	/**
	 * Adds the title, description, canonical, breadcrumb and sitemap hooks
	 * of the SEO plugin that prints them, or Pergel's own.
	 *
	 * @param string $plugin Plugin id from Detect, '' for none.
	 */
	private static function seo( string $plugin ): void {
		$hooks = match ( $plugin ) {
			''          => array(
				array( 'pre_get_document_title', 'document_title', 25, 1 ),
				array( 'pergel/seo/page', 'page', 10, 1 ),
			),
			'rank-math' => array(
				array( 'rank_math/frontend/title', 'title', 20, 1 ),
				array( 'rank_math/frontend/description', 'description', 20, 1 ),
				array( 'rank_math/frontend/canonical', 'canonical', 20, 1 ),
				array( 'rank_math/frontend/breadcrumb/items', 'rank_math_crumbs', 5, 1 ),
				array( 'rank_math/sitemap/product_cat_content', 'sitemap_xml', 10, 1 ),
			),
			'yoast'     => array(
				array( 'wpseo_title', 'title', 20, 1 ),
				array( 'wpseo_opengraph_title', 'title', 20, 1 ),
				array( 'wpseo_metadesc', 'description', 20, 1 ),
				array( 'wpseo_opengraph_desc', 'description', 20, 1 ),
				array( 'wpseo_canonical', 'canonical', 20, 1 ),
				array( 'wpseo_opengraph_url', 'canonical', 20, 1 ),
				array( 'wpseo_breadcrumb_links', 'yoast_crumbs', 20, 1 ),
				array( 'wpseo_sitemap_product_cat_content', 'sitemap_xml', 10, 1 ),
			),
			'aioseo'    => array(
				array( 'aioseo_title', 'title', 20, 1 ),
				array( 'aioseo_description', 'description', 20, 1 ),
				array( 'aioseo_canonical_url', 'canonical', 20, 1 ),
				array( 'aioseo_sitemap_additional_pages', 'sitemap_entries', 10, 1 ),
			),
			'seopress'  => array(
				array( 'seopress_titles_title', 'title', 20, 1 ),
				array( 'seopress_titles_desc', 'description', 20, 1 ),
				array( 'seopress_titles_canonical', 'canonical_tag', 20, 1 ),
			),
			'tsf'       => array(
				array( 'the_seo_framework_title_from_custom_field', 'tsf_title', 20, 2 ),
				array( 'the_seo_framework_use_title_branding', 'tsf_branding', 20, 2 ),
				array( 'the_seo_framework_custom_field_description', 'tsf_description', 20, 2 ),
				array( 'the_seo_framework_meta_render_data', 'tsf_tags', 20, 1 ),
			),
			default     => array(),
		};
		foreach ( $hooks as [ $hook, $method, $priority, $args ] ) {
			add_filter( $hook, array( self::class, $method ), $priority, $args );
		}
		if ( '' === $plugin ) {
			// Core starts its sitemaps on every request; the provider (a file
			// of its own) only joins on a sitemap address.
			add_action( 'parse_request', array( self::class, 'sitemap_request' ) );
		}
	}

	/**
	 * Answers a filter page's address: the category with the page's filters.
	 * Filter for `request`.
	 *
	 * @param array<string, mixed> $vars Query variables from the address.
	 * @return array<string, mixed>
	 */
	public static function request( array $vars ): array {
		$path = isset( $vars['product_cat'] ) && is_string( $vars['product_cat'] ) ? trim( $vars['product_cat'], '/' ) : '';
		$cut  = strrpos( $path, '/' );
		if ( false === $cut ) {
			return $vars;
		}
		$slug   = substr( $path, $cut + 1 );
		$parent = substr( $path, 0, $cut );
		$term   = get_term_by( 'slug', wp_basename( $parent ), 'product_cat' );
		if ( ! $term instanceof \WP_Term || ! self::at( $term, $parent ) || term_exists( $slug, 'product_cat', $term->term_id ) ) {
			return $vars;
		}
		$page = self::find( $term->term_id, $slug );
		if ( null === $page ) {
			return $vars;
		}
		self::$routed        = array(
			'page' => $page,
			'term' => $term,
			'more' => array() !== self::params( self::query() ),
		);
		$vars['product_cat'] = $parent;
		// Query variables for the Product Collection block, the query for
		// WooCommerce's classic layered navigation (WC_Query reads $_GET).
		foreach ( $page['filters'] as $key => $value ) {
			$vars[ $key ] = $value;
			$_GET[ $key ] = $value;
		}
		return $vars;
	}

	/**
	 * Whether a category's address ends in a path.
	 *
	 * @param \WP_Term $term Category.
	 * @param string   $path Slugs from the address, parents first.
	 */
	private static function at( \WP_Term $term, string $path ): bool {
		$link = get_term_link( $term );
		$own  = is_string( $link ) ? trim( (string) wp_parse_url( $link, PHP_URL_PATH ), '/' ) : '';
		return '' !== $own && str_ends_with( '/' . $own, '/' . $path );
	}

	/**
	 * Keeps WordPress from sending a filter page's address to the category.
	 *
	 * @param mixed $redirect Address WordPress would redirect to.
	 * @return mixed
	 */
	public static function redirect( mixed $redirect ): mixed {
		return null !== self::$routed ? false : $redirect;
	}

	/**
	 * The filter page shown: reached by its address (without further
	 * filters), or the same choice made with the filters on its category.
	 *
	 * @return array<string, mixed>|null
	 * @phpstan-return Current|null
	 */
	public static function current(): ?array {
		if ( self::$known ) {
			return self::$current;
		}
		$current = self::find_current();
		// Kept once the main query has run; asked earlier, it is looked up again.
		if ( did_action( 'wp' ) ) {
			self::$known   = true;
			self::$current = $current;
		}
		return $current;
	}

	/**
	 * Looks for the filter page shown.
	 *
	 * @return array<string, mixed>|null
	 * @phpstan-return Current|null
	 */
	private static function find_current(): ?array {
		if ( null !== self::$routed ) {
			return self::$routed['more'] ? null : self::with_url( self::$routed['page'], self::$routed['term'] );
		}
		$params = self::params( self::query() );
		$term   = function_exists( 'is_product_category' ) && is_product_category() ? get_queried_object() : null;
		if ( ! $term instanceof \WP_Term || array() === $params ) {
			return null;
		}
		$key = self::key( $params );
		foreach ( self::pages( $term->term_id ) as $page ) {
			if ( self::key( $page['filters'] ) === $key ) {
				return self::with_url( $page, $term );
			}
		}
		return null;
	}

	/**
	 * A filter page with its address, or null without pretty permalinks.
	 *
	 * @param array<string, mixed> $page Filter page.
	 * @param \WP_Term             $term Its category.
	 * @phpstan-param FilterPage $page
	 * @return array<string, mixed>|null
	 * @phpstan-return Current|null
	 */
	private static function with_url( array $page, \WP_Term $term ): ?array {
		$url = self::url( $term, $page['slug'] );
		return '' === $url ? null : array(
			'page' => $page,
			'term' => $term,
			'url'  => $url,
		);
	}

	/**
	 * Address of a filter page: its category's plus its slug; '' while
	 * the category's address has a query (plain permalinks).
	 *
	 * @param \WP_Term $term Category.
	 * @param string   $slug Filter page slug.
	 */
	public static function url( \WP_Term $term, string $slug ): string {
		$link = get_term_link( $term );
		if ( ! is_string( $link ) || str_contains( $link, '?' ) ) {
			return '';
		}
		return user_trailingslashit( trailingslashit( $link ) . $slug );
	}

	/**
	 * An address with the page number of the listing shown.
	 *
	 * @param string $url Address of the first page.
	 */
	private static function paged( string $url ): string {
		$paged = (int) get_query_var( 'paged' );
		return $paged > 1 ? user_trailingslashit( trailingslashit( $url ) . 'page/' . $paged, 'paged' ) : $url;
	}

	/**
	 * The filter pages of a category.
	 *
	 * @param int $term_id Category id.
	 * @return list<array<string, mixed>>
	 * @phpstan-return list<FilterPage>
	 */
	public static function pages( int $term_id ): array {
		return self::clean( get_term_meta( $term_id, self::META, true ) );
	}

	/**
	 * One filter page of a category.
	 *
	 * @param int    $term_id Category id.
	 * @param string $slug    Filter page slug.
	 * @return array<string, mixed>|null
	 * @phpstan-return FilterPage|null
	 */
	private static function find( int $term_id, string $slug ): ?array {
		foreach ( self::pages( $term_id ) as $page ) {
			if ( $page['slug'] === $slug ) {
				return $page;
			}
		}
		return null;
	}

	/**
	 * Filter pages as they may be stored: a slug and at least one filter
	 * each, slugs unique and not "page" (the listing's page numbers).
	 *
	 * @param mixed $value Rows, each with slug, filters (a map, or the
	 *                     filtered listing's address or query), title, h1,
	 *                     description and intro.
	 * @return list<array<string, mixed>>
	 * @phpstan-return list<FilterPage>
	 */
	public static function clean( mixed $value ): array {
		$pages = array();
		$seen  = array();
		foreach ( is_array( $value ) ? $value : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slug    = sanitize_title( is_string( $row['slug'] ?? null ) ? $row['slug'] : '' );
			$filters = self::filters( $row['filters'] ?? '' );
			if ( '' === $slug || 'page' === $slug || isset( $seen[ $slug ] ) || ! array_diff_key( $filters, self::types( $filters ) ) ) {
				continue;
			}
			$seen[ $slug ] = true;
			$pages[]       = array(
				'slug'        => $slug,
				'filters'     => $filters,
				'title'       => sanitize_text_field( is_string( $row['title'] ?? null ) ? $row['title'] : '' ),
				'h1'          => sanitize_text_field( is_string( $row['h1'] ?? null ) ? $row['h1'] : '' ),
				'description' => sanitize_text_field( is_string( $row['description'] ?? null ) ? $row['description'] : '' ),
				'intro'       => sanitize_textarea_field( is_string( $row['intro'] ?? null ) ? $row['intro'] : '' ),
			);
		}
		return $pages;
	}

	/**
	 * Filter parameters from a map, or from a filtered listing's address or
	 * its query ("filter_features=automation-sbs").
	 *
	 * @param mixed $value Map or text.
	 * @return array<string, string>
	 */
	public static function filters( mixed $value ): array {
		if ( is_string( $value ) ) {
			$query = trim( html_entity_decode( $value, ENT_QUOTES ) );
			$mark  = strpos( $query, '?' );
			$query = false === $mark ? $query : substr( $query, $mark + 1 );
			$query = explode( '#', $query, 2 )[0];
			parse_str( $query, $parsed );
			$value = $parsed;
		}
		$filters = array();
		foreach ( self::params( is_array( $value ) ? $value : array() ) as $key => $text ) {
			$list = array_filter( explode( ',', (string) preg_replace( '/[^a-z0-9_,.%-]/', '', strtolower( $text ) ) ), static fn ( string $part ): bool => '' !== $part );
			if ( $list ) {
				$filters[ $key ] = implode( ',', $list );
			}
		}
		return $filters;
	}

	/**
	 * The filter parameters of a query, sorted by name.
	 *
	 * @param array<mixed> $query Query variables.
	 * @return array<string, string>
	 */
	private static function params( array $query ): array {
		$params = array();
		foreach ( $query as $key => $value ) {
			if ( is_string( $key ) && is_string( $value ) && '' !== trim( $value ) && preg_match( self::PARAM, $key ) ) {
				$params[ $key ] = sanitize_text_field( $value );
			}
		}
		ksort( $params );
		return $params;
	}

	/**
	 * The query_type_ parameters (AND or OR) among filter parameters.
	 *
	 * @param array<string, string> $params Filter parameters.
	 * @return array<string, string>
	 */
	private static function types( array $params ): array {
		return array_filter( $params, static fn ( string $key ): bool => str_starts_with( $key, 'query_type_' ), ARRAY_FILTER_USE_KEY );
	}

	/**
	 * What a set of filters chooses, comparable whatever the order of the
	 * parameters and of the values in them. AND or OR does not count.
	 *
	 * @param array<string, string> $params Filter parameters.
	 */
	private static function key( array $params ): string {
		$parts = array();
		foreach ( array_diff_key( $params, self::types( $params ) ) as $key => $value ) {
			$values = array_filter( array_map( 'trim', explode( ',', strtolower( $value ) ) ), static fn ( string $part ): bool => '' !== $part );
			sort( $values );
			$parts[ $key ] = implode( ',', $values );
		}
		ksort( $parts );
		return (string) wp_json_encode( $parts );
	}

	/**
	 * The query of the request.
	 *
	 * @return array<mixed>
	 */
	private static function query(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reads which filters a visitor chose; nothing is changed.
		return wp_unslash( $_GET );
	}

	/**
	 * Keeps filtered listings other than the filter pages out of search
	 * results. Filter for pergel/seo/noindex.
	 *
	 * @param mixed $noindex Earlier answer.
	 */
	public static function noindex( mixed $noindex ): bool {
		if ( true === $noindex ) {
			return true;
		}
		if ( null !== self::$routed ) {
			return self::$routed['more'];
		}
		if ( array() === self::params( self::query() ) ) {
			return false;
		}
		return self::listing() && null === self::current();
	}

	/**
	 * Whether the page shown lists products: the shop, a product category,
	 * tag or attribute, or a page with the Product Filters block.
	 */
	private static function listing(): bool {
		if ( Layout::listing() ) {
			return true;
		}
		$post = is_singular() ? get_queried_object() : null;
		return $post instanceof \WP_Post && has_block( 'woocommerce/product-filters', $post );
	}

	/**
	 * While the Product Filters block of a filter page's address draws, the
	 * request address is the category with the page's filters: WooCommerce
	 * reads the ticked choices from it, and builds the address its untick
	 * and reset lead to (the category) from it.
	 *
	 * @param mixed                $pre   Output from earlier filters, null to draw.
	 * @param array<string, mixed> $block Parsed block.
	 * @return mixed
	 */
	public static function before_filters( mixed $pre, array $block ): mixed {
		if ( null !== $pre || 'woocommerce/product-filters' !== ( $block['blockName'] ?? '' ) || null !== self::$uri || null === self::$routed ) {
			return $pre;
		}
		$current = self::current();
		$link    = $current ? get_term_link( $current['term'] ) : '';
		if ( ! $current || ! is_string( $link ) ) {
			return $pre;
		}
		self::$uri = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Put back as it was after the block.

		$_SERVER['REQUEST_URI'] = (string) wp_parse_url( $link, PHP_URL_PATH ) . '?' . build_query( $current['page']['filters'] );
		return $pre;
	}

	/**
	 * Puts the request address back after the Product Filters block.
	 *
	 * @param mixed $html Block markup.
	 * @return mixed
	 */
	public static function after_filters( mixed $html ): mixed {
		if ( null !== self::$uri ) {
			$_SERVER['REQUEST_URI'] = self::$uri;
			self::$uri              = null;
		}
		return $html;
	}

	/**
	 * The page's heading as the archive title (the Query Title block).
	 *
	 * @param mixed $title Archive title.
	 * @return mixed
	 */
	public static function heading( mixed $title ): mixed {
		$current = self::current();
		return $current && '' !== $current['page']['h1'] ? esc_html( $current['page']['h1'] ) : $title;
	}

	/**
	 * The page's intro as the category description (the Term Description
	 * block), on the page only.
	 *
	 * @param mixed $description Description.
	 * @param mixed $term_id     Term id.
	 * @return mixed
	 */
	public static function intro( mixed $description, mixed $term_id = 0 ): mixed {
		$current = self::current();
		if ( ! $current || '' === $current['page']['intro'] || (int) $term_id !== $current['term']->term_id ) {
			return $description;
		}
		return wpautop( esc_html( $current['page']['intro'] ) );
	}

	/**
	 * The document title: the page's own, else its heading and the site
	 * name; with the page number after the first page.
	 *
	 * @param array<string, mixed> $current Filter page shown.
	 * @phpstan-param Current $current
	 */
	private static function title_text( array $current ): string {
		$page  = $current['page'];
		$title = '' !== $page['title'] ? $page['title'] : ( '' !== $page['h1'] ? $page['h1'] . ' – ' . wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) : '' );
		$paged = (int) get_query_var( 'paged' );
		if ( '' !== $title && $paged > 1 ) {
			/* translators: %s: page number. */
			$title .= ' – ' . sprintf( __( 'Page %s', 'pergel-engine' ), number_format_i18n( $paged ) );
		}
		return $title;
	}

	/**
	 * The document title without an SEO plugin.
	 *
	 * @param mixed $title Title from earlier filters.
	 * @return mixed
	 */
	public static function document_title( mixed $title ): mixed {
		$current = self::current();
		$text    = $current ? self::title_text( $current ) : '';
		return '' !== $text ? esc_html( $text ) : $title;
	}

	/**
	 * An SEO plugin's title.
	 *
	 * @param mixed $title Title.
	 * @return mixed
	 */
	public static function title( mixed $title ): mixed {
		$current = self::current();
		$text    = $current ? self::title_text( $current ) : '';
		return '' !== $text ? $text : $title;
	}

	/**
	 * An SEO plugin's meta description.
	 *
	 * @param mixed $description Description.
	 * @return mixed
	 */
	public static function description( mixed $description ): mixed {
		$current = self::current();
		return $current && '' !== $current['page']['description'] ? $current['page']['description'] : $description;
	}

	/**
	 * An SEO plugin's canonical address.
	 *
	 * @param mixed $url Address.
	 * @return mixed
	 */
	public static function canonical( mixed $url ): mixed {
		$current = self::current();
		return $current ? self::paged( $current['url'] ) : $url;
	}

	/**
	 * SEOPress's canonical link tag.
	 *
	 * @param mixed $tag Link tag.
	 * @return mixed
	 */
	public static function canonical_tag( mixed $tag ): mixed {
		$current = self::current();
		return $current ? '<link rel="canonical" href="' . esc_url( self::paged( $current['url'] ) ) . '">' : $tag;
	}

	/**
	 * The SEO Framework's title of the page shown (no query arguments).
	 *
	 * @param mixed $title Title.
	 * @param mixed $args  Query arguments, null for the page shown.
	 * @return mixed
	 */
	public static function tsf_title( mixed $title, mixed $args = null ): mixed {
		return null === $args ? self::title( $title ) : $title;
	}

	/**
	 * The SEO Framework adds no site name to a filter page's own title.
	 *
	 * @param mixed $branding Whether to add it.
	 * @param mixed $args     Query arguments, null for the page shown.
	 * @return mixed
	 */
	public static function tsf_branding( mixed $branding, mixed $args = null ): mixed {
		$current = null === $args ? self::current() : null;
		return $current && '' !== $current['page']['title'] ? false : $branding;
	}

	/**
	 * The SEO Framework's description of the page shown.
	 *
	 * @param mixed $description Description.
	 * @param mixed $args        Query arguments, null for the page shown.
	 * @return mixed
	 */
	public static function tsf_description( mixed $description, mixed $args = null ): mixed {
		return null === $args ? self::description( $description ) : $description;
	}

	/**
	 * The SEO Framework's canonical and og:url tags.
	 *
	 * @param mixed $tags Render data of the tags, by id.
	 * @return mixed
	 */
	public static function tsf_tags( mixed $tags ): mixed {
		$current = self::current();
		if ( ! $current || ! is_array( $tags ) ) {
			return $tags;
		}
		$url = self::paged( $current['url'] );
		if ( isset( $tags['canonical']['attributes'] ) && is_array( $tags['canonical']['attributes'] ) ) {
			$tags['canonical']['attributes']['href'] = $url;
		}
		if ( isset( $tags['og:url']['attributes'] ) && is_array( $tags['og:url']['attributes'] ) ) {
			$tags['og:url']['attributes']['content'] = $url;
		}
		return $tags;
	}

	/**
	 * Pergel's own page data (no SEO plugin): address, name, description,
	 * and the page as the last step of the trail. Filter for pergel/seo/page.
	 *
	 * @param mixed $page Page data.
	 * @return mixed
	 */
	public static function page( mixed $page ): mixed {
		$current = self::current();
		if ( ! $current || ! is_array( $page ) ) {
			return $page;
		}
		$page['url'] = self::paged( $current['url'] );
		if ( '' !== $current['page']['h1'] ) {
			$page['name'] = $current['page']['h1'];
		}
		if ( '' !== $current['page']['description'] ) {
			$page['description'] = $current['page']['description'];
		}
		if ( isset( $page['crumbs'] ) && is_array( $page['crumbs'] ) && array() !== $page['crumbs'] ) {
			$page['crumbs'][] = array(
				'name' => (string) $page['name'],
				'url'  => $current['url'],
			);
		}
		return $page;
	}

	/**
	 * The page as the last step of Rank Math's trail; the category before
	 * it gets its link.
	 *
	 * @param mixed $crumbs Steps: [name, address, ...].
	 * @return mixed
	 */
	public static function rank_math_crumbs( mixed $crumbs ): mixed {
		$current = self::current();
		if ( ! $current || ! is_array( $crumbs ) || array() === $crumbs ) {
			return $crumbs;
		}
		$key  = array_key_last( $crumbs );
		$link = get_term_link( $current['term'] );
		if ( is_array( $crumbs[ $key ] ) && empty( $crumbs[ $key ][1] ) && is_string( $link ) ) {
			$crumbs[ $key ][1] = $link;
		}
		$crumbs[] = array( self::name( $current ), $current['url'] );
		return $crumbs;
	}

	/**
	 * The page as the last step of Yoast SEO's trail.
	 *
	 * @param mixed $crumbs Steps with text and url.
	 * @return mixed
	 */
	public static function yoast_crumbs( mixed $crumbs ): mixed {
		$current = self::current();
		if ( ! $current || ! is_array( $crumbs ) || array() === $crumbs ) {
			return $crumbs;
		}
		$crumbs[] = array(
			'text' => self::name( $current ),
			'url'  => $current['url'],
		);
		return $crumbs;
	}

	/**
	 * The name of the page shown: its heading, else its category's name.
	 *
	 * @param array<string, mixed> $current Filter page shown.
	 * @phpstan-param Current $current
	 */
	private static function name( array $current ): string {
		return '' !== $current['page']['h1'] ? $current['page']['h1'] : $current['term']->name;
	}

	/**
	 * Addresses of every filter page, for the sitemaps.
	 *
	 * @return list<string>
	 */
	public static function addresses(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'meta_key'   => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Only while a sitemap is built.
			)
		);
		$urls  = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			foreach ( self::pages( $term->term_id ) as $page ) {
				$url = self::url( $term, $page['slug'] );
				if ( '' !== $url ) {
					$urls[] = $url;
				}
			}
		}
		return $urls;
	}

	/**
	 * Adds the filter pages to an XML sitemap of product categories (Rank
	 * Math, Yoast SEO).
	 *
	 * @param mixed $xml Extra XML.
	 * @return mixed
	 */
	public static function sitemap_xml( mixed $xml ): mixed {
		if ( ! is_string( $xml ) ) {
			return $xml;
		}
		foreach ( self::addresses() as $url ) {
			$xml .= "\t<url>\n\t\t<loc>" . esc_url( $url ) . "</loc>\n\t</url>\n";
		}
		return $xml;
	}

	/**
	 * Adds the filter pages to All in One SEO's sitemap.
	 *
	 * @param mixed $entries Extra entries.
	 * @return mixed
	 */
	public static function sitemap_entries( mixed $entries ): mixed {
		if ( ! is_array( $entries ) ) {
			return $entries;
		}
		foreach ( self::addresses() as $url ) {
			$entries[] = array(
				'loc'        => $url,
				'lastmod'    => '',
				'changefreq' => 'weekly',
				'priority'   => 0.6,
			);
		}
		return $entries;
	}

	/**
	 * Adds the filter pages to core's sitemap when a sitemap is asked for.
	 * Action for `parse_request`; core draws it on template_redirect.
	 *
	 * @param \WP $wp The request.
	 */
	public static function sitemap_request( \WP $wp ): void {
		if ( ! empty( $wp->query_vars['sitemap'] ) && function_exists( 'wp_sitemaps_get_server' ) ) {
			self::core_sitemap( wp_sitemaps_get_server() );
		}
	}

	/**
	 * Adds the filter pages to core's sitemap.
	 *
	 * @param mixed $server Core's sitemap server.
	 */
	public static function core_sitemap( mixed $server ): void {
		if ( $server instanceof \WP_Sitemaps && class_exists( \WP_Sitemaps_Provider::class ) ) {
			$server->registry->add_provider( self::SITEMAP, new FilterPagesSitemap() );
		}
	}

	/**
	 * Whether the listing head takes part: a category with filter pages.
	 */
	private static function here(): bool {
		if ( null !== self::$routed ) {
			return true;
		}
		$term = function_exists( 'is_product_category' ) && is_product_category() ? get_queried_object() : null;
		return $term instanceof \WP_Term && array() !== self::pages( $term->term_id );
	}

	/**
	 * Attributes for the element around a theme's listing head (heading,
	 * intro, breadcrumb): the router redraws it with the products, so a
	 * ticked filter that makes a filter page shows that page's heading. ''
	 * on pages without filter pages; loads the script module otherwise.
	 */
	public static function attributes(): string {
		if ( ! self::here() ) {
			return '';
		}
		wp_enqueue_script_module( self::SCRIPT );
		return sprintf( ' data-wp-interactive="%1$s" data-wp-router-region="%2$s"', esc_attr( self::SCRIPT ), esc_attr( self::REGION ) );
	}

	/**
	 * A hidden mark for inside that element: drawn anew with each filter
	 * page, it puts the page's own address in the address bar. '' on pages
	 * without filter pages.
	 */
	public static function marker(): string {
		if ( ! self::here() ) {
			return '';
		}
		$current = self::current();
		$url     = $current ? self::paged( $current['url'] ) : '';
		return sprintf(
			'<span hidden data-wp-key="%1$s" data-wp-init="callbacks.address" data-pergel-url="%2$s"></span>',
			esc_attr( '' === $url ? 'pergel-category' : md5( $url ) ),
			esc_url( $url )
		);
	}

	/**
	 * Ids of the products a filter page lists: those in its category (and
	 * the categories under it) with its attribute filters, visible in the
	 * catalogue; at most MOST of them. Price and rating filters are not
	 * counted.
	 *
	 * @param array<string, mixed> $current Filter page.
	 * @phpstan-param Current $current
	 * @return list<int>
	 */
	public static function products( array $current ): array {
		if ( isset( self::$products[ $current['url'] ] ) ) {
			return self::$products[ $current['url'] ];
		}
		$tax = array(
			'relation' => 'AND',
			array(
				'taxonomy' => 'product_cat',
				'terms'    => array( $current['term']->term_id ),
			),
		);
		foreach ( $current['page']['filters'] as $key => $value ) {
			if ( ! str_starts_with( $key, 'filter_' ) ) {
				continue;
			}
			$taxonomy = 'pa_' . substr( $key, 7 );
			if ( taxonomy_exists( $taxonomy ) ) {
				$tax[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => explode( ',', $value ),
					'operator' => 'and' === ( $current['page']['filters'][ 'query_type_' . substr( $key, 7 ) ] ?? '' ) ? 'AND' : 'IN',
				);
			}
		}
		$hidden = array( 'exclude-from-catalog' );
		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$hidden[] = 'outofstock';
		}
		$tax[] = array(
			'taxonomy' => 'product_visibility',
			'field'    => 'name',
			'terms'    => $hidden,
			'operator' => 'NOT IN',
		);
		$ids   = get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => 'publish',
				'posts_per_page'   => self::MOST,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'tax_query'        => $tax, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- One query per filter page shown.
			)
		);

		self::$products[ $current['url'] ] = array_values( array_map( 'intval', $ids ) );
		return self::$products[ $current['url'] ];
	}

	/**
	 * Forgets what this request found. For tests.
	 */
	public static function flush(): void {
		self::$routed   = null;
		self::$current  = null;
		self::$known    = false;
		self::$uri      = null;
		self::$products = array();
	}
}
