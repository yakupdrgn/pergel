<?php
/**
 * Prices of flash sale campaigns.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * Writes a running campaign's price into each product as WooCommerce's own
 * scheduled sale (sale price, from the start to the end), and puts the
 * product's earlier sale back afterwards. So the cart, the price filter,
 * sorting and feeds all see the campaign price.
 *
 * The work happens in steps (Action Scheduler): at the start, at the end,
 * when a campaign is saved or its status changes, and again until every
 * product is done, a batch at a time. A step compares what the campaign
 * wants now with what it has written, so running it twice changes nothing.
 * The step hook is added even with the campaigns turned off, so written
 * prices always go back.
 *
 * A product takes part in one campaign at a time; another campaign skips
 * it and says so on its edit screen. A sale price changed by hand during
 * the campaign is left as it is.
 */
final class FlashSalePrices {

	public const HOOK  = 'pergel_sale_step';
	public const NEXT  = 'pergel_sale_next';
	public const GROUP = 'pergel';

	/**
	 * Product meta: the campaign and the product's own sale before it.
	 */
	public const META = '_pergel_sale';

	/**
	 * Campaign meta: written products (product id => campaign signature) and
	 * left out ones (product id => "taken" by another campaign, or "price":
	 * no regular price).
	 */
	public const APPLIED = '_pergel_sale_applied';
	public const SKIPPED = '_pergel_sale_skipped';

	/**
	 * Products per step.
	 */
	public const BATCH = 50;

	/**
	 * Option name prefix of a campaign's lock while a step runs.
	 */
	private const LOCK = 'pergel_sale_lock_';

	/**
	 * Runs a step at the campaign's start and end, and one now.
	 *
	 * @param int $id Campaign.
	 */
	public static function schedule( int $id ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}
		as_unschedule_all_actions( self::HOOK, array( 'sale' => $id ), self::GROUP );
		as_unschedule_all_actions( self::NEXT, array( 'sale' => $id ), self::GROUP );
		$sale = FlashSales::campaign( $id );
		foreach ( array_unique( array( $sale['start'], $sale['end'] ) ) as $time ) {
			if ( $time > time() ) {
				as_schedule_single_action( $time, self::HOOK, array( 'sale' => $id ), self::GROUP );
			}
		}
		delete_post_meta( $id, self::SKIPPED );
		self::run( $id );
	}

	/**
	 * Runs a step soon, in the background.
	 *
	 * @param int $id Campaign.
	 */
	public static function queue( int $id ): void {
		if ( function_exists( 'as_enqueue_async_action' ) && ! as_has_scheduled_action( self::NEXT, array( 'sale' => $id ), self::GROUP ) ) {
			as_enqueue_async_action( self::NEXT, array( 'sale' => $id ), self::GROUP );
		}
	}

	/**
	 * One step: puts back the products the campaign no longer wants (or
	 * wrote with other values), then writes the ones it wants, up to a
	 * batch; queues the next step while some are left.
	 *
	 * @param mixed $id Campaign, from Action Scheduler.
	 */
	public static function step( mixed $id ): void {
		self::run( absint( $id ) );
	}

	/**
	 * A step for a campaign.
	 *
	 * @param int $id Campaign.
	 * @return bool Whether everything is done.
	 */
	public static function run( int $id ): bool {
		if ( 0 === $id || ! function_exists( 'wc_get_product' ) ) {
			return true;
		}
		// One step at a time: a step that read the campaign before a change
		// would write old prices over the new ones. A busy campaign tries again.
		if ( ! self::lock( $id ) ) {
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time() + MINUTE_IN_SECONDS, self::NEXT, array( 'sale' => $id ), self::GROUP );
			}
			return false;
		}
		try {
			return self::work( $id );
		} finally {
			delete_option( self::LOCK . $id );
		}
	}

	/**
	 * Takes a campaign's lock: an option row, as inserting one is atomic. A
	 * lock older than a few minutes is from a step that died and is taken
	 * over.
	 *
	 * @param int $id Campaign.
	 */
	private static function lock( int $id ): bool {
		global $wpdb;
		$name  = self::LOCK . $id;
		$taken = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The lock must not come from a cache.
		if ( $taken > 0 && $taken < time() - 5 * MINUTE_IN_SECONDS ) {
			delete_option( $name );
		}
		return 1 === (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $name, (string) time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic: only one step gets the row.
	}

	/**
	 * The step itself.
	 *
	 * @param int $id Campaign.
	 * @return bool Whether everything is done.
	 */
	private static function work( int $id ): bool {
		$running   = FlashSales::running( $id );
		$items     = $running ? FlashSales::products( $id ) : array();
		$wanted    = self::targets( $items );
		$signature = self::signature( $id );
		$applied   = self::applied( $id );
		$skipped   = self::skipped( $id );
		$left      = self::BATCH;
		$parents   = array();
		if ( $running ) {
			update_post_meta( $id, FlashSales::ITEMS, $items );
		}
		// An ended campaign keeps its last answer.
		if ( $running || 'ended' !== FlashSales::state( $id ) ) {
			if ( $running ? $items : FlashSales::products( $id ) ) {
				delete_post_meta( $id, FlashSales::BARE );
			} else {
				update_post_meta( $id, FlashSales::BARE, '1' );
			}
		}
		foreach ( $applied as $product => $written ) {
			if ( $left <= 0 ) {
				break;
			}
			if ( $written === $signature && isset( $wanted[ $product ] ) ) {
				continue;
			}
			$parents[] = self::restore( $product, $id );
			unset( $applied[ $product ] );
			--$left;
		}
		foreach ( array_keys( $wanted ) as $product ) {
			if ( $left <= 0 ) {
				break;
			}
			if ( isset( $applied[ $product ] ) || isset( $skipped[ $product ] ) ) {
				continue;
			}
			$result = self::apply( $product, $id );
			if ( 'done' === $result ) {
				$applied[ $product ] = $signature;
				$parents[]           = (int) wp_get_post_parent_id( $product );
			} else {
				$skipped[ $product ] = $result;
			}
			--$left;
		}
		foreach ( array_unique( array_filter( $parents ) ) as $parent ) {
			\WC_Product_Variable::sync( $parent );
		}
		self::save( $id, $applied, $skipped );
		// The products shown with the campaign: those with a written price.
		$shown = array();
		foreach ( array_keys( $applied ) as $product ) {
			if ( isset( $wanted[ $product ] ) ) {
				$shown[ $wanted[ $product ] ] = true;
			}
		}
		update_post_meta( $id, FlashSales::SHOWN, array_keys( $shown ) );
		FlashSales::reindex();

		$done = $left > 0;
		// Not through queue(): this step's own action still counts as scheduled.
		if ( ! $done && function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::NEXT, array( 'sale' => $id ), self::GROUP );
		}
		return $done;
	}

	/**
	 * Puts back every product of a campaign that is being deleted.
	 *
	 * @param int      $id   Post being deleted.
	 * @param \WP_Post $post Post.
	 */
	public static function deleted( int $id, \WP_Post $post ): void {
		if ( FlashSales::TYPE !== $post->post_type ) {
			return;
		}
		foreach ( array_keys( self::applied( $id ) ) as $product ) {
			$parent = self::restore( $product, $id );
			if ( $parent > 0 ) {
				\WC_Product_Variable::sync( $parent );
			}
		}
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array( 'sale' => $id ), self::GROUP );
			as_unschedule_all_actions( self::NEXT, array( 'sale' => $id ), self::GROUP );
		}
		delete_post_meta( $id, FlashSales::SHOWN );
		FlashSales::reindex();
	}

	/**
	 * Deleting the plugin: puts every campaign's products back, then removes
	 * the campaigns and their scheduled steps. The post type is not
	 * registered then, so the ids come from the table.
	 */
	public static function uninstall(): void {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", FlashSales::TYPE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uninstall; nothing to cache.
		foreach ( $ids as $id ) {
			$post = get_post( (int) $id );
			if ( $post instanceof \WP_Post ) {
				if ( function_exists( 'wc_get_product' ) ) {
					self::deleted( $post->ID, $post );
				}
				wp_delete_post( $post->ID, true );
			}
		}
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', array(), self::GROUP );
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::LOCK ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uninstall; locks left by a step that died.
	}

	/**
	 * The products whose price is written: simple and external products, and
	 * the variations of variable ones; each with the product it belongs to.
	 *
	 * @param int[] $items Products of the campaign.
	 * @return array<int, int> Product or variation => product.
	 */
	public static function targets( array $items ): array {
		if ( ! $items ) {
			return array();
		}
		$variable   = FlashSales::ids(
			wc_get_products(
				array(
					'include' => $items,
					'type'    => 'variable',
					'limit'   => -1,
					'return'  => 'ids',
				)
			)
		);
		$variations = $variable ? get_posts(
			array(
				'post_type'       => 'product_variation',
				'post_parent__in' => $variable,
				'post_status'     => 'publish',
				'fields'          => 'id=>parent',
				'posts_per_page'  => -1,
				'orderby'         => 'ID',
				'order'           => 'ASC',
			)
		) : array();
		$targets    = array();
		foreach ( array_diff( $items, $variable ) as $item ) {
			$targets[ (int) $item ] = (int) $item;
		}
		foreach ( $variations as $variation => $parent ) {
			$targets[ (int) $variation ] = (int) $parent;
		}
		return $targets;
	}

	/**
	 * Writes the campaign price into a product.
	 *
	 * @param int $product Product or variation.
	 * @param int $id      Campaign.
	 * @return string "done", "taken" (another campaign has it) or "price"
	 *                (gone, or no regular price).
	 */
	private static function apply( int $product, int $id ): string {
		$item = wc_get_product( $product );
		if ( ! $item instanceof \WC_Product || $item->is_type( array( 'variable', 'grouped' ) ) ) {
			return 'price';
		}
		$kept = $item->get_meta( self::META );
		if ( is_array( $kept ) && (int) ( $kept['sale'] ?? 0 ) !== $id && FlashSales::running( (int) ( $kept['sale'] ?? 0 ) ) ) {
			return 'taken';
		}
		$regular = (float) $item->get_regular_price( 'edit' );
		if ( $regular <= 0 ) {
			return 'price';
		}
		$sale  = FlashSales::campaign( $id );
		$price = $regular * ( 100 - $sale['discount'] ) / 100;
		// Never dearer than the product's own sale running now.
		if ( ! is_array( $kept ) && $item->is_on_sale( 'edit' ) ) {
			$price = min( $price, (float) $item->get_sale_price( 'edit' ) );
		}
		$price = wc_format_decimal( $price, wc_get_price_decimals(), true );
		// A product left over from an earlier campaign keeps its first own values.
		if ( ! is_array( $kept ) || (int) ( $kept['sale'] ?? 0 ) !== $id ) {
			$kept = array(
				'sale'  => $id,
				'price' => (string) $item->get_sale_price( 'edit' ),
				'from'  => self::time( $item->get_date_on_sale_from( 'edit' ) ),
				'to'    => self::time( $item->get_date_on_sale_to( 'edit' ) ),
			);
		}
		$kept['ours'] = $price;
		$item->update_meta_data( self::META, $kept );
		$item->set_sale_price( $price );
		$item->set_date_on_sale_from( $sale['start'] );
		$item->set_date_on_sale_to( $sale['end'] );
		$item->save();
		return 'done';
	}

	/**
	 * Puts a product's own sale back.
	 *
	 * @param int $product Product or variation.
	 * @param int $id      Campaign.
	 * @return int The variable product to sync, or 0.
	 */
	private static function restore( int $product, int $id ): int {
		$item = wc_get_product( $product );
		if ( ! $item instanceof \WC_Product ) {
			return 0;
		}
		$kept = $item->get_meta( self::META );
		if ( ! is_array( $kept ) || (int) ( $kept['sale'] ?? 0 ) !== $id ) {
			return 0;
		}
		if ( wc_format_decimal( (string) $item->get_sale_price( 'edit' ) ) === wc_format_decimal( (string) ( $kept['ours'] ?? '' ) ) ) {
			$item->set_sale_price( (string) ( $kept['price'] ?? '' ) );
			$item->set_date_on_sale_from( self::date( $kept['from'] ?? 0 ) );
			$item->set_date_on_sale_to( self::date( $kept['to'] ?? 0 ) );
		}
		$item->delete_meta_data( self::META );
		$item->save();
		return $item->is_type( 'variation' ) ? $item->get_parent_id() : 0;
	}

	/**
	 * What the written prices depend on.
	 *
	 * @param int $id Campaign.
	 */
	private static function signature( int $id ): string {
		$sale = FlashSales::campaign( $id );
		return $sale['discount'] . '/' . $sale['start'] . '/' . $sale['end'];
	}

	/**
	 * Products the campaign has written.
	 *
	 * @param int $id Campaign.
	 * @return array<int, string>
	 */
	public static function applied( int $id ): array {
		$kept = get_post_meta( $id, self::APPLIED, true );
		$list = array();
		foreach ( is_array( $kept ) ? $kept : array() as $product => $signature ) {
			if ( is_int( $product ) && $product > 0 && is_string( $signature ) ) {
				$list[ $product ] = $signature;
			}
		}
		return $list;
	}

	/**
	 * Products the campaign left out, with the reason.
	 *
	 * @param int $id Campaign.
	 * @return array<int, string>
	 */
	public static function skipped( int $id ): array {
		$kept = get_post_meta( $id, self::SKIPPED, true );
		$list = array();
		foreach ( is_array( $kept ) ? $kept : array() as $product => $reason ) {
			if ( is_int( $product ) && $product > 0 && in_array( $reason, array( 'taken', 'price' ), true ) ) {
				$list[ $product ] = $reason;
			}
		}
		return $list;
	}

	/**
	 * Stores what a step did.
	 *
	 * @param int                $id      Campaign.
	 * @param array<int, string> $applied Written products.
	 * @param array<int, string> $skipped Left out products.
	 */
	private static function save( int $id, array $applied, array $skipped ): void {
		if ( $applied ) {
			update_post_meta( $id, self::APPLIED, $applied );
		} else {
			delete_post_meta( $id, self::APPLIED );
		}
		if ( $skipped ) {
			update_post_meta( $id, self::SKIPPED, $skipped );
		} else {
			delete_post_meta( $id, self::SKIPPED );
		}
	}

	/**
	 * Unix time of a sale date, 0 without one.
	 *
	 * @param \WC_DateTime|null $date Date.
	 */
	private static function time( ?\WC_DateTime $date ): int {
		return null === $date ? 0 : $date->getTimestamp();
	}

	/**
	 * A sale date to set again: the Unix time, or empty for none.
	 *
	 * @param mixed $time Kept time.
	 */
	private static function date( mixed $time ): string {
		$time = absint( $time );
		return $time > 0 ? (string) $time : '';
	}
}
