<?php
/**
 * Question answered email.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * Tells the customer who asked a question about a product that the shop
 * answered it: the question, the answer and a link to the product page.
 * Sent once, with the first answer.
 */
final class QuestionEmail extends StockEmail {

	/**
	 * Key in WooCommerce's list of emails.
	 */
	public const KEY = 'pergel_question_answered';

	/**
	 * The question, as plain text.
	 *
	 * @var string
	 */
	public string $question = '';

	/**
	 * The answer, as plain text.
	 *
	 * @var string
	 */
	public string $answer = '';

	/**
	 * Sets the email up.
	 */
	public function __construct() {
		$this->id          = self::KEY;
		$this->title       = __( 'Question answered', 'pergel-engine' );
		$this->description = __( 'Sent to a customer who asked a question about a product, when the shop answers it.', 'pergel-engine' );
		parent::__construct();
	}

	/**
	 * Sends the email about an answered question.
	 *
	 * @param \WP_Comment $question The question.
	 * @param \WP_Comment $answer   Its answer.
	 * @param \WC_Product $product  The product it is about.
	 * @return bool Whether the email was sent.
	 */
	public function answered( \WP_Comment $question, \WP_Comment $answer, \WC_Product $product ): bool {
		$this->setup_locale();
		$this->object                         = $product;
		$this->recipient                      = $question->comment_author_email;
		$this->question                       = $question->comment_content;
		$this->answer                         = $answer->comment_content;
		$this->link                           = '';
		$this->placeholders['{product_name}'] = wp_strip_all_tags( $product->get_name() );
		$sent                                 = '' !== $this->recipient && $this->send_notification();
		$this->restore_locale();
		return $sent;
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Your question about {product_name} is answered', 'pergel-engine' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Your question is answered', 'pergel-engine' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function lines(): array {
		$product = $this->product();
		return array(
			/* translators: %s: product name. */
			sprintf( __( 'You asked about %s:', 'pergel-engine' ), null === $product ? '…' : wp_strip_all_tags( $product->get_name() ) ),
			'' === $this->question ? __( 'Does it come with a warranty?', 'pergel-engine' ) : $this->question,
			__( 'Our answer:', 'pergel-engine' ),
			'' === $this->answer ? __( 'Yes, two years.', 'pergel-engine' ) : $this->answer,
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
			'text'  => '',
			'link'  => '',
			'label' => '',
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
		return $product->get_id() > 0 ? $product->get_permalink() . '#' . Questions::ANCHOR : home_url( '/' );
	}
}
