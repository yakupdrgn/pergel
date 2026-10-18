<?php
/**
 * Form submissions.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Forms;

use Pergel\Core\Visitor;
use Pergel\Settings\Labels;
use Pergel\Settings\Schema;
use Pergel\Settings\Settings;

/**
 * Checks a message, keeps it and emails it. In order: a filled trap field
 * gets a quiet "sent" and nothing else; the token must be one the site
 * signed; a form sent within seconds of loading is turned back, and so is
 * one a browser posts from another site; the fields are checked; each
 * connection may send a few messages in ten minutes; Turnstile is asked
 * when it has keys; the site takes at most HOURLY_CAP messages an hour.
 * Only a message with good fields counts against the limits, so a visitor
 * fixing a typo does not use them up. A message that passes is stored
 * first and mailed second, so a lost email loses nothing. The visitor's IP
 * address is used for the limit only, as a hash, and is not stored with
 * the message (Core\Visitor).
 *
 * The token has no age limit: pages are cached for days, and a form on a
 * cached page must still send. A copied token still has to pass the
 * limits, which count per connection and for the whole site. Code that knows the post a form is about checks
 * its own fields (pergel/forms/subject_values) and hears about each stored
 * message (pergel/forms/received), e.g. to pass a quote on.
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
	 * Messages and back-in-stock sign-ups the whole site takes in an hour,
	 * together; filtered with pergel/forms/hourly_cap.
	 */
	public const HOURLY_CAP = 50;

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
		if ( Visitor::foreign_origin() ) {
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
		if ( $spec['subject'] > 0 ) {
			$about            = self::subject_values( $spec['subject'], $input );
			$result['errors'] = $result['errors'] + $about['errors'];
			$at               = array_search( 'message', array_keys( $values ), true );
			$values           = false === $at
				? $values + $about['values']
				: array_slice( $values, 0, $at, true ) + $about['values'] + array_slice( $values, $at, null, true );
		}
		if ( $spec['consent'] && '1' !== ( $input['consent'] ?? null ) ) {
			$result['errors']['consent'] = __( 'Tick the box to agree, so we can answer you.', 'pergel-engine' );
		}
		if ( array() !== $result['errors'] ) {
			$result['code'] = 'invalid';
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
		if ( ! self::within_site_cap() ) {
			$result['code'] = 'limit';
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

			/**
			 * Fires after a message is stored and mailed.
			 *
			 * @param int                   $entry  Stored message.
			 * @param array                 $spec   The form: kind, post, subject and more.
			 * @param array<string, string> $values Clean field values.
			 * @param array                 $input  Untrusted input, unslashed.
			 */
			do_action( 'pergel/forms/received', $entry, $spec, $values, $input );
		}
		return $result;
	}

	/**
	 * Checks the fields a form shows about the post it is on.
	 *
	 * @param int          $subject Post the form is about.
	 * @param array<mixed> $input   Untrusted input, unslashed.
	 * @return array{values: array<string, string>, errors: array<string, string>}
	 */
	private static function subject_values( int $subject, array $input ): array {
		/**
		 * Filters the checked values of the fields added with
		 * pergel/forms/subject_fields. Return an array with 'values' (clean
		 * text by field name, stored and mailed with the message) and
		 * 'errors' (a message by field name; any error turns the form back).
		 *
		 * @param array|null   $checked Null when no code knows the post.
		 * @param int          $subject Post the form is about.
		 * @param array<mixed> $input   Untrusted input, unslashed.
		 */
		$checked = apply_filters( 'pergel/forms/subject_values', null, $subject, $input );
		$own     = array_merge( array_keys( Form::fields( 'contact', Form::OPTIONAL ) ), array( 'consent' ) );
		$clean   = array(
			'values' => array(),
			'errors' => array(),
		);
		foreach ( array( 'values', 'errors' ) as $part ) {
			$given = is_array( $checked ) && is_array( $checked[ $part ] ?? null ) ? $checked[ $part ] : array();
			foreach ( $given as $field => $value ) {
				// The form's own fields cannot be replaced.
				if ( is_string( $field ) && sanitize_key( $field ) === $field && ! in_array( $field, $own, true ) && is_string( $value ) ) {
					$clean[ $part ][ $field ] = $value;
				}
			}
		}
		return $clean;
	}

	/**
	 * The visitor's address (Core\Visitor::ip(): REMOTE_ADDR, or the header
	 * PERGEL_CLIENT_IP_HEADER names behind a proxy).
	 */
	public static function ip(): string {
		$ip = Visitor::ip();

		/**
		 * Filters the visitor address used for the sending limits.
		 *
		 * @param string $ip The address Pergel found.
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
	 * Counts this message against the connection's limit. Back-in-stock
	 * alerts (Woo\StockAlert) share it.
	 *
	 * @param string $ip Visitor's address.
	 */
	public static function within_limit( string $ip ): bool {
		return Visitor::hit( 'form:' . Visitor::bucket( $ip ), self::LIMIT, self::WINDOW );
	}

	/**
	 * Counts this message against what the whole site takes in an hour, so
	 * a flood from many addresses stops too. Back-in-stock alerts share it.
	 */
	public static function within_site_cap(): bool {
		/**
		 * Filters how many messages and back-in-stock sign-ups the site
		 * takes in an hour. 0 turns both off.
		 *
		 * @param int $cap Default 50.
		 */
		$cap = apply_filters( 'pergel/forms/hourly_cap', self::HOURLY_CAP );
		return Visitor::hit( 'form:site', is_numeric( $cap ) ? (int) $cap : self::HOURLY_CAP, HOUR_IN_SECONDS );
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
			$lines[] = ( $labels[ $field ] ?? $field ) . ":\n" . $value . "\n";
		}
		$page = $post > 0 ? get_permalink( $post ) : false;
		if ( is_string( $page ) ) {
			/* translators: %s: page address. */
			$lines[] = sprintf( __( 'Sent from: %s', 'pergel-engine' ), $page );
		}
		/* translators: %s: admin address of the stored message. */
		$lines[] = sprintf( __( 'Kept in the admin area: %s', 'pergel-engine' ), admin_url( 'post.php?post=' . $entry . '&action=edit' ) );

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$email   = $values['email'] ?? '';
		if ( is_email( $email ) ) {
			$display   = trim( (string) preg_replace( '/["<>,;:\\\\\r\n]+/', ' ', $name ) );
			$headers[] = 'Reply-To: ' . ( '' === $display ? $email : '"' . $display . '" <' . $email . '>' );
		}
		return wp_mail( self::recipient(), $subject, implode( "\n", $lines ), $headers );
	}
}
