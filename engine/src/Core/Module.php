<?php
/**
 * Contract for engine modules.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Core;

/**
 * A self-contained feature (SEO, Blocks, Woo, ...). The plugin only loads a
 * module's code when should_load() says so, which keeps the per-request cost
 * of disabled or irrelevant modules at zero.
 */
interface Module {

	/**
	 * Stable machine name, e.g. "seo". Used as the settings key.
	 */
	public static function id(): string;

	/**
	 * Whether the module applies to the current request (context, settings,
	 * active plugins). Must be cheap: no queries beyond autoloaded options.
	 */
	public static function should_load(): bool;

	/**
	 * Adds the module's hooks. Called once, on plugins_loaded.
	 */
	public function register(): void;
}
