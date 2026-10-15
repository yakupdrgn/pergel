<?php
/**
 * Read access to pergel_settings.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Settings;

/**
 * The one place that reads the option. Values are normalized against the
 * schema once per request; the cache is dropped whenever the option changes.
 */
final class Settings {

	public const OPTION = 'pergel_settings';

	/**
	 * Normalized settings for this request.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static ?array $cache = null;

	/**
	 * Every setting, merged with the defaults.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			self::$cache = Schema::normalize( get_option( self::OPTION, array() ) );
		}
		return self::$cache;
	}

	/**
	 * One setting by "group.key", or a whole group by "group".
	 *
	 * @param string $path Dotted path, e.g. "brand.email".
	 */
	public static function get( string $path ): mixed {
		$all             = self::all();
		[ $group, $key ] = array_pad( explode( '.', $path, 2 ), 2, null );
		if ( null === $key ) {
			return $all[ $group ] ?? null;
		}
		return $all[ $group ][ $key ] ?? null;
	}

	/**
	 * Whether a module may load. Ids without a switch (settings, cli, and
	 * modules added through the pergel/modules filter) are always enabled.
	 *
	 * @param string $id Module id.
	 */
	public static function module_enabled( string $id ): bool {
		$modules = self::all()['modules'];
		return ! array_key_exists( $id, $modules ) || true === $modules[ $id ];
	}

	/**
	 * Merges $changes into the current settings and saves them.
	 *
	 * @param array<string, mixed> $changes Partial settings, e.g. [ 'brand' => [ 'email' => '...' ] ].
	 * @return list<array{field: string, code: string}> Problems; the valid part is saved anyway.
	 */
	public static function update( array $changes ): array {
		$result = Schema::sanitize( $changes, self::all() );
		update_option( self::OPTION, $result['value'], true );
		self::flush();
		return $result['problems'];
	}

	/**
	 * Saves the defaults when the option does not exist yet. Hooked on
	 * plugin activation: a missing option costs every request one query,
	 * a stored one comes with the autoloaded options.
	 */
	public static function install(): void {
		add_option( self::OPTION, Schema::defaults(), '', true );
		self::flush();
	}

	/**
	 * Drops the request cache. Hooked on every write to the option.
	 */
	public static function flush(): void {
		self::$cache = null;
	}
}
