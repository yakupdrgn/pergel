<?php
/**
 * Base of the shop's own customer emails.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * What the back-in-stock emails and the question answered email share:
 * the product they are about, the address they go to, one link and
 * WooCommerce's header and footer.
 * Loaded only from the woocommerce_email_classes filter, once WC_Email
 * exists.
 *
 * @phpstan-import-type Row from StockAlertStore
 */
abstract class StockEmail extends \WC_Email {

	/**
	 * The link the email is for: confirm, or remove the address.
	 *
	 * @var string
	 */
	public string $link = '';

	/**
	 * Sets the shared parts. Subclasses set id, title and description
	 * first.
	 */
	public function __construct() {
		$this->customer_email  = true;
		$this->supports_cc_bcc = false;
		$this->placeholders    = array( '{product_name}' => '' );
		$this->template_html   = '';
		$this->template_plain  = '';
		parent::__construct();
	}

	/**
	 * Sends the email about an alert.
	 *
	 * @param array       $row     The alert.
	 * @param \WC_Product $product Its product or variation.
	 * @param string      $link    Link of the email.
	 * @phpstan-param Row $row
	 * @return bool Whether the email was sent.
	 */
	public function trigger( array $row, \WC_Product $product, string $link ): bool {
		$this->setup_locale();
		$this->object                         = $product;
		$this->recipient                      = $row['email'];
		$this->link                           = $link;
		$this->placeholders['{product_name}'] = wp_strip_all_tags( $product->get_name() );
		$sent                                 = $this->send_notification();
		$this->restore_locale();
		return $sent;
	}

	/**
	 * Filter for woocommerce_prepare_email_for_preview: a sample product
	 * and link, so the preview shows the whole email.
	 *
	 * @param mixed $email Email being previewed.
	 * @return mixed
	 */
	public static function preview( mixed $email ): mixed {
		if ( $email instanceof self ) {
			$product = new \WC_Product();
			$product->set_name( __( 'Sample product', 'pergel-engine' ) );
			$email->object                         = $product;
			$email->link                           = home_url( '/' );
			$email->placeholders['{product_name}'] = $product->get_name();
		}
		return $email;
	}

	/**
	 * The product, or null before trigger().
	 */
	protected function product(): ?\WC_Product {
		return $this->object instanceof \WC_Product ? $this->object : null;
	}

	/**
	 * The paragraphs above the button, as plain text.
	 *
	 * @return list<string>
	 */
	abstract protected function lines(): array;

	/**
	 * Text of the button.
	 */
	abstract protected function button(): string;

	/**
	 * Small print under the button, as plain text, and its link.
	 *
	 * @return array{text: string, link: string, label: string}
	 */
	abstract protected function footnote(): array;

	/**
	 * Where the button goes.
	 */
	abstract protected function target(): string;

	/**
	 * HTML body, between WooCommerce's header and footer.
	 *
	 * @return string
	 */
	public function get_content_html() {
		ob_start();
		do_action( 'woocommerce_email_header', $this->get_heading(), $this ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own action.
		foreach ( $this->lines() as $line ) {
			echo '<p>' . esc_html( $line ) . '</p>';
		}
		$target = $this->target();
		if ( '' !== $target ) {
			// The colours set under WooCommerce → Settings → Emails.
			$base  = sanitize_hex_color( (string) get_option( 'woocommerce_email_base_color', '#720eec' ) ) ?? '#720eec';
			$text  = wc_light_or_dark( $base, '#1e1e1e', '#ffffff' );
			$style = 'display:inline-block;padding:12px 20px;border-radius:4px;background-color:' . $base . ';color:' . $text . ';text-decoration:none;font-weight:bold;';
			echo '<p><a class="button" href="' . esc_url( $target ) . '" style="' . esc_attr( $style ) . '">' . esc_html( $this->button() ) . '</a></p>';
		}
		$note = $this->footnote();
		if ( '' !== $note['text'] ) {
			echo '<p><small>' . esc_html( $note['text'] ) . ( '' === $note['link'] ? '' : ' <a href="' . esc_url( $note['link'] ) . '">' . esc_html( $note['label'] ) . '</a>' ) . '</small></p>';
		}
		$extra = $this->get_additional_content();
		if ( '' !== $extra ) {
			echo wp_kses_post( wpautop( wptexturize( $extra ) ) );
		}
		do_action( 'woocommerce_email_footer', $this ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own action.
		return (string) ob_get_clean();
	}

	/**
	 * Plain text body.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		$parts  = array( '= ' . wp_strip_all_tags( $this->get_heading() ) . ' =' );
		$parts  = array_merge( $parts, $this->lines() );
		$target = $this->target();
		if ( '' !== $target ) {
			$parts[] = $this->button() . ': ' . $target;
		}
		$note = $this->footnote();
		if ( '' !== $note['text'] ) {
			$parts[] = $note['text'] . ( '' === $note['link'] ? '' : ' ' . $note['label'] . ': ' . $note['link'] );
		}
		$extra = $this->get_additional_content();
		if ( '' !== $extra ) {
			$parts[] = wp_strip_all_tags( wptexturize( $extra ) );
		}
		return implode( "\n\n", $parts ) . "\n";
	}
}
