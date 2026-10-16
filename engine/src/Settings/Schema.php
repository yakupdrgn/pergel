<?php
/**
 * The pergel_settings schema: one definition for defaults, sanitizing and REST.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Settings;

/**
 * Describes every Pergel setting and turns untrusted input into a value that
 * matches it.
 *
 * Pure PHP (no WordPress calls) so the rules can be unit tested. Field
 * labels and error messages live in the settings screen; here a problem is
 * only a field path and an error code.
 *
 * @phpstan-type Field array{type: string, default?: mixed, max?: int, min?: int, options?: list<string>, svg?: bool}
 * @phpstan-type Problem array{field: string, code: string}
 */
final class Schema {

	/**
	 * Default maximum length per string type: characters for text and
	 * textarea, bytes for the rest (URLs, e-mail addresses and phone numbers
	 * are ASCII or travel percent-encoded, so bytes keep the option small).
	 */
	private const MAX = array(
		'text'     => 200,
		'textarea' => 1000,
		'email'    => 254,
		'url'      => 500,
		'tel'      => 32,
	);

	/**
	 * Default maximum number of items in a urls field.
	 */
	private const MAX_ITEMS = 10;

	/**
	 * Every group and field.
	 *
	 * Types: bool, text, textarea, email, url, tel, country (ISO 3166-1
	 * alpha-2), enum (options), int (min, max), image (attachment id, 0 =
	 * none), urls (list of URLs, max = item count).
	 *
	 * @return array<string, array<string, Field>>
	 */
	public static function groups(): array {
		return array(
			// Module switches. Modules that are not listed always load.
			'modules'     => array(
				'brand'       => array(
					'type'    => 'bool',
					'default' => true,
				),
				'seo'         => array(
					'type'    => 'bool',
					'default' => true,
				),
				'performance' => array(
					'type'    => 'bool',
					'default' => true,
				),
				'forms'       => array(
					'type'    => 'bool',
					'default' => true,
				),
				'search'      => array(
					'type'    => 'bool',
					'default' => true,
				),
				'woo'         => array(
					'type'    => 'bool',
					'default' => true,
				),
			),
			// Company details: block bindings and Organization schema.
			'brand'       => array(
				'name'         => array(
					'type' => 'text',
					'max'  => 120,
				),
				'legal_name'   => array( 'type' => 'text' ),
				'email'        => array( 'type' => 'email' ),
				'phone'        => array( 'type' => 'tel' ),
				'street'       => array( 'type' => 'text' ),
				'locality'     => array(
					'type' => 'text',
					'max'  => 100,
				),
				'region'       => array(
					'type' => 'text',
					'max'  => 100,
				),
				'postal_code'  => array(
					'type' => 'text',
					'max'  => 20,
				),
				'country'      => array( 'type' => 'country' ),
				'vat_id'       => array(
					'type' => 'text',
					'max'  => 40,
				),
				'logo_inverse' => array( 'type' => 'image' ),
				'social'       => array( 'type' => 'urls' ),
			),
			// Used only when no SEO plugin is active.
			'seo'         => array(
				// Social networks show no SVG.
				'og_image'      => array(
					'type' => 'image',
					'svg'  => false,
				),
				'twitter_site'  => array(
					'type' => 'text',
					'max'  => 16,
				),
				'sitemap_users' => array(
					'type'    => 'bool',
					'default' => false,
				),
			),
			// Front-end trimming, used while the performance module is on.
			'performance' => array(
				'disable_emoji'       => array(
					'type'    => 'bool',
					'default' => true,
				),
				'remove_oembed_links' => array(
					'type'    => 'bool',
					'default' => true,
				),
				'dequeue_dashicons'   => array(
					'type'    => 'bool',
					'default' => true,
				),
				'preload_fonts'       => array(
					'type'    => 'bool',
					'default' => true,
				),
			),
			// Contact and quote forms.
			'forms'       => array(
				'recipient' => array( 'type' => 'email' ),
			),
			// Own search index and live suggestions.
			'search'      => array(
				'live'       => array(
					'type'    => 'bool',
					'default' => true,
				),
				'live_limit' => array(
					'type'    => 'int',
					'min'     => 3,
					'max'     => 12,
					'default' => 6,
				),
			),
			// WooCommerce; the tab is shown only while it is active.
			'woo'         => array(
				// Most open first; see Pergel\Woo\Modes.
				'sales_mode'        => array(
					'type'    => 'enum',
					'options' => array( 'sale', 'login', 'quote', 'catalog' ),
					'default' => 'sale',
				),
				// Product enquiry block; the phone number is brand.phone.
				'whatsapp'          => array( 'type' => 'tel' ),
				'contact_url'       => array( 'type' => 'url' ),
				'mini_cart'         => array(
					'type'    => 'bool',
					'default' => true,
				),
				'shop_assets_only'  => array(
					'type'    => 'bool',
					'default' => true,
				),
				'no_cart_fragments' => array(
					'type'    => 'bool',
					'default' => true,
				),
				// Wishlist and compare; see Pergel\Woo\Lists.
				'wishlist'          => array(
					'type'    => 'bool',
					'default' => false,
				),
				'wishlist_url'      => array( 'type' => 'url' ),
				'compare'           => array(
					'type'    => 'bool',
					'default' => false,
				),
				'compare_url'       => array( 'type' => 'url' ),
				// Product page; see Pergel\Woo\Swatches and Pergel\Woo\StickyCart.
				'swatches'          => array(
					'type'    => 'bool',
					'default' => false,
				),
				'sticky_cart'       => array(
					'type'    => 'bool',
					'default' => false,
				),
			),
		);
	}

	/**
	 * Default value of a field.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @phpstan-param Field $field
	 */
	public static function field_default( array $field ): mixed {
		if ( array_key_exists( 'default', $field ) ) {
			return $field['default'];
		}
		return match ( $field['type'] ) {
			'bool'          => false,
			'int', 'image'  => 0,
			'urls'          => array(),
			default         => '',
		};
	}

	/**
	 * The complete default settings.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function defaults(): array {
		$defaults = array();
		foreach ( self::groups() as $group => $fields ) {
			foreach ( $fields as $key => $field ) {
				$defaults[ $group ][ $key ] = self::field_default( $field );
			}
		}
		return $defaults;
	}

	/**
	 * Checks input against the schema.
	 *
	 * Missing groups and fields keep their value from $base, so a caller can
	 * send only what changes. Unknown keys are dropped. An invalid value also
	 * keeps its $base value and adds a problem.
	 *
	 * @param mixed                               $input Untrusted input (form, REST, CLI, stored option).
	 * @param array<string, array<string, mixed>> $base  Current settings; defaults() when null.
	 * @return array{value: array<string, array<string, mixed>>, problems: list<Problem>}
	 */
	public static function sanitize( mixed $input, ?array $base = null ): array {
		$defaults = self::defaults();
		$value    = array();
		$problems = array();

		if ( ! is_array( $input ) ) {
			$problems[] = array(
				'field' => '',
				'code'  => 'invalid_type',
			);
			$input      = array();
		}

		foreach ( self::groups() as $group => $fields ) {
			$group_input = $input[ $group ] ?? array();
			if ( ! is_array( $group_input ) ) {
				$problems[]  = array(
					'field' => $group,
					'code'  => 'invalid_type',
				);
				$group_input = array();
			}
			foreach ( $fields as $key => $field ) {
				$current = $base[ $group ][ $key ] ?? $defaults[ $group ][ $key ];
				if ( ! array_key_exists( $key, $group_input ) ) {
					$value[ $group ][ $key ] = $current;
					continue;
				}
				$result = self::field( $field, $group_input[ $key ] );
				if ( is_string( $result ) ) {
					$problems[]              = array(
						'field' => "$group.$key",
						'code'  => $result,
					);
					$value[ $group ][ $key ] = $current;
				} else {
					$value[ $group ][ $key ] = $result[0];
				}
			}
		}

		return array(
			'value'    => $value,
			'problems' => $problems,
		);
	}

	/**
	 * Brings a stored option into shape: anything invalid becomes the default.
	 *
	 * @param mixed $stored Value read from the database.
	 * @return array<string, array<string, mixed>>
	 */
	public static function normalize( mixed $stored ): array {
		return self::sanitize( is_array( $stored ) ? $stored : array() )['value'];
	}

	/**
	 * Sanitizes one field value.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $raw   Untrusted value.
	 * @phpstan-param Field $field
	 * @return array{0: mixed}|string The clean value wrapped in an array, or an error code.
	 */
	public static function field( array $field, mixed $raw ): array|string {
		$type = $field['type'];
		$max  = $field['max'] ?? self::MAX[ $type ] ?? 0;

		switch ( $type ) {
			case 'bool':
				$bool = is_bool( $raw ) ? $raw : filter_var( $raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
				return null === $bool ? 'invalid_type' : array( $bool );

			case 'int':
			case 'image':
				$int = is_int( $raw ) ? $raw : filter_var( $raw, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE );
				if ( null === $int ) {
					return 'invalid_type';
				}
				$min = 'image' === $type ? 0 : ( $field['min'] ?? PHP_INT_MIN );
				$top = 'image' === $type ? PHP_INT_MAX : ( $field['max'] ?? PHP_INT_MAX );
				return $int < $min || $int > $top ? 'out_of_range' : array( $int );

			case 'enum':
				$options = $field['options'] ?? array();
				return is_string( $raw ) && in_array( $raw, $options, true ) ? array( $raw ) : 'invalid_choice';

			case 'urls':
				return self::urls( $raw, $field['max'] ?? self::MAX_ITEMS );
		}

		$text = self::text( $raw, 'textarea' === $type );
		if ( null === $text ) {
			return 'invalid_type';
		}
		$length = in_array( $type, array( 'text', 'textarea' ), true ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
		if ( $max > 0 && $length > $max ) {
			return 'too_long';
		}
		if ( '' === $text ) {
			return array( '' );
		}

		switch ( $type ) {
			case 'email':
				return false === filter_var( $text, FILTER_VALIDATE_EMAIL ) ? 'invalid_email' : array( $text );
			case 'url':
				return self::is_url( $text ) ? array( $text ) : 'invalid_url';
			case 'tel':
				$digits = preg_match_all( '/[0-9]/', $text );
				return preg_match( '/^\+?[0-9 ().\-]+$/', $text ) && $digits >= 3 ? array( $text ) : 'invalid_tel';
			case 'country':
				$code = strtoupper( $text );
				return preg_match( '/^[A-Z]{2}$/', $code ) ? array( $code ) : 'invalid_country';
		}
		return array( $text );
	}

	/**
	 * Plain text from untrusted input: no tags, no control characters,
	 * single spaces (newlines kept for multi-line fields), trimmed.
	 *
	 * @param mixed $raw       Untrusted value.
	 * @param bool  $multiline Keep line breaks.
	 * @return string|null Null when the input is not text or not valid UTF-8.
	 */
	private static function text( mixed $raw, bool $multiline ): ?string {
		if ( is_int( $raw ) || is_float( $raw ) ) {
			$raw = (string) $raw;
		}
		if ( ! is_string( $raw ) || ! mb_check_encoding( $raw, 'UTF-8' ) ) {
			return null;
		}
		// Like wp_strip_all_tags(), in pure PHP so it is unit tested without WordPress.
		$text = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', str_replace( "\r\n", "\n", $raw ) );
		$text = strip_tags( $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- see above.
		if ( $multiline ) {
			$lines = array_map(
				static fn ( string $line ): string => trim( (string) preg_replace( '/[\p{Cc}\s]+/u', ' ', $line ) ),
				explode( "\n", $text )
			);
			return trim( (string) preg_replace( "/\n{3,}/", "\n\n", implode( "\n", $lines ) ) );
		}
		return trim( (string) preg_replace( '/[\p{Cc}\s]+/u', ' ', $text ) );
	}

	/**
	 * Whether a string is an absolute http(s) URL that is safe to store.
	 *
	 * @param string $url Candidate URL, already trimmed.
	 */
	public static function is_url( string $url ): bool {
		if ( preg_match( '/[\s<>"\'`\\\\]/u', $url ) ) {
			return false;
		}
		$parts = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure PHP on purpose.
		return is_array( $parts )
			&& in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true )
			&& '' !== ( $parts['host'] ?? '' );
	}

	/**
	 * A list of URLs from an array or a newline-separated string. Empty
	 * lines and duplicates are dropped.
	 *
	 * @param mixed $raw       Untrusted value.
	 * @param int   $max_items Item limit.
	 * @return array{0: list<string>}|string
	 */
	private static function urls( mixed $raw, int $max_items ): array|string {
		if ( is_string( $raw ) ) {
			$raw = explode( "\n", str_replace( "\r\n", "\n", $raw ) );
		}
		if ( ! is_array( $raw ) ) {
			return 'invalid_type';
		}
		$urls = array();
		foreach ( $raw as $item ) {
			$url = self::field( array( 'type' => 'url' ), $item );
			if ( is_string( $url ) ) {
				return $url;
			}
			if ( '' !== $url[0] && ! in_array( $url[0], $urls, true ) ) {
				$urls[] = $url[0];
			}
		}
		return count( $urls ) > $max_items ? 'too_many' : array( $urls );
	}

	/**
	 * JSON Schema for register_setting( show_in_rest ). Empty strings are
	 * valid everywhere, because an empty field means "not set".
	 *
	 * @return array<string, mixed>
	 */
	public static function json_schema(): array {
		$properties = array();
		foreach ( self::groups() as $group => $fields ) {
			$group_properties = array();
			foreach ( $fields as $key => $field ) {
				$group_properties[ $key ] = self::field_json_schema( $field );
			}
			$properties[ $group ] = array(
				'type'                 => 'object',
				'properties'           => $group_properties,
				'additionalProperties' => false,
			);
		}
		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
		);
	}

	/**
	 * JSON Schema of one field.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @phpstan-param Field $field
	 * @return array<string, mixed>
	 */
	private static function field_json_schema( array $field ): array {
		$type   = $field['type'];
		$max    = $field['max'] ?? self::MAX[ $type ] ?? 0;
		$schema = match ( $type ) {
			'bool'    => array( 'type' => 'boolean' ),
			'int'     => array_filter(
				array(
					'type'    => 'integer',
					'minimum' => $field['min'] ?? null,
					'maximum' => $field['max'] ?? null,
				),
				static fn ( $v ): bool => null !== $v
			),
			'image'   => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
			'enum'    => array(
				'type' => 'string',
				'enum' => $field['options'] ?? array(),
			),
			'urls'    => array(
				'type'     => 'array',
				'items'    => array(
					'type'      => 'string',
					'format'    => 'uri',
					'maxLength' => self::MAX['url'],
				),
				'maxItems' => $field['max'] ?? self::MAX_ITEMS,
			),
			'email'   => self::empty_or(
				array(
					'type'      => 'string',
					'format'    => 'email',
					'maxLength' => $max,
				)
			),
			'url'     => self::empty_or(
				array(
					'type'      => 'string',
					'format'    => 'uri',
					'maxLength' => $max,
				)
			),
			'tel'     => array(
				'type'      => 'string',
				'pattern'   => '^(\+?[0-9 ().\-]*)$',
				'maxLength' => $max,
			),
			'country' => array(
				'type'    => 'string',
				'pattern' => '^([A-Za-z]{2})?$',
			),
			default   => array(
				'type'      => 'string',
				'maxLength' => $max,
			),
		};
		$schema['default'] = self::field_default( $field );
		return $schema;
	}

	/**
	 * Allows an empty string next to a formatted one.
	 *
	 * @param array<string, mixed> $schema Schema for a non-empty value.
	 * @return array<string, mixed>
	 */
	private static function empty_or( array $schema ): array {
		return array(
			'type'  => 'string',
			'anyOf' => array(
				array(
					'type' => 'string',
					'enum' => array( '' ),
				),
				$schema,
			),
		);
	}
}
