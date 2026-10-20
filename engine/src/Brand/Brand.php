<?php
/**
 * Brand details with their WordPress fallbacks.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Brand;

use Pergel\Settings\Labels;
use Pergel\Settings\Schema;
use Pergel\Settings\Settings;

/**
 * Reads the brand group and fills the gaps: the site title for the name,
 * the home page for the URL, the Site Logo for the main logo.
 */
final class Brand {

	/**
	 * Keys that are not brand fields but are derived from them.
	 */
	private const DERIVED = array( 'url', 'address', 'email_url', 'phone_url', 'copyright' );

	/**
	 * Brand values with fallbacks applied.
	 *
	 * @return array<string, mixed>
	 */
	public static function data(): array {
		$brand = Settings::get( 'brand' );
		$brand = is_array( $brand ) ? $brand : array();

		if ( '' === ( $brand['name'] ?? '' ) ) {
			// blogname is stored HTML-escaped; schema and bindings escape on output.
			$brand['name'] = html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' );
		}
		$brand['url']  = home_url( '/' );
		$brand['logo'] = (int) get_theme_mod( 'custom_logo' );

		return $brand;
	}

	/**
	 * Value of one key. Null when it is empty, so a bound block keeps the
	 * text it was saved with.
	 *
	 * @param string               $key       Brand key: a brand field or a derived key.
	 * @param array<string, mixed> $args      Binding arguments ("network" for social).
	 * @param string               $attribute Block attribute being filled.
	 */
	public static function value( string $key, array $args = array(), string $attribute = 'content' ): string|int|null {
		$brand = self::data();

		switch ( $key ) {
			case 'email_url':
				$value = Format::mailto( (string) $brand['email'] );
				break;
			case 'phone_url':
				$value = Format::tel_uri( (string) $brand['phone'] );
				break;
			case 'address':
				$value = Format::address( $brand );
				break;
			case 'copyright':
				/* translators: 1: current year, 2: brand name. */
				$value = sprintf( __( '© %1$s %2$s', 'pergel-engine' ), wp_date( 'Y' ), (string) $brand['name'] );
				break;
			case 'social':
				$value = Format::social( (array) $brand['social'], is_string( $args['network'] ?? null ) ? $args['network'] : '' );
				break;
			case 'logo_inverse':
				$value = self::logo( (int) $brand['logo_inverse'], (string) $brand['name'], $attribute );
				break;
			default:
				$value = in_array( $key, self::text_keys(), true ) && is_string( $brand[ $key ] ?? null ) ? $brand[ $key ] : '';
		}

		return '' === $value || 0 === $value ? null : $value;
	}

	/**
	 * Schema.org Organization for the site, used by the SEO module.
	 *
	 * @return array<string, mixed>
	 */
	public static function organization(): array {
		$brand = self::data();
		return Format::organization( $brand, self::image( (int) $brand['logo'] ) );
	}

	/**
	 * Keys a block can bind to, with labels for the editor.
	 *
	 * @return array<string, string>
	 */
	public static function fields(): array {
		$labels = Labels::fields()['brand'];
		$fields = array();
		foreach ( self::text_keys() as $key ) {
			$fields[ $key ] = $labels[ $key ]['label'] ?? match ( $key ) {
				'url'       => __( 'Home page address', 'pergel-engine' ),
				'address'   => __( 'Address (one line)', 'pergel-engine' ),
				'email_url' => __( 'Email link', 'pergel-engine' ),
				'phone_url' => __( 'Phone link', 'pergel-engine' ),
				'copyright' => __( 'Copyright line', 'pergel-engine' ),
				default     => $key,
			};
		}
		$fields['logo_inverse'] = $labels['logo_inverse']['label'];
		return $fields;
	}

	/**
	 * What the block editor needs to show bound values: the field list for
	 * the bindings panel and the current values.
	 *
	 * @return array{fields: list<array{label: string, type: string, args: array<string, string>}>, values: array<string, string>, logo: array{id: int, url: string}, social: array<string, string>}
	 */
	public static function editor_data(): array {
		$items  = array();
		$values = array();
		foreach ( self::fields() as $key => $label ) {
			if ( 'logo_inverse' === $key ) {
				continue;
			}
			$items[]        = array(
				'label' => $label,
				'type'  => 'string',
				'args'  => array( 'key' => $key ),
			);
			$values[ $key ] = (string) self::value( $key );
		}

		$logo_label = self::fields()['logo_inverse'];
		foreach ( array( 'string', 'number' ) as $type ) {
			$items[] = array(
				'label' => $logo_label,
				'type'  => $type,
				'args'  => array( 'key' => 'logo_inverse' ),
			);
		}

		$social = array();
		foreach ( (array) Settings::get( 'brand.social' ) as $url ) {
			$network = Format::network( (string) $url );
			if ( '' === $network || isset( $social[ $network ] ) ) {
				continue;
			}
			$social[ $network ] = (string) $url;
			$items[]            = array(
				/* translators: %s: social network name, e.g. LinkedIn. */
				'label' => sprintf( __( '%s profile', 'pergel-engine' ), Format::NETWORKS[ $network ] ),
				'type'  => 'string',
				'args'  => array(
					'key'     => 'social',
					'network' => $network,
				),
			);
		}

		$logo_id = (int) Settings::get( 'brand.logo_inverse' );
		return array(
			'fields' => $items,
			'values' => $values,
			'logo'   => array(
				'id'  => $logo_id,
				'url' => (string) self::logo( $logo_id, '', 'url' ),
			),
			'social' => $social,
		);
	}

	/**
	 * Text keys: the brand fields that hold a string, plus derived keys.
	 *
	 * @return list<string>
	 */
	private static function text_keys(): array {
		$keys = array();
		foreach ( Schema::groups()['brand'] as $key => $field ) {
			if ( ! in_array( $field['type'], array( 'image', 'urls', 'bool' ), true ) ) {
				$keys[] = $key;
			}
		}
		return array_merge( $keys, self::DERIVED );
	}

	/**
	 * An image attribute from an attachment: the id, the URL, or the brand
	 * name as alt text.
	 *
	 * @param int    $id        Attachment id.
	 * @param string $name      Brand name.
	 * @param string $attribute Block attribute.
	 */
	private static function logo( int $id, string $name, string $attribute ): string|int {
		if ( $id <= 0 ) {
			return '';
		}
		return match ( $attribute ) {
			'id'           => $id,
			'alt', 'title' => $name,
			default        => (string) wp_get_attachment_image_url( $id, 'full' ),
		};
	}

	/**
	 * URL and size of an attachment.
	 *
	 * @param int $id Attachment id.
	 * @return array{url: string, width: int, height: int}|null
	 */
	private static function image( int $id ): ?array {
		$src = $id > 0 ? wp_get_attachment_image_src( $id, 'full' ) : false;
		if ( ! $src ) {
			return null;
		}
		return array(
			'url'    => $src[0],
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
		);
	}
}
