<?php
/**
 * Settings against a real WordPress: option writes, autoload, REST, modules.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Core\Plugin;
use Pergel\Settings\Schema;
use Pergel\Settings\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Each test starts from the site's saved option and puts it back afterwards.
 */
final class SettingsTest extends TestCase {

	/**
	 * The option as it was before the test (false: not saved).
	 *
	 * @var mixed
	 */
	private mixed $saved;

	protected function setUp(): void {
		// An explicit default: without one, get_option() returns register_setting()'s default.
		$this->saved = get_option( Settings::OPTION, false );
		delete_option( Settings::OPTION );
	}

	protected function tearDown(): void {
		delete_option( Settings::OPTION );
		if ( false !== $this->saved ) {
			// Restored as stored, without running it through the schema again.
			remove_all_filters( 'sanitize_option_' . Settings::OPTION );
			add_option( Settings::OPTION, $this->saved, '', true );
			add_filter( 'sanitize_option_' . Settings::OPTION, array( \Pergel\Settings\Module::class, 'sanitize' ) );
		}
		wp_set_current_user( 0 );
		Settings::flush();
	}

	public function test_settings_module_always_loads(): void {
		$this->assertContains( 'settings', Plugin::loaded() );
	}

	public function test_defaults_without_option(): void {
		$this->assertSame( Schema::defaults(), Settings::all() );
		$this->assertTrue( Settings::get( 'modules.seo' ) );
		$this->assertSame( '', Settings::get( 'brand.email' ) );
		$this->assertNull( Settings::get( 'brand.missing' ) );
	}

	public function test_update_option_runs_through_schema(): void {
		$problems = array();
		$listener = static function ( array $found ) use ( &$problems ): void {
			$problems = $found;
		};
		add_action( 'pergel/settings/invalid', $listener );

		update_option(
			Settings::OPTION,
			array(
				'brand' => array(
					'name'  => '<b>Ariumlab</b>',
					'email' => 'bad',
					'x'     => 1,
				),
			)
		);
		remove_action( 'pergel/settings/invalid', $listener );

		$stored = get_option( Settings::OPTION );
		$this->assertSame( 'Ariumlab', $stored['brand']['name'] );
		$this->assertSame( '', $stored['brand']['email'] );
		$this->assertArrayNotHasKey( 'x', $stored['brand'] );
		$this->assertSame( Schema::defaults()['modules'], $stored['modules'] );
		$this->assertSame(
			array(
				array(
					'field' => 'brand.email',
					'code'  => 'invalid_email',
				),
			),
			$problems
		);
	}

	public function test_partial_update_keeps_other_fields_and_cache_follows(): void {
		Settings::update( array( 'brand' => array( 'name' => 'Ariumlab' ) ) );
		$this->assertSame( 'Ariumlab', Settings::get( 'brand.name' ) );

		// A direct write, not through Settings::update(), must also reach the cache.
		update_option( Settings::OPTION, array( 'brand' => array( 'phone' => '+90 212 000 00 00' ) ) );

		$this->assertSame( 'Ariumlab', Settings::get( 'brand.name' ) );
		$this->assertSame( '+90 212 000 00 00', Settings::get( 'brand.phone' ) );
	}

	public function test_option_is_autoloaded(): void {
		Settings::update( array( 'brand' => array( 'name' => 'Ariumlab' ) ) );
		wp_cache_delete( 'alloptions', 'options' );

		$this->assertArrayHasKey( Settings::OPTION, wp_load_alloptions() );
	}

	public function test_install_adds_defaults_once(): void {
		Settings::install();
		$this->assertSame( Schema::defaults(), get_option( Settings::OPTION, false ) );

		Settings::update( array( 'brand' => array( 'name' => 'Ariumlab' ) ) );
		Settings::install();
		$this->assertSame( 'Ariumlab', Settings::get( 'brand.name' ) );
	}

	public function test_module_switch(): void {
		$this->assertTrue( Settings::module_enabled( 'seo' ) );
		$this->assertTrue( Settings::module_enabled( 'settings' ) );
		$this->assertTrue( Settings::module_enabled( 'some-project-module' ) );

		Settings::update( array( 'modules' => array( 'seo' => false ) ) );

		$this->assertFalse( Settings::module_enabled( 'seo' ) );
		$this->assertTrue( Settings::module_enabled( 'brand' ) );
	}

	public function test_rest_round_trip(): void {
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		$this->assertNotEmpty( $admins, 'The site needs an administrator.' );
		wp_set_current_user( (int) $admins[0] );

		$request = new \WP_REST_Request( 'POST', '/wp/v2/settings' );
		$request->set_body_params(
			array(
				Settings::OPTION => array(
					'brand' => array(
						'name'   => 'Ariumlab',
						'social' => array( 'https://www.linkedin.com/company/ariumlab' ),
					),
				),
			)
		);
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Ariumlab', $response->get_data()[ Settings::OPTION ]['brand']['name'] );
		$this->assertSame( 'Ariumlab', Settings::get( 'brand.name' ) );
		$this->assertTrue( Settings::get( 'modules.seo' ) );

		$bad = new \WP_REST_Request( 'POST', '/wp/v2/settings' );
		$bad->set_body_params( array( Settings::OPTION => array( 'brand' => array( 'email' => 'bad' ) ) ) );
		$this->assertSame( 400, rest_do_request( $bad )->get_status() );

		$unknown = new \WP_REST_Request( 'POST', '/wp/v2/settings' );
		$unknown->set_body_params( array( Settings::OPTION => array( 'brand' => array( 'nope' => 'x' ) ) ) );
		$this->assertSame( 400, rest_do_request( $unknown )->get_status() );
	}

	public function test_rest_hides_settings_from_non_admins(): void {
		wp_set_current_user( 0 );
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/settings' ) );
		$this->assertSame( 401, $response->get_status() );
	}
}
