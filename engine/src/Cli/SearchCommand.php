<?php
/**
 * `wp pergel search` commands.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Cli;

use Pergel\Search\Index;
use Pergel\Search\Query;
use Pergel\Search\Text;
use WP_CLI;

/**
 * Shows, rebuilds and tries out the search index.
 */
final class SearchCommand {

	/**
	 * Shows the state of the index and its size.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp pergel search status
	 *
	 * @param string[]              $args       Positional arguments (unused).
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function status( array $args, array $assoc_args ): void {
		global $wpdb;
		unset( $args );
		$state  = Index::state();
		$counts = array(
			Index::KIND_POST => 0,
			Index::KIND_TERM => 0,
		);
		$rows   = 0;
		if ( Index::SCHEMA === $state['db'] ) {
			$table = Index::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Own table, name from the prefix; a one-off report.
			foreach ( (array) $wpdb->get_results( "SELECT kind, COUNT(DISTINCT object_id) AS objects, COUNT(*) AS tokens FROM $table GROUP BY kind", ARRAY_A ) as $row ) {
				$counts[ (int) $row['kind'] ] = (int) $row['objects'];
				$rows                        += (int) $row['tokens'];
			}
		}
		$items = array(
			array(
				'key'   => 'schema',
				'value' => $state['db'] . ' / ' . Index::SCHEMA,
			),
			array(
				'key'   => 'ready',
				'value' => $state['ready'] ? 'yes' : 'no',
			),
			array(
				'key'   => 'build',
				'value' => '' === $state['phase'] ? '-' : $state['phase'] . ' @ ' . $state['cursor'],
			),
			array(
				'key'   => 'posts',
				'value' => (string) $counts[ Index::KIND_POST ],
			),
			array(
				'key'   => 'terms',
				'value' => (string) $counts[ Index::KIND_TERM ],
			),
			array(
				'key'   => 'rows',
				'value' => (string) $rows,
			),
			array(
				'key'   => 'post_types',
				'value' => implode( ', ', Index::post_types() ),
			),
			array(
				'key'   => 'taxonomies',
				'value' => implode( ', ', Index::taxonomies() ),
			),
		);
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $items, array( 'key', 'value' ) );
	}

	/**
	 * Rebuilds the whole index now, instead of in the background.
	 *
	 * Searches use WordPress's own search until it finishes.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pergel search reindex
	 *
	 * @param string[]              $args       Positional arguments (unused).
	 * @param array<string, string> $assoc_args Associative arguments (unused).
	 */
	public function reindex( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );
		if ( Index::SCHEMA !== Index::state()['db'] ) {
			Index::install();
		}
		Index::start();
		$started = microtime( true );
		$phase   = '';
		do {
			$done  = Index::step();
			$state = Index::state();
			if ( ! $done && $state['phase'] !== $phase ) {
				$phase = $state['phase'];
				WP_CLI::log( sprintf( 'Indexing %s…', $phase ) );
			}
		} while ( ! $done );
		wp_clear_scheduled_hook( Index::BUILD_HOOK );
		WP_CLI::success( sprintf( 'Index rebuilt in %.1f s.', microtime( true ) - $started ) );
	}

	/**
	 * Searches the index, as the search page would.
	 *
	 * ## OPTIONS
	 *
	 * <text>
	 * : What to search for.
	 *
	 * [--type=<post-type>]
	 * : Limit to one post type, e.g. product.
	 *
	 * [--limit=<number>]
	 * : Most results shown.
	 * ---
	 * default: 10
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp pergel search query "ışık"
	 *     wp pergel search query "masa ahşap" --type=product
	 *
	 * @param string[]              $args       The search text.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function query( array $args, array $assoc_args ): void {
		$terms = Text::terms( $args[0] ?? '' );
		WP_CLI::log( 'Terms: ' . ( array() === $terms ? '-' : implode( ' ', $terms ) ) );
		$scores = Query::match( Index::KIND_POST, $terms, Query::types( $assoc_args['type'] ?? 'any' ) );
		$rows   = array();
		foreach ( array_slice( $scores, 0, max( 1, (int) ( $assoc_args['limit'] ?? 10 ) ), true ) as $id => $score ) {
			$rows[] = array(
				'id'    => $id,
				'score' => $score,
				'type'  => (string) get_post_type( $id ),
				'title' => get_the_title( $id ),
			);
		}
		WP_CLI::log( sprintf( 'Results: %d', count( $scores ) ) );
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'id', 'score', 'type', 'title' ) );
	}
}
