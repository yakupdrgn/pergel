<?php
/**
 * Back-in-stock alert confirmation email.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * Asks a visitor who is not logged in to confirm the address of an alert
 * (double opt-in). It cannot be turned off: without it no such alert
 * would start.
 */
final class StockConfirmEmail extends StockEmail {

	/**
	 * Sets the email up.
	 */
	public function __construct() {
		$this->id          = StockAlert::EMAIL_CONFIRM;
		$this->title       = __( 'Back-in-stock alert confirmation', 'pergel-engine' );
		$this->description = __( 'Sent to visitors who are not logged in when they ask to be told that a product is back in stock, with a link to confirm their email address.', 'pergel-engine' );
		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Confirm your back-in-stock alert for {product_name}', 'pergel-engine' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Confirm your alert', 'pergel-engine' );
	}

	/**
	 * Always on.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return true;
	}

	/**
	 * The settings, without the switch to turn the email off.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		unset( $this->form_fields['enabled'] );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function lines(): array {
		$product = $this->product();
		return array(
			sprintf(
				/* translators: %s: product name. */
				__( 'You asked us to email you when %s is back in stock. Please confirm your email address with the button below.', 'pergel-engine' ),
				null === $product ? '…' : wp_strip_all_tags( $product->get_name() )
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function button(): string {
		return __( 'Confirm my alert', 'pergel-engine' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function footnote(): array {
		return array(
			'text'  => __( 'If you did not ask for this, ignore this email: without confirmation your address is deleted in 7 days and we will not write again.', 'pergel-engine' ),
			'link'  => '',
			'label' => '',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function target(): string {
		return $this->link;
	}
}
