<?php
/**
 * The settings module: registration, sanitizing and cache upkeep.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Settings;

use Pergel\Core;

/**
 * Always loads. Its web-request cost is a few add_action calls; the schema
 * class is only read when the option is read or written.
 */
final class Module implements Core\Module {

	/**
	 * {@inheritDoc}
	 */
	public static function id(): string {
		return 'settings';
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
		// Every write goes through the schema, whoever makes it (form, REST, CLI, update_option).
		add_filter( 'sanitize_option_' . Settings::OPTION, array( self::class, 'sanitize' ) );
		foreach ( array( 'add_option_', 'update_option_', 'delete_option_' ) as $hook ) {
			add_action( $hook . Settings::OPTION, array( Settings::class, 'flush' ) );
		}
		add_action( 'update_option_' . Settings::OPTION, array( Core\Plugin::class, 'switched' ), 10, 2 );
		add_action( 'admin_init', array( self::class, 'register_setting' ) );
		add_action( 'rest_api_init', array( self::class, 'register_setting' ) );
		add_action( 'rest_api_init', array( self::class, 'preview_route' ) );
		// Preview::ARG, written out so that other pages do not load the class.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preview checks the nonce.
		if ( isset( $_GET['pergel_preview'] ) && ! is_admin() ) {
			Preview::register();
		}
		if ( is_admin() ) {
			Screen::register();
		}
	}

	/**
	 * The route the settings screen sends unsaved values to for the live
	 * preview. Preview loads only when it is called.
	 */
	public static function preview_route(): void {
		register_rest_route(
			'pergel/v1',
			'/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( Preview::class, 'save' ),
				'permission_callback' => array( self::class, 'can_preview' ),
				'args'                => array(
					'settings' => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Whether the user may preview settings: who may change them.
	 */
	public static function can_preview(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Registers the option for the settings screen and /wp/v2/settings.
	 */
	public static function register_setting(): void {
		register_setting(
			'pergel',
			Settings::OPTION,
			array(
				'type'         => 'object',
				'label'        => 'Pergel',
				'description'  => __( 'Pergel Engine settings.', 'pergel-engine' ),
				'show_in_rest' => array( 'schema' => Schema::json_schema() ),
				'default'      => Schema::defaults(),
			)
		);
	}

	/**
	 * Hooked on sanitize_option_pergel_settings. Fields missing from the
	 * input keep their saved value; invalid ones keep it too.
	 *
	 * @param mixed $value New value.
	 * @return array<string, array<string, mixed>>
	 */
	public static function sanitize( mixed $value ): array {
		$result = Schema::sanitize( $value, Schema::normalize( get_option( Settings::OPTION, array() ) ) );

		/**
		 * Fires when a settings write contained invalid fields. The settings
		 * screen turns these into notices.
		 *
		 * @param list<array{field: string, code: string}> $problems Field paths and error codes.
		 */
		if ( $result['problems'] ) {
			do_action( 'pergel/settings/invalid', $result['problems'] );
		}

		return $result['value'];
	}
}
