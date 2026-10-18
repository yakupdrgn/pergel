<?php
/**
 * Sales modes: sale, prices after login, quote, catalog.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

/**
 * Decides whether a product is sold to the current visitor.
 *
 * The shop has a mode in the settings; a product category and a product
 * can choose their own. A product's own mode wins, then the strictest mode
 * of its categories (a category passes its mode down to its children),
 * then the shop's. A variation follows its product.
 *
 * Category modes and the modes products use are kept in one autoloaded
 * option, rebuilt when a mode is saved, so a page view reads no extra rows
 * and writes nothing.
 */
final class Modes {

	public const SALE    = 'sale';
	public const LOGIN   = 'login';
	public const QUOTE   = 'quote';
	public const CATALOG = 'catalog';

	/**
	 * Every mode, the most open first.
	 */
	public const ALL = array( self::SALE, self::LOGIN, self::QUOTE, self::CATALOG );

	public const TERM_META = 'pergel_sales_mode';
	public const POST_META = '_pergel_sales_mode';
	public const INDEX     = 'pergel_woo_modes';

	/**
	 * The index for this request.
	 *
	 * @var array{terms: array<int, string>, slugs: array<string, string>, used: list<string>}|null
	 */
	private static ?array $index = null;

	/**
	 * Resolved modes by product id, for this request.
	 *
	 * @var array<int, string>
	 */
	private static array $products = array();

	/**
	 * Whether the modes apply to this request; null until asked.
	 *
	 * @var bool|null
	 */
	private static ?bool $enforced = null;

	/**
	 * Keeps the index in step with saved modes.
	 */
	public static function register(): void {
		foreach ( array( 'added', 'updated', 'deleted' ) as $action ) {
			add_action( "{$action}_term_meta", array( self::class, 'meta_changed' ), 10, 3 );
			add_action( "{$action}_post_meta", array( self::class, 'meta_changed' ), 10, 3 );
		}
		foreach ( array( 'created', 'edited', 'delete' ) as $action ) {
			// A new parent or slug changes what the categories pass down.
			add_action( "{$action}_product_cat", array( self::class, 'rebuild' ) );
		}
		// The shop mode and a product's categories change resolved modes.
		add_action( 'update_option_' . Settings::OPTION, array( self::class, 'flush' ) );
		add_action( 'set_object_terms', array( self::class, 'flush' ) );
		// A site that has never saved a mode gets the option in the admin
		// area, so the front end does not look for a missing option.
		add_action( 'admin_init', array( self::class, 'install' ) );
	}

	/**
	 * Labels of the modes, for select boxes.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			self::SALE    => __( 'Sale: prices and the cart for everyone', 'pergel-engine' ),
			self::LOGIN   => __( 'Prices for logged-in customers only', 'pergel-engine' ),
			self::QUOTE   => __( 'Quote: no prices, customers ask for an offer', 'pergel-engine' ),
			self::CATALOG => __( 'Catalog: products only, no prices', 'pergel-engine' ),
		);
	}

	/**
	 * Whether a value is a mode.
	 *
	 * @param mixed $mode Value to check.
	 * @phpstan-assert-if-true string $mode
	 */
	public static function valid( mixed $mode ): bool {
		return is_string( $mode ) && in_array( $mode, self::ALL, true );
	}

	/**
	 * Sanitize callback for the meta fields: a mode or '' (inherit).
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function sanitize( mixed $value ): string {
		return self::valid( $value ) ? $value : '';
	}

	/**
	 * The shop's mode.
	 */
	public static function site(): string {
		$mode = Settings::get( 'woo.sales_mode' );
		return self::valid( $mode ) ? $mode : self::SALE;
	}

	/**
	 * The mode of a product; a variation follows its product.
	 *
	 * @param \WC_Product|int $product Product or product id.
	 */
	public static function of( \WC_Product|int $product ): string {
		if ( $product instanceof \WC_Product ) {
			$id = $product->get_parent_id() > 0 ? $product->get_parent_id() : $product->get_id();
		} else {
			$id     = $product;
			$parent = (int) wp_get_post_parent_id( $id );
			$id     = $parent > 0 && 'product_variation' === get_post_type( $id ) ? $parent : $id;
		}
		if ( ! isset( self::$products[ $id ] ) ) {
			self::$products[ $id ] = self::resolve( $id );
		}
		return self::$products[ $id ];
	}

	/**
	 * Own mode, then the strictest category mode, then the shop's.
	 *
	 * @param int $id Product id.
	 */
	private static function resolve( int $id ): string {
		$own = get_post_meta( $id, self::POST_META, true );
		if ( self::valid( $own ) ) {
			return $own;
		}
		$terms = self::index()['terms'];
		if ( array() !== $terms ) {
			$found = array();
			foreach ( wc_get_product_term_ids( $id, 'product_cat' ) as $term ) {
				if ( isset( $terms[ $term ] ) ) {
					$found[] = $terms[ $term ];
				}
			}
			if ( array() !== $found ) {
				return self::strictest( $found );
			}
		}
		return self::site();
	}

	/**
	 * The mode of a product category, inherited from its parents or the
	 * shop.
	 *
	 * @param int|string $term Term id or slug.
	 */
	public static function of_category( int|string $term ): string {
		$index = self::index();
		$mode  = is_int( $term ) ? ( $index['terms'][ $term ] ?? '' ) : ( $index['slugs'][ $term ] ?? '' );
		return '' === $mode ? self::site() : $mode;
	}

	/**
	 * The strictest of some modes.
	 *
	 * @param string[] $modes Modes.
	 * @phpstan-param list<string> $modes
	 */
	public static function strictest( array $modes ): string {
		$rank = 0;
		foreach ( $modes as $mode ) {
			$rank = max( $rank, (int) array_search( $mode, self::ALL, true ) );
		}
		return self::ALL[ $rank ];
	}

	/**
	 * Whether a mode sells to the current visitor.
	 *
	 * @param string $mode Mode.
	 */
	public static function sells( string $mode ): bool {
		return self::SALE === $mode || ( self::LOGIN === $mode && is_user_logged_in() );
	}

	/**
	 * Whether this request shows the product without its price and cart.
	 *
	 * @param \WC_Product|int $product Product or product id.
	 */
	public static function hidden( \WC_Product|int $product ): bool {
		return self::enforced() && ! self::sells( self::of( $product ) );
	}

	/**
	 * Whether nothing in the shop sells to the current visitor: the shop
	 * does not, and no category or product chooses a mode that does.
	 */
	public static function nothing_sells(): bool {
		if ( ! self::enforced() || self::sells( self::site() ) ) {
			return false;
		}
		foreach ( self::index()['used'] as $mode ) {
			if ( self::sells( $mode ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether the modes apply to this request. They apply on the front end,
	 * to the Store API, and to AJAX and other REST routes unless a shop
	 * manager asks. The admin screens, cron and WP-CLI see every price.
	 * Pergel's own REST routes (pergel/v1/shop-list) render prices, so the
	 * REST API is off only for those who may edit products.
	 */
	public static function enforced(): bool {
		if ( null !== self::$enforced ) {
			return self::$enforced;
		}
		if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			$enforced = false;
		} elseif ( wp_doing_ajax() ) {
			$enforced = ! current_user_can( 'edit_products' );
		} elseif ( is_admin() ) {
			$enforced = false;
		} elseif ( WC()->is_rest_api_request() ) {
			$enforced = WC()->is_store_api_request() || ! current_user_can( 'edit_products' );
		} else {
			$enforced = true;
		}

		/**
		 * Filters whether the sales modes apply to this request.
		 *
		 * @param bool $enforced True on the front end and in the Store API.
		 */
		self::$enforced = (bool) apply_filters( 'pergel/woo/enforce_modes', $enforced );
		return self::$enforced;
	}

	/**
	 * The index: effective category modes by id and slug, and the modes
	 * categories and products choose.
	 *
	 * @return array{terms: array<int, string>, slugs: array<string, string>, used: list<string>}
	 */
	public static function index(): array {
		if ( null === self::$index ) {
			$stored      = get_option( self::INDEX );
			self::$index = array(
				'terms' => is_array( $stored ) && is_array( $stored['terms'] ?? null ) ? array_filter( $stored['terms'], array( self::class, 'valid' ) ) : array(),
				'slugs' => is_array( $stored ) && is_array( $stored['slugs'] ?? null ) ? array_filter( $stored['slugs'], array( self::class, 'valid' ) ) : array(),
				'used'  => is_array( $stored ) && is_array( $stored['used'] ?? null ) ? array_values( array_filter( $stored['used'], array( self::class, 'valid' ) ) ) : array(),
			);
		}
		return self::$index;
	}

	/**
	 * Rebuilds the index when a mode meta key changes.
	 *
	 * @param mixed $meta_ids  Meta ids.
	 * @param mixed $object_id Term or post id.
	 * @param mixed $meta_key  Meta key.
	 */
	public static function meta_changed( mixed $meta_ids, mixed $object_id, mixed $meta_key ): void {
		if ( self::TERM_META === $meta_key || self::POST_META === $meta_key ) {
			self::rebuild();
		}
	}

	/**
	 * Saves the index once, if it is missing.
	 */
	public static function install(): void {
		if ( false === get_option( self::INDEX ) ) {
			self::rebuild();
		}
	}

	/**
	 * Builds and saves the index.
	 */
	public static function rebuild(): void {
		global $wpdb;

		$own     = array();
		$parents = array();
		$slugs   = array();
		$terms   = get_terms(
			array(
				'taxonomy'               => 'product_cat',
				'hide_empty'             => false,
				'update_term_meta_cache' => true,
			)
		);
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$parents[ $term->term_id ] = $term->parent;
			$slugs[ $term->term_id ]   = $term->slug;
			$mode                      = get_term_meta( $term->term_id, self::TERM_META, true );
			if ( self::valid( $mode ) ) {
				$own[ $term->term_id ] = $mode;
			}
		}

		$index = array(
			'terms' => array(),
			'slugs' => array(),
			'used'  => array_values( $own ),
		);
		if ( array() !== $own ) {
			foreach ( $parents as $id => $parent ) {
				// Up the tree to the nearest category with a mode.
				$seen = array();
				$at   = $id;
				while ( ! isset( $own[ $at ] ) && $at > 0 && ! isset( $seen[ $at ] ) ) {
					$seen[ $at ] = true;
					$at          = $parents[ $at ] ?? 0;
				}
				if ( isset( $own[ $at ] ) ) {
					$index['terms'][ $id ]           = $own[ $at ];
					$index['slugs'][ $slugs[ $id ] ] = $own[ $at ];
				}
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Runs only when a mode is saved.
		$products = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", self::POST_META ) );
		foreach ( $products as $mode ) {
			if ( self::valid( $mode ) ) {
				$index['used'][] = $mode;
			}
		}
		$index['used'] = array_values( array_unique( $index['used'] ) );

		update_option( self::INDEX, $index, true );
		self::flush();
	}

	/**
	 * Drops the request caches.
	 */
	public static function flush(): void {
		self::$index    = null;
		self::$products = array();
		self::$enforced = null;
	}
}
