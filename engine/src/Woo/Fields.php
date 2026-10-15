<?php
/**
 * Sales mode fields on products and product categories.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * A select box in the General panel of the product data and in the
 * product category forms. Empty means the product follows its categories
 * and the category follows its parent or the shop.
 */
final class Fields {

	/**
	 * Adds the hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'meta' ) );
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'woocommerce_product_options_general_product_data', array( self::class, 'product_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save_product' ) );
		add_action( 'product_cat_add_form_fields', array( self::class, 'category_add' ) );
		add_action( 'product_cat_edit_form_fields', array( self::class, 'category_edit' ) );
		add_action( 'created_product_cat', array( self::class, 'save_category' ) );
		add_action( 'edited_product_cat', array( self::class, 'save_category' ) );
	}

	/**
	 * Registers the meta keys with their sanitize callback.
	 */
	public static function meta(): void {
		$args = array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'sanitize_callback' => array( Modes::class, 'sanitize' ),
			'show_in_rest'      => false,
		);
		register_post_meta( 'product', Modes::POST_META, $args + array( 'auth_callback' => static fn(): bool => current_user_can( 'edit_products' ) ) );
		register_term_meta( 'product_cat', Modes::TERM_META, $args + array( 'auth_callback' => static fn(): bool => current_user_can( 'manage_product_terms' ) ) );
	}

	/**
	 * Choices with the inherit option first.
	 *
	 * @param string $inherit Label of the empty choice.
	 * @return array<string, string>
	 */
	private static function choices( string $inherit ): array {
		return array( '' => $inherit ) + Modes::labels();
	}

	/**
	 * The select box in the product data.
	 */
	public static function product_field(): void {
		global $product_object;
		$value = $product_object instanceof \WC_Product ? Modes::sanitize( $product_object->get_meta( Modes::POST_META ) ) : '';

		echo '<div class="options_group">';
		woocommerce_wp_select(
			array(
				'id'          => Modes::POST_META,
				'label'       => __( 'Sales mode', 'pergel-engine' ),
				'options'     => self::choices( __( 'Same as its categories or the shop', 'pergel-engine' ) ),
				'value'       => $value,
				'desc_tip'    => true,
				'description' => __( 'Whether this product shows its price and can be added to the cart. Quote and catalog hide the price everywhere.', 'pergel-engine' ),
			)
		);
		echo '</div>';
	}

	/**
	 * Saves the product's mode. WooCommerce has checked the nonce and the
	 * capability.
	 *
	 * @param \WC_Product $product Product being saved.
	 */
	public static function save_product( \WC_Product $product ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked by WooCommerce before this action.
		$mode = Modes::sanitize( isset( $_POST[ Modes::POST_META ] ) ? sanitize_key( wp_unslash( $_POST[ Modes::POST_META ] ) ) : '' );
		if ( '' === $mode ) {
			$product->delete_meta_data( Modes::POST_META );
		} else {
			$product->update_meta_data( Modes::POST_META, $mode );
		}
	}

	/**
	 * The select box in the "Add new category" form.
	 */
	public static function category_add(): void {
		printf(
			'<div class="form-field"><label for="%1$s">%2$s</label>%3$s<p>%4$s</p></div>',
			esc_attr( Modes::TERM_META ),
			esc_html__( 'Sales mode', 'pergel-engine' ),
			self::select( '' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in select().
			esc_html( self::category_help() )
		);
	}

	/**
	 * The select box in the category edit form.
	 *
	 * @param mixed $term Category being edited.
	 */
	public static function category_edit( mixed $term ): void {
		$value = $term instanceof \WP_Term ? Modes::sanitize( get_term_meta( $term->term_id, Modes::TERM_META, true ) ) : '';
		printf(
			'<tr class="form-field"><th scope="row"><label for="%1$s">%2$s</label></th><td>%3$s<p class="description">%4$s</p></td></tr>',
			esc_attr( Modes::TERM_META ),
			esc_html__( 'Sales mode', 'pergel-engine' ),
			self::select( $value ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in select().
			esc_html( self::category_help() )
		);
	}

	/**
	 * Help text of the category field.
	 */
	private static function category_help(): string {
		return __( 'Applies to the products of this category and its subcategories, unless a product chooses its own mode. A product in several categories takes the strictest mode.', 'pergel-engine' );
	}

	/**
	 * Select box HTML for the category forms.
	 *
	 * @param string $value Current mode; '' inherits.
	 */
	private static function select( string $value ): string {
		$html = sprintf( '<select id="%1$s" name="%1$s">', esc_attr( Modes::TERM_META ) );
		foreach ( self::choices( __( 'Same as the parent category or the shop', 'pergel-engine' ) ) as $mode => $label ) {
			$html .= sprintf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $mode ), selected( $value, $mode, false ), esc_html( $label ) );
		}
		return $html . '</select>';
	}

	/**
	 * Saves a category's mode. WordPress has checked the form nonce.
	 *
	 * @param int $term_id Category id.
	 */
	public static function save_category( int $term_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked by WordPress before the term is saved.
		if ( ! isset( $_POST[ Modes::TERM_META ] ) || ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- As above.
		$mode = Modes::sanitize( sanitize_key( wp_unslash( $_POST[ Modes::TERM_META ] ) ) );
		if ( '' === $mode ) {
			delete_term_meta( $term_id, Modes::TERM_META );
		} else {
			update_term_meta( $term_id, Modes::TERM_META, $mode );
		}
	}
}
