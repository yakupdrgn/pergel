<?php
/**
 * The settings screen: generated from the schema, errors next to fields.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Core\Svg;
use Pergel\Settings\Labels;
use Pergel\Settings\Schema;
use Pergel\Settings\Screen;
use Pergel\Settings\Settings;
use Pergel\Woo\Preset;
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
		unset( $_GET['tab'], $_GET['section'] );
		$GLOBALS['wp_settings_errors'] = array();
		wp_set_current_user( 0 );
	}

	/**
	 * Renders one tab.
	 *
	 * @param string $tab     Tab id, '' for the default.
	 * @param string $section Page of the Shop tab, '' for its first.
	 */
	private static function render( string $tab, string $section = '' ): string {
		if ( '' !== $tab ) {
			$_GET['tab'] = $tab;
		}
		if ( '' !== $section ) {
			$_GET['section'] = $section;
		}
		ob_start();
		Screen::render();
		return (string) ob_get_clean();
	}

	/**
	 * The groups and fields this site shows: the Shop tab needs WooCommerce.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function shown(): array {
		$groups = array();
		foreach ( Schema::groups() as $group => $fields ) {
			if ( Screen::shown( $group ) ) {
				$groups[ $group ] = array_filter( $fields, static fn ( string $key ): bool => Screen::shown( $group, $key ), ARRAY_FILTER_USE_KEY );
			}
		}
		return $groups;
	}

	public function test_the_menu_comes_right_after_dashboard(): void {
		// Another plugin moved its menu under Dashboard; Pergel goes back on top of it.
		$this->assertSame(
			array( 'index.php', 'pergel', 'googlesitekit-dashboard', 'separator1', 'edit.php' ),
			Screen::menu_order( array( 'index.php', 'googlesitekit-dashboard', 'separator1', 'edit.php', 'pergel' ) )
		);
		// Without the Pergel menu (no rights) the order stays.
		$this->assertSame( array( 'index.php', 'edit.php' ), Screen::menu_order( array( 'index.php', 'edit.php' ) ) );
	}

	public function test_every_field_is_labelled_and_placed_once(): void {
		foreach ( Schema::groups() as $group => $fields ) {
			$this->assertArrayHasKey( $group, Labels::tabs(), "Tab title for $group" );
			$placed = array_merge( array(), ...array_column( Screen::sections( $group ), 'fields' ) );
			$this->assertSame( array(), array_diff( array_keys( self::shown()[ $group ] ?? array() ), $placed ), "Unplaced fields in $group" );
			$this->assertSame( count( $placed ), count( array_unique( $placed ) ), "Duplicate fields in $group" );
			foreach ( array_keys( $fields ) as $key ) {
				$this->assertNotEmpty( Labels::fields()[ $group ][ $key ]['label'] ?? '', "Label for $group.$key" );
			}
		}
	}

	public function test_each_tab_renders_its_fields(): void {
		foreach ( self::shown() as $group => $fields ) {
			if ( in_array( $group, array( Screen::MENU, Screen::LAYOUT ), true ) ) {
				continue;
			}
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

	public function test_shop_tab_pages_hold_every_shop_field_once(): void {
		if ( ! Screen::shown( Screen::MENU ) ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
		$this->assertStringNotContainsString( 'tab=' . Screen::LAYOUT . '"', self::render( Screen::MENU ), 'No tab of its own.' );

		$seen = array();
		foreach ( Screen::pages() as $key => $page ) {
			$html = self::render( Screen::MENU, $key );
			$this->assertMatchesRegularExpression( '/nav-tab-active" aria-current="page">' . preg_quote( Labels::tabs()[ Screen::MENU ]['title'], '/' ) . '</', $html );
			$this->assertStringContainsString( 'aria-current="page">' . esc_html( $page['title'] ) . '</a>', $html, $key );
			$own = array();
			foreach ( $page['sections'] as $section ) {
				foreach ( $section['fields'] as $field ) {
					$own[] = $section['group'] . '.' . $field;
				}
			}
			preg_match_all( '/name="' . Settings::OPTION . '\[(\w+)\]\[(\w+)\](?:\[\w*\])?"/', $html, $posted, PREG_SET_ORDER );
			$names = array_values( array_unique( array_map( static fn ( array $m ): string => $m[1] . '.' . $m[2], $posted ) ) );
			sort( $names );
			sort( $own );
			$this->assertSame( $own, $names, "Page $key posts only its own fields." );
			$seen = array_merge( $seen, $own );
		}

		$shown = array();
		foreach ( array( Screen::MENU, Screen::LAYOUT ) as $group ) {
			foreach ( array_keys( self::shown()[ $group ] ?? array() ) as $field ) {
				$shown[] = "$group.$field";
			}
		}
		sort( $shown );
		$this->assertSame( count( $seen ), count( array_unique( $seen ) ), 'No field on two pages.' );
		sort( $seen );
		$this->assertSame( $shown, $seen, 'Every shop field on a page.' );
		$this->assertArrayNotHasKey( 'other', Screen::pages(), 'Labels::menu() places every section.' );
	}

	public function test_old_links_and_presets_page(): void {
		if ( ! Screen::shown( Screen::MENU ) ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
		$this->assertStringContainsString( 'aria-current="page">' . esc_html( Labels::menu()['listing']['title'] ) . '</a>', self::render( Screen::LAYOUT ) );
		$html = self::render( Screen::MENU, 'presets' );
		$this->assertStringContainsString( 'class="card pergel-preset"', $html );
		$this->assertStringNotContainsString( 'action="options.php"', $html );
		$this->assertStringContainsString( 'tab=' . Screen::MENU . '&#038;section=presets&#038;' . Preset::PREVIEW . '=', $html );
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

	public function test_length_and_colour_fields(): void {
		if ( ! Screen::shown( Screen::MENU ) ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
		$stored = static function ( mixed $option ): array {
			$option         = is_array( $option ) ? $option : array();
			$option['shop'] = array_merge(
				is_array( $option['shop'] ?? null ) ? $option['shop'] : array(),
				array(
					'card_radius'       => 12,
					'card_bg'           => '#123abc',
					'card_border_color' => 'gone-colour',
				)
			);
			return $option;
		};
		add_filter( 'option_' . Settings::OPTION, $stored );
		Settings::flush();
		try {
			$html = self::render( Screen::MENU, 'cards' );
		} finally {
			remove_filter( 'option_' . Settings::OPTION, $stored );
			Settings::flush();
		}

		$this->assertMatchesRegularExpression( '#<input type="number" id="pergel-shop-card_radius" name="pergel_settings\[shop\]\[card_radius\]"[^>]* value="12" min="0" max="40" placeholder="[^"]+">#', $html );
		$this->assertMatchesRegularExpression( '#name="pergel_settings\[shop\]\[card_padding\]"[^>]* value="" min="0" max="40"#', $html, 'Not set: empty.' );

		// A custom colour: picked, with its value in the colour input.
		$this->assertMatchesRegularExpression( '#<span class="pergel-color__swatch" aria-hidden="true" style="background:\#123abc"></span><select id="pergel-shop-card_bg" name="pergel_settings\[shop\]\[card_bg\]\[pick\]"#', $html );
		$this->assertMatchesRegularExpression( '#<option value="custom" selected=\'selected\'>[^<]+</option></select> <input type="color" class="pergel-color__hex" name="pergel_settings\[shop\]\[card_bg\]\[hex\]" value="\#123abc"#', $html );
		// A colour the theme no longer has stays chosen.
		$this->assertStringContainsString( '<option value="gone-colour" selected>gone-colour</option>', $html );
		foreach ( array_keys( Screen::palette() ) as $slug ) {
			$this->assertStringContainsString( '<option value="' . esc_attr( $slug ) . '" data-color="', $html );
		}
	}

	public function test_item_lists(): void {
		if ( ! Screen::shown( Screen::MENU ) ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
		$html = self::render( Screen::MENU, 'cards' );
		$this->assertMatchesRegularExpression( '#<fieldset class="pergel-items"[^>]*><legend class="screen-reader-text">[^<]+</legend><input type="hidden" name="pergel_settings\[shop\]\[card_items\]\[order\]" value="[a-z,]+">#', $html );
		$this->assertMatchesRegularExpression( '#name="pergel_settings\[shop\]\[card_items\]\[template\]" value="1" checked=\'checked\'#', $html, 'No list yet: as in the template.' );
		foreach ( array_keys( \Pergel\Woo\Items::CARD ) as $key ) {
			$this->assertStringContainsString( '<li data-key="' . $key . '"><label><input type="checkbox" name="pergel_settings[shop][card_items][show][]" value="' . $key . '"', $html );
		}
		$this->assertSame( 2 * count( \Pergel\Woo\Items::CARD ), substr_count( $html, 'data-move=' ), 'Up and down for each item.' );

		$html = self::render( Screen::MENU, 'product' );
		$this->assertStringContainsString( 'value="buying"', $html );
		$this->assertDoesNotMatchRegularExpression( '#value="buying"[^>]*disabled#', $html, 'The theme template has the buying form.' );
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

	public function test_share_image_takes_no_svg(): void {
		$svg = wp_insert_attachment(
			array(
				'post_title'     => 'pergel-logo.svg',
				'post_mime_type' => Svg::MIME,
				'post_status'    => 'inherit',
			),
			'pergel-test/pergel-logo.svg'
		);
		wp_update_attachment_metadata(
			$svg,
			array(
				'width'  => 240,
				'height' => 64,
				'file'   => 'pergel-test/pergel-logo.svg',
			)
		);
		$settings = Settings::all();
		// The stored value is replaced for this render only.
		$settings['seo']['og_image'] = $svg;
		$stored                      = static fn (): array => $settings;
		add_filter( 'pre_option_' . Settings::OPTION, $stored );
		Settings::flush();
		$html = self::render( 'seo' );
		remove_filter( 'pre_option_' . Settings::OPTION, $stored );
		Settings::flush();
		wp_delete_attachment( $svg, true );

		$this->assertStringContainsString( 'data-types="image/jpeg,image/png,image/gif,image/webp"', $html );
		$this->assertStringContainsString( '<p class="pergel-settings__error" id="pergel-seo-og_image-error">' . esc_html( Labels::problem( 'svg' ) ) . '</p>', $html );
		$this->assertStringNotContainsString( 'data-types', self::render( 'brand' ) );
	}

	public function test_svg_hint_only_while_svg_uploads_are_off(): void {
		// Safe SVG adds svgz too, so every extension of the type goes.
		$off = static fn ( array $mimes ): array => array_diff( $mimes, array( Svg::MIME ) );
		$on  = static fn ( array $mimes ): array => $mimes + array( 'svg' => Svg::MIME );

		add_filter( 'upload_mimes', $off, 99 );
		$html = self::render( 'brand' );
		remove_filter( 'upload_mimes', $off, 99 );
		$this->assertStringContainsString( 'aria-describedby="pergel-brand-logo_inverse-help pergel-brand-logo_inverse-svg"', $html );
		$this->assertStringContainsString( 'plugin-install.php?s=safe-svg', $html );
		$this->assertStringNotContainsString( 'pergel-seo-og_image-svg', self::render( 'seo' ) );

		add_filter( 'upload_mimes', $on );
		$html = self::render( 'brand' );
		remove_filter( 'upload_mimes', $on );
		$this->assertStringNotContainsString( 'pergel-brand-logo_inverse-svg', $html );
	}

	public function test_hidden_from_non_admins(): void {
		wp_set_current_user( 0 );
		$this->assertSame( '', self::render( 'brand' ) );
	}
}
