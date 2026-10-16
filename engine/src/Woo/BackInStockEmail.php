<?php
/**
 * Back-in-stock email.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * Tells a waiting address that the product is back in stock, once, with
 * a link to the product and one to remove the address.
 */
final class BackInStockEmail extends StockEmail {

	/**
	 * Sets the email up.
	 */
	public function __construct() {
		$this->id          = StockAlert::EMAIL_BACK;
		$this->title       = __( 'Back in stock', 'pergel-engine' );
		$this->description = __( 'Sent once to each address that asked to be told when a sold-out product or option is back in stock.', 'pergel-engine' );
		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( '{product_name} is back in stock', 'pergel-engine' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Back in stock', 'pergel-engine' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function lines(): array {
		$product = $this->product();
		return array(
			sprintf(
				/* translators: %s: product name. */
				__( 'Good news: %s is back in stock. Stock can run out quickly, so order soon if you want it.', 'pergel-engine' ),
				null === $product ? '…' : wp_strip_all_tags( $product->get_name() )
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function button(): string {
		return __( 'View the product', 'pergel-engine' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function footnote(): array {
		return array(
			'text'  => __( 'You get this email once, because you asked for it. We delete your address 30 days from now.', 'pergel-engine' ),
			'link'  => $this->link,
			'label' => __( 'Delete it now', 'pergel-engine' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function target(): string {
		$product = $this->product();
		if ( null === $product ) {
			return '';
		}
		// The preview's sample product has no page.
		return $product->get_id() > 0 ? $product->get_permalink() : home_url( '/' );
	}
}
