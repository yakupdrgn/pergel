<?php
/**
 * The abilities module.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Abilities;

use Pergel\Core;

/**
 * Describes the site to AI agents through the WordPress Abilities API: the
 * patterns and their fields, the pages and the sections they are made of,
 * the design tokens and the Pergel settings. Every ability only reads,
 * needs a logged-in user with the right capability and counts against a
 * per-user limit. The REST API serves them under /wp-abilities/v1; an MCP
 * server such as the WordPress MCP Adapter offers them as tools. Off by
 * default (modules.abilities).
 */
final class Module implements Core\Module {

	/**
	 * {@inheritDoc}
	 */
	public static function id(): string {
		return 'abilities';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function should_load(): bool {
		return function_exists( 'wp_register_ability' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'wp_abilities_api_categories_init', array( Abilities::class, 'category' ) );
		add_action( 'wp_abilities_api_init', array( Abilities::class, 'register' ) );
	}
}
