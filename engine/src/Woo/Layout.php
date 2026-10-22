<?php
/**
 * Shop listing layout from the settings.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Schema;
use Pergel\Settings\Settings;

use const Pergel\FILE;
use const Pergel\VERSION;

/**
 * Applies the "shop" settings to the product listing pages: the shop, product
 * categories, product tags and product search. The templates stay plain
 * block templates (K10); a setting reaches the main product grid (the
 * Product Collection block that inherits the page's query) while it renders.
 *
 * Every default ("template", "auto", "wide", 0) leaves the page as the
 * template draws it, so a template edited in the Site Editor, or a child
 * theme's own template, keeps its look until someone picks a value. A
 * product category may choose its own values (term meta, see
 * LayoutFields); its subcategories follow it.
 */
final class Layout {

	public const TERM_META = 'pergel_shop';

	public const STYLE = 'pergel-shop-layout';

	public const CARD_STYLE = 'pergel-shop-cards';

	/**
	 * Script module of the cards (quick view, countdown); named here, like
	 * LISTING_SCRIPT.
	 */
	public const CARDS_SCRIPT = 'pergel/shop-cards';

	/**
	 * Script module of Listing; named here so registering it does not load
	 * that class on every page.
	 */
	public const LISTING_SCRIPT = 'pergel/shop-listing';

	/**
	 * Templates that hold the main product grid.
	 */
	private const TEMPLATES = array( 'archive-product', 'taxonomy-product_cat', 'taxonomy-product_tag', 'product-search-results' );

	/**
	 * A category's own values for this request, by term id.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static array $own = array();

	/**
	 * Adds the hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'meta' ) );
		add_action( 'updated_term_meta', array( self::class, 'flush' ) );
		add_action( 'added_term_meta', array( self::class, 'flush' ) );
		add_action( 'deleted_term_meta', array( self::class, 'flush' ) );
		// Also for the editors, so the theme's filter pattern looks the same there.
		add_filter( 'pergel/shop/filters', array( Filters::class, 'groups' ) );
		if ( is_admin() ) {
			LayoutFields::register();
			BadgeField::register();
			return;
		}
		Filters::register();
		add_action( 'init', array( self::class, 'register_style' ) );
		// Listing and ShopHeader load only on the pages that use them.
		add_filter( 'render_block_woocommerce/catalog-sorting', array( Listing::class, 'toolbar' ) );
		add_filter( 'render_block_core/term-description', array( self::class, 'above' ) );
		add_filter( 'render_block_core/columns', array( self::class, 'before_layout' ), 20, 2 );
		add_filter( 'wp_robots', array( self::class, 'robots' ) );
		add_action( 'wp', array( self::class, 'cards' ) );
		add_action( 'rest_api_init', array( QuickView::class, 'routes' ) );
		add_filter( 'loop_shop_per_page', array( self::class, 'per_page' ), 20 );
		add_filter( 'render_block_context', array( self::class, 'context' ), 10, 3 );
		add_filter( 'render_block_woocommerce/product-collection', array( self::class, 'grid' ), 10, 2 );
		add_filter( 'body_class', array( self::class, 'body_class' ) );
	}

	/**
	 * Registers the category meta with its sanitize callback.
	 */
	public static function meta(): void {
		register_term_meta(
			'product_cat',
			self::TERM_META,
			array(
				'type'              => 'object',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => static fn ( mixed $value ): array => Schema::level_values( $value, 'category' ),
				'auth_callback'     => static fn (): bool => current_user_can( 'manage_product_terms' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Registers the layout CSS; WordPress inlines it while its inline budget
	 * allows.
	 */
	public static function register_style(): void {
		wp_register_style( self::STYLE, plugins_url( 'assets/shop-layout.css', FILE ), array(), VERSION );
		wp_style_add_data( self::STYLE, 'path', dirname( FILE ) . '/assets/shop-layout.css' );
		wp_register_style( self::CARD_STYLE, plugins_url( 'assets/shop-cards.css', FILE ), array(), VERSION );
		wp_register_script_module( self::CARDS_SCRIPT, plugins_url( 'assets/shop-cards.js', FILE ), array(), VERSION, array( 'fetchpriority' => 'low' ) );
		wp_style_add_data( self::CARD_STYLE, 'path', dirname( FILE ) . '/assets/shop-cards.css' );
		wp_register_script_module(
			self::LISTING_SCRIPT,
			plugins_url( 'assets/shop-listing.js', FILE ),
			array(
				array(
					'id'     => '@wordpress/interactivity-router',
					'import' => 'dynamic',
				),
			),
			VERSION,
			array( 'fetchpriority' => 'low' )
		);
	}

	/**
	 * The banner and category strip after the term description, on listing
	 * pages only (blog archives use the same block).
	 *
	 * @param string $html Rendered term description.
	 */
	public static function above( string $html ): string {
		return self::listing() ? ShopHeader::render( $html ) : $html;
	}

	/**
	 * The banner and category strip before the shop layout columns, when
	 * the template draws its term description only after the products.
	 *
	 * @param string               $html  Rendered columns.
	 * @param array<string, mixed> $block Parsed block.
	 */
	public static function before_layout( string $html, array $block ): string {
		$classes = $block['attrs']['className'] ?? '';
		if ( ! is_string( $classes ) || ! in_array( 'pergel-shop-layout', explode( ' ', $classes ), true ) || ! self::listing() ) {
			return $html;
		}
		return ShopHeader::before( $html );
	}

	/**
	 * Robots directives for pages with a visitor's products per page.
	 *
	 * @param array<string, bool|string> $robots Robots directives.
	 * @return array<string, bool|string>
	 */
	public static function robots( array $robots ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checks that the argument is there.
		return isset( $_GET['per_page'] ) && self::listing() ? Listing::robots( $robots ) : $robots;
	}

	/**
	 * Loads the layout CSS on this page. Called wherever a setting adds one
	 * of its classes; the shop templates render before the head is printed.
	 */
	public static function style(): void {
		wp_enqueue_style( self::STYLE );
	}

	/**
	 * Loads the card CSS on this page; Cards calls it.
	 */
	public static function card_style(): void {
		wp_enqueue_style( self::CARD_STYLE );
	}

	/**
	 * Adds the card hooks where they can change something: on listing pages
	 * (a category may choose a card style) and wherever a card setting was
	 * chosen. Other pages, product pages with the defaults among them, never
	 * load Cards. Hooked on wp, when the page is known.
	 */
	public static function cards(): void {
		if ( self::listing() || self::cards_chosen() ) {
			Cards::register();
		}
	}

	/**
	 * Whether a site-wide card or badge setting differs from its default.
	 */
	private static function cards_chosen(): bool {
		foreach ( Schema::defaults()['shop'] ?? array() as $key => $default ) {
			if ( ( str_starts_with( $key, 'card_' ) || str_starts_with( $key, 'badge_' ) || 'quick_view' === $key ) && Settings::get( "shop.$key" ) !== $default ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Drops the request cache when a category's values change.
	 */
	public static function flush(): void {
		self::$own = array();
	}

	/**
	 * Whether this request shows a product listing.
	 */
	public static function listing(): bool {
		if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) {
			return true;
		}
		$type = get_query_var( 'post_type' );
		return is_search() && ( 'product' === $type || array( 'product' ) === $type );
	}

	/**
	 * The value of a level setting for this page: the nearest category that
	 * chose one, else the site setting.
	 *
	 * @param string $path "group.key".
	 */
	public static function get( string $path ): mixed {
		foreach ( self::chain() as $term_id ) {
			$own = self::own( $term_id );
			if ( array_key_exists( $path, $own ) ) {
				return $own[ $path ];
			}
		}
		return Settings::get( $path );
	}

	/**
	 * The product category on screen and its ancestors, nearest first.
	 *
	 * @return list<int>
	 */
	private static function chain(): array {
		$term = is_tax( 'product_cat' ) ? get_queried_object() : null;
		if ( ! $term instanceof \WP_Term ) {
			return array();
		}
		$ids = array_values( array_merge( array( $term->term_id ), array_map( 'intval', get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) ) ) );
		update_termmeta_cache( $ids );
		return $ids;
	}

	/**
	 * A category's own values.
	 *
	 * @param int $term_id Category id.
	 * @return array<string, mixed>
	 */
	public static function own( int $term_id ): array {
		if ( ! isset( self::$own[ $term_id ] ) ) {
			self::$own[ $term_id ] = Schema::level_values( get_term_meta( $term_id, self::TERM_META, true ), 'category' );
		}
		return self::$own[ $term_id ];
	}

	/**
	 * Products per page, then a visitor's choice (Listing). WooCommerce
	 * reads it while it prepares the main query, when the queried category
	 * is already known.
	 *
	 * @param mixed $per_page WooCommerce's number.
	 */
	public static function per_page( mixed $per_page ): mixed {
		$chosen = self::get( 'shop.per_page' );
		if ( is_int( $chosen ) && $chosen > 0 ) {
			$per_page = $chosen;
		}
		return is_int( $per_page ) && $per_page > 0 && self::listing() ? Listing::per_page( $per_page ) : $per_page;
	}

	/**
	 * Whether a parsed block is the main product grid.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 */
	private static function main_grid( array $block ): bool {
		return 'woocommerce/product-collection' === ( $block['blockName'] ?? '' )
			&& true === ( $block['attrs']['query']['inherit'] ?? null );
	}

	/**
	 * Columns on wide screens. WordPress builds a block's children with the
	 * context of the block as parsed, so the number goes to the Product
	 * Template's context, which draws the grid.
	 *
	 * @param array<string, mixed> $context      Context of the block about to render.
	 * @param array<string, mixed> $block        Parsed block about to render.
	 * @param \WP_Block|null       $parent_block Its parent.
	 * @return array<string, mixed>
	 */
	public static function context( array $context, array $block, ?\WP_Block $parent_block = null ): array {
		if ( 'woocommerce/product-template' !== ( $block['blockName'] ?? '' ) || null === $parent_block || ! self::main_grid( $parent_block->parsed_block ) || ! self::listing() ) {
			return $context;
		}
		Listing::prepare();
		$columns = self::get( 'shop.columns' );
		if ( is_string( $columns ) && ctype_digit( $columns ) ) {
			$layout                   = is_array( $context['displayLayout'] ?? null ) ? $context['displayLayout'] : array();
			$context['displayLayout'] = array_merge(
				array(
					'type'          => 'flex',
					'shrinkColumns' => true,
				),
				$layout,
				array( 'columns' => (int) $columns )
			);
		}
		return $context;
	}

	/**
	 * Columns on tablets and phones, as classes and custom properties on the
	 * grid; shop-layout.css reads them. Listing adds the view classes.
	 *
	 * @param string               $html  Rendered block.
	 * @param array<string, mixed> $block Parsed block.
	 */
	public static function grid( string $html, array $block ): string {
		if ( ! self::main_grid( $block ) || ! self::listing() ) {
			return $html;
		}
		// The body class for the full width comes after the head is printed.
		if ( 'full' === self::get( 'shop.width' ) ) {
			self::style();
		}
		$classes = array();
		$styles  = array();
		foreach ( array(
			'tablet' => 'shop.columns_tablet',
			'phone'  => 'shop.columns_phone',
		) as $screen => $path ) {
			$columns = self::get( $path );
			if ( is_string( $columns ) && ctype_digit( $columns ) ) {
				$classes[] = "pergel-columns-$screen";
				$styles[]  = "--pergel-columns-$screen:$columns";
			}
		}
		$tags = new \WP_HTML_Tag_Processor( $html );
		if ( ! $tags->next_tag() ) {
			return $html;
		}
		$view = Listing::grid( $tags );
		if ( ! $classes && ! $view ) {
			return $html;
		}
		self::style();
		foreach ( $classes as $class ) {
			$tags->add_class( $class );
		}
		if ( $styles ) {
			$style = trim( (string) $tags->get_attribute( 'style' ) );
			$tags->set_attribute( 'style', ( '' === $style ? '' : rtrim( $style, ';' ) . ';' ) . implode( ';', $styles ) );
		}
		return $tags->get_updated_html();
	}

	/**
	 * Page width as a body class; the theme widens the page column with it.
	 *
	 * @param array<int, string> $classes Body classes.
	 * @return array<int, string>
	 */
	public static function body_class( array $classes ): array {
		if ( self::listing() && 'full' === self::get( 'shop.width' ) ) {
			$classes[] = 'pergel-shop-full';
		}
		return $classes;
	}

	/**
	 * Listing templates that were saved in the Site Editor without the main
	 * product grid, so the settings cannot reach them.
	 *
	 * @return list<string> Template titles.
	 */
	public static function templates_without_grid(): array {
		$lost = array();
		foreach ( self::TEMPLATES as $slug ) {
			$template = get_block_template( get_stylesheet() . '//' . $slug );
			if ( $template && 'custom' === $template->source && ! preg_match( '/"inherit":\s*true/', (string) $template->content ) ) {
				$lost[] = '' !== $template->title ? $template->title : $slug;
			}
		}
		return $lost;
	}
}
