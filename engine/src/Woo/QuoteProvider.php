<?php
/**
 * A service that takes quote requests about products.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * A quote request from the Product enquiry block is stored and mailed by the
 * Forms module, then passed to each available provider, e.g. a CRM or an
 * ERP. Providers join with the pergel/woo/quote_providers filter.
 */
interface QuoteProvider {

	/**
	 * Short unique id, e.g. "divit".
	 */
	public function id(): string;

	/**
	 * Name shown with the stored message.
	 */
	public function label(): string;

	/**
	 * Whether the provider is set up and can take requests now.
	 */
	public function available(): bool;

	/**
	 * Passes one request on. Runs while the visitor waits, so it should
	 * answer within a few seconds; a thrown error counts as a failure.
	 *
	 * @param QuoteRequest $request The request.
	 * @return bool Whether the service took it.
	 */
	public function send( QuoteRequest $request ): bool;
}
