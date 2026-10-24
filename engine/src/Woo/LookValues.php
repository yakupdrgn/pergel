<?php
/**
 * Look settings as CSS values.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

/**
 * Reads the length, colour and weight settings of the shop group as CSS
 * values; '' while a setting is not set. CardLook and ShopLook use it.
 */
trait LookValues {

	/**
	 * A length setting as CSS, '' when not set.
	 *
	 * @param string $key Setting key in the shop group.
	 */
	private static function px( string $key ): string {
		$value = Settings::get( "shop.$key" );
		return is_int( $value ) ? $value . 'px' : '';
	}

	/**
	 * A colour setting as CSS: a palette colour as its preset variable,
	 * #rrggbb as it is, '' when not set.
	 *
	 * @param string $key Setting key in the shop group.
	 */
	private static function color( string $key ): string {
		$value = Settings::get( "shop.$key" );
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}
		if ( preg_match( '/^#[0-9a-f]{6}$/', $value ) ) {
			return $value;
		}
		return preg_match( '/^[a-z0-9][a-z0-9-]*$/', $value ) ? "var(--wp--preset--color--$value)" : '';
	}

	/**
	 * A font weight setting, '' when not set.
	 *
	 * @param string $key Setting key in the shop group.
	 */
	private static function weight( string $key ): string {
		$value = Settings::get( "shop.$key" );
		return is_string( $value ) && ctype_digit( $value ) ? $value : '';
	}
}
