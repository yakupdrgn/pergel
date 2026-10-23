<?php
/**
 * Flash sale campaigns.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

use const Pergel\FILE;

/**
 * Campaigns under Products → Flash sales: a time window, a discount and the
 * products or categories it covers. While a campaign runs its prices are
 * WooCommerce's own sale prices, written and put back by FlashSalePrices.
 * The list page shows the running and upcoming campaigns; a campaign page
 * shows its picture, a countdown and its products, and after the end a
 * note with the campaigns still running. Both templates come from the
 * engine and can be changed in the site editor.
 *
 * While a campaign runs, its products can show a box under the price on
 * their page, a "Flash sale" badge on their cards, and the shop pages a
 * band above the products, each with its own setting. They read a small
 * autoloaded index the price steps keep, so a shop listing makes no extra
 * query.
 */
final class FlashSales {

	public const TYPE = 'pergel_sale';

	/**
	 * Campaign meta: start and end (Unix time), discount (percent), chosen
	 * products and categories.
	 */
	public const START      = '_pergel_sale_start';
	public const END        = '_pergel_sale_end';
	public const DISCOUNT   = '_pergel_sale_discount';
	public const PRODUCTS   = '_pergel_sale_products';
	public const CATEGORIES = '_pergel_sale_categories';

	/**
	 * Campaign meta: the products it covers (variable products, not their
	 * variations), worked out on save and at each price step.
	 */
	public const ITEMS = '_pergel_sale_items';

	/**
	 * Campaign meta: the products shown with it, those with a written price
	 * (variable products, not their variations).
	 */
	public const SHOWN = '_pergel_sale_shown';

	/**
	 * Option: the running campaigns (id => end, soonest end first) and their
	 * products (product id => campaign id).
	 */
	public const INDEX = 'pergel_sale_index';

	/**
	 * Classes that mark the campaign page's product grid and its "running
	 * campaigns" section in the template.
	 */
	public const PRODUCTS_CLASS = 'pergel-sale-products';
	public const OTHERS_CLASS   = 'pergel-sale-others';

	/**
	 * Option: the address part the rewrite rules were last made with.
	 */
	public const RULES = 'pergel_sale_rules';

	/**
	 * Query id of the campaign page's product grid, once it is drawn.
	 *
	 * @var int|null
	 */
	private static ?int $grid = null;

	/**
	 * Adds the hooks; the shop module calls it with the setting on.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'init' ) );
		add_action( 'wp_loaded', array( self::class, 'rules' ) );
		add_action( 'transition_post_status', array( self::class, 'status' ), 10, 3 );
		add_action( 'before_delete_post', array( FlashSalePrices::class, 'deleted' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'editor' ) );
		// The editors list campaigns through the REST API: the same order there.
		add_filter( 'rest_' . self::TYPE . '_query', array( self::class, 'loop' ) );
		if ( is_admin() ) {
			FlashSaleFields::register();
			return;
		}
		add_action( 'pre_get_posts', array( self::class, 'archive' ) );
		add_filter( 'query_loop_block_query_vars', array( self::class, 'loop' ) );
		add_filter( 'render_block_data', array( self::class, 'collection' ) );
		add_filter( 'render_block_context', array( self::class, 'context' ) );
		add_filter( 'pre_render_block', array( self::class, 'sections' ), 10, 2 );
		add_filter( 'wp_robots', array( self::class, 'robots' ) );
		if ( true === Settings::get( 'woo.sales_product' ) ) {
			add_filter( 'render_block_woocommerce/product-price', array( self::class, 'price' ), 30, 3 );
		}
	}

	/**
	 * The "Flash sale list" Query Loop variation in the editors.
	 */
	public static function editor(): void {
		wp_enqueue_script( 'pergel-sale-list', plugins_url( 'assets/sale-list.js', FILE ), array( 'wp-blocks', 'wp-i18n' ), \Pergel\VERSION, true );
		wp_set_script_translations( 'pergel-sale-list', 'pergel-engine', dirname( FILE ) . '/languages' );
	}

	/**
	 * Registers the post type, its meta and the templates.
	 */
	public static function init(): void {
		register_post_type(
			self::TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Flash sales', 'pergel-engine' ),
					'singular_name'      => __( 'Flash sale', 'pergel-engine' ),
					'menu_name'          => __( 'Flash sales', 'pergel-engine' ),
					'all_items'          => __( 'Flash sales', 'pergel-engine' ),
					'add_new'            => __( 'Add flash sale', 'pergel-engine' ),
					'add_new_item'       => __( 'Add flash sale', 'pergel-engine' ),
					'edit_item'          => __( 'Edit flash sale', 'pergel-engine' ),
					'new_item'           => __( 'New flash sale', 'pergel-engine' ),
					'view_item'          => __( 'View flash sale', 'pergel-engine' ),
					'view_items'         => __( 'View flash sales', 'pergel-engine' ),
					'search_items'       => __( 'Search flash sales', 'pergel-engine' ),
					'not_found'          => __( 'No flash sales yet.', 'pergel-engine' ),
					'not_found_in_trash' => __( 'No flash sales in the trash.', 'pergel-engine' ),
					'archives'           => __( 'Flash sales', 'pergel-engine' ),
				),
				'public'          => true,
				'show_in_menu'    => 'edit.php?post_type=product',
				'show_in_rest'    => true,
				'has_archive'     => true,
				'rewrite'         => array(
					'slug'       => self::slug(),
					'with_front' => false,
				),
				'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
				// Shop managers run the campaigns, as they run the products.
				'capability_type' => 'product',
				'map_meta_cap'    => true,
			)
		);
		foreach ( array( self::START, self::END, self::DISCOUNT ) as $key ) {
			register_post_meta(
				self::TYPE,
				$key,
				array(
					'type'              => 'integer',
					'single'            => true,
					'default'           => 0,
					'sanitize_callback' => 'absint',
					'auth_callback'     => static fn ( mixed $allowed, string $meta, int $id ): bool => current_user_can( 'edit_post', $id ),
					'show_in_rest'      => false,
				)
			);
		}
		foreach ( array( self::PRODUCTS, self::CATEGORIES ) as $key ) {
			register_post_meta(
				self::TYPE,
				$key,
				array(
					'type'              => 'array',
					'single'            => true,
					'default'           => array(),
					'sanitize_callback' => array( self::class, 'ids' ),
					'auth_callback'     => static fn ( mixed $allowed, string $meta, int $id ): bool => current_user_can( 'edit_post', $id ),
					'show_in_rest'      => false,
				)
			);
		}
		self::templates();
	}

	/**
	 * Remakes the rewrite rules once after the address part changed, or the
	 * first time the campaigns are on. Late, so every plugin's rules are in.
	 */
	public static function rules(): void {
		if ( get_option( self::RULES ) !== self::slug() ) {
			flush_rewrite_rules( false );
			update_option( self::RULES, self::slug(), true );
		}
	}

	/**
	 * The address part of the campaign pages.
	 */
	public static function slug(): string {
		$own = sanitize_title( (string) Settings::get( 'woo.sales_slug' ) );
		return '' !== $own ? $own : sanitize_title( _x( 'flash-sale', 'Address part of the flash sale pages', 'pergel-engine' ) );
	}

	/**
	 * Registers the list and campaign templates; a theme's own files with the
	 * same names come first.
	 */
	public static function templates(): void {
		$templates = array(
			'archive-' . self::TYPE => array( __( 'Flash sales', 'pergel-engine' ), __( 'The list of running and upcoming flash sales.', 'pergel-engine' ) ),
			'single-' . self::TYPE  => array( __( 'Flash sale', 'pergel-engine' ), __( 'A flash sale with its countdown and products.', 'pergel-engine' ) ),
		);
		foreach ( $templates as $slug => [ $title, $description ] ) {
			if ( \WP_Block_Templates_Registry::get_instance()->is_registered( 'pergel-engine//' . $slug ) ) {
				continue;
			}
			register_block_template(
				'pergel-engine//' . $slug,
				array(
					'title'       => $title,
					'description' => $description,
					'content'     => self::template( $slug ),
				)
			);
		}
	}

	/**
	 * Markup of a template file.
	 *
	 * @param string $slug Template name.
	 */
	public static function template( string $slug ): string {
		ob_start();
		include dirname( FILE ) . '/templates/' . $slug . '.php';
		return (string) ob_get_clean();
	}

	/**
	 * Keeps the prices in step when a campaign is published, unpublished,
	 * trashed or restored.
	 *
	 * @param string   $next     New status.
	 * @param string   $previous Old status.
	 * @param \WP_Post $post     Post.
	 */
	public static function status( string $next, string $previous, \WP_Post $post ): void {
		if ( self::TYPE === $post->post_type && $next !== $previous && ( 'publish' === $next || 'publish' === $previous ) ) {
			FlashSalePrices::queue( $post->ID );
		}
	}

	/**
	 * A campaign's settings.
	 *
	 * @param int $id Campaign.
	 * @return array{start: int, end: int, discount: int, products: list<int>, categories: list<int>}
	 */
	public static function campaign( int $id ): array {
		return array(
			'start'      => (int) get_post_meta( $id, self::START, true ),
			'end'        => (int) get_post_meta( $id, self::END, true ),
			'discount'   => min( 90, (int) get_post_meta( $id, self::DISCOUNT, true ) ),
			'products'   => self::ids( get_post_meta( $id, self::PRODUCTS, true ) ),
			'categories' => self::ids( get_post_meta( $id, self::CATEGORIES, true ) ),
		);
	}

	/**
	 * Where a campaign stands: "upcoming", "running", "ended", or "unset"
	 * without valid dates and discount. Only a published campaign runs.
	 *
	 * @param int      $id  Campaign.
	 * @param int|null $now Time to check; now by default.
	 */
	public static function state( int $id, ?int $now = null ): string {
		$sale  = self::campaign( $id );
		$now ??= time();
		if ( $sale['start'] <= 0 || $sale['end'] <= $sale['start'] || $sale['discount'] <= 0 ) {
			return 'unset';
		}
		if ( $now >= $sale['end'] ) {
			return 'ended';
		}
		return $now < $sale['start'] ? 'upcoming' : 'running';
	}

	/**
	 * Whether the campaign's prices should be on now.
	 *
	 * @param int $id Campaign.
	 */
	public static function running( int $id ): bool {
		return 'publish' === get_post_status( $id ) && 'running' === self::state( $id );
	}

	/**
	 * The products a campaign covers: the chosen ones that are sold (see
	 * sold()).
	 *
	 * @param int $id Campaign.
	 * @return list<int>
	 */
	public static function products( int $id ): array {
		return array_values( array_filter( self::chosen( $id ), array( self::class, 'sold' ) ) );
	}

	/**
	 * Whether a product is sold under some mode: in the shop or to logged-in
	 * customers. Under the quote and catalog modes a product shows no
	 * price, so a campaign leaves it out.
	 *
	 * @param int $product Product.
	 */
	public static function sold( int $product ): bool {
		return in_array( Modes::of( $product ), array( Modes::SALE, Modes::LOGIN ), true );
	}

	/**
	 * The published products a campaign chooses: the chosen ones and those in
	 * the chosen categories (with their subcategories). Grouped products have
	 * no price of their own and are left out.
	 *
	 * @param int $id Campaign.
	 * @return list<int>
	 */
	public static function chosen( int $id ): array {
		$sale = self::campaign( $id );
		$ids  = $sale['products'];
		if ( $sale['categories'] ) {
			$slugs = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'include'    => $sale['categories'],
					'fields'     => 'slugs',
					'hide_empty' => false,
				)
			);
			if ( is_array( $slugs ) && $slugs ) {
				$ids = array_merge(
					$ids,
					self::ids(
						wc_get_products(
							array(
								'category' => $slugs,
								'status'   => 'publish',
								'limit'    => -1,
								'return'   => 'ids',
							)
						)
					)
				);
			}
		}
		$ids = array_values( array_unique( $ids ) );
		if ( ! $ids ) {
			return array();
		}
		return self::ids(
			wc_get_products(
				array(
					'include' => $ids,
					'type'    => array( 'simple', 'external', 'variable' ),
					'status'  => 'publish',
					'limit'   => -1,
					'orderby' => 'ID',
					'order'   => 'ASC',
					'return'  => 'ids',
				)
			)
		);
	}

	/**
	 * The products shown on the campaign page, as last worked out.
	 *
	 * @param int $id Campaign.
	 * @return list<int>
	 */
	public static function items( int $id ): array {
		$kept = get_post_meta( $id, self::ITEMS, true );
		return is_array( $kept ) ? self::ids( $kept ) : self::products( $id );
	}

	/**
	 * Positive whole numbers of a list, once each.
	 *
	 * @param mixed $value Stored or posted value.
	 * @return list<int>
	 */
	public static function ids( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
	}

	/**
	 * The running and upcoming campaigns, soonest first, on the list page.
	 *
	 * @param \WP_Query $query Query being prepared.
	 */
	public static function archive( \WP_Query $query ): void {
		if ( $query->is_main_query() && $query->is_post_type_archive( self::TYPE ) ) {
			foreach ( self::current( array() ) as $key => $value ) {
				$query->set( $key, $value );
			}
		}
	}

	/**
	 * The same order in a Query Loop block of campaigns (the "running
	 * campaigns" section of an ended campaign, a page of their own).
	 *
	 * @param array<string, mixed> $query Query vars.
	 * @return array<string, mixed>
	 */
	public static function loop( array $query ): array {
		$type = $query['post_type'] ?? '';
		return self::TYPE === $type || array( self::TYPE ) === $type ? self::current( $query ) : $query;
	}

	/**
	 * Query vars for campaigns that have not ended.
	 *
	 * @param array<string, mixed> $query Query vars to add to.
	 * @return array<string, mixed>
	 */
	private static function current( array $query ): array {
		return array(
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- A handful of campaigns; the list pages are cached.
			'meta_query' => array(
				array(
					'key'     => self::END,
					'value'   => time(),
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
			'meta_key'   => self::START, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- As above.
			'orderby'    => 'meta_value_num',
			'order'      => 'ASC',
		) + $query;
	}

	/**
	 * Notes the query id of the campaign page's product grid.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return array<string, mixed>
	 */
	public static function collection( array $block ): array {
		if ( 'woocommerce/product-collection' === ( $block['blockName'] ?? '' ) && self::marked( $block, self::PRODUCTS_CLASS ) && is_singular( self::TYPE ) ) {
			self::$grid = (int) ( $block['attrs']['queryId'] ?? 0 );
		}
		return $block;
	}

	/**
	 * Fills the grid with the campaign's products. Through the context of
	 * the blocks inside it: WordPress builds them from the grid's attributes
	 * as they were parsed, before render_block_data.
	 *
	 * @param array<string, mixed> $context Block context.
	 * @return array<string, mixed>
	 */
	public static function context( array $context ): array {
		if ( null === self::$grid || ( $context['queryId'] ?? null ) !== self::$grid || ! is_array( $context['query'] ?? null ) ) {
			return $context;
		}
		$items = self::items( (int) get_queried_object_id() );
		// No products: an id that matches none, as an empty list means all.
		$context['query']['woocommerceHandPickedProducts'] = $items ? $items : array( 0 );
		return $context;
	}

	/**
	 * On a campaign page, the product grid while it has not ended, and the
	 * running campaigns once it has; the other one is not drawn at all.
	 *
	 * @param string|null          $html  Markup from an earlier filter, or null.
	 * @param array<string, mixed> $block Parsed block.
	 */
	public static function sections( ?string $html, array $block ): ?string {
		$products = self::marked( $block, self::PRODUCTS_CLASS );
		$others   = ! $products && self::marked( $block, self::OTHERS_CLASS );
		if ( null !== $html || ( ! $products && ! $others ) || ! is_singular( self::TYPE ) ) {
			return $html;
		}
		$ended = 'ended' === self::state( (int) get_queried_object_id() );
		return $ended === $others ? null : '';
	}

	/**
	 * Keeps ended campaigns out of search results.
	 *
	 * @param array<string, bool|string> $robots Robots directives.
	 * @return array<string, bool|string>
	 */
	public static function robots( array $robots ): array {
		if ( is_singular( self::TYPE ) && 'ended' === self::state( (int) get_queried_object_id() ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	/**
	 * A date and time in the site's formats and time zone.
	 *
	 * @param int    $time   Unix time.
	 * @param string $format Format; the site's date and time by default.
	 */
	public static function date( int $time, string $format = '' ): string {
		$date = wp_date( '' !== $format ? $format : get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time );
		return false === $date ? '' : $date;
	}

	/**
	 * Whether a parsed block has a class.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @param string               $name  Class.
	 */
	private static function marked( array $block, string $name ): bool {
		$classes = $block['attrs']['className'] ?? '';
		return is_string( $classes ) && in_array( $name, explode( ' ', $classes ), true );
	}

	/**
	 * The Flash sale dates block: the discount, the dates and a countdown to
	 * the start or the end, or that the campaign has ended.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param \WP_Block            $block      Block, for the post in context.
	 */
	public static function timer( array $attributes, \WP_Block $block ): string {
		$id = (int) ( $block->context['postId'] ?? 0 );
		if ( 0 === $id || self::TYPE !== get_post_type( $id ) ) {
			return '';
		}
		$parts = self::dates( $id, ( $attributes['countdown'] ?? true ) !== false );
		if ( '' === $parts ) {
			return '';
		}
		return sprintf( '<div %1$s>%2$s</div>', get_block_wrapper_attributes( array( 'class' => 'is-' . self::state( $id ) ) ), $parts );
	}

	/**
	 * The parts of the dates block: the discount, the dates and the
	 * countdown (each unless left out), or that the campaign has ended.
	 *
	 * @param int  $id        Campaign.
	 * @param bool $countdown With the countdown.
	 * @param bool $range     With the dates.
	 */
	public static function dates( int $id, bool $countdown = true, bool $range = true ): string {
		$state = self::state( $id );
		if ( 'unset' === $state ) {
			return '';
		}
		$sale  = self::campaign( $id );
		$parts = sprintf(
			'<span class="wp-block-pergel-sale-timer__discount">%s</span>',
			/* translators: %d: discount in percent. */
			esc_html( sprintf( __( '%d%% off', 'pergel-engine' ), $sale['discount'] ) )
		);
		if ( $range ) {
			$parts .= sprintf(
				'<span class="wp-block-pergel-sale-timer__dates"><time datetime="%1$s">%2$s</time> – <time datetime="%3$s">%4$s</time></span>',
				esc_attr( gmdate( 'c', $sale['start'] ) ),
				esc_html( self::date( $sale['start'] ) ),
				esc_attr( gmdate( 'c', $sale['end'] ) ),
				esc_html( self::date( $sale['end'] ) )
			);
		}
		if ( 'ended' === $state ) {
			$parts .= '<p class="wp-block-pergel-sale-timer__ended">' . esc_html__( 'This campaign has ended.', 'pergel-engine' ) . '</p>';
		} elseif ( $countdown ) {
			$running = 'running' === $state;
			$parts  .= sprintf(
				'<span class="wp-block-pergel-sale-timer__count"><span class="wp-block-pergel-sale-timer__label">%1$s</span>%2$s</span>',
				esc_html( $running ? __( 'Ends in', 'pergel-engine' ) : __( 'Starts in', 'pergel-engine' ) ),
				Cards::timer(
					$running ? $sale['end'] : $sale['start'],
					/* translators: %s: date and time. */
					sprintf( $running ? __( 'Campaign ends %s', 'pergel-engine' ) : __( 'Campaign starts %s', 'pergel-engine' ), self::date( $running ? $sale['end'] : $sale['start'] ) )
				)
			);
		}
		return $parts;
	}

	/**
	 * The campaign index.
	 *
	 * @return array{sales: array<int, int>, products: array<int, int>}
	 */
	public static function index(): array {
		$kept  = get_option( self::INDEX, array() );
		$index = array(
			'sales'    => array(),
			'products' => array(),
		);
		foreach ( array( 'sales', 'products' ) as $part ) {
			foreach ( is_array( $kept ) && is_array( $kept[ $part ] ?? null ) ? $kept[ $part ] : array() as $key => $value ) {
				if ( is_int( $key ) && is_int( $value ) ) {
					$index[ $part ][ $key ] = $value;
				}
			}
		}
		return $index;
	}

	/**
	 * Makes the index again from the running campaigns; the price steps
	 * call it.
	 */
	public static function reindex(): void {
		$now   = time();
		$index = array(
			'sales'    => array(),
			'products' => array(),
		);
		$ids   = get_posts(
			array(
				'post_type'      => self::TYPE,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 100,
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Only when a price step runs.
				'meta_query'     => array(
					array(
						'key'     => self::END,
						'value'   => $now,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => self::START,
						'value'   => $now,
						'compare' => '<=',
						'type'    => 'NUMERIC',
					),
				),
				'meta_key'       => self::END, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- As above.
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
			)
		);
		foreach ( self::ids( $ids ) as $id ) {
			// A campaign that shows no product (none sold, or all in another
			// campaign) is not offered to the band or the block.
			$shown = self::ids( get_post_meta( $id, self::SHOWN, true ) );
			if ( ! $shown ) {
				continue;
			}
			$index['sales'][ $id ] = self::campaign( $id )['end'];
			foreach ( $shown as $product ) {
				$index['products'][ $product ] ??= $id;
			}
		}
		update_option( self::INDEX, $index, true );
	}

	/**
	 * The running campaign a product is in, or 0.
	 *
	 * @param int $product Product (not a variation).
	 */
	public static function of( int $product ): int {
		$index = self::index();
		$id    = $index['products'][ $product ] ?? 0;
		return ( $index['sales'][ $id ] ?? 0 ) > time() ? $id : 0;
	}

	/**
	 * The running campaigns, soonest end first.
	 *
	 * @return list<int>
	 */
	public static function live(): array {
		$now = time();
		return array_keys( array_filter( self::index()['sales'], static fn ( int $end ): bool => $end > $now ) );
	}

	/**
	 * The campaign box under the price on the product's own page.
	 *
	 * @param string    $html     Rendered price.
	 * @param mixed     $block    Parsed block.
	 * @param \WP_Block $instance Block, for the product in context.
	 */
	public static function price( string $html, mixed $block, \WP_Block $instance ): string {
		$product = (int) get_queried_object_id();
		if ( ! is_product() || (int) ( $instance->context['postId'] ?? 0 ) !== $product ) {
			return $html;
		}
		$id = self::of( $product );
		if ( 0 === $id || ! self::running( $id ) || Modes::hidden( $product ) ) {
			return $html;
		}
		self::style();
		return $html . sprintf(
			'<div class="pergel-sale-box"><a class="pergel-sale-box__name" href="%1$s">%2$s</a><div class="wp-block-pergel-sale-timer is-running">%3$s</div></div>',
			esc_url( (string) get_permalink( $id ) ),
			esc_html( get_the_title( $id ) ),
			self::dates( $id, true, false )
		);
	}

	/**
	 * The band above the products on the shop and category pages. The card
	 * script hides it once its countdown is over, so a cached page does not
	 * show it after the end.
	 */
	public static function band(): string {
		if ( Modes::nothing_sells() ) {
			return '';
		}
		$id = self::pick( is_product_category() ? (int) get_queried_object_id() : 0 );
		if ( 0 === $id ) {
			return '';
		}
		self::style();
		return sprintf(
			'<div class="pergel-sale-band alignwide"><span class="pergel-sale-band__name">%1$s</span><div class="wp-block-pergel-sale-timer is-running">%2$s</div><a class="pergel-sale-band__link" href="%3$s">%4$s</a></div>',
			esc_html( get_the_title( $id ) ),
			self::dates( $id, true, false ),
			esc_url( (string) get_permalink( $id ) ),
			esc_html__( 'See the campaign', 'pergel-engine' )
		);
	}

	/**
	 * The running campaign to show: on a category, one of that category or
	 * a parent first, else the one that ends soonest; 0 when none runs.
	 *
	 * @param int $category Product category, or 0.
	 */
	public static function pick( int $category ): int {
		$live = array_values( array_filter( self::live(), array( self::class, 'running' ) ) );
		if ( $category > 0 ) {
			$chain = array_merge( array( $category ), array_map( 'intval', get_ancestors( $category, 'product_cat', 'taxonomy' ) ) );
			foreach ( $live as $id ) {
				if ( array_intersect( $chain, self::campaign( $id )['categories'] ) ) {
					return $id;
				}
			}
		}
		return $live[0] ?? 0;
	}

	/**
	 * The Running flash sale block: a campaign's name, discount, countdown
	 * and some of its products, with a link to all of them. Nothing while
	 * no campaign runs.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public static function running_sale( array $attributes ): string {
		$chosen = absint( $attributes['sale'] ?? 0 );
		$id     = $chosen > 0 ? ( self::running( $chosen ) ? $chosen : 0 ) : self::pick( 0 );
		if ( 0 === $id ) {
			return '';
		}
		self::style();
		$count = max( 1, min( 12, absint( $attributes['count'] ?? 4 ) ) );
		$items = self::items( $id );
		$title = trim( (string) ( $attributes['title'] ?? '' ) );
		return sprintf(
			'<section %1$s><div class="wp-block-pergel-sale-current__head"><h2 class="wp-block-pergel-sale-current__title"><a href="%2$s">%3$s</a></h2><div class="wp-block-pergel-sale-timer is-running">%4$s</div></div>%5$s<p class="wp-block-pergel-sale-current__more"><a href="%2$s">%6$s</a></p></section>',
			get_block_wrapper_attributes(),
			esc_url( (string) get_permalink( $id ) ),
			esc_html( '' !== $title ? $title : get_the_title( $id ) ),
			self::dates( $id, true, false ),
			$items ? self::grid( $items, $count ) : '',
			esc_html__( 'See all the products', 'pergel-engine' )
		);
	}

	/**
	 * Some of a campaign's products, as WooCommerce's product collection
	 * draws them.
	 *
	 * @param int[] $items Products.
	 * @param int   $count How many.
	 */
	private static function grid( array $items, int $count ): string {
		$attributes = array(
			'queryId'       => 0,
			'query'         => array(
				'perPage'                       => $count,
				'pages'                         => 1,
				'offset'                        => 0,
				'postType'                      => 'product',
				'order'                         => 'asc',
				'orderBy'                       => 'title',
				'inherit'                       => false,
				'isProductCollectionBlock'      => true,
				'woocommerceStockStatus'        => array( 'instock', 'onbackorder' ),
				'woocommerceHandPickedProducts' => $items,
			),
			'tagName'       => 'div',
			'displayLayout' => array(
				'type'          => 'flex',
				'columns'       => min( 4, $count ),
				'shrinkColumns' => true,
			),
		);
		return do_blocks(
			'<!-- wp:woocommerce/product-collection ' . wp_json_encode( $attributes ) . ' --><div class="wp-block-woocommerce-product-collection"><!-- wp:woocommerce/product-template -->'
			. '<!-- wp:woocommerce/product-image {"imageSizing":"thumbnail","isDescendentOfQueryLoop":true,"aspectRatio":"1","scale":"contain","style":{"border":{"radius":"var(--wp--custom--radius--card)"}}} -->'
			. '<!-- wp:woocommerce/product-sale-badge {"isDescendentOfQueryLoop":true,"align":"right"} /--><!-- /wp:woocommerce/product-image -->'
			. '<!-- wp:post-title {"level":3,"isLink":true,"fontSize":"medium","__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->'
			. '<!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true} /-->'
			. '<!-- wp:woocommerce/product-button {"textAlign":"left","isDescendentOfQueryLoop":true,"fontSize":"small"} /-->'
			. '<!-- /wp:woocommerce/product-template --></div><!-- /wp:woocommerce/product-collection -->'
		);
	}

	/**
	 * The styles of the box, the band and the blocks: those of the dates
	 * block.
	 */
	private static function style(): void {
		wp_enqueue_style( generate_block_asset_handle( 'pergel/sale-timer', 'style' ) );
	}
}
