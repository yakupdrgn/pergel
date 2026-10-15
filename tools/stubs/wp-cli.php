<?php
/**
 * Minimal WP-CLI signatures for static analysis. php-stubs/wp-cli-stubs does
 * not yet allow wordpress-stubs 7.x; extend this file as commands grow.
 *
 * @package Pergel
 */

// phpcs:ignoreFile -- stub declarations only, never loaded at runtime.

namespace {
	class WP_CLI {
		/**
		 * @param string               $name     Command name.
		 * @param callable|string      $callable Class or callable.
		 * @param array<string, mixed> $args     Registration args.
		 */
		public static function add_command( $name, $callable, $args = array() ): bool {}

		/** @param string $message */
		public static function success( $message ): void {}

		/** @param string $message */
		public static function log( $message ): void {}

		/** @param string $message */
		public static function warning( $message ): void {}

		/**
		 * @param string|\WP_Error|\Exception $message
		 * @return never
		 */
		public static function error( $message, bool $exit = true ) {}
	}
}

namespace WP_CLI\Utils {
	/**
	 * @param string               $format Output format.
	 * @param array<mixed>         $items  Rows.
	 * @param array<string>|string $fields Fields.
	 */
	function format_items( $format, $items, $fields ): void {}
}
