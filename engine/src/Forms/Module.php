<?php
/**
 * The forms module.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Forms;

use Pergel\Core;

/**
 * Takes the messages of the Form block: a REST endpoint for the view
 * script, an admin-post fallback for browsers without JavaScript, a hidden
 * post type that keeps every message, and the email to the site owner.
 * Switched off, the Form block prints nothing. A page view loads none of
 * the module's other classes: the post type is registered where it is
 * read (admin, WP-CLI) and before a message is stored.
 */
final class Module implements Core\Module {

	/**
	 * Action of the fallback form on admin-post.php.
	 */
	public const ACTION = 'pergel_form';

	/**
	 * {@inheritDoc}
	 */
	public static function id(): string {
		return 'forms';
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
		if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			add_action( 'init', array( Inbox::class, 'register_type' ) );
		}
		add_action( 'rest_api_init', array( Submit::class, 'route' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( Submit::class, 'fallback' ) );
		add_action( 'admin_post_' . self::ACTION, array( Submit::class, 'fallback' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( Inbox::class, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( Inbox::class, 'erasers' ) );
		if ( is_admin() ) {
			Inbox::register_admin();
		}
	}
}
