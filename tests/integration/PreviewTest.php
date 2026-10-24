<?php
/**
 * Live preview of unsaved settings.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Settings\Preview;
use Pergel\Settings\Settings;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

/**
 * Sends values to the route as the settings screen does, then reads them
 * back as a preview page would. Nothing is saved.
 */
final class PreviewTest extends TestCase {

	/**
	 * An administrator.
	 */
	private int $admin = 0;

	protected function setUp(): void {
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		$this->admin = (int) $admins[0];
		wp_set_current_user( $this->admin );
		// The route is registered on rest_api_init; tests may run before it.
		rest_get_server();
	}

	protected function tearDown(): void {
		delete_transient( 'pergel_preview_' . $this->admin );
		remove_filter( 'pre_option_' . Settings::OPTION, array( Preview::class, 'option' ) );
		unset( $_GET[ Preview::ARG ] );
		$reset = new \ReflectionProperty( Preview::class, 'values' );
		$reset->setValue( null, null );
		Settings::flush();
		wp_set_current_user( 0 );
	}

	/**
	 * Posts values to the preview route.
	 *
	 * @param array<string, mixed> $settings Values by group.
	 */
	private static function send( array $settings ): \WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/pergel/v1/preview' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( array( 'settings' => $settings ) ) );
		return rest_do_request( $request );
	}

	public function test_values_are_cleaned_kept_and_read_by_the_preview_page_only(): void {
		$saved    = get_option( Settings::OPTION );
		$response = self::send(
			array(
				'shop' => array(
					'card_radius' => '18',
					'card_bg'     => array(
						'pick' => 'custom',
						'hex'  => '#123abc',
					),
					'card_gap'    => '999',
				),
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$urls = $response->get_data();
		$this->assertIsArray( $urls );
		$this->assertStringContainsString( Preview::ARG . '=', (string) $urls['shop'] );
		$this->assertStringContainsString( Preview::ARG . '=', (string) $urls['product'] );
		$this->assertSame( $saved, get_option( Settings::OPTION ), 'Nothing is saved.' );

		// A page opened with the link reads the values.
		parse_str( (string) wp_parse_url( (string) $urls['shop'], PHP_URL_QUERY ), $query );
		$_GET[ Preview::ARG ] = $query[ Preview::ARG ];
		Preview::register();
		Settings::flush();
		$this->assertSame( 18, Settings::get( 'shop.card_radius' ) );
		$this->assertSame( '#123abc', Settings::get( 'shop.card_bg' ) );
		$this->assertSame( '', Settings::get( 'shop.card_gap' ), 'Out of range: the saved value.' );
	}

	public function test_others_do_not_get_the_values(): void {
		self::send( array( 'shop' => array( 'card_radius' => '18' ) ) );
		Preview::register();

		// A wrong nonce.
		$_GET[ Preview::ARG ] = 'nope';
		Settings::flush();
		$this->assertNotSame( 18, Settings::get( 'shop.card_radius' ) );

		// The right nonce, without the capability.
		$reset = new \ReflectionProperty( Preview::class, 'values' );
		$reset->setValue( null, null );
		$_GET[ Preview::ARG ] = wp_create_nonce( 'pergel_preview' );
		wp_set_current_user( 0 );
		Settings::flush();
		$this->assertNotSame( 18, Settings::get( 'shop.card_radius' ) );

		$this->assertSame( 401, self::send( array( 'shop' => array() ) )->get_status(), 'Visitors may not send values.' );
	}
}
