<?php
/**
 * Quick view of a product from a product card.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

/**
 * GET /pergel/v1/quick-view?id=N returns the markup the quick view window
 * shows (assets/shop-cards.js): pictures, name, price, short description,
 * SKU, stock and, for simple products that can be bought, a quantity box
 * and the add to cart button (a plain form, as on the product page), plus
 * a link to the product page. Prices and buttons follow the sales modes,
 * as everywhere (Selling filters the price and whether it can be bought).
 *
 * The route exists only while the "Quick view" setting is on; Layout adds
 * the hook for REST requests, so other pages never load this class.
 */
final class QuickView {

	/**
	 * Most pictures in the window.
	 */
	private const PICTURES = 6;

	/**
	 * Registers the route. Hooked on rest_api_init.
	 */
	public static function routes(): void {
		if ( true !== Settings::get( 'shop.quick_view' ) ) {
			return;
		}
		register_rest_route(
			'pergel/v1',
			'/quick-view',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'minimum'  => 1,
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * The window's markup for a published, visible product.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 */
	public static function show( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$product = wc_get_product( (int) $request->get_param( 'id' ) );
		if ( ! $product instanceof \WC_Product || 'publish' !== $product->get_status() || ! $product->is_visible() || post_password_required( $product->get_id() ) ) {
			return new \WP_Error( 'pergel_quick_view', __( 'This product cannot be shown.', 'pergel-engine' ), array( 'status' => 404 ) );
		}
		return new \WP_REST_Response(
			array(
				'title' => $product->get_name(),
				'html'  => self::markup( $product ),
			)
		);
	}

	/**
	 * The window's content.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function markup( \WC_Product $product ): string {
		$link    = (string) $product->get_permalink();
		$price   = (string) $product->get_price_html();
		$summary = trim( (string) $product->get_short_description() );
		if ( '' === $summary ) {
			$summary = wp_trim_words( wp_strip_all_tags( (string) $product->get_description() ), 40 );
		}
		$sku = (string) $product->get_sku();

		$html  = '<div class="pergel-quick">';
		$html .= self::pictures( $product );
		$html .= '<div class="pergel-quick__summary">';
		$html .= sprintf( '<h2 class="pergel-quick__title" id="pergel-quick-title">%s</h2>', esc_html( $product->get_name() ) );
		if ( '' !== $price ) {
			$html .= '<div class="pergel-quick__price">' . wp_kses_post( $price ) . '</div>';
		}
		if ( '' !== $summary ) {
			$html .= '<div class="pergel-quick__text">' . wp_kses_post( wpautop( $summary ) ) . '</div>';
		}
		if ( '' !== $sku ) {
			$html .= sprintf( '<p class="pergel-quick__sku">%1$s %2$s</p>', esc_html__( 'SKU:', 'pergel-engine' ), esc_html( $sku ) );
		}
		$html .= wp_kses_post( wc_get_stock_html( $product ) );
		$html .= self::cart_form( $product );
		$html .= sprintf( '<p class="pergel-quick__more"><a href="%1$s">%2$s</a></p>', esc_url( $link ), esc_html__( 'View full details', 'pergel-engine' ) );
		$html .= '</div></div>';
		return $html;
	}

	/**
	 * The main picture and buttons for the other pictures.
	 *
	 * @param \WC_Product $product Product.
	 */
	private static function pictures( \WC_Product $product ): string {
		$ids = array_slice( array_values( array_unique( array_filter( array_map( 'intval', array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) ) ) ) ), 0, self::PICTURES );
		if ( ! $ids ) {
			return '<div class="pergel-quick__media">' . wp_kses_post( wc_placeholder_img( 'woocommerce_single' ) ) . '</div>';
		}
		$alt    = static fn ( int $id ): string => trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		$main   = wp_get_attachment_image(
			$ids[0],
			'woocommerce_single',
			false,
			array(
				'class' => 'pergel-quick__image',
				'alt'   => '' !== $alt( $ids[0] ) ? $alt( $ids[0] ) : $product->get_name(),
			)
		);
		$thumbs = '';
		if ( count( $ids ) > 1 ) {
			foreach ( $ids as $index => $id ) {
				$thumbs .= sprintf(
					'<li><button type="button" class="pergel-quick__thumb" data-pergel-src="%1$s" data-pergel-srcset="%2$s" data-pergel-alt="%3$s" aria-pressed="%4$s">%5$s<span class="screen-reader-text">%6$s</span></button></li>',
					esc_url( (string) wp_get_attachment_image_url( $id, 'woocommerce_single' ) ),
					esc_attr( (string) wp_get_attachment_image_srcset( $id, 'woocommerce_single' ) ),
					esc_attr( '' !== $alt( $id ) ? $alt( $id ) : $product->get_name() ),
					0 === $index ? 'true' : 'false',
					wp_get_attachment_image(
						$id,
						'woocommerce_gallery_thumbnail',
						false,
						array(
							'alt'     => '',
							'loading' => 'lazy',
						)
					),
					/* translators: %d: picture number. */
					esc_html( sprintf( __( 'Picture %d', 'pergel-engine' ), $index + 1 ) )
				);
			}
			$thumbs = '<ul class="pergel-quick__thumbs">' . $thumbs . '</ul>';
		}
		return '<div class="pergel-quick__media">' . $main . $thumbs . '</div>';
	}

	/**
	 * Quantity and add to cart, for a simple product that can be bought now.
	 *
	 * @param \WC_Product $product Product.
	 */
	private static function cart_form( \WC_Product $product ): string {
		if ( ! $product->is_type( 'simple' ) || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return '';
		}
		$quantity = '';
		if ( ! $product->is_sold_individually() ) {
			$most     = $product->get_max_purchase_quantity();
			$quantity = sprintf(
				'<label class="screen-reader-text" for="pergel-quick-quantity">%1$s</label><input type="number" id="pergel-quick-quantity" class="pergel-quick__quantity" name="quantity" value="1" min="1"%2$s step="1" inputmode="numeric">',
				esc_html__( 'Quantity', 'pergel-engine' ),
				$most > 0 ? sprintf( ' max="%d"', $most ) : ''
			);
		}
		return sprintf(
			'<form class="pergel-quick__cart" method="post" action="%1$s">%2$s<button type="submit" name="add-to-cart" value="%3$d" class="wp-element-button">%4$s</button></form>',
			esc_url( (string) $product->get_permalink() ),
			$quantity,
			$product->get_id(),
			esc_html( $product->single_add_to_cart_text() )
		);
	}
}
