<?php
/**
 * The Product enquiry block.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

/**
 * What a visitor can do on a product page when the product is not sold to
 * them (see Modes): log in to see prices, ask for a quote with the product's
 * options, or get in touch by WhatsApp, phone or the contact page. On a
 * product that is for sale the block shows nothing, unless it is set to
 * show the contact links there too. Every link and the quote form report a
 * generate_lead event to Google Analytics.
 */
final class Cta {

	/**
	 * Interactivity store namespace.
	 */
	public const STORE = 'pergel/product-cta';

	/**
	 * Anchor of the quote form; a link to it opens the form.
	 */
	public const QUOTE_ANCHOR = 'pergel-quote';

	/**
	 * Block markup.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param \WP_Block|null       $block      Block instance.
	 */
	public static function render( array $attributes, ?\WP_Block $block = null ): string {
		$id      = $block instanceof \WP_Block && is_int( $block->context['postId'] ?? null ) ? $block->context['postId'] : (int) get_the_ID();
		$product = $id > 0 && 'product' === get_post_type( $id ) ? wc_get_product( $id ) : null;
		if ( ! $product instanceof \WC_Product ) {
			return wp_is_serving_rest_request()
				? '<div ' . get_block_wrapper_attributes() . '><p>' . esc_html__( 'Shown on product pages: what a visitor can do when a product is not sold to them.', 'pergel-engine' ) . '</p></div>'
				: '';
		}
		// The mode itself, not Modes::hidden(), so the editor shows what visitors see.
		$mode = Modes::of( $product );
		$sold = Modes::sells( $mode );
		$on   = static fn ( string $name, bool $fallback = true ): bool => (bool) ( $attributes[ $name ] ?? $fallback );

		$html = '';
		if ( Modes::LOGIN === $mode && ! is_user_logged_in() ) {
			$html .= '<p class="wp-block-pergel-product-cta__note">' . esc_html__( 'Prices are shown to registered customers.', 'pergel-engine' ) . '</p>'
				. '<p class="wp-block-pergel-product-cta__login"><a class="wp-element-button" href="' . esc_url( wp_login_url( (string) get_permalink( $product->get_id() ) ) ) . '">' . esc_html__( 'Log in to see prices', 'pergel-engine' ) . '</a></p>';
		}
		if ( Modes::QUOTE === $mode && $on( 'quote' ) ) {
			$html .= self::quote( $product );
		}
		if ( ! $sold || $on( 'onSale', false ) ) {
			$html .= self::contacts( $product, $on( 'whatsapp' ), $on( 'phone' ), $on( 'contact' ) );
		}
		if ( '' === $html ) {
			return '';
		}
		return '<div ' . get_block_wrapper_attributes( array( 'class' => 'is-mode-' . $mode ) ) . ' data-wp-interactive="' . esc_attr( self::STORE ) . '">' . $html . '</div>';
	}

	/**
	 * The quote form about the product, folded under a button. It is open
	 * when the page comes back with a result (the form sent without
	 * JavaScript), and when the address ends with its anchor (a card's
	 * "Request a quote" button).
	 *
	 * @param \WC_Product $product Product.
	 */
	private static function quote( \WC_Product $product ): string {
		$form = ( new \WP_Block(
			array(
				'blockName'    => 'pergel/form',
				'attrs'        => array(
					'kind'        => 'quote',
					'phone'       => true,
					'company'     => true,
					'aboutPost'   => true,
					'submitLabel' => __( 'Send the request', 'pergel-engine' ),
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			array(
				'postId'   => $product->get_id(),
				'postType' => 'product',
			)
		) )->render();
		if ( '' === trim( $form ) ) {
			// The Forms module is switched off.
			return '';
		}
		$open = isset( $_GET['pergel_form'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only opens the panel.
		return '<details id="' . self::QUOTE_ANCHOR . '" class="wp-block-pergel-product-cta__quote" data-wp-init="callbacks.anchor"' . ( $open ? ' open' : '' ) . '>'
			. '<summary class="wp-element-button">' . esc_html__( 'Request a quote', 'pergel-engine' ) . '</summary>'
			. '<div class="wp-block-pergel-product-cta__panel">' . $form . '</div>'
			. '</details>';
	}

	/**
	 * Links to get in touch about the product.
	 *
	 * @param \WC_Product $product  Product.
	 * @param bool        $whatsapp Show the WhatsApp link.
	 * @param bool        $phone    Show the phone link.
	 * @param bool        $contact  Show the contact page link.
	 */
	private static function contacts( \WC_Product $product, bool $whatsapp, bool $phone, bool $contact ): string {
		$links = array();
		$name  = wp_strip_all_tags( $product->get_name() );
		$url   = (string) get_permalink( $product->get_id() );

		$number = self::digits( Settings::get( 'woo.whatsapp' ) );
		if ( $whatsapp && '' !== $number ) {
			/* translators: 1: product name, 2: product page address. */
			$text    = sprintf( __( 'Hello, I would like to ask about %1$s: %2$s', 'pergel-engine' ), $name, $url );
			$links[] = array( 'whatsapp', 'https://wa.me/' . $number . '?text=' . rawurlencode( $text ), __( 'Ask on WhatsApp', 'pergel-engine' ) );
		}
		$tel = Settings::get( 'brand.phone' );
		if ( $phone && is_string( $tel ) && '' !== self::digits( $tel ) ) {
			/* translators: %s: phone number. */
			$links[] = array( 'phone', 'tel:' . ( str_starts_with( trim( $tel ), '+' ) ? '+' : '' ) . self::digits( $tel ), sprintf( __( 'Call %s', 'pergel-engine' ), $tel ) );
		}
		$page = Settings::get( 'woo.contact_url' );
		if ( $contact && is_string( $page ) && '' !== $page ) {
			$links[] = array( 'contact_page', $page, __( 'Contact us', 'pergel-engine' ) );
		}
		if ( array() === $links ) {
			return '';
		}

		$items = '';
		foreach ( $links as [ $source, $href, $label ] ) {
			$items .= '<li><a class="wp-block-pergel-product-cta__link is-' . esc_attr( str_replace( '_', '-', $source ) ) . '" href="' . esc_url( $href, array( 'https', 'http', 'tel' ) ) . '" '
				. wp_interactivity_data_wp_context( array( 'source' => $source ) ) . ' data-wp-on--click="actions.lead">' . esc_html( $label ) . '</a></li>';
		}
		return '<ul class="wp-block-pergel-product-cta__contacts" aria-label="' . esc_attr__( 'Get in touch about this product', 'pergel-engine' ) . '">' . $items . '</ul>';
	}

	/**
	 * The digits of a phone number.
	 *
	 * @param mixed $number Phone number.
	 */
	private static function digits( mixed $number ): string {
		return is_string( $number ) ? (string) preg_replace( '/\D+/', '', $number ) : '';
	}
}
