<?php
/**
 * The abilities against a real WordPress: what they return, who may call
 * them, the REST methods and the limit.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Abilities\Abilities;
use Pergel\Package\Importer;
use Pergel\Settings\Schema;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_REST_Request;

/**
 * The module is off by default, so the tests register the abilities the way
 * the module would and take them away afterwards.
 */
final class AbilitiesTest extends TestCase {

	/**
	 * Abilities the tests registered (none when the module is on).
	 *
	 * @var list<string>
	 */
	private static array $registered = array();

	/**
	 * A page the test made.
	 */
	private int $page = 0;

	public static function setUpBeforeClass(): void {
		if ( wp_has_ability( 'pergel/get-settings' ) ) {
			return;
		}
		$before = array_keys( wp_get_abilities() );
		self::during( 'wp_abilities_api_categories_init', array( Abilities::class, 'category' ) );
		self::during( 'wp_abilities_api_init', array( Abilities::class, 'register' ) );
		self::$registered = array_values( array_diff( array_keys( wp_get_abilities() ), $before ) );
	}

	public static function tearDownAfterClass(): void {
		self::during(
			'wp_abilities_api_init',
			static function (): void {
				foreach ( self::$registered as $name ) {
					wp_unregister_ability( $name );
				}
			}
		);
		if ( array() !== self::$registered ) {
			wp_unregister_ability_category( Abilities::CATEGORY );
		}
	}

	protected function setUp(): void {
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		wp_set_current_user( (int) ( $admins[0] ?? 0 ) );
		delete_transient( 'pergel_abilities_' . get_current_user_id() );
	}

	protected function tearDown(): void {
		delete_transient( 'pergel_abilities_' . get_current_user_id() );
		remove_all_filters( 'pergel/abilities/limit' );
		if ( 0 !== $this->page ) {
			wp_delete_post( $this->page, true );
		}
		wp_set_current_user( 0 );
	}

	/**
	 * Runs a callback as if inside an action, which the Abilities API
	 * requires for registering.
	 *
	 * @param string   $action   Action name.
	 * @param callable $callback What to run.
	 */
	private static function during( string $action, callable $callback ): void {
		global $wp_current_filter;
		$wp_current_filter[] = $action; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the API checks doing_action().
		try {
			$callback();
		} finally {
			array_pop( $wp_current_filter );
		}
	}

	/**
	 * Runs an ability.
	 *
	 * @param string $name  Ability name.
	 * @param mixed  $input Its input.
	 */
	private static function call( string $name, mixed $input = null ): mixed {
		$ability = wp_get_ability( $name );
		self::assertNotNull( $ability, "$name is registered" );
		return $ability->execute( $input );
	}

	public function test_the_module_is_off_by_default(): void {
		$this->assertFalse( Schema::defaults()['modules']['abilities'] );
	}

	public function test_every_ability_only_reads_and_is_offered_to_agents(): void {
		$names = array( 'pergel/list-patterns', 'pergel/get-pattern-fields', 'pergel/list-pages', 'pergel/get-design-tokens', 'pergel/get-settings' );
		$this->assertSame( $names, array_keys( wp_get_abilities( array( 'category' => Abilities::CATEGORY ) ) ) );
		foreach ( $names as $name ) {
			$meta = wp_get_ability( $name )->get_meta();
			$this->assertTrue( $meta['annotations']['readonly'], $name );
			$this->assertFalse( $meta['annotations']['destructive'], $name );
			$this->assertTrue( $meta['show_in_rest'], $name );
			$this->assertTrue( $meta['mcp']['public'], $name );
		}
	}

	public function test_the_patterns_and_their_fields(): void {
		$patterns = self::call( 'pergel/list-patterns' );
		$this->assertIsArray( $patterns );
		$hero = array_values( array_filter( $patterns, static fn( array $p ): bool => 'hero-split' === $p['name'] ) );
		$this->assertCount( 1, $hero );
		$this->assertStringContainsString( 'heading', $hero[0]['fields'] );

		$faq = self::call( 'pergel/list-patterns', array( 'category' => 'pergel-faq' ) );
		$this->assertNotEmpty( $faq );
		foreach ( $faq as $pattern ) {
			$this->assertContains( 'pergel-faq', $pattern['categories'] );
		}

		$fields = self::call( 'pergel/get-pattern-fields', array( 'name' => 'pergel/hero-split' ) );
		$this->assertIsArray( $fields );
		$this->assertSame( 'hero-split', $fields['name'] );
		$this->assertSame( 'text', $fields['fields']->heading['type'] );
		$this->assertSame( 'hero-split', $fields['section']->pattern );

		$unknown = self::call( 'pergel/get-pattern-fields', array( 'name' => 'yok-boyle-bir-sey' ) );
		$this->assertInstanceOf( WP_Error::class, $unknown );
		$this->assertSame( 'pergel_unknown_pattern', $unknown->get_error_code() );
	}

	public function test_the_pages_with_their_sections(): void {
		$this->page = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => 'Yetenek testi',
				'menu_order'   => -1000,
				'post_content' => "<!-- wp:group {\"metadata\":{\"patternName\":\"pergel/hero-split\"}} -->\n<div class=\"wp-block-group\"></div>\n<!-- /wp:group -->\n\n<!-- wp:paragraph -->\n<p>Metin</p>\n<!-- /wp:paragraph -->",
				'meta_input'   => array( Importer::META => 'yetenek-paketi' ),
			)
		);
		$result     = self::call( 'pergel/list-pages', array( 'per_page' => 1 ) );
		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['pages'] );
		$page = $result['pages'][0];
		$this->assertSame( $this->page, $page['id'] );
		$this->assertSame( 'draft', $page['status'] );
		$this->assertSame( 'yetenek-paketi', $page['package'] );
		$this->assertSame( array( 'pergel/hero-split', 'core/paragraph' ), $page['outline'] );
		$this->assertSame( $result['total'] > 1, $result['more'] );
	}

	public function test_a_page_writer_sees_only_their_own_pages(): void {
		$this->page = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_title'  => 'Başkasının taslağı',
				'post_author' => get_current_user_id(),
			)
		);
		$writer     = wp_insert_user(
			array(
				'user_login' => 'pergel-page-writer',
				'user_pass'  => wp_generate_password(),
				'role'       => 'contributor',
			)
		);
		$this->assertIsInt( $writer );
		( new \WP_User( $writer ) )->add_cap( 'edit_pages' );
		wp_set_current_user( $writer );
		delete_transient( 'pergel_abilities_' . $writer );
		$own = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_title'  => 'Kendi taslağım',
				'post_author' => $writer,
			)
		);

		$result = self::call( 'pergel/list-pages', array( 'per_page' => 100 ) );
		wp_delete_post( (int) $own, true );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $writer );

		$this->assertIsArray( $result );
		$this->assertSame( array( $own ), array_column( $result['pages'], 'id' ) );
		$this->assertSame( 1, $result['total'] );
	}

	public function test_the_tokens_and_the_settings(): void {
		$tokens = self::call( 'pergel/get-design-tokens' );
		$this->assertIsArray( $tokens );
		$this->assertSame( get_stylesheet(), $tokens['theme'] );
		$this->assertObjectHasProperty( 'color', $tokens['tokens'] );

		$settings = self::call( 'pergel/get-settings' );
		$this->assertIsArray( $settings );
		$this->assertSame( array_keys( Schema::defaults() ), array_keys( $settings ) );
	}

	public function test_a_user_without_the_capability_is_refused(): void {
		wp_set_current_user( 0 );
		foreach ( array( 'pergel/list-patterns', 'pergel/list-pages', 'pergel/get-settings' ) as $name ) {
			$result = self::call( $name );
			$this->assertInstanceOf( WP_Error::class, $result, $name );
			$this->assertSame( 'ability_invalid_permissions', $result->get_error_code(), $name );
		}
	}

	public function test_rest_serves_them_on_get_only(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/pergel/get-pattern-fields/run' );
		$request->set_query_params( array( 'input' => array( 'name' => 'faq-accordion' ) ) );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'faq-accordion', $response->get_data()['name'] );

		$response = rest_do_request( new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/pergel/get-settings/run' ) );
		$this->assertSame( 405, $response->get_status() );
	}

	public function test_calls_beyond_the_limit_are_refused(): void {
		add_filter( 'pergel/abilities/limit', static fn(): int => 2 );
		$this->assertIsArray( self::call( 'pergel/get-settings' ) );
		$this->assertIsArray( self::call( 'pergel/get-design-tokens' ) );
		$refused = self::call( 'pergel/get-settings' );
		$this->assertInstanceOf( WP_Error::class, $refused );
		$this->assertSame( 'pergel_ability_limit', $refused->get_error_code() );
		$this->assertSame( 429, $refused->get_error_data()['status'] );
	}
}
