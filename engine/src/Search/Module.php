<?php
/**
 * The search module.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Search;

use Pergel\Core;

/**
 * Pergel's own search index: prefix matching with Turkish folding, weights
 * per field, products with their SKUs and attributes, and live suggestions
 * on the Search block. A page view only reads the index; content changes
 * keep it current.
 */
final class Module implements Core\Module {

	/**
	 * {@inheritDoc}
	 */
	public static function id(): string {
		return 'search';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function should_load(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		Index::register();
		add_action( 'admin_init', array( Index::class, 'maintain' ) );
		add_action( 'rest_api_init', array( Suggest::class, 'route' ) );
		if ( ! is_admin() ) {
			Query::register();
			Box::register();
		}
	}

	/**
	 * The module was switched back on: content saved while it was off is
	 * missing from the index, so it is rebuilt. Called by Core\Plugin.
	 */
	public static function enabled(): void {
		if ( Index::SCHEMA === Index::state()['db'] ) {
			Index::start();
		}
	}
}
