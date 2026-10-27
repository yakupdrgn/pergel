<?php
/**
 * Estimated delivery date.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

/**
 * The estimated delivery dates under the add-to-cart form ("Estimated
 * delivery: 20–22 October"), and before the cut-off time on a working day
 * that an order ships today.
 *
 * An order placed before the cut-off on a working day counts from that
 * day, a later one from the next working day. It ships after the handling
 * days and arrives after the shortest to the longest delivery time, all
 * counted in working days, skipping the days off. A shipping class can set
 * its own delivery time ("2-4") under WooCommerce → Settings → Shipping →
 * Classes; the product's own class counts, not a variation's.
 *
 * The page carries the rules, and the script counts the dates in the
 * site's time zone, so a cached page never shows yesterday's dates. The
 * block (pergel/delivery-estimate) is hooked after the add-to-cart form;
 * it draws nothing for a product that is not shipped or that the visitor
 * cannot buy here (see Modes), hides for a chosen variation of that kind,
 * and draws nothing without JavaScript.
 */
final class Delivery {

	public const BLOCK = 'pergel/delivery-estimate';

	/**
	 * Term meta of a shipping class: its delivery time, "min-max".
	 */
	public const META = 'pergel_delivery_days';

	/**
	 * Column of the shipping classes screen, and the field's name.
	 */
	private const COLUMN = 'pergel-delivery';
	private const FIELD  = 'pergel_delivery';

	/**
	 * Product types with a single add-to-cart form for one product.
	 */
	private const TYPES = array( 'simple', 'variable' );

	/**
	 * Working days of each setting, as JavaScript counts them (0 is Sunday).
	 */
	private const DAYS = array(
		'mon_fri'   => array( 1, 2, 3, 4, 5 ),
		'mon_sat'   => array( 1, 2, 3, 4, 5, 6 ),
		'every_day' => array( 0, 1, 2, 3, 4, 5, 6 ),
	);

	/**
	 * Adds the hooks. Woo\Module calls it when the setting is on.
	 */
	public static function register(): void {
		add_filter( 'hooked_block_types', array( self::class, 'hook' ), 22, 4 );
		if ( is_admin() ) {
			add_filter( 'woocommerce_shipping_classes_columns', array( self::class, 'columns' ) );
			add_action( 'woocommerce_shipping_classes_column_' . self::COLUMN, array( self::class, 'column' ) );
			add_action( 'woocommerce_shipping_classes_save_class', array( self::class, 'save_class' ), 10, 2 );
			add_filter( 'woocommerce_get_shipping_classes', array( self::class, 'classes' ) );
			add_action( 'admin_enqueue_scripts', array( self::class, 'admin_style' ), 20 );
		}
	}

	/**
	 * Filter for hooked_block_types: the estimate after the add-to-cart
	 * form, not where it is placed by hand.
	 *
	 * @param mixed $hooked   Block types hooked at this position.
	 * @param mixed $position Relative position.
	 * @param mixed $anchor   Anchor block type.
	 * @param mixed $context  Pattern data, template or post the blocks are in.
	 * @return mixed
	 */
	public static function hook( mixed $hooked, mixed $position, mixed $anchor, mixed $context ): mixed {
		if ( ! is_array( $hooked ) || 'after' !== $position || 'woocommerce/add-to-cart-with-options' !== $anchor ) {
			return $hooked;
		}
		if ( ! str_contains( self::content( $context ), 'wp:' . self::BLOCK ) ) {
			$hooked[] = self::BLOCK;
		}
		return $hooked;
	}

	/**
	 * The block's markup: hidden until the script has counted the dates.
	 *
	 * @param \WP_Block|null $block Block instance.
	 */
	public static function render( ?\WP_Block $block = null ): string {
		if ( wp_is_serving_rest_request() ) {
			return '<div ' . get_block_wrapper_attributes() . '><p>' . esc_html__( 'Shown on product pages: the estimated delivery dates, counted when the page opens.', 'pergel-engine' ) . '</p></div>';
		}
		$product = true === Settings::get( 'woo.delivery' ) ? self::product( $block ) : null;
		if ( ! $product instanceof \WC_Product ) {
			return '';
		}
		$config = self::config( $product );
		$today  = '';
		if ( null !== $config['cutoff'] && 0 === $config['handling'] ) {
			$time = wp_date( (string) get_option( 'time_format', 'H:i' ), self::today_at( $config['cutoff'] ) );
			/* translators: %s: cut-off time, such as 15:00. */
			$today = '<p class="wp-block-pergel-delivery-estimate__today" hidden>' . esc_html( sprintf( __( 'Order by %s and it ships today.', 'pergel-engine' ), (string) $time ) ) . '</p>';
		}
		return '<div ' . get_block_wrapper_attributes(
			array(
				'data-pergel-delivery' => (string) wp_json_encode( $config ),
				'hidden'               => 'hidden',
			)
		) . '><p class="wp-block-pergel-delivery-estimate__dates">'
			. '<span class="wp-block-pergel-delivery-estimate__label">' . esc_html__( 'Estimated delivery:', 'pergel-engine' ) . '</span> '
			. '<strong class="wp-block-pergel-delivery-estimate__range"></strong>'
			. '</p>' . $today . '</div>';
	}

	/**
	 * The product the estimate is for: one in the block's context that is
	 * shipped and that the visitor can buy here.
	 *
	 * @param \WP_Block|null $block Block instance.
	 */
	public static function product( ?\WP_Block $block ): ?\WC_Product {
		if ( ! wc_shipping_enabled() ) {
			return null;
		}
		$id      = $block instanceof \WP_Block && is_int( $block->context['postId'] ?? null ) ? $block->context['postId'] : (int) get_the_ID();
		$product = $id > 0 && 'product' === get_post_type( $id ) ? wc_get_product( $id ) : null;
		if ( ! $product instanceof \WC_Product || ! $product->is_type( self::TYPES ) || $product->is_virtual() ) {
			return null;
		}
		// Purchasable covers the sales modes: Selling turns it off where the product is hidden.
		return $product->is_purchasable() && $product->is_in_stock() ? $product : null;
	}

	/**
	 * The rules the script counts the dates with.
	 *
	 * @param \WC_Product $product Product.
	 * @return array{cutoff: int|null, handling: int, min: int, max: int, days: list<int>, off: list<list<string>>, tz: string, offset: int, locale: string, skip: list<list<string>>}
	 */
	public static function config( \WC_Product $product ): array {
		$cutoff = (string) Settings::get( 'woo.delivery_cutoff' );
		$range  = self::days( get_term_meta( $product->get_shipping_class_id(), self::META, true ) )
			?? array( (int) Settings::get( 'woo.delivery_min' ), (int) Settings::get( 'woo.delivery_max' ) );
		$days   = (string) Settings::get( 'woo.delivery_days' );
		$off    = array();
		foreach ( (array) Settings::get( 'woo.delivery_holidays' ) as $date ) {
			$parts = explode( '/', (string) $date );
			$off[] = array( $parts[0], $parts[1] ?? $parts[0] );
		}
		$zone = wp_timezone_string();
		return array(
			'cutoff'   => preg_match( '/^(\d{2}):(\d{2})$/', $cutoff, $m ) ? (int) $m[1] * 60 + (int) $m[2] : null,
			'handling' => (int) Settings::get( 'woo.delivery_handling' ),
			'min'      => min( $range ),
			'max'      => max( $range ),
			'days'     => self::DAYS[ $days ] ?? self::DAYS['mon_fri'],
			'off'      => $off,
			// A city's zone follows its clock changes; a fixed offset is all a "+03:00" site has.
			'tz'       => preg_match( '/^[+-]/', $zone ) ? '' : $zone,
			'offset'   => intdiv( wp_timezone()->getOffset( new \DateTimeImmutable( 'now' ) ), 60 ),
			'locale'   => str_replace( '_', '-', determine_locale() ),
			'skip'     => self::skip( $product ),
		);
	}

	/**
	 * Option values of the variations with no estimate: not shipped, out of
	 * stock or not for sale. The script hides the estimate while all values
	 * of one of them are chosen; an "Any" value matches every choice.
	 *
	 * @param \WC_Product $product Product.
	 * @return list<list<string>>
	 */
	private static function skip( \WC_Product $product ): array {
		if ( ! $product->is_type( 'variable' ) ) {
			return array();
		}
		$skip = array();
		foreach ( $product->get_children() as $id ) {
			$variation = wc_get_product( $id );
			if ( ! $variation instanceof \WC_Product_Variation || ( $variation->is_in_stock() && $variation->is_purchasable() && ! $variation->is_virtual() ) ) {
				continue;
			}
			$skip[] = array_values( array_filter( array_map( 'strval', $variation->get_variation_attributes() ), static fn( string $value ): bool => '' !== $value ) );
		}
		return $skip;
	}

	/**
	 * A delivery time as written: "3", or "2-4" from the shortest to the
	 * longest. Null when it is empty or not a delivery time.
	 *
	 * @param mixed $value Value.
	 * @return array{0: int, 1: int}|null
	 */
	public static function days( mixed $value ): ?array {
		if ( ! is_string( $value ) || ! preg_match( '/^\s*(\d{1,2})\s*(?:[-–—]\s*(\d{1,2})\s*)?$/u', $value, $m ) ) {
			return null;
		}
		$min = (int) $m[1];
		$max = isset( $m[2] ) ? (int) $m[2] : $min;
		if ( max( $min, $max ) > 60 ) {
			return null;
		}
		return array( min( $min, $max ), max( $min, $max ) );
	}

	/**
	 * Filter for woocommerce_shipping_classes_columns: the delivery time
	 * column, before the product count.
	 *
	 * @param mixed $columns Column keys and headings.
	 * @return mixed
	 */
	public static function columns( mixed $columns ): mixed {
		if ( ! is_array( $columns ) ) {
			return $columns;
		}
		$heading = array( self::COLUMN => __( 'Delivery time', 'pergel-engine' ) );
		$at      = array_search( 'wc-shipping-class-count', array_keys( $columns ), true );
		return false === $at ? $columns + $heading : array_slice( $columns, 0, $at, true ) + $heading + array_slice( $columns, $at, null, true );
	}

	/**
	 * The column's cell in the table and its field in the class's window,
	 * both Underscore templates; the stylesheet hides the field in the
	 * table.
	 */
	public static function column(): void {
		echo '<div class="view">{{ data.' . esc_attr( self::FIELD ) . ' }}</div>'
			. '<div class="edit"><input type="text" name="' . esc_attr( self::FIELD ) . '" data-attribute="' . esc_attr( self::FIELD ) . '" value="{{ data.' . esc_attr( self::FIELD ) . ' }}" placeholder="2-4" inputmode="numeric" /></div>'
			. '<div class="wc-shipping-class-modal-help-text">' . esc_html__( 'Working days from shipping to delivery, such as 3 or 2-4, for products of this class. Leave it empty to use Pergel → Settings → Shop.', 'pergel-engine' ) . '</div>';
	}

	/**
	 * Action for woocommerce_shipping_classes_save_class: stores the
	 * delivery time. WooCommerce has checked the nonce and the capability.
	 *
	 * @param mixed $term_id Shipping class ID.
	 * @param mixed $data    Posted fields of the class.
	 */
	public static function save_class( mixed $term_id, mixed $data ): void {
		if ( ! is_int( $term_id ) || $term_id <= 0 || ! is_array( $data ) || ! array_key_exists( self::FIELD, $data ) ) {
			return;
		}
		$days = self::days( $data[ self::FIELD ] );
		if ( null === $days ) {
			delete_term_meta( $term_id, self::META );
			return;
		}
		update_term_meta( $term_id, self::META, $days[0] === $days[1] ? (string) $days[0] : $days[0] . '-' . $days[1] );
	}

	/**
	 * Filter for woocommerce_get_shipping_classes: each class with its
	 * delivery time, for the classes screen. New copies, so cached terms
	 * stay as they are.
	 *
	 * @param mixed $classes Shipping classes.
	 * @return mixed
	 */
	public static function classes( mixed $classes ): mixed {
		if ( ! is_array( $classes ) ) {
			return $classes;
		}
		return array_map(
			static function ( mixed $term ): mixed {
				if ( ! $term instanceof \WP_Term ) {
					return $term;
				}
				$fields                = get_object_vars( $term );
				$fields[ self::FIELD ] = (string) get_term_meta( $term->term_id, self::META, true );
				return new \WP_Term( (object) $fields );
			},
			$classes
		);
	}

	/**
	 * Hides the column's field in the classes table; the window shows it.
	 */
	public static function admin_style(): void {
		if ( wp_style_is( 'woocommerce_admin_styles', 'enqueued' ) ) {
			wp_add_inline_style( 'woocommerce_admin_styles', 'table.wc-shipping-classes .' . self::COLUMN . ' .edit,table.wc-shipping-classes .' . self::COLUMN . ' .wc-shipping-class-modal-help-text{display:none}' );
		}
	}

	/**
	 * Timestamp of a time of day today, in the site's time zone.
	 *
	 * @param int $minutes Minutes after midnight.
	 */
	private static function today_at( int $minutes ): int {
		return ( new \DateTimeImmutable( 'today', wp_timezone() ) )->setTime( intdiv( $minutes, 60 ), $minutes % 60 )->getTimestamp();
	}

	/**
	 * Markup of the hook context: a pattern's content, a template's or a
	 * post's.
	 *
	 * @param mixed $context Hook context.
	 */
	private static function content( mixed $context ): string {
		if ( is_array( $context ) ) {
			return (string) ( $context['content'] ?? '' );
		}
		if ( $context instanceof \WP_Block_Template ) {
			return (string) $context->content;
		}
		return $context instanceof \WP_Post ? $context->post_content : '';
	}
}
