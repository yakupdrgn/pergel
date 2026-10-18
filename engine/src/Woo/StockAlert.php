<?php
/**
 * Back-in-stock alerts.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Core\Visitor;
use Pergel\Forms\Form;
use Pergel\Forms\Submit;
use Pergel\Settings\Labels;
use Pergel\Settings\Settings;

/**
 * A form under the add-to-cart form of a sold-out product, or of a chosen
 * sold-out option, where a visitor leaves an email address; when the
 * product is back in stock, each address gets one email.
 *
 * A visitor who is not logged in confirms the address first, with a link
 * in an email (double opt-in); a logged-in customer who gives the address
 * of the account is signed up at once. The form is guarded like the
 * contact form: a trap field, a signed token, a few seconds' wait, no
 * posts from other sites, and the same limits per connection and for the
 * whole site (Forms\Submit). An address gets at most MAILS_PER_DAY
 * confirmation emails a day, whatever the products, and the answer never
 * tells whether an address is signed up already. The links in the emails
 * open a page with a button: mail scanners that open links do not
 * confirm or remove anything. Only products in the "sale" or "login"
 * sales mode take alerts.
 * When a product or
 * variation comes back in stock, Action Scheduler sends the emails in
 * batches of BATCH. Both emails are WooCommerce emails, so their texts
 * and looks are edited under WooCommerce → Settings → Emails. The
 * addresses are kept in StockAlertStore and listed under WooCommerce →
 * Stock alerts.
 *
 * The block (pergel/stock-alert) is hooked after the add-to-cart form.
 * For a sold-out product it shows the form; for a product with some
 * options sold out the script shows it while such an option is chosen.
 *
 * @phpstan-import-type Row from StockAlertStore
 * @phpstan-type Result array{code: string, errors: array<string, string>}
 */
final class StockAlert {

	public const BLOCK = 'pergel/stock-alert';

	public const ROUTE  = 'stock-alert';
	public const ACTION = 'pergel_stock_alert';

	/**
	 * Action Scheduler hook that emails the addresses waiting for a product.
	 */
	public const SEND_HOOK = 'pergel/stock_alert/send';

	/**
	 * Addresses emailed per run.
	 */
	public const BATCH = 50;

	/**
	 * Confirmation emails an address gets in a day.
	 */
	public const MAILS_PER_DAY = 3;

	/**
	 * Keys of the two emails in WooCommerce's list.
	 */
	public const EMAIL_CONFIRM = 'pergel_stock_alert_confirm';
	public const EMAIL_BACK    = 'pergel_back_in_stock';

	/**
	 * Address parameters of the links in the emails, and of the result
	 * shown on the product page.
	 */
	private const LINK   = 'pergel_stock';
	private const RESULT = 'pergel_stock_alert';

	/**
	 * Id of the block on the page, the target of the links.
	 */
	private const ANCHOR = 'pergel-stock-alert';

	/**
	 * Product types with a single add-to-cart form for one product.
	 */
	private const TYPES = array( 'simple', 'variable' );

	/**
	 * HTTP status per result code.
	 */
	private const STATUS = array(
		'confirm'     => 200,
		'subscribed'  => 200,
		'invalid'     => 422,
		'wait'        => 400,
		'limit'       => 429,
		'expired'     => 400,
		'unavailable' => 409,
	);

	/**
	 * Adds the hooks. Woo\Module calls it when the setting is on.
	 */
	public static function register(): void {
		add_filter( 'hooked_block_types', array( self::class, 'hook' ), 22, 4 );
		add_action( 'rest_api_init', array( self::class, 'route' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( self::class, 'fallback' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'fallback' ) );
		add_action( 'woocommerce_product_set_stock_status', array( self::class, 'stock_status' ), 10, 2 );
		add_action( 'woocommerce_variation_set_stock_status', array( self::class, 'stock_status' ), 10, 2 );
		add_filter( 'woocommerce_email_classes', array( self::class, 'emails' ) );
		add_filter( 'woocommerce_prepare_email_for_preview', array( StockEmail::class, 'preview' ) );
		if ( is_admin() ) {
			add_action( 'admin_init', array( StockAlertStore::class, 'maintain' ) );
		}
	}

	/**
	 * Filter for hooked_block_types: the form after the add-to-cart form,
	 * not where it is placed by hand.
	 *
	 * @param mixed $hooked   Block types hooked at this position.
	 * @param mixed $position Relative position.
	 * @param mixed $anchor   Anchor block type.
	 * @param mixed $context  Pattern data, template or post the blocks are in.
	 * @return mixed
	 */
	public static function hook( mixed $hooked, mixed $position, mixed $anchor, mixed $context ): mixed {
		if ( ! is_array( $hooked ) || 'after' !== $position || 'woocommerce/add-to-cart-with-options' !== $anchor ) {
			return $hooked;
		}
		if ( ! str_contains( self::content( $context ), 'wp:' . self::BLOCK ) ) {
			$hooked[] = self::BLOCK;
		}
		return $hooked;
	}

	/**
	 * The block's markup.
	 *
	 * @param \WP_Block|null $block Block instance.
	 */
	public static function render( ?\WP_Block $block = null ): string {
		if ( wp_is_serving_rest_request() ) {
			return '<div ' . get_block_wrapper_attributes() . '><p>' . esc_html__( 'Shown on product pages: a form for an email when a sold-out product or option is back in stock.', 'pergel-engine' ) . '</p></div>';
		}
		$product = true === Settings::get( 'woo.stock_alert' ) ? self::product( $block ) : null;
		$result  = self::returned();
		$notice  = null === $result ? '' : '<p class="wp-block-pergel-stock-alert__notice" role="status">' . esc_html( self::message( $result ) ) . '</p>';
		if ( ! $product instanceof \WC_Product ) {
			return '' === $notice ? '' : '<div ' . get_block_wrapper_attributes( array( 'id' => self::ANCHOR ) ) . '>' . $notice . '</div>';
		}
		$whole      = self::sold_out( $product );
		$variations = $whole ? array() : self::variations( $product );
		if ( ! $whole && array() === $variations ) {
			return '' === $notice ? '' : '<div ' . get_block_wrapper_attributes( array( 'id' => self::ANCHOR ) ) . '>' . $notice . '</div>';
		}

		$config = array(
			'endpoint'   => rest_url( Submit::NAMESPACE . '/' . self::ROUTE ),
			'variations' => $variations,
			'offline'    => Form::message( 'offline' ),
		);
		$user   = wp_get_current_user();
		$email  = '';
		if ( $user->exists() ) {
			// Logged-in pages are not cached, so the nonce stays fresh.
			$config['nonce'] = wp_create_nonce( 'wp_rest' );
			$email           = $user->user_email;
		}
		$id      = self::ANCHOR;
		$policy  = get_privacy_policy_url();
		$consent = '' === $policy
			? esc_html__( 'I agree that my email address is kept to tell me when this product is back in stock.', 'pergel-engine' )
			: sprintf(
				/* translators: %s: link to the privacy policy. */
				esc_html__( 'I agree that my email address is kept to tell me when this product is back in stock, as described in the %s.', 'pergel-engine' ),
				'<a href="' . esc_url( $policy ) . '">' . esc_html__( 'privacy policy', 'pergel-engine' ) . '</a>'
			);
		$attrs = array(
			'id'                => $id,
			'data-pergel-stock' => (string) wp_json_encode( $config ),
		);
		if ( ! $whole && '' === $notice ) {
			$attrs['hidden'] = 'hidden';
		}

		return '<div ' . get_block_wrapper_attributes( $attrs ) . '>'
			. $notice
			. '<form class="wp-block-pergel-stock-alert__form" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post"' . ( $whole ? '' : ' hidden' ) . ' aria-labelledby="' . esc_attr( $id ) . '-title">'
			. '<p class="wp-block-pergel-stock-alert__title" id="' . esc_attr( $id ) . '-title">' . esc_html__( 'Get an email when it is back in stock', 'pergel-engine' ) . '</p>'
			. '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">'
			. '<input type="hidden" name="token" value="' . esc_attr( self::sign( $product->get_id() ) ) . '">'
			. '<input type="hidden" name="product" value="' . esc_attr( (string) $product->get_id() ) . '">'
			. '<div class="wp-block-pergel-stock-alert__field is-email">'
			. '<label for="' . esc_attr( $id ) . '-email">' . esc_html__( 'Email address', 'pergel-engine' ) . ' <span aria-hidden="true">*</span></label>'
			. '<div class="wp-block-pergel-stock-alert__row">'
			. '<input type="email" id="' . esc_attr( $id ) . '-email" name="email" value="' . esc_attr( $email ) . '" maxlength="100" autocomplete="email" required aria-describedby="' . esc_attr( $id ) . '-email-error">'
			. '<button type="submit" class="wp-block-pergel-stock-alert__submit wp-element-button">' . esc_html__( 'Notify me', 'pergel-engine' ) . '</button>'
			. '</div>'
			. '<p class="wp-block-pergel-stock-alert__error" id="' . esc_attr( $id ) . '-email-error" hidden></p>'
			. '</div>'
			. '<div class="wp-block-pergel-stock-alert__field is-consent">'
			. '<input type="checkbox" id="' . esc_attr( $id ) . '-consent" name="consent" value="1" required aria-describedby="' . esc_attr( $id ) . '-consent-error">'
			. '<label for="' . esc_attr( $id ) . '-consent">' . $consent . ' <span aria-hidden="true">*</span></label>'
			. '<p class="wp-block-pergel-stock-alert__error" id="' . esc_attr( $id ) . '-consent-error" hidden></p>'
			. '</div>'
			. '<div class="wp-block-pergel-stock-alert__trap" aria-hidden="true"><label for="' . esc_attr( $id ) . '-website">' . esc_html__( 'Leave this field empty', 'pergel-engine' ) . '</label>'
			. '<input type="text" id="' . esc_attr( $id ) . '-website" name="' . esc_attr( Submit::TRAP ) . '" tabindex="-1" autocomplete="off"></div>'
			. '<p class="wp-block-pergel-stock-alert__status" role="status"></p>'
			. '</form></div>';
	}

	/**
	 * The product page's product: one in the block's context that the
	 * visitor could buy here when it is in stock.
	 *
	 * @param \WP_Block|null $block Block instance.
	 */
	public static function product( ?\WP_Block $block ): ?\WC_Product {
		return self::page_product( $block instanceof \WP_Block && is_int( $block->context['postId'] ?? null ) ? $block->context['postId'] : (int) get_the_ID() );
	}

	/**
	 * A product that could be bought here when it is in stock.
	 *
	 * @param int $id Product id.
	 */
	private static function page_product( int $id ): ?\WC_Product {
		$product = $id > 0 && 'product' === get_post_type( $id ) ? wc_get_product( $id ) : null;
		if ( ! $product instanceof \WC_Product || ! $product->is_type( self::TYPES ) ) {
			return null;
		}
		// Purchasable covers the sales modes: Selling turns it off where the product is hidden. A product
		// whose options are all sold out may have no price, so it asks the filter without one.
		$purchasable = $product->is_type( 'variable' )
			? apply_filters( 'woocommerce_is_purchasable', $product->exists() && $product->is_viewable(), $product ) // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own filter.
			: $product->is_purchasable();
		return true === $purchasable ? $product : null;
	}

	/**
	 * Whether the whole product is sold out: a simple product, or a
	 * product whose options are all sold out. Its alert is for the
	 * product itself; a product with options comes back in stock when one
	 * of them does.
	 *
	 * @param \WC_Product $product Product.
	 */
	private static function sold_out( \WC_Product $product ): bool {
		return ! $product->is_in_stock() && ( ! $product->is_type( 'variable' ) || array() !== $product->get_children() );
	}

	/**
	 * The sold-out options of a product with options in stock, with the
	 * option values that pick them; an "Any" value matches every choice.
	 *
	 * @param \WC_Product $product Product.
	 * @return list<array{id: int, values: list<string>}>
	 */
	private static function variations( \WC_Product $product ): array {
		if ( ! $product->is_type( 'variable' ) ) {
			return array();
		}
		$list = array();
		foreach ( $product->get_children() as $id ) {
			$variation = wc_get_product( $id );
			if ( ! $variation instanceof \WC_Product_Variation || $variation->is_in_stock() || ! $variation->is_purchasable() ) {
				continue;
			}
			$values = array_values( array_filter( array_map( 'strval', $variation->get_variation_attributes() ), static fn( string $value ): bool => '' !== $value ) );
			if ( array() !== $values ) {
				$list[] = array(
					'id'     => $variation->get_id(),
					'values' => $values,
				);
			}
		}
		return $list;
	}

	/**
	 * The product or option an alert may be for: the page's sold-out
	 * product, or a sold-out option of it, that could be bought here.
	 *
	 * @param int   $page  Product of the page, from the token.
	 * @param mixed $given Product or variation id from the form.
	 */
	public static function target( int $page, mixed $given ): ?\WC_Product {
		$id = is_numeric( $given ) ? (int) $given : 0;
		if ( $id <= 0 || $page <= 0 ) {
			return null;
		}
		$product = wc_get_product( $id );
		if ( ! $product instanceof \WC_Product || $product->is_in_stock() || ! self::for_sale( $product ) || 'publish' !== get_post_status( $product->is_type( 'variation' ) ? $product->get_parent_id() : $id ) ) {
			return null;
		}
		if ( $id === $page ) {
			$own = self::page_product( $id );
			return null !== $own && self::sold_out( $own ) ? $product : null;
		}
		return $product instanceof \WC_Product_Variation && $product->get_parent_id() === $page && $product->is_purchasable() ? $product : null;
	}

	/**
	 * Whether the product's sales mode sells it to someone here: "sale" or
	 * "login". The modes are not applied everywhere (admin-post, cron), so
	 * the alerts ask for the mode themselves.
	 *
	 * @param \WC_Product $product Product or variation.
	 */
	private static function for_sale( \WC_Product $product ): bool {
		return in_array( Modes::of( $product ), array( Modes::SALE, Modes::LOGIN ), true );
	}

	/**
	 * Registers the endpoint. Hooked on rest_api_init.
	 */
	public static function route(): void {
		register_rest_route(
			Submit::NAMESPACE,
			'/' . self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rest' ),
				// A public form: anyone may send it. The token, the limit and the confirmation guard it.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * POST pergel/v1/stock-alert.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 */
	public static function rest( \WP_REST_Request $request ): \WP_REST_Response {
		$input  = $request->get_body_params();
		$input  = array() === $input ? (array) $request->get_json_params() : $input;
		$result = self::handle( $input, Submit::ip(), get_current_user_id() );
		return new \WP_REST_Response(
			array(
				'ok'      => in_array( $result['code'], array( 'confirm', 'subscribed' ), true ),
				'code'    => $result['code'],
				'message' => self::message( $result['code'] ),
				'errors'  => (object) $result['errors'],
			),
			self::STATUS[ $result['code'] ] ?? 400
		);
	}

	/**
	 * The form sent without JavaScript: handles it and goes back to the
	 * product with the result in the address. Hooked on admin_post(_nopriv).
	 * A logged-in customer counts as a guest here: the browser sends their
	 * cookies with a form another site posts, so the address is confirmed
	 * by email. (The REST route knows the user only with a nonce.)
	 */
	public static function fallback(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Public form; pages may be cached, so the signed token stands in for a nonce. handle() checks every field.
		$input  = wp_unslash( $_POST );
		$result = self::handle( $input, Submit::ip(), 0 );
		$page   = self::verify( $input['token'] ?? null );
		$back   = null === $page ? false : get_permalink( $page['product'] );
		$back   = is_string( $back ) ? $back : home_url( '/' );
		wp_safe_redirect( add_query_arg( self::RESULT, $result['code'], $back ) . '#' . self::ANCHOR, 303 );
		exit;
	}

	/**
	 * Checks and stores one alert, and emails the confirmation link when
	 * the address needs one.
	 *
	 * @param array<mixed> $input Untrusted input, unslashed.
	 * @param string       $ip    Visitor's address, for the limit.
	 * @param int          $user  Logged-in user, 0 for a guest.
	 * @return array<string, mixed>
	 * @phpstan-return Result
	 */
	public static function handle( array $input, string $ip, int $user ): array {
		$result = array(
			'code'   => 'expired',
			'errors' => array(),
		);
		$trap   = $input[ Submit::TRAP ] ?? '';
		if ( ! is_string( $trap ) || '' !== trim( $trap ) ) {
			$result['code'] = 'confirm';
			return $result;
		}
		$spec = self::verify( $input['token'] ?? null );
		if ( null === $spec ) {
			return $result;
		}
		if ( time() - $spec['time'] < Submit::MIN_SECONDS ) {
			$result['code'] = 'wait';
			return $result;
		}
		if ( Visitor::foreign_origin() ) {
			return $result;
		}
		$product = self::target( $spec['product'], $input['product'] ?? null );
		if ( null === $product ) {
			$result['code'] = 'unavailable';
			return $result;
		}
		$email = is_string( $input['email'] ?? null ) ? strtolower( trim( $input['email'] ) ) : '';
		if ( strlen( $email ) > 100 || ! is_email( $email ) ) {
			$result['errors']['email'] = Labels::problem( 'invalid_email' );
		}
		if ( '1' !== ( $input['consent'] ?? null ) ) {
			$result['errors']['consent'] = __( 'Tick the box to agree, so we can email you.', 'pergel-engine' );
		}
		if ( array() !== $result['errors'] ) {
			$result['code'] = 'invalid';
			return $result;
		}
		if ( ! Submit::within_limit( $ip ) || ! Submit::within_site_cap() ) {
			$result['code'] = 'limit';
			return $result;
		}

		StockAlertStore::maintain();
		StockAlertStore::purge();
		$account  = $user > 0 ? get_userdata( $user ) : false;
		$existing = StockAlertStore::find( $product->get_id(), $email );
		if ( $account instanceof \WP_User && strtolower( $account->user_email ) === $email ) {
			if ( null === $existing || StockAlertStore::ACTIVE !== $existing['status'] ) {
				StockAlertStore::save( $product->get_id(), $email, $user, StockAlertStore::ACTIVE );
			}
			$result['code'] = 'subscribed';
			return $result;
		}
		// From here the answer is always the same, so no one learns whether an address is signed up.
		$result['code'] = 'confirm';
		if ( null !== $existing && StockAlertStore::ACTIVE === $existing['status'] ) {
			return $result;
		}
		// One confirmation email an hour per address and product, however often the form is sent.
		if ( null !== $existing && StockAlertStore::PENDING === $existing['status'] && strtotime( $existing['created'] . ' UTC' ) > time() - HOUR_IN_SECONDS ) {
			return $result;
		}
		// And a few a day per address, whatever the products.
		if ( ! Visitor::hit( 'stock-mail:' . $email, self::MAILS_PER_DAY, DAY_IN_SECONDS ) ) {
			return $result;
		}
		$row = StockAlertStore::save( $product->get_id(), $email, $user, StockAlertStore::PENDING );
		if ( null !== $row ) {
			$mail = self::email( self::EMAIL_CONFIRM );
			if ( $mail instanceof StockConfirmEmail ) {
				$mail->trigger( $row, $product, self::link( 'confirm', $row ) );
			}
		}
		return $result;
	}

	/**
	 * A link of the emails: confirm or remove.
	 *
	 * @param string $action confirm or remove.
	 * @param array  $row    The alert.
	 * @phpstan-param Row $row
	 */
	public static function link( string $action, array $row ): string {
		return add_query_arg(
			array(
				self::LINK          => $action,
				self::LINK . '_id'  => $row['id'],
				self::LINK . '_key' => self::key( $action, $row ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Handles a link from the emails. Opening it shows a page with a
	 * button; the button confirms or removes the alert and goes to the
	 * product with the result. Mail scanners and link previews open links
	 * but do not press buttons. Hooked on template_redirect.
	 */
	public static function link_clicked(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- A link from an email; the key, made from a site secret, is its proof.
		$action = isset( $_GET[ self::LINK ] ) && is_string( $_GET[ self::LINK ] ) ? sanitize_key( wp_unslash( $_GET[ self::LINK ] ) ) : '';
		if ( ! in_array( $action, array( 'confirm', 'remove' ), true ) ) {
			return;
		}
		$id  = isset( $_GET[ self::LINK . '_id' ] ) && is_numeric( $_GET[ self::LINK . '_id' ] ) ? (int) $_GET[ self::LINK . '_id' ] : 0;
		$key = isset( $_GET[ self::LINK . '_key' ] ) && is_string( $_GET[ self::LINK . '_key' ] ) ? sanitize_key( wp_unslash( $_GET[ self::LINK . '_key' ] ) ) : '';
		// phpcs:enable
		$posted = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'];
		$code   = $posted ? self::use_link( $action, $id, $key ) : self::check_link( $action, $id, $key );
		if ( null === $code['product'] ) {
			wp_die(
				esc_html( self::message( 'link' ) ),
				esc_html__( 'Back-in-stock alert', 'pergel-engine' ),
				array(
					'response'  => 410,
					'link_url'  => esc_url( home_url( '/' ) ),
					'link_text' => esc_html__( 'Go to the home page', 'pergel-engine' ),
				)
			);
		}
		nocache_headers();
		if ( ! $posted ) {
			self::link_page( $action, $id, $key, $code['product'] );
		}
		$page = $code['product']->is_type( 'variation' ) ? $code['product']->get_parent_id() : $code['product']->get_id();
		$back = get_permalink( $page );
		wp_safe_redirect( add_query_arg( self::RESULT, $code['code'], is_string( $back ) ? $back : home_url( '/' ) ) . '#' . self::ANCHOR, 303 );
		exit;
	}

	/**
	 * The page an email link opens: what the button will do, and the
	 * button.
	 *
	 * @param string      $action  confirm or remove.
	 * @param int         $id      Alert id.
	 * @param string      $key     Key from the link.
	 * @param \WC_Product $product The alert's product.
	 */
	private static function link_page( string $action, int $id, string $key, \WC_Product $product ): void {
		$name   = wp_strip_all_tags( $product->get_name() );
		$target = add_query_arg(
			array(
				self::LINK          => $action,
				self::LINK . '_id'  => $id,
				self::LINK . '_key' => $key,
			),
			home_url( '/' )
		);
		if ( 'remove' === $action ) {
			$title = __( 'Remove your email address', 'pergel-engine' );
			/* translators: %s: product name. */
			$text   = sprintf( __( 'You will no longer get an email when %s is back in stock.', 'pergel-engine' ), $name );
			$button = __( 'Remove my address', 'pergel-engine' );
		} else {
			$title = __( 'Confirm your back-in-stock alert', 'pergel-engine' );
			/* translators: %s: product name. */
			$text   = sprintf( __( 'We will email you once when %s is back in stock.', 'pergel-engine' ), $name );
			$button = __( 'Confirm the alert', 'pergel-engine' );
		}
		wp_die(
			'<h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $text ) . '</p><form method="post" action="' . esc_url( $target ) . '"><p><button type="submit" class="button">' . esc_html( $button ) . '</button></p></form>',
			esc_html( $title ),
			array( 'response' => 200 )
		);
	}

	/**
	 * Looks a link up without using it.
	 *
	 * @param string $action confirm or remove.
	 * @param int    $id     Alert id.
	 * @param string $key    Key from the link.
	 * @return array{code: string, product: \WC_Product|null} The product is null for a link that is wrong or used up.
	 */
	public static function check_link( string $action, int $id, string $key ): array {
		$row     = StockAlertStore::installed() && $id > 0 ? StockAlertStore::get( $id ) : null;
		$product = null === $row ? null : wc_get_product( $row['product_id'] );
		$good    = null !== $row && hash_equals( self::key( $action, $row ), $key ) && $product instanceof \WC_Product;
		return array(
			'code'    => $good ? 'ok' : 'link',
			'product' => $good ? $product : null,
		);
	}

	/**
	 * Confirms or removes an alert from a link.
	 *
	 * @param string $action confirm or remove.
	 * @param int    $id     Alert id.
	 * @param string $key    Key from the link.
	 * @return array{code: string, product: \WC_Product|null} The product is null for a link that is wrong or used up.
	 */
	public static function use_link( string $action, int $id, string $key ): array {
		$row     = StockAlertStore::installed() && $id > 0 ? StockAlertStore::get( $id ) : null;
		$product = null === $row ? null : wc_get_product( $row['product_id'] );
		if ( null === $row || ! hash_equals( self::key( $action, $row ), $key ) || ! $product instanceof \WC_Product ) {
			return array(
				'code'    => 'link',
				'product' => null,
			);
		}
		if ( 'remove' === $action ) {
			StockAlertStore::delete( $row['id'] );
			return array(
				'code'    => 'removed',
				'product' => $product,
			);
		}
		if ( StockAlertStore::PENDING === $row['status'] ) {
			StockAlertStore::confirm( $row['id'] );
			// Back in stock while the email waited: send it now.
			if ( $product->is_in_stock() ) {
				self::schedule( $product->get_id() );
			}
		}
		return array(
			'code'    => 'confirmed',
			'product' => $product,
		);
	}

	/**
	 * Action for woocommerce_(product|variation)_set_stock_status: queues
	 * the emails when a product with waiting addresses is back in stock.
	 * An option back in stock brings its product back too; WooCommerce
	 * updates the product only at the end of the request, before the
	 * queued run starts.
	 *
	 * @param mixed $id     Product or variation id.
	 * @param mixed $status New stock status.
	 */
	public static function stock_status( mixed $id, mixed $status ): void {
		if ( 'instock' !== $status || ! is_numeric( $id ) || ! StockAlertStore::installed() ) {
			return;
		}
		$ids = array( (int) $id );
		if ( 'product_variation' === get_post_type( (int) $id ) ) {
			$ids[] = (int) wp_get_post_parent_id( (int) $id );
		}
		foreach ( $ids as $one ) {
			if ( $one > 0 && StockAlertStore::waiting( $one ) ) {
				self::schedule( $one );
			}
		}
	}

	/**
	 * Queues a run of the emails for a product, unless one is waiting.
	 *
	 * @param int  $id   Product or variation id.
	 * @param bool $next Whether this is the next batch, queued by the run itself.
	 */
	public static function schedule( int $id, bool $next = false ): void {
		$args = array( $id );
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			if ( $next || ! as_has_scheduled_action( self::SEND_HOOK, $args, 'pergel' ) ) {
				as_enqueue_async_action( self::SEND_HOOK, $args, 'pergel' );
			}
			return;
		}
		if ( ! wp_next_scheduled( self::SEND_HOOK, $args ) ) {
			wp_schedule_single_event( time(), self::SEND_HOOK, $args );
		}
	}

	/**
	 * Sends one batch. Hooked on SEND_HOOK.
	 *
	 * @param mixed $id Product or variation id.
	 */
	public static function run( mixed $id ): void {
		self::send( $id );
	}

	/**
	 * Emails the addresses waiting for a product that is back in stock,
	 * BATCH at a time; queues the next batch when more wait. Each address
	 * gets the email once, even when sending fails. run() calls it.
	 *
	 * @param mixed $id Product or variation id.
	 * @return int Addresses emailed.
	 */
	public static function send( mixed $id ): int {
		$product = is_numeric( $id ) ? wc_get_product( (int) $id ) : null;
		if ( true !== Settings::get( 'woo.stock_alert' ) || ! $product instanceof \WC_Product || ! $product->is_in_stock() || ! $product->is_purchasable() || ! self::for_sale( $product ) ) {
			return 0;
		}
		$rows = StockAlertStore::due( $product->get_id(), self::BATCH );
		$mail = self::email( self::EMAIL_BACK );
		foreach ( $rows as $row ) {
			StockAlertStore::mark_sent( $row['id'] );
			$sent = $mail instanceof BackInStockEmail && $mail->trigger( $row, $product, self::link( 'remove', $row ) );
			if ( ! $sent && function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->warning( sprintf( 'Back-in-stock email for product %d could not be sent (alert %d).', $product->get_id(), $row['id'] ), array( 'source' => 'pergel' ) );
			}
		}
		if ( count( $rows ) === self::BATCH ) {
			self::schedule( $product->get_id(), true );
		}
		StockAlertStore::purge();
		return count( $rows );
	}

	/**
	 * Filter for woocommerce_email_classes: the two emails.
	 *
	 * @param mixed $emails Email objects by class key.
	 * @return mixed
	 */
	public static function emails( mixed $emails ): mixed {
		if ( is_array( $emails ) ) {
			$emails[ self::EMAIL_CONFIRM ] = new StockConfirmEmail();
			$emails[ self::EMAIL_BACK ]    = new BackInStockEmail();
		}
		return $emails;
	}

	/**
	 * One of the two emails, from WooCommerce's mailer.
	 *
	 * @param string $key EMAIL_CONFIRM or EMAIL_BACK.
	 */
	private static function email( string $key ): ?\WC_Email {
		$emails = WC()->mailer()->get_emails();
		$email  = $emails[ $key ] ?? null;
		return $email instanceof \WC_Email ? $email : null;
	}

	/**
	 * Text for a result code, of the form or of a link.
	 *
	 * @param string $code Result code.
	 */
	public static function message( string $code ): string {
		return match ( $code ) {
			'confirm'     => __( 'Almost done: we have sent you an email. Click the link in it to confirm your alert.', 'pergel-engine' ),
			'subscribed'  => __( 'Done. We will email you once when it is back in stock.', 'pergel-engine' ),
			'confirmed'   => __( 'Thank you, your alert is confirmed. We will email you once when the product is back in stock.', 'pergel-engine' ),
			'removed'     => __( 'Your email address has been removed. You will not get an email about this product.', 'pergel-engine' ),
			'link'        => __( 'This link has expired or has already been used.', 'pergel-engine' ),
			'invalid'     => Form::message( 'invalid' ),
			'wait'        => Form::message( 'wait' ),
			'limit'       => Form::message( 'limit' ),
			'unavailable' => __( 'This product can be ordered again, or no alert is offered for it. Reload the page.', 'pergel-engine' ),
			default       => Form::message( 'expired' ),
		};
	}

	/**
	 * A signed token for the form of a product's page.
	 *
	 * @param int      $product Product id.
	 * @param int|null $time    When the form was made; now by default.
	 */
	public static function sign( int $product, ?int $time = null ): string {
		$payload = $product . '.' . ( $time ?? time() );
		return $payload . '.' . self::signature( $payload );
	}

	/**
	 * The product and time in a token, or null when the site did not make
	 * it.
	 *
	 * @param mixed $token Untrusted token.
	 * @return array{product: int, time: int}|null
	 */
	public static function verify( mixed $token ): ?array {
		if ( ! is_string( $token ) || 1 !== preg_match( '/^([0-9]{1,20})\.([0-9]{1,12})\.([0-9a-f]{64})$/', $token, $parts ) || ! hash_equals( self::signature( $parts[1] . '.' . $parts[2] ), $parts[3] ) ) {
			return null;
		}
		return array(
			'product' => (int) $parts[1],
			'time'    => (int) $parts[2],
		);
	}

	/**
	 * HMAC of a token payload with a site secret.
	 *
	 * @param string $payload Product and time.
	 */
	private static function signature( string $payload ): string {
		return hash_hmac( 'sha256', 'pergel-stock|' . $payload, wp_salt( 'nonce' ) );
	}

	/**
	 * The key of a link. It changes when the alert is started over, so an
	 * old link stops working.
	 *
	 * @param string $action confirm or remove.
	 * @param array  $row    The alert.
	 * @phpstan-param Row $row
	 */
	private static function key( string $action, array $row ): string {
		return substr( hash_hmac( 'sha256', 'pergel-stock-link|' . $action . '|' . $row['id'] . '|' . $row['email'] . '|' . $row['created'], wp_salt( 'nonce' ) ), 0, 32 );
	}

	/**
	 * The result in the address after a link or the no-JavaScript form.
	 */
	private static function returned(): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks one of the fixed messages to show.
		$code = isset( $_GET[ self::RESULT ] ) && is_string( $_GET[ self::RESULT ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT ] ) ) : '';
		return in_array( $code, array( 'confirm', 'subscribed', 'confirmed', 'removed', 'invalid', 'wait', 'limit', 'expired', 'unavailable' ), true ) ? $code : null;
	}

	/**
	 * Markup of the hook context: a pattern's content, a template's or a
	 * post's.
	 *
	 * @param mixed $context Hook context.
	 */
	private static function content( mixed $context ): string {
		if ( is_array( $context ) ) {
			return (string) ( $context['content'] ?? '' );
		}
		if ( $context instanceof \WP_Block_Template ) {
			return (string) $context->content;
		}
		return $context instanceof \WP_Post ? $context->post_content : '';
	}
}
