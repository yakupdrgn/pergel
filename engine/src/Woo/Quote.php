<?php
/**
 * Product fields on quote forms and the quote providers.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Forms\Form;
use Pergel\Forms\Inbox;

/**
 * A form about a product (the quote form of the Product enquiry block)
 * names the product and asks for the option of a variable product and the
 * quantity. The checked values are stored and mailed with the message.
 * A stored quote request is then passed to each available QuoteProvider,
 * and the results are kept with the message.
 */
final class Quote {

	/**
	 * Largest quantity a visitor can ask for.
	 */
	public const MAX_QUANTITY = 999999;

	/**
	 * Filter for pergel/forms/subject_fields.
	 *
	 * @param mixed $html    Fields so far.
	 * @param mixed $subject Post the form is about.
	 * @param mixed $id      Form id.
	 */
	public static function fields( mixed $html, mixed $subject, mixed $id ): mixed {
		$product = self::product( $subject );
		if ( null === $product || ! is_string( $id ) || ! is_string( $html ) ) {
			return $html;
		}
		$labels = Form::labels( 'quote' );
		$html  .= '<p class="wp-block-pergel-form__about"><span class="wp-block-pergel-form__about-label">' . esc_html( $labels['product'] ) . ':</span> ' . esc_html( self::product_text( $product ) ) . '</p>';

		$options = self::options( $product );
		if ( array() !== $options ) {
			$input   = $id . '-variation';
			$choices = '<option value="">' . esc_html__( 'Choose an option', 'pergel-engine' ) . '</option>';
			foreach ( $options as $value => $label ) {
				$choices .= '<option value="' . esc_attr( (string) $value ) . '">' . esc_html( $label ) . '</option>';
			}
			$html .= self::wrap(
				'variation',
				$input,
				$labels['variation'],
				'<select id="' . esc_attr( $input ) . '" name="variation" required aria-describedby="' . esc_attr( $input ) . '-error" data-wp-bind--aria-invalid="state.invalid">' . $choices . '</select>'
			);
		}

		$input = $id . '-quantity';
		return $html . self::wrap(
			'quantity',
			$input,
			$labels['quantity'],
			'<input type="number" id="' . esc_attr( $input ) . '" name="quantity" value="1" min="1" max="' . esc_attr( (string) self::MAX_QUANTITY ) . '" step="1" inputmode="numeric" required aria-describedby="' . esc_attr( $input ) . '-error" data-wp-bind--aria-invalid="state.invalid">'
		);
	}

	/**
	 * Filter for pergel/forms/subject_values.
	 *
	 * @param mixed $checked Values and errors so far.
	 * @param mixed $subject Post the form is about.
	 * @param mixed $input   Untrusted input, unslashed.
	 */
	public static function values( mixed $checked, mixed $subject, mixed $input ): mixed {
		$product = self::product( $subject );
		if ( null === $product || ! is_array( $input ) ) {
			return $checked;
		}
		$values = array( 'product' => self::product_text( $product ) );
		$errors = array();

		$options = self::options( $product );
		if ( array() !== $options ) {
			$variation = self::variation( $product, $input );
			if ( null === $variation ) {
				$errors['variation'] = __( 'Choose one of the options.', 'pergel-engine' );
			} else {
				$values['variation'] = $options[ $variation->get_id() ] . self::sku_text( $variation->get_sku( 'edit' ) );
			}
		}

		$quantity = self::quantity( $input );
		if ( 0 === $quantity ) {
			/* translators: %s: largest quantity. */
			$errors['quantity'] = sprintf( __( 'Enter a whole number from 1 to %s.', 'pergel-engine' ), number_format_i18n( self::MAX_QUANTITY ) );
		} else {
			$values['quantity'] = (string) $quantity;
		}

		return array(
			'values' => $values,
			'errors' => $errors,
		);
	}

	/**
	 * Passes a stored quote request about a product to the providers.
	 * Hooked on pergel/forms/received.
	 *
	 * @param mixed $entry  Stored message.
	 * @param mixed $spec   The form.
	 * @param mixed $values Clean field values.
	 * @param mixed $input  Untrusted input, unslashed.
	 */
	public static function received( mixed $entry, mixed $spec, mixed $values, mixed $input ): void {
		if ( ! is_int( $entry ) || ! is_array( $spec ) || 'quote' !== ( $spec['kind'] ?? null ) || ! is_array( $values ) || ! is_array( $input ) ) {
			return;
		}
		$product = self::product( $spec['subject'] ?? 0 );
		if ( null === $product ) {
			return;
		}
		$providers = self::providers();
		if ( array() === $providers ) {
			return;
		}
		$variation = array() === self::options( $product ) ? null : self::variation( $product, $input );
		$sku       = null === $variation ? '' : $variation->get_sku( 'edit' );
		$text      = static fn ( string $field ): string => is_string( $values[ $field ] ?? null ) ? $values[ $field ] : '';
		$request   = new QuoteRequest(
			entry: $entry,
			product: $product->get_id(),
			variation: null === $variation ? 0 : $variation->get_id(),
			sku: '' === $sku ? $product->get_sku( 'edit' ) : $sku,
			quantity: max( 1, self::quantity( $input ) ),
			name: $text( 'name' ),
			email: $text( 'email' ),
			phone: $text( 'phone' ),
			company: $text( 'company' ),
			message: $text( 'message' ),
			page: is_int( $spec['post'] ?? null ) ? $spec['post'] : 0,
		);

		$handoff = array();
		foreach ( $providers as $provider ) {
			try {
				$sent = $provider->send( $request );
			} catch ( \Throwable $error ) {
				$sent = false;
			}
			$handoff[ $provider->id() ] = array(
				'label' => $provider->label(),
				'sent'  => $sent,
			);
		}
		update_post_meta( $entry, Inbox::META_HANDOFF, $handoff );
	}

	/**
	 * The available quote providers.
	 *
	 * @return list<QuoteProvider>
	 */
	public static function providers(): array {
		/**
		 * Filters the services quote requests about products are passed to.
		 *
		 * @param QuoteProvider[] $providers None by default.
		 */
		$providers = apply_filters( 'pergel/woo/quote_providers', array() );
		$available = array();
		foreach ( is_array( $providers ) ? $providers : array() as $provider ) {
			if ( $provider instanceof QuoteProvider && $provider->available() ) {
				$available[] = $provider;
			}
		}
		return $available;
	}

	/**
	 * Address of the page where a quote about the product is asked, when a
	 * quote plugin keeps its own form there; empty for the quote form of the
	 * Product enquiry block. The product page then links there instead of
	 * folding the form, and so do the cards' "Request a quote" buttons.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function url( \WC_Product $product ): string {
		/**
		 * Filters the address of a quote plugin's form for a product.
		 *
		 * @param string      $url     Empty by default: Pergel's own form.
		 * @param \WC_Product $product Product.
		 */
		$url = apply_filters( 'pergel/woo/quote_url', '', $product );
		return is_string( $url ) ? esc_url_raw( $url, array( 'https', 'http' ) ) : '';
	}

	/**
	 * The options of a variable product: labels of its published
	 * variations by id. Empty for other products.
	 *
	 * @param \WC_Product $product Product.
	 * @return array<int, string>
	 */
	public static function options( \WC_Product $product ): array {
		if ( ! $product->is_type( 'variable' ) ) {
			return array();
		}
		$options = array();
		foreach ( $product->get_children() as $child ) {
			$variation = wc_get_product( $child );
			if ( $variation instanceof \WC_Product_Variation && 'publish' === $variation->get_status() ) {
				$options[ $variation->get_id() ] = wp_strip_all_tags( wc_get_formatted_variation( $variation, true, true, false ) );
			}
		}
		return $options;
	}

	/**
	 * A published product, not a variation.
	 *
	 * @param mixed $id Post id.
	 */
	private static function product( mixed $id ): ?\WC_Product {
		if ( ! is_int( $id ) || $id <= 0 || 'product' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return null;
		}
		$product = wc_get_product( $id );
		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * The variation the visitor chose, if it is one of the options.
	 *
	 * @param \WC_Product  $product Variable product.
	 * @param array<mixed> $input   Untrusted input.
	 */
	private static function variation( \WC_Product $product, array $input ): ?\WC_Product {
		$chosen = $input['variation'] ?? '';
		$chosen = is_string( $chosen ) && 1 === preg_match( '/^[1-9][0-9]{0,18}$/', $chosen ) ? (int) $chosen : 0;
		if ( ! isset( self::options( $product )[ $chosen ] ) ) {
			return null;
		}
		$variation = wc_get_product( $chosen );
		return $variation instanceof \WC_Product ? $variation : null;
	}

	/**
	 * The quantity asked for, or 0 when it is not a whole number in range.
	 *
	 * @param array<mixed> $input Untrusted input.
	 */
	private static function quantity( array $input ): int {
		$quantity = $input['quantity'] ?? '';
		$quantity = is_string( $quantity ) ? trim( $quantity ) : '';
		if ( 1 !== preg_match( '/^[0-9]{1,6}$/', $quantity ) ) {
			return 0;
		}
		$quantity = (int) $quantity;
		return $quantity >= 1 && $quantity <= self::MAX_QUANTITY ? $quantity : 0;
	}

	/**
	 * Product name with its SKU.
	 *
	 * @param \WC_Product $product Product.
	 */
	private static function product_text( \WC_Product $product ): string {
		return wp_strip_all_tags( $product->get_name( 'edit' ) ) . self::sku_text( $product->get_sku( 'edit' ) );
	}

	/**
	 * " (SKU: X)", or '' without a SKU.
	 *
	 * @param string $sku SKU.
	 */
	private static function sku_text( string $sku ): string {
		/* translators: %s: stock keeping unit. */
		return '' === $sku ? '' : ' (' . sprintf( __( 'SKU: %s', 'pergel-engine' ), $sku ) . ')';
	}

	/**
	 * A required field with its label and error slot.
	 *
	 * @param string $name    Field name.
	 * @param string $input   Input id.
	 * @param string $label   Label text.
	 * @param string $control Escaped control HTML.
	 */
	private static function wrap( string $name, string $input, string $label, string $control ): string {
		return '<div class="wp-block-pergel-form__field is-' . esc_attr( $name ) . '" ' . wp_interactivity_data_wp_context( array( 'field' => $name ) ) . '>'
			. '<label for="' . esc_attr( $input ) . '">' . esc_html( $label ) . ' <span aria-hidden="true">*</span></label>'
			. $control
			. Form::error( $input )
			. '</div>';
	}
}
