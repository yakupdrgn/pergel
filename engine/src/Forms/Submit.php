<?php
/**
 * Form submissions.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Forms;

use Pergel\Settings\Labels;
use Pergel\Settings\Schema;
use Pergel\Settings\Settings;

/**
 * Checks a message, keeps it and emails it. In order: a filled trap field
 * gets a quiet "sent" and nothing else; the token must be one the site
 * signed; a form sent within seconds of loading is turned back; each
 * connection may send a few messages in ten minutes; Turnstile is asked
 * when it has keys; then the fields are checked. A message that passes is
 * stored first and mailed second, so a lost email loses nothing. The
 * visitor's IP address is used for the limit only, as a hash, and is not
 * stored with the message.
 *
 * @phpstan-import-type Spec from Form
 * @phpstan-type Result array{code: string, errors: array<string, string>, spec: Spec|null, entry: int}
 */
final class Submit {

	public const NAMESPACE = 'pergel/v1';
	public const ROUTE     = 'form';
	public const ACTION    = Module::ACTION;

	/**
	 * Name of the trap field that people do not see.
	 */
	public const TRAP = 'website';

	/**
	 * Result codes; see Form::message().
	 */
	public const CODES = array( 'sent', 'invalid', 'wait', 'limit', 'challenge', 'expired' );

	/**
	 * Seconds a person needs at least to fill in the form.
	 */
	public const MIN_SECONDS = 3;

	/**
	 * Messages per connection in WINDOW seconds.
	 */
	public const LIMIT  = 5;
	public const WINDOW = 600;

	/**
	 * HTTP status per result code.
	 */
	private const STATUS = array(
		'sent'      => 200,
		'invalid'   => 422,
		'wait'      => 400,
		'limit'     => 429,
		'challenge' => 400,
		'expired'   => 400,
	);

	/**
	 * Registers the endpoint. Hooked on rest_api_init.
	 */
	public static function route(): void {
		register_rest_route(
			self::NAMESPACE,
			'/' . self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rest' ),
				// A public form: anyone may send it. The token and the limit guard it.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * REST callback.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public static function rest( \WP_REST_Request $request ): \WP_REST_Response {
		$input  = $request->get_body_params();
		$input  = array() === $input ? (array) $request->get_json_params() : $input;
		$result = self::handle( $input, self::ip() );
		// The block's own thank-you text, if any, is in the page; the view script prefers it.
		return new \WP_REST_Response(
			array(
				'ok'      => 'sent' === $result['code'],
				'code'    => $result['code'],
				'message' => Form::message( $result['code'] ),
				'errors'  => (object) $result['errors'],
			),
			self::STATUS[ $result['code'] ] ?? 400
		);
	}

	/**
	 * The form sent without JavaScript: handles it and goes back to the page
	 * with the result in the address. Hooked on admin_post(_nopriv).
	 */
	public static function fallback(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Public form; pages may be cached, so the signed token stands in for a nonce. handle() checks every field.
		$result = self::handle( wp_unslash( $_POST ), self::ip() );
		$back   = wp_get_referer();
		if ( false === $back && null !== $result['spec'] && $result['spec']['post'] > 0 ) {
			$back = get_permalink( $result['spec']['post'] );
		}
		$back = remove_query_arg( array( 'pergel_form', 'pergel_form_id' ), is_string( $back ) && '' !== $back ? $back : home_url( '/' ) );
		if ( null !== $result['spec'] ) {
			$back = add_query_arg(
				array(
					'pergel_form'    => $result['code'],
					'pergel_form_id' => $result['spec']['id'],
				),
				$back
			) . '#' . $result['spec']['id'];
		}
		wp_safe_redirect( $back, 303 );
		exit;
	}

	/**
	 * Checks, stores and mails one message.
	 *
	 * @param array<mixed> $input Untrusted input, unslashed.
	 * @param string       $ip    Visitor's address, for the limit.
	 * @return array<string, mixed>
	 * @phpstan-return Result
	 */
	public static function handle( array $input, string $ip ): array {
		$result = array(
			'code'   => 'expired',
			'errors' => array(),
			'spec'   => null,
			'entry'  => 0,
		);
		$trap   = $input[ self::TRAP ] ?? '';
		$spec   = Form::verify( $input['token'] ?? null );
		if ( null !== $spec ) {
			$result['spec'] = $spec;
		}
		if ( ! is_string( $trap ) || '' !== trim( $trap ) ) {
			$result['code'] = 'sent';
			return $result;
		}
		if ( null === $spec ) {
			return $result;
		}
		if ( time() - $spec['time'] < self::MIN_SECONDS ) {
			$result['code'] = 'wait';
			return $result;
		}
		if ( ! self::within_limit( $ip ) ) {
			$result['code'] = 'limit';
			return $result;
		}
		if ( ! self::challenge_passed( $input['cf-turnstile-response'] ?? null, $ip ) ) {
			$result['code'] = 'challenge';
			return $result;
		}

		$values = array();
		$labels = Form::labels( $spec['kind'] );
		foreach ( Form::fields( $spec['kind'], $spec['optional'] ) as $name => $field ) {
			$check = Schema::field(
				array(
					'type' => $field['type'],
					'max'  => $field['max'],
				),
				$input[ $name ] ?? ''
			);
			if ( is_string( $check ) ) {
				$result['errors'][ $name ] = Labels::problem( $check );
			} elseif ( $field['required'] && '' === $check[0] ) {
				/* translators: %s: field label. */
				$result['errors'][ $name ] = sprintf( __( 'Fill in %s.', 'pergel-engine' ), $labels[ $name ] );
			} elseif ( 'email' === $name && ! is_email( (string) $check[0] ) ) {
				$result['errors'][ $name ] = Labels::problem( 'invalid_email' );
			} else {
				$values[ $name ] = (string) $check[0];
			}
		}
		if ( $spec['consent'] && '1' !== ( $input['consent'] ?? null ) ) {
			$result['errors']['consent'] = __( 'Tick the box to agree, so we can answer you.', 'pergel-engine' );
		}
		if ( array() !== $result['errors'] ) {
			$result['code'] = 'invalid';
			return $result;
		}
		if ( $spec['consent'] ) {
			$values['consent'] = gmdate( 'c' );
		}

		$entry           = Inbox::store( $spec['kind'], $values, $spec['post'] );
		$result['code']  = 'sent';
		$result['entry'] = $entry;
		if ( $entry > 0 ) {
			update_post_meta( $entry, Inbox::META_MAILED, self::mail( $spec['kind'], $values, $spec['post'], $entry ) ? 1 : 0 );
		}
		return $result;
	}

	/**
	 * The visitor's address as the web server sees it. Proxies that rewrite
	 * it can be handled with the pergel/forms/ip filter.
	 */
	public static function ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Filters the visitor address used for the sending limit.
		 *
		 * @param string $ip REMOTE_ADDR.
		 */
		$filtered = apply_filters( 'pergel/forms/ip', $ip );
		return is_string( $filtered ) ? $filtered : $ip;
	}

	/**
	 * Where messages go.
	 */
	public static function recipient(): string {
		$to = Settings::get( 'forms.recipient' );
		return is_string( $to ) && '' !== $to ? $to : (string) get_option( 'admin_email' );
	}

	/**
	 * Counts this message against the connection's limit.
	 *
	 * @param string $ip Visitor's address.
	 */
	private static function within_limit( string $ip ): bool {
		$key   = 'pergel_form_' . substr( wp_hash( $ip, 'nonce' ), 0, 20 );
		$count = get_transient( $key );
		$count = is_numeric( $count ) ? (int) $count : 0;
		if ( $count >= self::LIMIT ) {
			return false;
		}
		set_transient( $key, $count + 1, self::WINDOW );
		return true;
	}

	/**
	 * Asks Cloudflare Turnstile, when it has keys.
	 *
	 * @param mixed  $response Widget response from the form.
	 * @param string $ip       Visitor's address.
	 */
	private static function challenge_passed( mixed $response, string $ip ): bool {
		$secret = Form::turnstile()['secret'];
		if ( '' === $secret ) {
			return true;
		}
		if ( ! is_string( $response ) || '' === $response || strlen( $response ) > 2048 ) {
			return false;
		}
		$answer = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $secret,
					'response' => $response,
					'remoteip' => $ip,
				),
			)
		);
		if ( is_wp_error( $answer ) ) {
			return false;
		}
		$data = json_decode( wp_remote_retrieve_body( $answer ), true );
		return is_array( $data ) && true === ( $data['success'] ?? null );
	}

	/**
	 * Emails a stored message to the site owner, with Reply-To set to the
	 * sender.
	 *
	 * @param string                $kind   contact or quote.
	 * @param array<string, string> $values Clean field values.
	 * @param int                   $post   Page the form was on.
	 * @param int                   $entry  Stored message.
	 * @return bool Whether wp_mail() accepted it.
	 */
	private static function mail( string $kind, array $values, int $post, int $entry ): bool {
		$site    = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$name    = $values['name'] ?? '';
		$subject = 'quote' === $kind
			/* translators: 1: site name, 2: sender's name. */
			? sprintf( __( '[%1$s] Quote request from %2$s', 'pergel-engine' ), $site, $name )
			/* translators: 1: site name, 2: sender's name. */
			: sprintf( __( '[%1$s] Message from %2$s', 'pergel-engine' ), $site, $name );
		$labels = Form::labels( $kind );
		$lines  = array();
		foreach ( $values as $field => $value ) {
			$lines[] = $labels[ $field ] . ":\n" . $value . "\n";
		}
		$page = $post > 0 ? get_permalink( $post ) : false;
		if ( is_string( $page ) ) {
			/* translators: %s: page address. */
			$lines[] = sprintf( __( 'Sent from: %s', 'pergel-engine' ), $page );
		}
		/* translators: %s: admin address of the stored message. */
		$lines[] = sprintf( __( 'Kept in the admin area: %s', 'pergel-engine' ), admin_url( 'post.php?post=' . $entry . '&action=edit' ) );

		$headers = array();
		$email   = $values['email'] ?? '';
		if ( is_email( $email ) ) {
			$display   = trim( (string) preg_replace( '/["<>,;:\\\\\r\n]+/', ' ', $name ) );
			$headers[] = 'Reply-To: ' . ( '' === $display ? $email : '"' . $display . '" <' . $email . '>' );
		}
		return wp_mail( self::recipient(), $subject, implode( "\n", $lines ), $headers );
	}
}
