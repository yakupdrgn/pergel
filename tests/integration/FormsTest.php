<?php
/**
 * The Form block and its messages.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Forms\Form;
use Pergel\Forms\Inbox;
use Pergel\Forms\Submit;
use Pergel\Settings\Settings;
use PHPUnit\Framework\TestCase;

/**
 * A message is checked against the signed form, kept as a hidden entry and
 * mailed with Reply-To set to the sender. No mail leaves the machine: the
 * pre_wp_mail filter catches it.
 */
final class FormsTest extends TestCase {

	/**
	 * Mails caught by pre_wp_mail.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $mails = array();

	/**
	 * What the caught mail returns.
	 */
	private bool $mail_result = true;

	/**
	 * Visitor address of the test, new for every test.
	 */
	private string $ip = '';

	/**
	 * Fake HTTP answer of the test, if any.
	 *
	 * @var \Closure|null
	 */
	private ?\Closure $http = null;

	protected function setUp(): void {
		$this->ip = '192.0.2.' . wp_rand( 1, 254 );
		add_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10, 2 );
		Form::flush();
	}

	protected function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'catch_mail' ) );
		remove_all_filters( 'pergel/forms/turnstile' );
		if ( null !== $this->http ) {
			remove_filter( 'pre_http_request', $this->http );
		}
		delete_transient( 'pergel_form_' . substr( wp_hash( $this->ip, 'nonce' ), 0, 20 ) );
		foreach ( get_posts(
			array(
				'post_type'      => Inbox::TYPE,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'meta_key'       => Inbox::META_EMAIL,
				'meta_value'     => 'ada@example.invalid',
			)
		) as $id ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			wp_delete_post( (int) $id, true );
		}
		Settings::update( array( 'forms' => array( 'recipient' => '' ) ) );
		wp_set_current_user( 0 );
		unset( $_GET['pergel_form'], $_GET['pergel_form_id'], $_SERVER['REMOTE_ADDR'] );
	}

	/**
	 * Short-circuits wp_mail().
	 *
	 * @param mixed                $result Null.
	 * @param array<string, mixed> $atts   Mail.
	 */
	public function catch_mail( mixed $result, array $atts ): bool {
		unset( $result );
		$this->mails[] = $atts;
		return $this->mail_result;
	}

	public function test_form_prints_the_chosen_fields_and_a_valid_token(): void {
		$html = do_blocks( '<!-- wp:pergel/form {"kind":"quote","company":true,"phone":false,"consent":true,"submitLabel":"Ask"} /-->' );

		$this->assertStringContainsString( 'id="pergel-form-1"', $html );
		$this->assertStringContainsString( 'name="company"', $html );
		$this->assertStringNotContainsString( 'name="phone"', $html );
		$this->assertStringContainsString( 'name="consent" value="1" required', $html );
		$this->assertStringContainsString( 'name="website" tabindex="-1"', $html );
		$this->assertStringContainsString( '>Ask</button>', $html );
		$this->assertStringContainsString( 'action="' . admin_url( 'admin-post.php' ) . '"', $html );
		$this->assertStringNotContainsString( 'cf-turnstile', $html );
		$this->assertSame( 1, preg_match( '/name="token" value="([^"]+)"/', $html, $token ) );
		$spec = Form::verify( $token[1] );
		$this->assertNotNull( $spec );
		$this->assertSame( 'quote', $spec['kind'] );
		$this->assertSame( array( 'company' ), $spec['optional'] );
		$this->assertTrue( $spec['consent'] );
		$this->assertSame( 'pergel-form-1', $spec['id'] );
		$this->assertStringContainsString( 'id="pergel-form-2"', do_blocks( '<!-- wp:pergel/form /-->' ) );
	}

	public function test_a_changed_token_is_refused(): void {
		$token                   = Form::sign( self::spec() );
		[ $payload, $signature ] = explode( '.', $token );
		$other                   = rtrim( strtr( base64_encode( (string) wp_json_encode( array( 'consent' => false ) + self::spec() ) ), '+/', '-_' ), '=' );

		$this->assertNotNull( Form::verify( $token ) );
		$this->assertNull( Form::verify( $other . '.' . $signature ) );
		$this->assertNull( Form::verify( $payload . '.' . str_repeat( '0', 64 ) ) );
		$this->assertNull( Form::verify( array( $token ) ) );
		$this->assertSame( 'expired', Submit::handle( array( 'token' => 'x.y' ) + self::fields(), $this->ip )['code'] );
	}

	public function test_a_filled_trap_looks_sent_but_keeps_nothing(): void {
		$result = Submit::handle(
			array(
				'token'   => Form::sign( self::spec() ),
				'website' => 'http://spam.example',
			) + self::fields(),
			$this->ip
		);

		$this->assertSame( 'sent', $result['code'] );
		$this->assertSame( 0, $result['entry'] );
		$this->assertSame( array(), $this->mails );
	}

	public function test_a_form_sent_at_once_is_turned_back(): void {
		$result = Submit::handle( array( 'token' => Form::sign( array( 'time' => time() ) + self::spec() ) ) + self::fields(), $this->ip );

		$this->assertSame( 'wait', $result['code'] );
	}

	public function test_fields_are_checked(): void {
		$result = Submit::handle(
			array(
				'token'   => Form::sign( self::spec() ),
				'name'    => '  ',
				'email'   => 'not-an-email',
				'phone'   => 'call me',
				'message' => str_repeat( 'a', 5001 ),
			),
			$this->ip
		);

		$this->assertSame( 'invalid', $result['code'] );
		$this->assertSame( array( 'name', 'email', 'phone', 'message', 'consent' ), array_keys( $result['errors'] ) );
		$this->assertSame( array(), $this->mails );
	}

	public function test_a_message_is_kept_and_mailed_to_the_recipient(): void {
		Settings::update( array( 'forms' => array( 'recipient' => 'owner@example.invalid' ) ) );
		$page   = (int) get_option( 'page_on_front' );
		$result = Submit::handle(
			array(
				'token'   => Form::sign( array( 'post' => $page ) + self::spec() ),
				'consent' => '1',
			) + self::fields(),
			$this->ip
		);

		$this->assertSame( 'sent', $result['code'] );
		$entry = get_post( $result['entry'] );
		$this->assertInstanceOf( \WP_Post::class, $entry );
		$this->assertSame( Inbox::TYPE, $entry->post_type );
		$this->assertSame( 'private', $entry->post_status );
		$this->assertSame( 'Ada Lovelace', $entry->post_title );
		$fields = get_post_meta( $entry->ID, Inbox::META_FIELDS, true );
		$this->assertIsArray( $fields );
		$this->assertSame( "Line one\nLine two", $fields['message'] );
		$this->assertSame( 'ada@example.invalid', get_post_meta( $entry->ID, Inbox::META_EMAIL, true ) );
		$this->assertSame( (string) $page, get_post_meta( $entry->ID, Inbox::META_PAGE, true ) );
		$this->assertSame( '1', get_post_meta( $entry->ID, Inbox::META_MAILED, true ) );
		$this->assertArrayHasKey( 'consent', $fields );
		$this->assertStringNotContainsString( $this->ip, (string) wp_json_encode( get_post_meta( $entry->ID ) ) );

		$this->assertCount( 1, $this->mails );
		$this->assertSame( 'owner@example.invalid', $this->mails[0]['to'] );
		$this->assertStringContainsString( 'Ada Lovelace', (string) $this->mails[0]['subject'] );
		$this->assertSame( array( 'Reply-To: "Ada Lovelace" <ada@example.invalid>' ), $this->mails[0]['headers'] );
		$this->assertStringContainsString( "Line one\nLine two", (string) $this->mails[0]['message'] );
		$this->assertStringContainsString( (string) get_permalink( $page ), (string) $this->mails[0]['message'] );
	}

	public function test_a_lost_mail_keeps_the_message(): void {
		$this->mail_result = false;
		$result            = Submit::handle( array( 'token' => Form::sign( self::spec( false ) ) ) + self::fields(), $this->ip );

		$this->assertSame( 'sent', $result['code'] );
		$this->assertSame( '0', get_post_meta( $result['entry'], Inbox::META_MAILED, true ) );
		$this->assertSame( get_option( 'admin_email' ), $this->mails[0]['to'] );
	}

	public function test_a_connection_may_send_a_few_messages(): void {
		$token = Form::sign( array( 'optional' => array() ) + self::spec( false ) );
		for ( $i = 0; $i < Submit::LIMIT; $i++ ) {
			$this->assertSame( 'invalid', Submit::handle( array( 'token' => $token ), $this->ip )['code'] );
		}

		$this->assertSame( 'limit', Submit::handle( array( 'token' => $token ) + self::fields(), $this->ip )['code'] );
	}

	public function test_turnstile_is_asked_when_it_has_keys(): void {
		add_filter(
			'pergel/forms/turnstile',
			static fn (): array => array(
				'site'   => 'site-key',
				'secret' => 'secret-key',
			)
		);
		$asked      = array();
		$this->http = static function ( $response, $args, $url ) use ( &$asked ) {
			$asked[] = array( $url, $args['body'] );
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( array( 'success' => 'good' === $args['body']['response'] ) ),
				'response' => array( 'code' => 200 ),
				'cookies'  => array(),
			);
		};
		add_filter( 'pre_http_request', $this->http, 10, 3 );
		$input = array( 'token' => Form::sign( self::spec( false ) ) ) + self::fields();

		$this->assertStringContainsString( 'cf-turnstile" data-sitekey="site-key"', do_blocks( '<!-- wp:pergel/form /-->' ) );
		$this->assertTrue( wp_script_is( 'pergel-turnstile' ) );
		$this->assertSame( 'challenge', Submit::handle( $input, $this->ip )['code'] );
		$this->assertSame( 'challenge', Submit::handle( array( 'cf-turnstile-response' => 'bad' ) + $input, $this->ip )['code'] );
		$this->assertSame( 'sent', Submit::handle( array( 'cf-turnstile-response' => 'good' ) + $input, $this->ip )['code'] );
		$this->assertCount( 2, $asked );
		$this->assertSame( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', $asked[0][0] );
		$this->assertSame( 'secret-key', $asked[0][1]['secret'] );
		wp_dequeue_script( 'pergel-turnstile' );
	}

	public function test_rest_endpoint_answers_with_field_errors(): void {
		$_SERVER['REMOTE_ADDR'] = $this->ip;
		$request                = new \WP_REST_Request( 'POST', '/' . Submit::NAMESPACE . '/' . Submit::ROUTE );
		$request->set_body_params(
			array(
				'token' => Form::sign( self::spec( false ) ),
				'email' => 'ada@example.invalid',
			)
		);
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 422, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertFalse( $data['ok'] );
		$this->assertSame( array( 'name', 'message' ), array_keys( (array) $data['errors'] ) );

		$request->set_body_params( array( 'token' => Form::sign( self::spec( false ) ) ) + self::fields() );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( Form::message( 'sent' ), $response->get_data()['message'] );
	}

	public function test_a_form_about_its_post_takes_fields_from_the_code_that_knows_the_post(): void {
		$page   = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Pergel form subject',
			)
		);
		$fields = static fn ( mixed $html, mixed $subject ): string => $html . '<p class="test-about">About ' . (int) $subject . '</p>';
		$values = static fn ( mixed $checked, mixed $subject, mixed $input ): array => array(
			'values' => array(
				'colour' => is_array( $input ) && is_string( $input['colour'] ?? null ) ? $input['colour'] : '',
				'email'  => 'replaced@example.invalid',
				'Bad'    => 'x',
			),
			'errors' => array(),
		);
		add_filter( 'pergel/forms/subject_fields', $fields, 10, 2 );
		add_filter( 'pergel/forms/subject_values', $values, 10, 3 );

		$block = new \WP_Block(
			array(
				'blockName'    => 'pergel/form',
				'attrs'        => array( 'aboutPost' => true ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			array( 'postId' => $page )
		);
		$html  = $block->render();
		$this->assertMatchesRegularExpression( '/<p class="test-about">About ' . $page . '<\/p><div class="wp-block-pergel-form__field is-message"/', $html );
		$this->assertStringContainsString( '"lead":"contact_form"', $html );
		$this->assertSame( 1, preg_match( '/name="token" value="([^"]+)"/', $html, $token ) );
		$spec = Form::verify( $token[1] );
		$this->assertNotNull( $spec );
		$this->assertSame( $page, $spec['subject'] );

		$result = Submit::handle(
			array(
				'token'  => Form::sign( array( 'time' => time() - 10 ) + $spec ),
				'colour' => 'Blue',
			) + self::fields(),
			$this->ip
		);
		remove_filter( 'pergel/forms/subject_fields', $fields );
		remove_filter( 'pergel/forms/subject_values', $values );
		wp_delete_post( $page, true );
		$this->assertSame( 'sent', $result['code'] );
		$stored = get_post_meta( $result['entry'], Inbox::META_FIELDS, true );
		$this->assertIsArray( $stored );
		$this->assertSame( array( 'name', 'email', 'phone', 'colour', 'message' ), array_keys( $stored ) );
		$this->assertSame( 'ada@example.invalid', $stored['email'] );
		$this->assertSame( 'Blue', $stored['colour'] );

		// A form that is not about its post asks no one.
		$this->assertStringNotContainsString( 'test-about', do_blocks( '<!-- wp:pergel/form /-->' ) );
	}

	public function test_the_page_shows_the_result_of_a_form_sent_without_javascript(): void {
		$_GET['pergel_form']    = 'sent';
		$_GET['pergel_form_id'] = 'pergel-form-2';

		$this->assertStringNotContainsString( Form::message( 'sent' ), do_blocks( '<!-- wp:pergel/form /-->' ) );
		$this->assertStringContainsString( '>Thanks!</p></form>', do_blocks( '<!-- wp:pergel/form {"successMessage":"Thanks!"} /-->' ) );
	}

	public function test_the_post_type_is_registered_before_a_message_is_kept(): void {
		unregister_post_type( Inbox::TYPE );
		$this->assertFalse( post_type_exists( Inbox::TYPE ) );

		$entry = Submit::handle( array( 'token' => Form::sign( self::spec( false ) ) ) + self::fields(), $this->ip )['entry'];

		$this->assertTrue( post_type_exists( Inbox::TYPE ) );
		$this->assertSame( Inbox::TYPE, get_post_type( $entry ) );
	}

	public function test_only_administrators_see_the_messages(): void {
		$entry  = Submit::handle( array( 'token' => Form::sign( self::spec( false ) ) ) + self::fields(), $this->ip )['entry'];
		$type   = get_post_type_object( Inbox::TYPE );
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);

		$this->assertNotNull( $type );
		$this->assertFalse( $type->public );
		$this->assertFalse( $type->publicly_queryable );
		$this->assertFalse( $type->show_in_rest );
		$this->assertFalse( current_user_can( 'read_post', $entry ) );
		wp_set_current_user( (int) $admins[0] );
		$this->assertTrue( current_user_can( 'read_post', $entry ) );
		$this->assertTrue( current_user_can( 'delete_post', $entry ) );
		$this->assertFalse( current_user_can( $type->cap->create_posts ) );
	}

	public function test_privacy_tools_export_and_erase_by_email(): void {
		Submit::handle( array( 'token' => Form::sign( self::spec( false ) ) ) + self::fields(), $this->ip );
		$export = Inbox::export( 'ADA@example.invalid' );

		$this->assertCount( 1, $export['data'] );
		$this->assertTrue( $export['done'] );
		$this->assertContains( 'Ada Lovelace', array_column( $export['data'][0]['data'], 'value' ) );
		$this->assertArrayHasKey( 'pergel-forms', apply_filters( 'wp_privacy_personal_data_exporters', array() ) );
		$this->assertTrue( Inbox::erase( 'ada@example.invalid' )['items_removed'] );
		$this->assertSame( array(), Inbox::export( 'ada@example.invalid' )['data'] );
	}

	/**
	 * A form spec made ten seconds ago.
	 *
	 * @param bool $consent Ask for consent.
	 * @return array{kind: string, optional: list<string>, consent: bool, time: int, post: int, id: string}
	 */
	private static function spec( bool $consent = true ): array {
		return array(
			'kind'     => 'contact',
			'optional' => array( 'phone' ),
			'consent'  => $consent,
			'time'     => time() - 10,
			'post'     => 0,
			'id'       => 'pergel-form-1',
		);
	}

	/**
	 * Valid field values.
	 *
	 * @return array<string, string>
	 */
	private static function fields(): array {
		return array(
			'name'    => 'Ada Lovelace',
			'email'   => 'ada@example.invalid',
			'phone'   => '+90 212 000 00 00',
			'message' => "Line one\r\nLine two",
		);
	}
}
