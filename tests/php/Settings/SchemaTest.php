<?php
/**
 * Settings schema tests.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Settings;

use Pergel\Settings\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers defaults, merge semantics, every field type, the JSON schema and
 * the autoload size limit.
 */
final class SchemaTest extends TestCase {

	/**
	 * Autoload limit for the option (ARCHITECTURE.md, "Ayar modeli").
	 */
	private const MAX_BYTES = 20 * 1024;

	public function test_defaults_cover_every_field(): void {
		$defaults = Schema::defaults();
		foreach ( Schema::groups() as $group => $fields ) {
			$this->assertSame( array_keys( $fields ), array_keys( $defaults[ $group ] ) );
		}
		$this->assertTrue( $defaults['modules']['seo'] );
		$this->assertSame( '', $defaults['brand']['email'] );
		$this->assertSame( array(), $defaults['brand']['social'] );
		$this->assertSame( 0, $defaults['brand']['logo_inverse'] );
	}

	public function test_missing_fields_keep_base_and_unknown_keys_are_dropped(): void {
		$base                  = Schema::defaults();
		$base['brand']['name'] = 'Ariumlab';

		$result = Schema::sanitize(
			array(
				'brand'   => array(
					'phone' => '+90 212 000 00 00',
					'extra' => 'x',
				),
				'unknown' => array( 'a' => 1 ),
			),
			$base
		);

		$this->assertSame( array(), $result['problems'] );
		$this->assertSame( 'Ariumlab', $result['value']['brand']['name'] );
		$this->assertSame( '+90 212 000 00 00', $result['value']['brand']['phone'] );
		$this->assertArrayNotHasKey( 'extra', $result['value']['brand'] );
		$this->assertArrayNotHasKey( 'unknown', $result['value'] );
	}

	public function test_invalid_value_keeps_base_and_reports_problem(): void {
		$base                   = Schema::defaults();
		$base['brand']['email'] = 'info@example.com';

		$result = Schema::sanitize(
			array(
				'brand'   => array( 'email' => 'not-an-email' ),
				'modules' => 'yes',
			),
			$base
		);

		$this->assertSame( 'info@example.com', $result['value']['brand']['email'] );
		$this->assertSame(
			array(
				array(
					'field' => 'modules',
					'code'  => 'invalid_type',
				),
				array(
					'field' => 'brand.email',
					'code'  => 'invalid_email',
				),
			),
			$result['problems']
		);
	}

	public function test_non_array_input_returns_defaults(): void {
		$result = Schema::sanitize( 'garbage' );
		$this->assertSame( Schema::defaults(), $result['value'] );
		$this->assertSame( 'invalid_type', $result['problems'][0]['code'] );
	}

	public function test_normalize_replaces_invalid_stored_values_with_defaults(): void {
		$stored = array(
			'modules' => array( 'seo' => false ),
			'brand'   => array(
				'name'   => 'Ariumlab',
				'social' => 'https://example.com/a',
				'email'  => array( 'x' ),
			),
		);

		$value = Schema::normalize( $stored );

		$this->assertFalse( $value['modules']['seo'] );
		$this->assertTrue( $value['modules']['brand'] );
		$this->assertSame( 'Ariumlab', $value['brand']['name'] );
		$this->assertSame( array( 'https://example.com/a' ), $value['brand']['social'] );
		$this->assertSame( '', $value['brand']['email'] );
		$this->assertSame( Schema::defaults(), Schema::normalize( false ) );
	}

	/**
	 * Field type cases: definition, input, expected clean value or error code.
	 *
	 * @return array<string, array{0: array{type: string, max?: int, min?: int, options?: list<string>}, 1: mixed, 2: mixed}>
	 */
	public static function field_cases(): array {
		$text   = array( 'type' => 'text' );
		$url    = array( 'type' => 'url' );
		$urls   = array(
			'type' => 'urls',
			'max'  => 2,
		);
		$dates  = array(
			'type' => 'dates',
			'max'  => 2,
		);
		$length = array(
			'type' => 'length',
			'min'  => 0,
			'max'  => 40,
		);
		$color  = array( 'type' => 'color' );
		return array(
			'bool true'            => array( array( 'type' => 'bool' ), true, true ),
			'bool form "1"'        => array( array( 'type' => 'bool' ), '1', true ),
			'bool form "0"'        => array( array( 'type' => 'bool' ), '0', false ),
			'bool garbage'         => array( array( 'type' => 'bool' ), 'maybe', 'invalid_type' ),
			'text strips tags'     => array( $text, ' <b>Arium</b>  lab<script>x</script> ', 'Arium lab' ),
			'text joins lines'     => array( $text, "a\r\nb\tc", 'a b c' ),
			'text number'          => array( $text, 42, '42' ),
			'text array'           => array( $text, array( 'a' ), 'invalid_type' ),
			'text bad utf-8'       => array( $text, "a\xff", 'invalid_type' ),
			'text max is chars'    => array(
				array(
					'type' => 'text',
					'max'  => 3,
				),
				'ığü',
				'ığü',
			),
			'text too long'        => array(
				array(
					'type' => 'text',
					'max'  => 3,
				),
				'abcd',
				'too_long',
			),
			'text far too long'    => array(
				array(
					'type' => 'text',
					'max'  => 3,
				),
				str_repeat( '<b></b>', 10 ),
				'too_long',
			),
			'textarea keeps lines' => array( array( 'type' => 'textarea' ), "a  b\r\n\r\n\r\n\r\nc", "a b\n\nc" ),
			'email ok'             => array( array( 'type' => 'email' ), ' info@ariumlab.com ', 'info@ariumlab.com' ),
			'email empty'          => array( array( 'type' => 'email' ), '', '' ),
			'email bad'            => array( array( 'type' => 'email' ), 'info@', 'invalid_email' ),
			'url ok'               => array( $url, 'https://ariumlab.com/a?b=1', 'https://ariumlab.com/a?b=1' ),
			'url unicode host'     => array( $url, 'https://örnek.com.tr/', 'https://örnek.com.tr/' ),
			'url javascript'       => array( $url, 'javascript:alert(1)', 'invalid_url' ),
			'url relative'         => array( $url, '/about', 'invalid_url' ),
			'url quote'            => array( $url, 'https://a.com/"onmouseover', 'invalid_url' ),
			'tel ok'               => array( array( 'type' => 'tel' ), '+90 (212) 000-00.00', '+90 (212) 000-00.00' ),
			'tel letters'          => array( array( 'type' => 'tel' ), '0212 CALL', 'invalid_tel' ),
			'tel too few digits'   => array( array( 'type' => 'tel' ), '+ 1', 'invalid_tel' ),
			'country upper-cased'  => array( array( 'type' => 'country' ), 'tr', 'TR' ),
			'country bad'          => array( array( 'type' => 'country' ), 'TUR', 'invalid_country' ),
			'enum ok'              => array(
				array(
					'type'    => 'enum',
					'options' => array( 'a', 'b' ),
				),
				'b',
				'b',
			),
			'enum bad'             => array(
				array(
					'type'    => 'enum',
					'options' => array( 'a', 'b' ),
				),
				'c',
				'invalid_choice',
			),
			'int range'            => array(
				array(
					'type' => 'int',
					'min'  => 1,
					'max'  => 5,
				),
				'5',
				5,
			),
			'int out of range'     => array(
				array(
					'type' => 'int',
					'min'  => 1,
					'max'  => 5,
				),
				6,
				'out_of_range',
			),
			'int float string'     => array( array( 'type' => 'int' ), '1.5', 'invalid_type' ),
			'image id'             => array( array( 'type' => 'image' ), '12', 12 ),
			'image negative'       => array( array( 'type' => 'image' ), -1, 'out_of_range' ),
			'urls from lines'      => array( $urls, "https://a.com\r\n\r\nhttps://b.com\nhttps://a.com", array( 'https://a.com', 'https://b.com' ) ),
			'urls bad item'        => array( $urls, array( 'https://a.com', 'ftp://b.com' ), 'invalid_url' ),
			'urls too many'        => array( $urls, array( 'https://a.com', 'https://b.com', 'https://c.com' ), 'too_many' ),
			'urls not list'        => array( $urls, 5, 'invalid_type' ),
			'time ok'              => array( array( 'type' => 'time' ), ' 9.30 ', '09:30' ),
			'time empty'           => array( array( 'type' => 'time' ), '', '' ),
			'time out of range'    => array( array( 'type' => 'time' ), '24:00', 'invalid_time' ),
			'time words'           => array( array( 'type' => 'time' ), 'noon', 'invalid_time' ),
			'dates sorted ranges'  => array(
				array( 'type' => 'dates' ),
				"2026-10-29\r\n\r\n2026-03-22 - 2026-03-19\n2026-01-01..2026-01-01\n2026-10-29",
				array( '2026-01-01', '2026-03-19/2026-03-22', '2026-10-29' ),
			),
			'dates slash range'    => array( $dates, array( '2026-05-26/2026-05-30' ), array( '2026-05-26/2026-05-30' ) ),
			'dates impossible day' => array( $dates, '2026-02-30', 'invalid_date' ),
			'dates wrong format'   => array( $dates, '29.10.2026', 'invalid_date' ),
			'dates too many'       => array( $dates, array( '2026-01-01', '2026-01-02', '2026-01-03' ), 'too_many' ),
			'dates not list'       => array( $dates, 5, 'invalid_type' ),
			'length empty'         => array( $length, '', '' ),
			'length number'        => array( $length, '12', 12 ),
			'length out of range'  => array( $length, 41, 'out_of_range' ),
			'length words'         => array( $length, 'big', 'invalid_type' ),
			'color empty'          => array( $color, '', '' ),
			'color slug'           => array( $color, ' Accent ', 'accent' ),
			'color hex'            => array( $color, '#1E64E6', '#1e64e6' ),
			'color form custom'    => array(
				$color,
				array(
					'pick' => 'custom',
					'hex'  => '#00ff00',
				),
				'#00ff00',
			),
			'color form slug'      => array(
				$color,
				array(
					'pick' => 'line',
					'hex'  => '#000000',
				),
				'line',
			),
			'color short hex'      => array( $color, '#fff', 'invalid_color' ),
			'color css'            => array( $color, 'red;}body{', 'invalid_color' ),
			'color not text'       => array( $color, 5, 'invalid_type' ),
		);
	}

	/**
	 * @param array{type: string, max?: int, min?: int, options?: list<string>} $field    Definition.
	 * @param mixed                                                             $input    Untrusted input.
	 * @param mixed                                                             $expected Clean value, or an error code for strings in $errors.
	 */
	#[DataProvider( 'field_cases' )]
	public function test_field( array $field, mixed $input, mixed $expected ): void {
		$result = Schema::field( $field, $input );
		if ( is_string( $result ) ) {
			$this->assertSame( $expected, $result, 'error code' );
			return;
		}
		$this->assertSame( $expected, $result[0] );
	}

	public function test_json_schema_mirrors_groups(): void {
		$schema = Schema::json_schema();

		$this->assertSame( 'object', $schema['type'] );
		$this->assertFalse( $schema['additionalProperties'] );
		$this->assertSame( array_keys( Schema::groups() ), array_keys( $schema['properties'] ) );

		$brand = $schema['properties']['brand']['properties'];
		$this->assertSame( 'boolean', $schema['properties']['modules']['properties']['seo']['type'] );
		$this->assertSame( 120, $brand['name']['maxLength'] );
		$this->assertSame( array( '' ), $brand['email']['anyOf'][0]['enum'] );
		$this->assertSame( 'email', $brand['email']['anyOf'][1]['format'] );
		$this->assertSame( 10, $brand['social']['maxItems'] );
		$this->assertSame( '', $brand['email']['default'] );
	}

	/**
	 * Every field at its longest (text in four-byte characters, the byte-limited
	 * types in ASCII) must stay under the autoload limit. Later groups (seo, performance) are covered too.
	 */
	public function test_worst_case_fits_autoload_limit(): void {
		$long  = static fn ( int $chars ): string => str_repeat( "\u{1F600}", $chars );
		$value = array();
		foreach ( Schema::groups() as $group => $fields ) {
			foreach ( $fields as $key => $field ) {
				$max                     = $field['max'] ?? null;
				$value[ $group ][ $key ] = match ( $field['type'] ) {
					'bool'          => false,
					'int', 'image', 'pattern' => PHP_INT_MAX,
					'enum'          => array_reduce( $field['options'] ?? array(), static fn ( string $a, string $b ): string => strlen( $b ) > strlen( $a ) ? $b : $a, '' ),
					'urls'          => array_fill( 0, $max ?? 10, 'https://' . str_repeat( 'a', 492 ) ),
					'dates'         => array_fill( 0, $max ?? 10, '2026-01-01/2026-12-31' ),
					'time'          => '23:59',
					'length'        => $field['max'] ?? 999,
					'color'         => str_repeat( 'a', 40 ),
					'country'       => 'TR',
					'email', 'url', 'tel' => str_repeat(
						'a',
						$max ?? array(
							'email' => 254,
							'url'   => 500,
							'tel'   => 32,
						)[ $field['type'] ]
					),
					default         => $long( $max ?? ( 'textarea' === $field['type'] ? 1000 : 200 ) ),
				};
			}
		}

		$bytes = strlen( serialize( $value ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- measures what wp_options stores.
		$this->assertLessThanOrEqual( self::MAX_BYTES, $bytes, "Worst case is $bytes bytes." );
	}

	public function test_level_fields_list_only_fields_with_that_level(): void {
		$category = Schema::level_fields( 'category' );
		$this->assertSame( array( 'shop' ), array_keys( $category ) );
		$this->assertArrayHasKey( 'columns', $category['shop'] );
		$this->assertSame( array(), Schema::level_fields( 'nowhere' ) );
	}

	public function test_level_values_keep_only_valid_choices(): void {
		$values = Schema::level_values(
			array(
				'shop.columns'       => '4',
				'shop.columns_phone' => '',
				'shop.per_page'      => '24',
				'shop.width'         => 'huge',
				'woo.sales_mode'     => 'quote',
				'shop.unknown'       => '1',
			),
			'category'
		);
		$this->assertSame(
			array(
				'shop.columns'  => '4',
				'shop.per_page' => 24,
			),
			$values
		);
		$this->assertSame( array(), Schema::level_values( 'shop.columns=4', 'category' ) );
		$this->assertSame( array(), Schema::level_values( array( 'shop.columns' => '4' ), 'product' ) );
	}

	public function test_shop_defaults_leave_the_template_alone(): void {
		$shop = Schema::defaults()['shop'];
		$this->assertSame( 'template', $shop['columns'] );
		$this->assertSame( 'auto', $shop['columns_tablet'] );
		$this->assertSame( 'auto', $shop['columns_phone'] );
		$this->assertSame( 0, $shop['per_page'] );
		$this->assertSame( 'wide', $shop['width'] );
		foreach ( array_merge( \Pergel\Woo\Cards::LOOK, \Pergel\Woo\Layout::LOOK, \Pergel\Woo\Layout::PRODUCT_LOOK ) as $key ) {
			$this->assertContains( $shop[ $key ], array( '', 'template' ), "$key is not set by default." );
		}
	}
}
