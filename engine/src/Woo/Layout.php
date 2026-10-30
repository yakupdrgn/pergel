<?php
/**
 * Shop listing layout from the settings.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Seo\Module as Seo;
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
 * LayoutFields); its subcategories follow it. On a product page, the
 * product's own values (post meta) come first, then those of its category
 * (the one the breadcrumb shows) and that category's parents.
 */
final class Layout {

	public const TERM_META = 'pergel_shop';

	/**
	 * A product's own values; the same key as on categories.
	 */
	public const PRODUCT_META = 'pergel_shop';

	/**
	 * Product page CSS (ProductPage).
	 */
	public const PRODUCT_STYLE = 'pergel-product-page';

	/**
	 * Product page script module (Buy now, coupon codes, share).
	 */
	public const PRODUCT_SCRIPT = 'pergel/product-page';

	/**
	 * Sections under the product (Details): CSS and the tabs script.
	 */
	public const DETAILS_STYLE = 'pergel-product-details';

	public const DETAILS_SCRIPT = 'pergel/product-details';

	/**
	 * A product's own feature list and extra tab (DetailsFields).
	 */
	public const DETAILS_META = '_pergel_details';

	/**
	 * Comment type of product questions and their answers (Questions).
	 */
	public const QUESTION = 'pergel_question';

	/**
	 * Comparison with similar products (Related): CSS.
	 */
	public const RELATED_STYLE = 'pergel-product-related';

	/**
	 * Recently viewed products (Related): the script module that remembers
	 * them and fills the block.
	 */
	public const RECENT_SCRIPT = 'pergel/recently-viewed';

	/**
	 * Script module that keeps the cart's numbers current (FreeShipping,
	 * BottomBar).
	 */
	public const CART_SCRIPT = 'pergel/cart-sync';

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
	 * The look settings of the filter box, the toolbar and the page numbers
	 * (ShopLook).
	 */
	public const LOOK = array( 'filter_bg', 'filter_edge', 'filter_edge_color', 'filter_radius', 'filter_padding', 'filter_head_size', 'filter_head_weight', 'filter_divider', 'filter_text_size', 'filter_accent', 'filter_input_size', 'filter_input_edge', 'filter_input_color', 'filter_counts', 'filter_swatch', 'filter_star_size', 'filter_star_color', 'toolbar_bg', 'toolbar_edge', 'toolbar_edge_color', 'toolbar_radius', 'toolbar_padding', 'control_radius', 'control_height', 'control_edge', 'control_edge_color', 'filter_title_look', 'strip_count', 'strip_picture', 'paging_shape', 'paging_size', 'paging_bg', 'paging_current' );

	/**
	 * The look settings of the top of the product page (ProductLook).
	 */
	public const PRODUCT_LOOK = array( 'picture_bg', 'picture_edge', 'picture_edge_color', 'picture_radius', 'thumb_radius', 'thumb_active', 'title_size', 'title_weight', 'price_size', 'price_weight', 'price_color', 'buy_look', 'buy_color', 'buy_shape', 'buy_height', 'qty_shape' );

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
	 * The category chain of the page on screen, by queried object id.
	 *
	 * @var array<int, list<int>>
	 */
	private static array $chains = array();

	/**
	 * Adds the hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'meta' ) );
		add_action( 'updated_term_meta', array( self::class, 'flush' ) );
		add_action( 'added_term_meta', array( self::class, 'flush' ) );
		add_action( 'deleted_term_meta', array( self::class, 'flush' ) );
		add_action( 'pre_get_comments', array( self::class, 'hide_questions' ) );
		// Also for the editors, so the theme's filter pattern looks the same there.
		add_filter( 'pergel/shop/filters', array( Filters::class, 'groups' ) );
		add_filter( 'pergel/shop/filter_attributes', array( Filters::class, 'attributes' ), 10, 2 );
		if ( is_admin() ) {
			LayoutFields::register();
			BadgeField::register();
			CouponField::register();
			DetailsFields::register();
			Preset::register();
			if ( true === Settings::get( 'shop.details_qa' ) ) {
				QuestionsAdmin::register();
			}
			return;
		}
		Filters::register();
		add_action( 'init', array( self::class, 'register_style' ) );
		// Listing and ShopHeader load only on the pages that use them.
		add_filter( 'render_block_woocommerce/catalog-sorting', array( Listing::class, 'toolbar' ) );
		add_filter( 'render_block_core/term-description', array( self::class, 'above' ) );
		add_filter( 'render_block_core/columns', array( self::class, 'before_layout' ), 20, 2 );
		add_filter( Seo::NOINDEX, array( self::class, 'noindex' ) );
		add_action( 'wp', array( self::class, 'cards' ) );
		add_action( 'wp', array( self::class, 'look' ) );
		add_action( 'wp', array( self::class, 'product_page' ) );
		add_action( 'rest_api_init', array( self::class, 'quick_view' ) );
		add_action( 'rest_api_init', array( self::class, 'recently_viewed' ) );
		add_filter( 'loop_shop_per_page', array( self::class, 'per_page' ), 20 );
		add_filter( 'render_block_context', array( self::class, 'context' ), 10, 3 );
		add_filter( 'render_block_woocommerce/product-collection', array( self::class, 'grid' ), 10, 2 );
		add_filter( 'render_block_woocommerce/product-collection', array( FirstCards::class, 'done' ) );
		add_filter( 'wp_get_attachment_image_attributes', array( FirstCards::class, 'attributes' ), 20 );
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
		register_post_meta(
			'product',
			self::PRODUCT_META,
			array(
				'type'              => 'object',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => static fn ( mixed $value ): array => Schema::level_values( $value, 'product' ),
				'auth_callback'     => static fn ( mixed $allowed, string $key, int $id ): bool => current_user_can( 'edit_post', $id ),
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
		wp_register_style( self::PRODUCT_STYLE, plugins_url( 'assets/product-page.css', FILE ), array(), VERSION );
		wp_style_add_data( self::PRODUCT_STYLE, 'path', dirname( FILE ) . '/assets/product-page.css' );
		wp_register_script_module( self::PRODUCT_SCRIPT, plugins_url( 'assets/product-page.js', FILE ), array(), VERSION, array( 'fetchpriority' => 'low' ) );
		wp_register_style( self::DETAILS_STYLE, plugins_url( 'assets/product-details.css', FILE ), array(), VERSION );
		wp_style_add_data( self::DETAILS_STYLE, 'path', dirname( FILE ) . '/assets/product-details.css' );
		wp_register_script_module( self::DETAILS_SCRIPT, plugins_url( 'assets/product-details.js', FILE ), array(), VERSION, array( 'fetchpriority' => 'low' ) );
		wp_register_style( self::RELATED_STYLE, plugins_url( 'assets/product-related.css', FILE ), array(), VERSION );
		wp_style_add_data( self::RELATED_STYLE, 'path', dirname( FILE ) . '/assets/product-related.css' );
		wp_register_script_module( self::RECENT_SCRIPT, plugins_url( 'assets/recently-viewed.js', FILE ), array(), VERSION, array( 'fetchpriority' => 'low' ) );
		wp_register_script_module( self::CART_SCRIPT, plugins_url( 'assets/cart-sync.js', FILE ), array(), VERSION, array( 'fetchpriority' => 'low' ) );
		add_filter( 'script_module_data_' . self::CART_SCRIPT, array( self::class, 'cart_data' ) );
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
	 * Keeps pages with a visitor's products per page out of search results.
	 *
	 * @param mixed $noindex Whether the page is kept out so far.
	 */
	public static function noindex( mixed $noindex ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checks that the argument is there.
		return true === $noindex || ( isset( $_GET['per_page'] ) && self::listing() && Listing::noindex() );
	}

	/**
	 * Loads the layout CSS on this page. Called wherever a setting adds one
	 * of its classes; the shop templates render before the head is printed.
	 */
	public static function style(): void {
		if ( ! wp_style_is( self::STYLE ) ) {
			wp_enqueue_style( self::STYLE );
			wp_add_inline_style( self::STYLE, self::panel_css() );
		}
	}

	/**
	 * While WooCommerce shows the filters as a "Filter products" button and
	 * a panel, the filter column holds only that button: it goes above the
	 * products, full width and not sticky. WooCommerce takes that width from
	 * the theme's settings.viewport (tablet, then mobile, else 782px), which
	 * the stylesheet cannot read, so the rule is written here.
	 */
	public static function panel_css(): string {
		$viewport = wp_get_global_settings( array( 'viewport' ) );
		$width    = '782px';
		foreach ( array( 'tablet', 'mobile' ) as $screen ) {
			if ( is_array( $viewport ) && is_string( $viewport[ $screen ] ?? null ) ) {
				$width = $viewport[ $screen ];
				break;
			}
		}
		if ( ! preg_match( '/^\d+(\.\d+)?(px|em|rem)$/', $width ) ) {
			$width = '782px';
		}
		$layout = '.pergel-shop-layout:has(.wc-block-product-filters.is-mobile-overlay)';
		return "@media (width <= $width){{$layout}{flex-wrap:wrap!important}{$layout}>.wp-block-column{flex-basis:100%!important}{$layout}>.pergel-shop-layout__filters{position:static;max-height:none;overflow:visible;padding-inline-end:0}}";
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
	 * Adds the look rules of the filter box, the toolbar and the page
	 * numbers on listing pages, while one of them is set. Hooked on wp.
	 */
	public static function look(): void {
		if ( self::listing() && self::chosen( self::LOOK ) ) {
			ShopLook::enqueue();
		}
	}

	/**
	 * Whether one of the given shop settings differs from its default.
	 *
	 * @param array $keys Setting keys in the shop group.
	 * @phpstan-param list<string> $keys
	 */
	public static function chosen( array $keys ): bool {
		$defaults = Schema::defaults()['shop'] ?? array();
		foreach ( $keys as $key ) {
			if ( Settings::get( "shop.$key" ) !== ( $defaults[ $key ] ?? null ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Adds the quick view route while the setting is on. Product pages
	 * preload Store API requests, which run rest_api_init; checking the
	 * setting here keeps them from loading QuickView.
	 */
	public static function quick_view(): void {
		if ( true === Settings::get( 'shop.quick_view' ) ) {
			QuickView::routes();
		}
	}

	/**
	 * The cart route the cart script reads.
	 *
	 * @param array<string, mixed> $data Module data.
	 * @return array<string, mixed>
	 */
	public static function cart_data( array $data ): array {
		return $data + array( 'cart' => rest_url( 'wc/store/v1/cart' ) );
	}

	/**
	 * Adds the recently viewed route while the setting is on; see
	 * quick_view() for why it is checked here.
	 */
	public static function recently_viewed(): void {
		if ( self::recent_on() ) {
			Related::routes();
		}
	}

	/**
	 * Whether recently viewed products are kept and shown: the setting under
	 * the product, or the column beside the sections showing them.
	 */
	public static function recent_on(): bool {
		return 'off' !== Settings::get( 'shop.related_recent' ) || in_array( Settings::get( 'shop.details_side' ), array( 'both', 'recent' ), true );
	}

	/**
	 * Adds the product page hooks when a product page setting differs from
	 * its default for the product on screen, those of the sections under
	 * the product when a "details" setting does or the product has a
	 * feature list or tab of its own, and those of the related products
	 * when a "related" setting does, the look rules when a look setting is
	 * set and the summary order when it is chosen. With the defaults,
	 * product pages load none of ProductPage, Details, Related, ProductLook
	 * and Items. Hooked on wp.
	 */
	public static function product_page(): void {
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		$id      = get_queried_object_id();
		$page    = false;
		$details = is_array( get_post_meta( $id, self::DETAILS_META, true ) );
		$related = false;
		foreach ( Schema::defaults()['shop'] ?? array() as $key => $default ) {
			if ( ! $page && str_starts_with( $key, 'product_' ) ) {
				$page = self::get( "shop.$key" ) !== $default;
			} elseif ( ! $details && str_starts_with( $key, 'details_' ) ) {
				$details = self::get( "shop.$key" ) !== $default;
			} elseif ( ! $related && str_starts_with( $key, 'related_' ) ) {
				$related = self::get( "shop.$key" ) !== $default;
			}
		}
		if ( $page ) {
			ProductPage::register( $id );
		}
		if ( $details ) {
			Details::register( $id );
		}
		if ( $related ) {
			Related::register( $id );
		}
		if ( self::chosen( self::PRODUCT_LOOK ) ) {
			ProductLook::enqueue();
		}
		if ( '' !== Settings::get( 'shop.summary_items' ) ) {
			add_filter( 'render_block_data', array( Items::class, 'summary' ) );
		}
	}

	/**
	 * Keeps product questions out of comment lists that do not ask for them
	 * by type: the reviews under a product, recent comments, Comments in
	 * the admin. They stay out when the setting is turned off, too.
	 *
	 * @param \WP_Comment_Query $query Comment query about to run.
	 */
	public static function hide_questions( \WP_Comment_Query $query ): void {
		$vars = &$query->query_vars;
		// WordPress's defaults for these are empty strings.
		if ( array() !== array_filter( (array) ( $vars['type'] ?? '' ) ) || array() !== array_filter( (array) ( $vars['type__in'] ?? '' ) ) ) {
			return;
		}
		$vars['type__not_in'] = array_values( array_unique( array_merge( array_filter( (array) ( $vars['type__not_in'] ?? '' ) ), array( self::QUESTION ) ) ) );
	}

	/**
	 * Whether a site-wide card or badge setting differs from its default.
	 */
	private static function cards_chosen(): bool {
		if ( self::sale_badges() ) {
			return true;
		}
		foreach ( Schema::defaults()['shop'] ?? array() as $key => $default ) {
			if ( ( str_starts_with( $key, 'card_' ) || str_starts_with( $key, 'badge_' ) || 'quick_view' === $key ) && Settings::get( "shop.$key" ) !== $default ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether cards show the "Flash sale" badge; read here, so a shop
	 * without campaigns does not load their class.
	 */
	public static function sale_badges(): bool {
		return true === Settings::get( 'woo.flash_sales' ) && true === Settings::get( 'woo.sales_card' );
	}

	/**
	 * Drops the request cache when a category's values change.
	 */
	public static function flush(): void {
		self::$own    = array();
		self::$chains = array();
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
		if ( is_singular( 'product' ) ) {
			$own = Schema::level_values( get_post_meta( get_queried_object_id(), self::PRODUCT_META, true ), 'product' );
			if ( array_key_exists( $path, $own ) ) {
				return $own[ $path ];
			}
		}
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
		$page = get_queried_object_id();
		if ( isset( self::$chains[ $page ] ) ) {
			return self::$chains[ $page ];
		}
		$term = null;
		if ( is_tax( 'product_cat' ) ) {
			$term = get_queried_object();
		} elseif ( is_singular( 'product' ) ) {
			$term = self::product_category( $page );
		}
		$ids = array();
		if ( $term instanceof \WP_Term ) {
			$ids = array_values( array_merge( array( $term->term_id ), array_map( 'intval', get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) ) ) );
			update_termmeta_cache( $ids );
		}
		self::$chains[ $page ] = $ids;
		return $ids;
	}

	/**
	 * The category of a product the breadcrumb shows: the deepest one,
	 * picked as WooCommerce's breadcrumb picks it (see Trail).
	 *
	 * @param int $product Product id.
	 */
	public static function product_category( int $product ): ?\WP_Term {
		$terms = wc_get_product_terms(
			$product,
			'product_cat',
			/** This filter is documented in WooCommerce's includes/class-wc-breadcrumb.php. */
			apply_filters(
				'woocommerce_breadcrumb_product_terms_args', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's filter, so the category matches its breadcrumb.
				array(
					'orderby' => 'parent',
					'order'   => 'DESC',
				)
			)
		);
		$term = $terms[0] ?? null;
		return $term instanceof \WP_Term ? $term : null;
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
		Listing::prepare( $block );
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
		FirstCards::arm( (int) ( $context['displayLayout']['columns'] ?? 3 ) );
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
