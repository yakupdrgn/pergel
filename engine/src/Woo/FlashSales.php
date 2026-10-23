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
	 * The published products a campaign covers: the chosen ones and those in
	 * the chosen categories (with their subcategories). Grouped products have
	 * no price of their own and are left out.
	 *
	 * @param int $id Campaign.
	 * @return list<int>
	 */
	public static function products( int $id ): array {
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
		$state = self::state( $id );
		if ( 'unset' === $state ) {
			return '';
		}
		$sale  = self::campaign( $id );
		$parts = sprintf(
			'<span class="wp-block-pergel-sale-timer__discount">%1$s</span><span class="wp-block-pergel-sale-timer__dates"><time datetime="%2$s">%3$s</time> – <time datetime="%4$s">%5$s</time></span>',
			/* translators: %d: discount in percent. */
			esc_html( sprintf( __( '%d%% off', 'pergel-engine' ), $sale['discount'] ) ),
			esc_attr( gmdate( 'c', $sale['start'] ) ),
			esc_html( self::date( $sale['start'] ) ),
			esc_attr( gmdate( 'c', $sale['end'] ) ),
			esc_html( self::date( $sale['end'] ) )
		);
		if ( 'ended' === $state ) {
			$parts .= '<p class="wp-block-pergel-sale-timer__ended">' . esc_html__( 'This campaign has ended.', 'pergel-engine' ) . '</p>';
		} elseif ( false !== ( $attributes['countdown'] ?? true ) ) {
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
		return sprintf( '<div %1$s>%2$s</div>', get_block_wrapper_attributes( array( 'class' => 'is-' . $state ) ), $parts );
	}
}
