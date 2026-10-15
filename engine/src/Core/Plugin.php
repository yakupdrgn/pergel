<?php
/**
 * Engine bootstrap and module registry.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Core;

use Pergel\Cli;

/**
 * Boots the modules that apply to the current request.
 */
final class Plugin {

	/**
	 * Modules loaded for this request, keyed by id.
	 *
	 * @var array<string, Module>
	 */
	private static array $loaded = array();

	/**
	 * Module classes shipped with the engine. Order matters only for hooks
	 * that share a priority.
	 *
	 * @return list<class-string<Module>>
	 */
	public static function modules(): array {
		$modules = array(
			Cli\Module::class,
		);

		/**
		 * Filters the engine's module classes.
		 *
		 * Child themes and project plugins can add their own Module
		 * implementations here.
		 *
		 * @param list<class-string<Module>> $modules Module class names.
		 */
		$filtered = apply_filters( 'pergel/modules', $modules );

		return array_values(
			array_filter(
				is_array( $filtered ) ? $filtered : $modules,
				static fn ( $class_name ): bool => is_string( $class_name ) && is_subclass_of( $class_name, Module::class )
			)
		);
	}

	/**
	 * Hooked on plugins_loaded.
	 */
	public static function boot(): void {
		foreach ( self::modules() as $class_name ) {
			if ( isset( self::$loaded[ $class_name::id() ] ) || ! $class_name::should_load() ) {
				continue;
			}
			$module = new $class_name();
			$module->register();
			self::$loaded[ $class_name::id() ] = $module;
		}

		/**
		 * Fires after the engine has registered every module for this request.
		 */
		do_action( 'pergel/loaded' );
	}

	/**
	 * Ids of the modules loaded for this request.
	 *
	 * @return list<string>
	 */
	public static function loaded(): array {
		return array_keys( self::$loaded );
	}
}
