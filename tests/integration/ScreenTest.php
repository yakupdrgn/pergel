<?php
/**
 * The settings screen: generated from the schema, errors next to fields.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Settings\Labels;
use Pergel\Settings\Schema;
use Pergel\Settings\Screen;
use Pergel\Settings\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Renders the page as an administrator; nothing is saved.
 */
final class ScreenTest extends TestCase {

	public static function setUpBeforeClass(): void {
		require_once ABSPATH . 'wp-admin/includes/admin.php';
	}

	protected function setUp(): void {
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		wp_set_current_user( (int) $admins[0] );
		// Set by wp-admin/admin.php in a real request; get_admin_page_title() reads it.
		$GLOBALS['title'] = 'Pergel settings';
	}

	protected function tearDown(): void {
		unset( $_GET['tab'] );
		$GLOBALS['wp_settings_errors'] = array();
		wp_set_current_user( 0 );
	}

	/**
	 * Renders one tab.
	 *
	 * @param string $tab Tab id, '' for the default.
	 */
	private static function render( string $tab ): string {
		if ( '' !== $tab ) {
			$_GET['tab'] = $tab;
		}
		ob_start();
		Screen::render();
		return (string) ob_get_clean();
	}

	public function test_every_field_is_labelled_and_placed_once(): void {
		foreach ( Schema::groups() as $group => $fields ) {
			$this->assertArrayHasKey( $group, Labels::tabs(), "Tab title for $group" );
			$placed = array_merge( ...array_column( Screen::sections( $group ), 'fields' ) );
			$this->assertSame( array(), array_diff( array_keys( $fields ), $placed ), "Unplaced fields in $group" );
			$this->assertSame( count( $placed ), count( array_unique( $placed ) ), "Duplicate fields in $group" );
			foreach ( array_keys( $fields ) as $key ) {
				$this->assertNotEmpty( Labels::fields()[ $group ][ $key ]['label'] ?? '', "Label for $group.$key" );
			}
		}
	}

	public function test_each_tab_renders_its_fields(): void {
		foreach ( Schema::groups() as $group => $fields ) {
			$html = self::render( $group );
			$this->assertStringContainsString( "<input type='hidden' name='option_page' value='pergel' />", $html );
			$this->assertStringContainsString( 'name="_wpnonce"', $html );
			$this->assertMatchesRegularExpression( '/nav-tab-active" aria-current="page">' . preg_quote( Labels::tabs()[ $group ]['title'], '/' ) . '</', $html );
			foreach ( array_keys( $fields ) as $key ) {
				$this->assertStringContainsString( 'name="' . Settings::OPTION . "[$group][$key]\"", $html, "$group.$key" );
			}
			// Only the open tab's fields are posted; the others keep their saved values.
			foreach ( array_diff( array_keys( Schema::groups() ), array( $group ) ) as $other ) {
				$this->assertStringNotContainsString( Settings::OPTION . "[$other]", $html );
			}
		}
	}

	public function test_default_and_unknown_tab_open_the_first_group(): void {
		$first = (string) array_key_first( Schema::groups() );
		$this->assertStringContainsString( Settings::OPTION . "[$first]", self::render( '' ) );
		$this->assertStringContainsString( Settings::OPTION . "[$first]", self::render( 'nope' ) );
	}

	public function test_bool_sends_zero_when_unchecked(): void {
		$html = self::render( 'modules' );
		$this->assertStringContainsString( '<input type="hidden" name="pergel_settings[modules][seo]" value="0"><input type="checkbox" id="pergel-modules-seo"', $html );
	}

	public function test_errors_are_shown_next_to_the_field(): void {
		Screen::report(
			array(
				array(
					'field' => 'brand.email',
					'code'  => 'invalid_email',
				),
			)
		);
		$html = self::render( 'brand' );

		$this->assertStringContainsString( 'aria-describedby="pergel-brand-email-error" aria-invalid="true"', $html );
		$this->assertStringContainsString( '<p class="pergel-settings__error" id="pergel-brand-email-error">Email was not saved.', $html );
		$this->assertStringNotContainsString( 'pergel-brand-phone-error', $html );
	}

	public function test_hidden_from_non_admins(): void {
		wp_set_current_user( 0 );
		$this->assertSame( '', self::render( 'brand' ) );
	}
}
