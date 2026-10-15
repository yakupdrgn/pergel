<?php
/**
 * The Form block's markup and its signed settings.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Forms;

use Pergel\Core\Plugin;

/**
 * Prints the contact or quote form. The fields the editor chose travel in
 * a signed token, so the server checks the message against the form that
 * was shown, without trusting the browser and without a nonce (pages may
 * be cached). The form posts to admin-post.php, so it works without
 * JavaScript; the view script sends it to the REST endpoint instead and
 * shows the answer in place.
 *
 * A form can be about the post it is on (aboutPost): code that knows the
 * post adds fields with pergel/forms/subject_fields and checks them with
 * pergel/forms/subject_values, e.g. a product's options on a quote form.
 *
 * @phpstan-type Spec array{kind: string, optional: list<string>, consent: bool, time: int, post: int, subject: int, id: string}
 */
final class Form {

	public const KINDS = array( 'contact', 'quote' );

	/**
	 * Fields the editor can switch on.
	 */
	public const OPTIONAL = array( 'phone', 'company' );

	/**
	 * Interactivity store namespace.
	 */
	public const STORE = 'pergel/form';

	/**
	 * Forms printed in this request, for stable ids.
	 *
	 * @var int
	 */
	private static int $count = 0;

	/**
	 * Block markup.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param \WP_Block|null       $block      Block instance.
	 */
	public static function render( array $attributes, ?\WP_Block $block = null ): string {
		if ( ! in_array( Module::id(), Plugin::loaded(), true ) ) {
			return wp_is_serving_rest_request()
				? '<div ' . get_block_wrapper_attributes() . '><p>' . esc_html__( 'The Forms module is switched off under Pergel → Settings → Modules, so this form is not shown.', 'pergel-engine' ) . '</p></div>'
				: '';
		}
		$kind     = in_array( $attributes['kind'] ?? null, self::KINDS, true ) ? $attributes['kind'] : 'contact';
		$optional = array_values( array_filter( self::OPTIONAL, static fn ( string $field ): bool => true === ( $attributes[ $field ] ?? false ) ) );
		$consent  = true === ( $attributes['consent'] ?? false );
		$post_id  = $block instanceof \WP_Block && is_int( $block->context['postId'] ?? null ) ? $block->context['postId'] : (int) get_the_ID();
		$subject  = true === ( $attributes['aboutPost'] ?? false ) ? $post_id : 0;
		$id       = 'pergel-form-' . ( ++self::$count );
		$token    = self::sign(
			array(
				'kind'     => $kind,
				'optional' => $optional,
				'consent'  => $consent,
				'time'     => time(),
				'post'     => $post_id,
				'subject'  => $subject,
				'id'       => $id,
			)
		);

		// A visitor without JavaScript comes back here with the result in the address.
		$result  = self::returned( $id );
		$success = is_string( $attributes['successMessage'] ?? null ) ? trim( $attributes['successMessage'] ) : '';
		$message = null === $result ? '' : ( 'sent' === $result && '' !== $success ? $success : self::message( $result ) );

		wp_interactivity_state(
			self::STORE,
			array(
				'endpoint' => rest_url( Submit::NAMESPACE . '/' . Submit::ROUTE ),
				'offline'  => self::message( 'offline' ),
			)
		);
		self::enqueue_challenge();

		$labels = self::labels( $kind );
		$fields = '';
		foreach ( self::fields( $kind, $optional ) as $name => $field ) {
			if ( 'message' === $name && $subject > 0 ) {
				$fields .= self::subject_fields( $subject, $id );
			}
			$fields .= self::field( $id, $name, $field, $labels[ $name ] );
		}
		if ( $consent ) {
			$fields .= self::consent( $id );
		}

		$submit = is_string( $attributes['submitLabel'] ?? null ) && '' !== trim( $attributes['submitLabel'] )
			? trim( $attributes['submitLabel'] )
			: ( 'quote' === $kind ? __( 'Request a quote', 'pergel-engine' ) : __( 'Send message', 'pergel-engine' ) );

		$context = array(
			'sending' => false,
			'failed'  => null !== $result && 'sent' !== $result,
			'message' => $message,
			'success' => $success,
			'errors'  => new \stdClass(),
			// Lead source reported to Google Analytics after sending.
			'lead'    => $kind . '_form',
		);

		return '<form ' . get_block_wrapper_attributes(
			array(
				'id'     => $id,
				'class'  => 'is-kind-' . $kind,
				'action' => admin_url( 'admin-post.php' ),
				'method' => 'post',
			)
		) . ' data-wp-interactive="' . esc_attr( self::STORE ) . '" ' . wp_interactivity_data_wp_context( $context ) . ' data-wp-on--submit="actions.submit">'
			. '<input type="hidden" name="action" value="' . esc_attr( Submit::ACTION ) . '">'
			. '<input type="hidden" name="token" value="' . esc_attr( $token ) . '">'
			. '<p class="wp-block-pergel-form__note">' . esc_html__( 'Fields marked with * are required.', 'pergel-engine' ) . '</p>'
			. $fields
			. '<div class="wp-block-pergel-form__trap" aria-hidden="true"><label for="' . esc_attr( $id ) . '-website">' . esc_html__( 'Leave this field empty', 'pergel-engine' ) . '</label>'
			. '<input type="text" id="' . esc_attr( $id ) . '-website" name="' . esc_attr( Submit::TRAP ) . '" tabindex="-1" autocomplete="off"></div>'
			. self::challenge()
			. '<div class="wp-block-pergel-form__actions"><button type="submit" class="wp-block-pergel-form__submit wp-element-button" data-wp-bind--aria-disabled="context.sending">' . esc_html( $submit ) . '</button></div>'
			. '<p class="wp-block-pergel-form__status" role="status" data-wp-text="context.message" data-wp-class--is-error="context.failed">' . esc_html( $message ) . '</p>'
			. '</form>';
	}

	/**
	 * Fields shown for a kind of form, in order.
	 *
	 * @param string   $kind     contact or quote.
	 * @param string[] $optional Optional fields switched on.
	 * @return array<string, array{type: string, max: int, required: bool, autocomplete: string}>
	 */
	public static function fields( string $kind, array $optional ): array {
		$fields = array(
			'name'    => array(
				'type'         => 'text',
				'max'          => 100,
				'required'     => true,
				'autocomplete' => 'name',
			),
			'email'   => array(
				'type'         => 'email',
				'max'          => 254,
				'required'     => true,
				'autocomplete' => 'email',
			),
			'phone'   => array(
				'type'         => 'tel',
				'max'          => 32,
				'required'     => false,
				'autocomplete' => 'tel',
			),
			'company' => array(
				'type'         => 'text',
				'max'          => 120,
				'required'     => false,
				'autocomplete' => 'organization',
			),
			'message' => array(
				'type'         => 'textarea',
				'max'          => 5000,
				'required'     => true,
				'autocomplete' => 'off',
			),
		);
		foreach ( self::OPTIONAL as $name ) {
			if ( ! in_array( $name, $optional, true ) ) {
				unset( $fields[ $name ] );
			}
		}
		return $fields;
	}

	/**
	 * Field labels.
	 *
	 * @param string $kind contact or quote.
	 * @return array<string, string>
	 */
	public static function labels( string $kind ): array {
		return array(
			'name'      => __( 'Name', 'pergel-engine' ),
			'email'     => __( 'Email', 'pergel-engine' ),
			'phone'     => __( 'Phone', 'pergel-engine' ),
			'company'   => __( 'Company', 'pergel-engine' ),
			'message'   => 'quote' === $kind ? __( 'What do you need?', 'pergel-engine' ) : __( 'Message', 'pergel-engine' ),
			'consent'   => __( 'Consent', 'pergel-engine' ),
			// Fields about the post the form is on.
			'product'   => __( 'Product', 'pergel-engine' ),
			'variation' => __( 'Option', 'pergel-engine' ),
			'quantity'  => __( 'Quantity', 'pergel-engine' ),
		);
	}

	/**
	 * Text for a result code of Submit::handle(), and "offline" for a
	 * request that did not reach the site.
	 *
	 * @param string $code Result code.
	 */
	public static function message( string $code ): string {
		return match ( $code ) {
			'sent'      => __( 'Thank you. Your message has been sent; we will get back to you soon.', 'pergel-engine' ),
			'invalid'   => __( 'Please check the fields marked below.', 'pergel-engine' ),
			'wait'      => __( 'That was very quick. Please wait a moment and send the form again.', 'pergel-engine' ),
			'limit'     => __( 'Too many messages were sent from your connection. Please try again in a few minutes.', 'pergel-engine' ),
			'challenge' => __( 'The spam check did not pass. Please try again.', 'pergel-engine' ),
			'offline'   => __( 'The message could not be sent. Check your connection and try again.', 'pergel-engine' ),
			default     => __( 'This form has expired. Reload the page and send it again.', 'pergel-engine' ),
		};
	}

	/**
	 * A signed form spec.
	 *
	 * @param array<string, mixed> $spec Form spec.
	 * @phpstan-param Spec $spec
	 */
	public static function sign( array $spec ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL-safe token, not code.
		$payload = rtrim( strtr( base64_encode( (string) wp_json_encode( $spec ) ), '+/', '-_' ), '=' );
		return $payload . '.' . self::signature( $payload );
	}

	/**
	 * The spec in a token, or null when the token is not one this site made.
	 *
	 * @param mixed $token Untrusted token.
	 * @return array<string, mixed>|null
	 * @phpstan-return Spec|null
	 */
	public static function verify( mixed $token ): ?array {
		if ( ! is_string( $token ) || 1 !== preg_match( '/^([A-Za-z0-9_-]+)\.([0-9a-f]{64})$/', $token, $parts ) || ! hash_equals( self::signature( $parts[1] ), $parts[2] ) ) {
			return null;
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- URL-safe token, not code.
		$spec = json_decode( (string) base64_decode( strtr( $parts[1], '-_', '+/' ), true ), true );
		if ( is_array( $spec ) && ! isset( $spec['subject'] ) ) {
			$spec['subject'] = 0;
		}
		if ( ! is_array( $spec )
			|| ! in_array( $spec['kind'] ?? null, self::KINDS, true )
			|| ! is_array( $spec['optional'] ?? null )
			|| array() !== array_diff( $spec['optional'], self::OPTIONAL )
			|| ! is_bool( $spec['consent'] ?? null )
			|| ! is_int( $spec['time'] ?? null )
			|| ! is_int( $spec['post'] ?? null )
			|| ! is_int( $spec['subject'] ?? null )
			|| ! is_string( $spec['id'] ?? null )
			|| 1 !== preg_match( '/^pergel-form-[0-9]+$/', $spec['id'] )
		) {
			return null;
		}
		return array(
			'kind'     => $spec['kind'],
			'optional' => array_values( $spec['optional'] ),
			'consent'  => $spec['consent'],
			'time'     => $spec['time'],
			'post'     => $spec['post'],
			'subject'  => $spec['subject'],
			'id'       => $spec['id'],
		);
	}

	/**
	 * Cloudflare Turnstile keys, from PERGEL_TURNSTILE_SITE_KEY and
	 * PERGEL_TURNSTILE_SECRET_KEY in wp-config.php, so the secret stays out
	 * of the database and of exports. Turnstile is used only with both.
	 *
	 * @return array{site: string, secret: string}
	 */
	public static function turnstile(): array {
		$keys = array(
			'site'   => defined( 'PERGEL_TURNSTILE_SITE_KEY' ) && is_string( PERGEL_TURNSTILE_SITE_KEY ) ? PERGEL_TURNSTILE_SITE_KEY : '',
			'secret' => defined( 'PERGEL_TURNSTILE_SECRET_KEY' ) && is_string( PERGEL_TURNSTILE_SECRET_KEY ) ? PERGEL_TURNSTILE_SECRET_KEY : '',
		);

		/**
		 * Filters the Turnstile keys, e.g. to read them from the environment.
		 *
		 * @param array{site: string, secret: string} $keys Keys from wp-config.php.
		 */
		$filtered = apply_filters( 'pergel/forms/turnstile', $keys );
		if ( is_array( $filtered ) && is_string( $filtered['site'] ?? null ) && is_string( $filtered['secret'] ?? null ) ) {
			$keys = array(
				'site'   => $filtered['site'],
				'secret' => $filtered['secret'],
			);
		}
		return '' === $keys['site'] || '' === $keys['secret'] ? array(
			'site'   => '',
			'secret' => '',
		) : $keys;
	}

	/**
	 * Resets the id counter. For tests.
	 */
	public static function flush(): void {
		self::$count = 0;
	}

	/**
	 * HMAC of a token payload with a site secret.
	 *
	 * @param string $payload Encoded spec.
	 */
	private static function signature( string $payload ): string {
		return hash_hmac( 'sha256', 'pergel-form|' . $payload, wp_salt( 'nonce' ) );
	}

	/**
	 * The result code in the address after the no-JavaScript fallback, for
	 * this form.
	 *
	 * @param string $id Form id.
	 */
	private static function returned( string $id ): ?string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only picks one of the fixed messages to show.
		$form = isset( $_GET['pergel_form_id'] ) && is_string( $_GET['pergel_form_id'] ) ? sanitize_key( wp_unslash( $_GET['pergel_form_id'] ) ) : '';
		$code = isset( $_GET['pergel_form'] ) && is_string( $_GET['pergel_form'] ) ? sanitize_key( wp_unslash( $_GET['pergel_form'] ) ) : '';
		// phpcs:enable
		return $form === $id && in_array( $code, Submit::CODES, true ) ? $code : null;
	}

	/**
	 * One field with its label and error slot.
	 *
	 * @param string                                                              $id    Form id.
	 * @param string                                                              $name  Field name.
	 * @param array{type: string, max: int, required: bool, autocomplete: string} $field Field definition.
	 * @param string                                                              $label Label text.
	 */
	private static function field( string $id, string $name, array $field, string $label ): string {
		$input   = $id . '-' . $name;
		$attrs   = ' id="' . esc_attr( $input ) . '" name="' . esc_attr( $name ) . '"'
			. ' autocomplete="' . esc_attr( $field['autocomplete'] ) . '"'
			. ( 'textarea' === $field['type'] ? '' : ' maxlength="' . esc_attr( (string) $field['max'] ) . '"' )
			. ( $field['required'] ? ' required' : '' )
			. ' aria-describedby="' . esc_attr( $input ) . '-error" data-wp-bind--aria-invalid="state.invalid"';
		$control = 'textarea' === $field['type']
			? '<textarea rows="6"' . $attrs . '></textarea>'
			: '<input type="' . esc_attr( $field['type'] ) . '"' . $attrs . '>';
		return '<div class="wp-block-pergel-form__field is-' . esc_attr( $name ) . '" ' . wp_interactivity_data_wp_context( array( 'field' => $name ) ) . '>'
			. '<label for="' . esc_attr( $input ) . '">' . esc_html( $label ) . ( $field['required'] ? ' <span aria-hidden="true">*</span>' : '' ) . '</label>'
			. $control
			. self::error( $input )
			. '</div>';
	}

	/**
	 * The consent checkbox, with a link to the privacy policy when the site
	 * has one.
	 *
	 * @param string $id Form id.
	 */
	private static function consent( string $id ): string {
		$input  = $id . '-consent';
		$policy = get_privacy_policy_url();
		$text   = '' === $policy
			? esc_html__( 'I agree that my details are used to answer this message.', 'pergel-engine' )
			: sprintf(
				/* translators: %s: link to the privacy policy. */
				esc_html__( 'I agree that my details are used to answer this message, as described in the %s.', 'pergel-engine' ),
				'<a href="' . esc_url( $policy ) . '">' . esc_html__( 'privacy policy', 'pergel-engine' ) . '</a>'
			);
		return '<div class="wp-block-pergel-form__field is-consent" ' . wp_interactivity_data_wp_context( array( 'field' => 'consent' ) ) . '>'
			. '<input type="checkbox" id="' . esc_attr( $input ) . '" name="consent" value="1" required aria-describedby="' . esc_attr( $input ) . '-error" data-wp-bind--aria-invalid="state.invalid">'
			. '<label for="' . esc_attr( $input ) . '">' . $text . ' <span aria-hidden="true">*</span></label>'
			. self::error( $input )
			. '</div>';
	}

	/**
	 * Fields about the post the form is on, from the code that knows it.
	 *
	 * @param int    $subject Post id.
	 * @param string $id      Form id.
	 */
	private static function subject_fields( int $subject, string $id ): string {
		/**
		 * Filters the fields a form shows about the post it is on, before the
		 * message field. Use Form::error() for each field's error slot and
		 * check the values with pergel/forms/subject_values.
		 *
		 * @param string $html    Escaped HTML; '' by default.
		 * @param int    $subject Post the form is about.
		 * @param string $id      Form id, the prefix of field ids.
		 */
		$html = apply_filters( 'pergel/forms/subject_fields', '', $subject, $id );
		return is_string( $html ) ? $html : '';
	}

	/**
	 * The slot for a field's error message.
	 *
	 * @param string $input Input id.
	 */
	public static function error( string $input ): string {
		return '<p class="wp-block-pergel-form__error" id="' . esc_attr( $input ) . '-error" hidden data-wp-bind--hidden="!state.error" data-wp-text="state.error"></p>';
	}

	/**
	 * The Turnstile widget, when keys are set in wp-config.php.
	 */
	private static function challenge(): string {
		$key = self::turnstile()['site'];
		return '' === $key ? '' : '<div class="wp-block-pergel-form__challenge cf-turnstile" data-sitekey="' . esc_attr( $key ) . '"></div>';
	}

	/**
	 * Loads the Turnstile script, only with keys and only on pages with a
	 * form.
	 */
	private static function enqueue_challenge(): void {
		if ( '' !== self::turnstile()['site'] ) {
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Cloudflare serves the current version.
			wp_enqueue_script( 'pergel-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, array( 'strategy' => 'defer' ) );
		}
	}
}
