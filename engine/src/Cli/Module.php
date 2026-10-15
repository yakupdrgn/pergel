<?php
/**
 * WP-CLI integration: the `wp pergel` command namespace.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Cli;

use Pergel\Core;
use WP_CLI;

/**
 * Loads only under WP-CLI, so web requests never include this code.
 */
final class Module implements Core\Module {

	/**
	 * {@inheritDoc}
	 */
	public static function id(): string {
		return 'cli';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function should_load(): bool {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		WP_CLI::add_command( 'pergel', Command::class );
		WP_CLI::add_command( 'pergel tokens', Tokens::class );
		WP_CLI::add_command( 'pergel settings', SettingsCommand::class );
		WP_CLI::add_command( 'pergel modules', ModulesCommand::class );
		WP_CLI::add_command( 'pergel starter', StarterCommand::class );
		if ( in_array( 'search', Core\Plugin::loaded(), true ) ) {
			WP_CLI::add_command( 'pergel search', SearchCommand::class );
		}
	}
}
