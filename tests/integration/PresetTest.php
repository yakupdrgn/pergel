<?php
/**
 * The "Unimart" shop preset.
 *
 * Needs WooCommerce; skipped without it. Run against a shop site
 * (PERGEL_WP_DIR=…/pergel-shop).
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Settings\Schema;
use Pergel\Settings\Settings;
use Pergel\Woo\Module as Woo;
use Pergel\Woo\Preset;
use PHPUnit\Framework\TestCase;

/**
 * The settings and the kept values are put back after each test.
 */
final class PresetTest extends TestCase {

	/**
	 * The option as it was before the test (false: not saved).
	 *
	 * @var mixed
	 */
	private mixed $saved = false;

	/**
	 * The kept values as they were before the test.
	 *
	 * @var mixed
	 */
	private mixed $undo = false;

	protected function setUp(): void {
		// Saved first: tearDown() runs after a skip too.
		$this->saved = get_option( Settings::OPTION, false );
		$this->undo  = get_option( Preset::UNDO, false );
		if ( ! Woo::active() ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
		delete_option( Preset::UNDO );
		Settings::update(
			array(
				'woo'  => Schema::defaults()['woo'],
				'shop' => Schema::defaults()['shop'],
			)
		);
	}

	protected function tearDown(): void {
		delete_option( Settings::OPTION );
		if ( false !== $this->saved ) {
			// Restored as stored, without running it through the schema again.
			remove_all_filters( 'sanitize_option_' . Settings::OPTION );
			add_option( Settings::OPTION, $this->saved, '', true );
			add_filter( 'sanitize_option_' . Settings::OPTION, array( \Pergel\Settings\Module::class, 'sanitize' ) );
		}
		Settings::flush();
		delete_option( Preset::UNDO );
		if ( false !== $this->undo ) {
			add_option( Preset::UNDO, $this->undo, '', false );
		}
	}

	public function test_every_value_is_valid_for_the_schema(): void {
		$values = Preset::values();
		$this->assertSame( array(), Schema::sanitize( $values, Settings::all() )['problems'] );
		foreach ( $values as $group => $fields ) {
			foreach ( array_keys( $fields ) as $key ) {
				$this->assertArrayHasKey( $key, Schema::defaults()[ $group ], "$group.$key" );
			}
		}
	}

	public function test_lists_only_with_their_pages(): void {
		$this->assertArrayNotHasKey( 'wishlist', Preset::values()['woo'] );

		Settings::update( array( 'woo' => array( 'wishlist_url' => home_url( '/wishlist/' ) ) ) );
		$this->assertTrue( Preset::values()['woo']['wishlist'] );
		$this->assertArrayNotHasKey( 'compare', Preset::values()['woo'] );
	}

	public function test_apply_then_undo(): void {
		Settings::update( array( 'shop' => array( 'columns' => '5' ) ) );
		$this->assertSame( array( '5', '3' ), Preset::changes()['shop']['columns'] );

		Preset::run();
		$this->assertSame( array(), Preset::changes(), 'Nothing left to change.' );
		$this->assertSame( '3', Settings::get( 'shop.columns' ) );
		$this->assertSame( 'carousel', Settings::get( 'shop.related_style' ) );
		$this->assertTrue( Settings::get( 'woo.bottom_bar' ) );
		$kept = get_option( Preset::UNDO );
		$this->assertIsArray( $kept );
		$this->assertSame( '5', $kept['values']['shop']['columns'] );

		// A setting changed by hand afterwards stays as it is.
		Settings::update( array( 'shop' => array( 'card_style' => 'plain' ) ) );
		Preset::revert();

		$this->assertSame( '5', Settings::get( 'shop.columns' ) );
		$this->assertSame( 'template', Settings::get( 'shop.related_style' ) );
		$this->assertFalse( Settings::get( 'woo.bottom_bar' ) );
		$this->assertSame( 'plain', Settings::get( 'shop.card_style' ) );
		$this->assertFalse( get_option( Preset::UNDO ) );
	}

	public function test_a_second_apply_keeps_the_first_old_values(): void {
		Settings::update( array( 'shop' => array( 'columns' => '5' ) ) );
		Preset::run();
		Settings::update( array( 'shop' => array( 'columns' => '6' ) ) );
		Preset::run();

		$kept = get_option( Preset::UNDO );
		$this->assertIsArray( $kept );
		$this->assertSame( '5', $kept['values']['shop']['columns'] );
	}

	public function test_preview_lists_the_changes(): void {
		$html = Preset::preview();
		$this->assertStringContainsString( '<table class="widefat striped">', $html );
		$this->assertStringContainsString( 'name="action" value="' . Preset::APPLY . '"', $html );
		$this->assertSame( array_sum( array_map( 'count', Preset::changes() ) ), substr_count( $html, '<tr><td>' ), 'One row per change.' );
		$this->assertStringNotContainsString( '<tr><td></td>', $html, 'Each change names the settings page that holds it.' );

		Preset::run();
		$this->assertStringNotContainsString( '<table', Preset::preview() );
		$this->assertStringContainsString( 'value="' . Preset::REVERT . '"', Preset::box() );
	}
}
