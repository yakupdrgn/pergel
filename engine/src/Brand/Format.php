<?php
/**
 * Brand values in the shapes blocks and search engines need.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Brand;

/**
 * Pure PHP: no WordPress calls, so every rule here is unit-tested.
 */
final class Format {

	/**
	 * Host labels that count as a network when the profile URL does not
	 * contain the network's own name.
	 */
	private const ALIASES = array(
		'x'        => array( 'twitter' ),
		'youtube'  => array( 'youtu' ),
		'whatsapp' => array( 'wa' ),
	);

	/**
	 * Networks recognised in profile URLs, with their display names.
	 */
	public const NETWORKS = array(
		'linkedin'  => 'LinkedIn',
		'instagram' => 'Instagram',
		'youtube'   => 'YouTube',
		'facebook'  => 'Facebook',
		'x'         => 'X',
		'tiktok'    => 'TikTok',
		'pinterest' => 'Pinterest',
		'github'    => 'GitHub',
		'threads'   => 'Threads',
		'whatsapp'  => 'WhatsApp',
		'behance'   => 'Behance',
		'dribbble'  => 'Dribbble',
	);

	/**
	 * A tel: link from a display number: "+90 (212) 000 00 00" gives
	 * "tel:+902120000000". Empty when there are no digits.
	 *
	 * @param string $phone Phone number as entered.
	 */
	public static function tel_uri( string $phone ): string {
		$digits = (string) preg_replace( '/\D+/', '', $phone );
		if ( '' === $digits ) {
			return '';
		}
		return 'tel:' . ( str_starts_with( ltrim( $phone ), '+' ) ? '+' : '' ) . $digits;
	}

	/**
	 * A mailto: link, empty for an empty address.
	 *
	 * @param string $email Email address, already validated by the schema.
	 */
	public static function mailto( string $email ): string {
		return '' === $email ? '' : 'mailto:' . $email;
	}

	/**
	 * One address line: "Street, 34000 Locality, Region". The country is
	 * left out; most sites show a domestic address. Bind "country" for it.
	 *
	 * @param array<string, mixed> $brand Brand group values.
	 */
	public static function address( array $brand ): string {
		$town  = trim( self::text( $brand, 'postal_code' ) . ' ' . self::text( $brand, 'locality' ) );
		$parts = array_filter(
			array( self::text( $brand, 'street' ), $town, self::text( $brand, 'region' ) ),
			static fn ( string $part ): bool => '' !== $part
		);
		return implode( ', ', $parts );
	}

	/**
	 * The network a profile URL belongs to, or '' when it is not a known one.
	 *
	 * @param string $url Profile URL.
	 */
	public static function network( string $url ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure PHP class; PHP 8.1+ parses consistently.
		$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
		if ( '' === $host ) {
			return '';
		}
		// The last label is the top-level domain ("com"); it never names a network.
		$labels = array_slice( explode( '.', $host ), 0, -1 );
		foreach ( array_keys( self::NETWORKS ) as $network ) {
			if ( array_intersect( array( $network, ...( self::ALIASES[ $network ] ?? array() ) ), $labels ) ) {
				return $network;
			}
		}
		return '';
	}

	/**
	 * The first profile URL of a network.
	 *
	 * @param string[] $urls    Profile URLs.
	 * @param string   $network Network id from NETWORKS.
	 */
	public static function social( array $urls, string $network ): string {
		foreach ( $urls as $url ) {
			if ( '' !== $network && self::network( $url ) === $network ) {
				return $url;
			}
		}
		return '';
	}

	/**
	 * Schema.org Organization. Empty properties are left out.
	 *
	 * @param array<string, mixed>           $brand Brand values with "name" and "url" filled in.
	 * @param array<string, int|string>|null $logo  Main logo: url, width, height.
	 * @phpstan-param array{url: string, width: int, height: int}|null $logo
	 * @return array<string, mixed>
	 */
	public static function organization( array $brand, ?array $logo = null ): array {
		$url     = self::text( $brand, 'url' );
		$address = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => self::text( $brand, 'street' ),
				'addressLocality' => self::text( $brand, 'locality' ),
				'addressRegion'   => self::text( $brand, 'region' ),
				'postalCode'      => self::text( $brand, 'postal_code' ),
				'addressCountry'  => self::text( $brand, 'country' ),
			)
		);
		$social  = is_array( $brand['social'] ?? null ) ? array_values( array_filter( $brand['social'], 'is_string' ) ) : array();

		return array_filter(
			array(
				'@type'     => 'Organization',
				'@id'       => '' === $url ? '' : $url . '#organization',
				'name'      => self::text( $brand, 'name' ),
				'legalName' => self::text( $brand, 'legal_name' ),
				'url'       => $url,
				'logo'      => null === $logo ? array() : array( '@type' => 'ImageObject' ) + $logo,
				'email'     => self::text( $brand, 'email' ),
				'telephone' => self::text( $brand, 'phone' ),
				'vatID'     => self::text( $brand, 'vat_id' ),
				'address'   => count( $address ) > 1 ? $address : array(),
				'sameAs'    => $social,
			),
			static fn ( mixed $value ): bool => '' !== $value && array() !== $value
		);
	}

	/**
	 * A string value from the brand group, '' for anything else.
	 *
	 * @param array<string, mixed> $brand Brand group values.
	 * @param string               $key   Field key.
	 */
	private static function text( array $brand, string $key ): string {
		return is_string( $brand[ $key ] ?? null ) ? $brand[ $key ] : '';
	}
}
