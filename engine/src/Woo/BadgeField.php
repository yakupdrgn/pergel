<?php
/**
 * A product's own badge text.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * A text box in the General panel of the product data; the product card
 * shows the text as a badge on the picture (Cards). Empty shows none.
 */
final class BadgeField {

	/**
	 * Adds the admin hooks; Layout calls it.
	 */
	public static function register(): void {
		add_action( 'woocommerce_product_options_general_product_data', array( self::class, 'field' ), 20 );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save' ) );
	}

	/**
	 * Cleans a badge text: one line, at most 24 characters.
	 *
	 * @param mixed $value Raw text.
	 */
	public static function clean( mixed $value ): string {
		return is_string( $value ) ? mb_substr( trim( sanitize_text_field( $value ) ), 0, Cards::BADGE_LENGTH ) : '';
	}

	/**
	 * The text box.
	 */
	public static function field(): void {
		global $product_object;
		$value = $product_object instanceof \WC_Product ? self::clean( $product_object->get_meta( Cards::BADGE_META ) ) : '';

		echo '<div class="options_group">';
		woocommerce_wp_text_input(
			array(
				'id'                => Cards::BADGE_META,
				'label'             => __( 'Badge', 'pergel-engine' ),
				'value'             => $value,
				'placeholder'       => __( 'e.g. Limited', 'pergel-engine' ),
				'desc_tip'          => true,
				'description'       => __( 'A short label on the product picture in product grids. Leave empty for none.', 'pergel-engine' ),
				'custom_attributes' => array( 'maxlength' => (string) Cards::BADGE_LENGTH ),
			)
		);
		echo '</div>';
	}

	/**
	 * Saves the text. WooCommerce has checked the nonce and the capability.
	 *
	 * @param \WC_Product $product Product being saved.
	 */
	public static function save( \WC_Product $product ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WooCommerce checked the nonce before this action; clean() sanitizes.
		$text = self::clean( isset( $_POST[ Cards::BADGE_META ] ) ? wp_unslash( $_POST[ Cards::BADGE_META ] ) : '' );
		if ( '' === $text ) {
			$product->delete_meta_data( Cards::BADGE_META );
		} else {
			$product->update_meta_data( Cards::BADGE_META, $text );
		}
	}
}
