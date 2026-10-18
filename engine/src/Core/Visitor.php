<?php
/**
 * The visitor behind a request: address, limits, origin.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Core;

/**
 * What the public endpoints (forms, stock alerts, search suggestions) know
 * about who is asking.
 *
 * The address is REMOTE_ADDR. Behind a proxy or CDN that is the proxy's,
 * and every visitor would share one limit; PERGEL_CLIENT_IP_HEADER in
 * wp-config.php names the header the proxy sets (HTTP_CF_CONNECTING_IP,
 * HTTP_X_REAL_IP or HTTP_X_FORWARDED_FOR, whose last address is the one the
 * proxy saw). Only set it when every request comes through that proxy;
 * otherwise anyone can write the header.
 *
 * Limits count in fixed windows. With a persistent object cache the count
 * is atomic; without one it is a transient, which a burst of parallel
 * requests can overrun a little. IPv6 addresses count per /64, the block a
 * single connection usually has.
 */
final class Visitor {

	private const HEADERS = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_TRUE_CLIENT_IP' );

	private const GROUP = 'pergel_limits';

	/**
	 * The visitor's address: from the trusted proxy header when
	 * PERGEL_CLIENT_IP_HEADER names one, else REMOTE_ADDR.
	 */
	public static function ip(): string {
		$ip     = self::server( 'REMOTE_ADDR' );
		$header = defined( 'PERGEL_CLIENT_IP_HEADER' ) && is_string( PERGEL_CLIENT_IP_HEADER ) ? strtoupper( PERGEL_CLIENT_IP_HEADER ) : '';
		if ( in_array( $header, self::HEADERS, true ) ) {
			$list = array_map( 'trim', explode( ',', self::server( $header ) ) );
			$last = (string) end( $list );
			if ( false !== filter_var( $last, FILTER_VALIDATE_IP ) ) {
				$ip = $last;
			}
		}
		return false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * What a limit counts for an address: an IPv4 address, or the /64 of an
	 * IPv6 one.
	 *
	 * @param string $ip Address.
	 */
	public static function bucket( string $ip ): string {
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return $ip;
		}
		$packed = inet_pton( $ip );
		return false === $packed ? $ip : bin2hex( substr( $packed, 0, 8 ) ) . '::/64';
	}

	/**
	 * Counts one try against a limit: at most $max in each window of
	 * $window seconds. False when the window is full; the try is not
	 * counted then.
	 *
	 * @param string $name   What is counted, e.g. 'form:' plus a bucket.
	 * @param int    $max    Tries allowed in a window.
	 * @param int    $window Window length in seconds.
	 */
	public static function hit( string $name, int $max, int $window ): bool {
		if ( $max <= 0 ) {
			return false;
		}
		$window = max( 1, $window );
		$key    = self::key( $name, $window );
		if ( wp_using_ext_object_cache() ) {
			wp_cache_add( $key, 0, self::GROUP, $window );
			$count = wp_cache_incr( $key, 1, self::GROUP );
			return false !== $count && $count <= $max;
		}
		$count = get_transient( $key );
		$count = is_numeric( $count ) ? (int) $count : 0;
		if ( $count >= $max ) {
			return false;
		}
		set_transient( $key, $count + 1, $window );
		return true;
	}

	/**
	 * Where a limit keeps the count of its current window.
	 *
	 * @param string $name   What is counted.
	 * @param int    $window Window length in seconds.
	 */
	public static function key( string $name, int $window ): string {
		return 'pergel_l_' . substr( wp_hash( $name, 'nonce' ), 0, 24 ) . '_' . intdiv( time(), max( 1, $window ) );
	}

	/**
	 * Whether a browser says the request comes from another site. Browsers
	 * send Origin with every POST; a page elsewhere that posts to a public
	 * form (and so spreads it over its own visitors' addresses) is turned
	 * back. Requests without the header (servers, old clients) pass, and so
	 * does "null", which browsers also send after some redirects; the other
	 * checks still apply to them. The site's own origin is compared with its
	 * port, which WordPress's list of allowed origins leaves out.
	 */
	public static function foreign_origin(): bool {
		$origin = strtolower( self::server( 'HTTP_ORIGIN' ) );
		if ( '' === $origin || 'null' === $origin || '' !== (string) is_allowed_http_origin( $origin ) ) {
			return false;
		}
		foreach ( array( home_url(), site_url() ) as $url ) {
			$parts = wp_parse_url( $url );
			if ( is_array( $parts ) && isset( $parts['scheme'], $parts['host'] ) && strtolower( $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) ) === $origin ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * A server variable as a plain string.
	 *
	 * @param string $name Variable name.
	 */
	private static function server( string $name ): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated by the callers (FILTER_VALIDATE_IP, is_allowed_http_origin).
		$value = isset( $_SERVER[ $name ] ) && is_string( $_SERVER[ $name ] ) ? wp_unslash( $_SERVER[ $name ] ) : '';
		return is_string( $value ) ? trim( $value ) : '';
	}
}
