<?php
/**
 * One quote request about a product.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * What a QuoteProvider receives. Texts are the checked values the visitor
 * sent; empty when the form did not ask for them.
 */
final class QuoteRequest {

	/**
	 * Builds the request.
	 *
	 * @param int    $entry     Stored message (Pergel → Form messages).
	 * @param int    $product   Product id.
	 * @param int    $variation Variation id; 0 for a product without options.
	 * @param string $sku       SKU of the variation or the product; may be empty.
	 * @param int    $quantity  Quantity asked for.
	 * @param string $name      Sender's name.
	 * @param string $email     Sender's email address.
	 * @param string $phone     Sender's phone number.
	 * @param string $company   Sender's company.
	 * @param string $message   What the sender needs.
	 * @param int    $page      Page the form was on.
	 */
	public function __construct(
		public readonly int $entry,
		public readonly int $product,
		public readonly int $variation,
		public readonly string $sku,
		public readonly int $quantity,
		public readonly string $name,
		public readonly string $email,
		public readonly string $phone,
		public readonly string $company,
		public readonly string $message,
		public readonly int $page,
	) {
	}
}
