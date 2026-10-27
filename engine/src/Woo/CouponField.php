<?php
/**
 * Coupons shown on product pages.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * A checkbox in the General panel of a coupon: "Show on product pages".
 * The product page lists the chosen coupons that apply to the product
 * (ProductPage, with the "Coupons" setting on).
 */
final class CouponField {

	/**
	 * Coupon meta: "yes" when the coupon is shown on product pages.
	 */
	public const META = '_pergel_show';

	/**
	 * Adds the admin hooks; Layout calls it.
	 */
	public static function register(): void {
		add_action( 'woocommerce_coupon_options', array( self::class, 'field' ), 20, 2 );
		add_action( 'woocommerce_coupon_options_save', array( self::class, 'save' ), 10, 2 );
	}

	/**
	 * The checkbox.
	 *
	 * @param int        $id     Coupon id.
	 * @param \WC_Coupon $coupon Coupon.
	 */
	public static function field( int $id, \WC_Coupon $coupon ): void {
		woocommerce_wp_checkbox(
			array(
				'id'          => self::META,
				'label'       => __( 'Show on product pages', 'pergel-engine' ),
				'value'       => 'yes' === $coupon->get_meta( self::META ) ? 'yes' : 'no',
				'description' => __( 'List this coupon on the pages of the products it applies to, with a button that copies the code (Dashboard → Pergel → Shop → Coupons).', 'pergel-engine' ),
			)
		);
	}

	/**
	 * Saves the checkbox. WooCommerce has checked the nonce and the
	 * capability.
	 *
	 * @param int        $id     Coupon id.
	 * @param \WC_Coupon $coupon Coupon being saved.
	 */
	public static function save( int $id, \WC_Coupon $coupon ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checked the nonce before this action; only presence is read.
		if ( isset( $_POST[ self::META ] ) ) {
			$coupon->update_meta_data( self::META, 'yes' );
		} else {
			$coupon->delete_meta_data( self::META );
		}
		$coupon->save_meta_data();
	}
}
